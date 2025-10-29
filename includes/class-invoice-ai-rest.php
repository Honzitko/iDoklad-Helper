<?php
/**
 * Minimal REST endpoint for triggering ChatGPT parsing and iDoklad REST workflow.
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_InvoiceAIRest {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('invoice-ai/v1', '/parse', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_parse_request'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'invoice_url' => array(
                    'required' => false,
                    'type' => 'string',
                ),
                'invoice_text' => array(
                    'required' => false,
                    'type' => 'string',
                ),
                'email_from' => array(
                    'required' => false,
                    'type' => 'string',
                ),
                'email_subject' => array(
                    'required' => false,
                    'type' => 'string',
                ),
                'send_to_idoklad' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ),
            ),
        ));
    }

    public function check_permissions() {
        return current_user_can('manage_options');
    }

    public function handle_parse_request(WP_REST_Request $request) {
        $api_key = get_option('idoklad_chatgpt_api_key');
        if (empty($api_key)) {
            return new WP_REST_Response(array('error' => 'ChatGPT API key is not configured.'), 500);
        }

        $logger = IDokladProcessor_Logger::get_instance();

        $invoice_text = trim((string) $request->get_param('invoice_text'));
        $invoice_url = trim((string) $request->get_param('invoice_url'));
        $send_to_idoklad = rest_sanitize_boolean($request->get_param('send_to_idoklad'));
        $email_from = sanitize_email((string) $request->get_param('email_from'));
        $email_subject = sanitize_text_field((string) $request->get_param('email_subject'));

        $attachment_name = '';
        if (!empty($invoice_url)) {
            $path = parse_url($invoice_url, PHP_URL_PATH);
            if (!empty($path)) {
                $attachment_name = basename($path);
            }
        }

        if (empty($invoice_text) && empty($invoice_url)) {
            return new WP_REST_Response(array('error' => 'Provide invoice_text or invoice_url.'), 400);
        }

        $temp_file = null;
        $log_id = null;

        $logger->info('Invoice AI REST parse request received', array(
            'invoice_url' => !empty($invoice_url),
            'send_to_idoklad' => $send_to_idoklad,
            'email_from' => $email_from,
        ));

        $email_for_storage = !empty($email_from) ? $email_from : 'api@local.test';

        $log_id = IDokladProcessor_Database::add_log(array(
            'email_from' => $email_for_storage,
            'email_subject' => $email_subject ?: null,
            'attachment_name' => $attachment_name ?: null,
            'processing_status' => 'processing',
            'extracted_data' => array('status' => 'initializing'),
        ));

        try {
        $pdf_path = null;

        if (empty($invoice_text) && !empty($invoice_url)) {
            $temp_file = $this->download_invoice($invoice_url);
            if (is_wp_error($temp_file)) {
                return new WP_REST_Response(array('error' => $temp_file->get_error_message()), 500);
            }

            $pdf_path = $temp_file;
        }

        if (empty($invoice_text) && empty($pdf_path)) {
            return new WP_REST_Response(array('error' => 'Invoice content is empty.'), 400);
        }

        $chatgpt = new IDokladProcessor_ChatGPTIntegration();
        $context = array(
            'file_name' => $attachment_name,
            'email_from' => $email_for_storage,
            'email_subject' => $email_subject,
        );

        if (!empty($invoice_text)) {
            $parsed = $chatgpt->extract_invoice_data_from_text($invoice_text, $context);
        } else {
            $parsed = $chatgpt->extract_invoice_data_from_pdf($pdf_path, $context);
        }
            $payload = $chatgpt->build_idoklad_payload($parsed, array(
                'email_from' => $email_for_storage,
                'email_subject' => $email_subject,
            ));

            if ($log_id) {
                $log_update = array(
                    'processing_status' => $send_to_idoklad ? 'processing' : 'success',
                    'extracted_data' => array(
                        'parsed' => $parsed,
                        'idoklad_payload' => $payload,
                        'summary' => array(
                            'warnings' => isset($parsed['warnings']) ? $parsed['warnings'] : array(),
                            'checklist' => isset($parsed['checklist']) ? $parsed['checklist'] : array(),
                        ),
                    ),
                );

                if (!$send_to_idoklad) {
                    $log_update['processed_at'] = current_time('mysql');
                }

                IDokladProcessor_Database::update_log($log_id, $log_update);
            }

            $response_data = array(
                'log_id' => $log_id,
                'parsed' => $parsed,
                'idoklad_payload' => $payload,
                'warnings' => isset($parsed['warnings']) ? $parsed['warnings'] : array(),
                'checklist' => isset($parsed['checklist']) ? $parsed['checklist'] : array(),
            );

            $response_data['debug'] = array(
                'timestamp' => current_time('mysql'),
                'model' => get_option('idoklad_chatgpt_model', 'gpt-4o'),
                'send_to_idoklad' => (bool) $send_to_idoklad,
                'email_from' => $email_for_storage,
            );

            if ($send_to_idoklad) {
                $client_id = get_option('idoklad_client_id');
                $client_secret = get_option('idoklad_client_secret');

                if (empty($client_id) || empty($client_secret)) {
                    return new WP_REST_Response(array(
                        'error' => 'iDoklad API credentials are not configured.',
                        'parsed' => $parsed,
                        'idoklad_payload' => $payload,
                    ), 500);
                }

                $integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
                $idoklad_response = $integration->create_invoice_complete_workflow($payload);
                $response_data['idoklad_response'] = $idoklad_response;

                if ($log_id) {
                    IDokladProcessor_Database::update_log($log_id, array(
                        'processing_status' => 'success',
                        'idoklad_response' => $idoklad_response,
                        'processed_at' => current_time('mysql'),
                    ));
                }
            } elseif ($log_id) {
                IDokladProcessor_Database::update_log($log_id, array(
                    'processed_at' => current_time('mysql'),
                ));
            }

            return new WP_REST_Response($response_data, 200);
        } catch (Exception $e) {
            if ($log_id) {
                IDokladProcessor_Database::update_log($log_id, array(
                    'processing_status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'processed_at' => current_time('mysql'),
                ));
            }

            $logger->error('Invoice AI REST parse request failed: ' . $e->getMessage());
            return new WP_REST_Response(array(
                'error' => $e->getMessage(),
                'log_id' => $log_id,
            ), 500);
        } finally {
            if ($temp_file && file_exists($temp_file)) {
                unlink($temp_file);
            }
        }
    }

    private function download_invoice($url) {
        $response = wp_remote_get($url, array('timeout' => 60));

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status !== 200 || empty($body)) {
            return new WP_Error('download_failed', 'Unable to download invoice from provided URL.');
        }

        $temp_file = wp_tempnam($url);
        if (!$temp_file) {
            return new WP_Error('temp_file_failed', 'Unable to create temporary file.');
        }

        file_put_contents($temp_file, $body);

        return $temp_file;
    }
}

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

        $invoice_text = trim((string) $request->get_param('invoice_text'));
        $invoice_url = trim((string) $request->get_param('invoice_url'));
        $send_to_idoklad = rest_sanitize_boolean($request->get_param('send_to_idoklad'));

        if (empty($invoice_text) && empty($invoice_url)) {
            return new WP_REST_Response(array('error' => 'Provide invoice_text or invoice_url.'), 400);
        }

        $temp_file = null;

        try {
            if (empty($invoice_text) && !empty($invoice_url)) {
                $temp_file = $this->download_invoice($invoice_url);
                if (is_wp_error($temp_file)) {
                    return new WP_REST_Response(array('error' => $temp_file->get_error_message()), 500);
                }

                $pdf_processor = new IDokladProcessor_PDFProcessor();
                $invoice_text = $pdf_processor->extract_text($temp_file);
            }

            if (empty($invoice_text)) {
                return new WP_REST_Response(array('error' => 'Invoice content is empty.'), 400);
            }

            $chatgpt = new IDokladProcessor_ChatGPTIntegration();
            $parsed = $chatgpt->extract_invoice_data($invoice_text);
            $payload = $chatgpt->build_idoklad_payload($parsed);

            $response_data = array(
                'parsed' => $parsed,
                'idoklad_payload' => $payload,
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
            }

            return new WP_REST_Response($response_data, 200);
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'error' => $e->getMessage(),
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

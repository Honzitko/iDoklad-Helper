<?php
/**
 * Invoice AI REST API integration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_InvoiceAIRest {

    private $model;

    public function __construct() {
        $this->model = get_option('idoklad_chatgpt_model', 'gpt-4.1-mini');
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('invoice-ai/v1', '/parse', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_parse_request'],
            'permission_callback' => [$this, 'check_permissions'],
            'args'                => [
                'invoice_url' => [
                    'required' => false,
                    'type'     => 'string',
                ],
                'invoice_text' => [
                    'required' => false,
                    'type'     => 'string',
                ],
            ],
        ]);
    }

    public function check_permissions() {
        return current_user_can('manage_options');
    }

    public function handle_parse_request(WP_REST_Request $request) {
        $api_key = get_option('idoklad_chatgpt_api_key');

        if (empty($api_key)) {
            return new WP_REST_Response([
                'error' => 'Missing OpenAI API key configuration.',
            ], 500);
        }

        $invoice_url  = $request->get_param('invoice_url');
        $invoice_text = $request->get_param('invoice_text');

        if (!empty($invoice_url)) {
            $invoice_url = esc_url_raw($invoice_url);
        }

        if (!empty($invoice_text)) {
            $invoice_text = wp_kses_post($invoice_text);
        }

        $file_payload = null;

        if (!empty($invoice_url)) {
            $file_payload = $this->download_invoice($invoice_url);

            if (is_wp_error($file_payload)) {
                return new WP_REST_Response([
                    'error' => $file_payload->get_error_message(),
                ], 500);
            }
        }

        if (empty($invoice_text) && empty($file_payload)) {
            return new WP_REST_Response([
                'error' => 'No invoice data provided.',
            ], 400);
        }

        $file_id = null;

        if (!empty($file_payload)) {
            $file_id = $this->upload_file_to_openai($file_payload, $api_key);

            if (is_wp_error($file_id)) {
                return new WP_REST_Response([
                    'error' => $file_id->get_error_message(),
                ], 500);
            }
        }

        $system_prompt = 'You are an invoice parser. Output clean JSON with vendor_name, invoice_number, total_amount, tax_amount, due_date, currency.';
        $user_instruction = !empty($invoice_text)
            ? "Extract fields from this invoice text:\n\n" . $invoice_text
            : 'Extract fields from the attached invoice document. If the document is unreadable, respond with an error description.';

        $response = $this->call_openai_responses($api_key, $system_prompt, $user_instruction, $file_id);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'error' => $response->get_error_message(),
            ], 500);
        }

        return new WP_REST_Response([
            'parsed' => $response,
        ], 200);
    }

    private function download_invoice($invoice_url) {
        $response = wp_remote_get($invoice_url, [
            'timeout'   => 30,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('invoice_download_failed', 'Could not download invoice: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('invoice_download_failed', 'Could not download invoice. HTTP status: ' . $code);
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error('invoice_download_failed', 'Downloaded invoice is empty.');
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (empty($content_type)) {
            $content_type = 'application/pdf';
        }

        $file_name = basename(parse_url($invoice_url, PHP_URL_PATH));
        if (empty($file_name)) {
            $file_name = 'invoice.pdf';
        }

        return [
            'filename'    => $file_name,
            'content'     => $body,
            'contentType' => $content_type,
        ];
    }

    private function upload_file_to_openai($file_payload, $api_key) {
        $boundary = wp_generate_uuid4();
        $file_name = $file_payload['filename'];
        $file_body = $file_payload['content'];
        $mime_type = $file_payload['contentType'];

        $multipart_body  = '--' . $boundary . "\r\n";
        $multipart_body .= "Content-Disposition: form-data; name=\"purpose\"\r\n\r\n";
        $multipart_body .= "assistants\r\n";
        $multipart_body .= '--' . $boundary . "\r\n";
        $multipart_body .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . "\r\n";
        $multipart_body .= 'Content-Type: ' . $mime_type . "\r\n\r\n";
        $multipart_body .= $file_body . "\r\n";
        $multipart_body .= '--' . $boundary . "--";

        $response = wp_remote_post('https://api.openai.com/v1/files', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body'    => $multipart_body,
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('file_upload_failed', 'OpenAI file upload failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('file_upload_failed', 'OpenAI file upload failed. Response: ' . $body);
        }

        $data = json_decode($body, true);
        if (empty($data['id'])) {
            return new WP_Error('file_upload_failed', 'OpenAI file upload did not return a file ID.');
        }

        return $data['id'];
    }

    private function call_openai_responses($api_key, $system_prompt, $user_instruction, $file_id = null) {
        $input_messages = [
            [
                'role'    => 'system',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $system_prompt,
                    ],
                ],
            ],
        ];

        $user_message = [
            'role'    => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $user_instruction,
                ],
            ],
        ];

        if (!empty($file_id)) {
            $user_message['attachments'] = [
                [
                    'file_id' => $file_id,
                ],
            ];

            $user_message['content'][] = [
                'type'    => 'input_file',
                'file_id' => $file_id,
            ];
        }

        $input_messages[] = $user_message;

        $payload = [
            'model'              => $this->model,
            'input'              => $input_messages,
            'temperature'        => 0,
            'max_output_tokens'  => 1024,
            'metadata'           => [
                'source' => 'idoklad-invoice-processor',
            ],
        ];

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('openai_request_failed', 'OpenAI request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('openai_request_failed', 'OpenAI request failed. Response: ' . $body);
        }

        $data = json_decode($body, true);

        if (empty($data)) {
            return new WP_Error('openai_request_failed', 'OpenAI response could not be decoded.');
        }

        $parsed = $this->extract_response_text($data);

        if ($parsed === '') {
            return new WP_Error('openai_request_failed', 'OpenAI response did not include output text.');
        }

        return $parsed;
    }

    private function extract_response_text($response) {
        $text = '';

        if (isset($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $message) {
                if (($message['type'] ?? '') === 'message' && !empty($message['content'])) {
                    foreach ($message['content'] as $content) {
                        if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                            $text .= $content['text'];
                        }
                    }
                }
            }
        }

        if ($text !== '') {
            return trim($text);
        }

        if (isset($response['output_text']) && is_array($response['output_text'])) {
            $joined = implode('\n', array_filter($response['output_text']));
            if (!empty($joined)) {
                return trim($joined);
            }
        }

        if (isset($response['response']['output'][0]['content'][0]['text'])) {
            return trim($response['response']['output'][0]['content'][0]['text']);
        }

        if (isset($response['response']['output_text'][0])) {
            return trim($response['response']['output_text'][0]);
        }

        return '';
    }
}

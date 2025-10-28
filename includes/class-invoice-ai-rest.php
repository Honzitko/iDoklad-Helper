<?php
/**
 * Invoice AI REST API integration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_InvoiceAIRest {

    private $model;
    private $logger;

    public function __construct() {
        $this->model = get_option('idoklad_chatgpt_model', 'gpt-4.1-mini');

        if (class_exists('IDokladProcessor_Logger')) {
            $this->logger = IDokladProcessor_Logger::get_instance();
        }

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
                'send_to_idoklad' => [
                    'required'          => false,
                    'type'              => 'boolean',
                    'default'           => true,
                    'sanitize_callback' => 'rest_sanitize_boolean',
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

        $send_to_idoklad = $request->get_param('send_to_idoklad');
        if (null === $send_to_idoklad) {
            $send_to_idoklad = true;
        }
        $send_to_idoklad = rest_sanitize_boolean($send_to_idoklad);

        $prompt_id = trim((string) get_option('idoklad_openai_prompt_id', ''));
        $prompt_version = trim((string) get_option('idoklad_openai_prompt_version', ''));
        $use_hosted_prompt = !empty($prompt_id);

        $this->log('Invoice AI REST: Received parse request', [
            'has_invoice_url'   => !empty($request->get_param('invoice_url')),
            'has_invoice_text'  => !empty($request->get_param('invoice_text')),
            'send_to_idoklad'   => $send_to_idoklad,
            'openai_prompt_id'  => $use_hosted_prompt ? $prompt_id : null,
            'prompt_version'    => $use_hosted_prompt ? $prompt_version : null,
        ]);

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

        if ($use_hosted_prompt) {
            $system_prompt = '';
            $user_instruction = !empty($invoice_text)
                ? "Invoice text:\n\n" . $invoice_text
                : 'Invoice document attached for parsing.';
        } else {
            $system_prompt = 'You are an invoice parser. Output clean JSON with vendor_name, invoice_number, total_amount, tax_amount, due_date, currency.';
            $user_instruction = !empty($invoice_text)
                ? "Extract fields from this invoice text:\n\n" . $invoice_text
                : 'Extract fields from the attached invoice document. If the document is unreadable, respond with an error description.';
        }

        $response = $this->call_openai_responses($api_key, [
            'system_prompt'    => $system_prompt,
            'user_instruction' => $user_instruction,
            'prompt_id'        => $prompt_id,
            'prompt_version'   => $prompt_version,
        ], $file_id);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'error' => $response->get_error_message(),
            ], 500);
        }

        $structured_data = $this->parse_openai_json($response);

        if (is_wp_error($structured_data)) {
            return new WP_REST_Response([
                'error' => $structured_data->get_error_message(),
                'raw_response' => $response,
            ], 500);
        }

        $structured_data['source'] = $use_hosted_prompt ? 'openai_prompt' : 'chatgpt_rest';

        $this->log('Invoice AI REST: Parsed OpenAI response', [
            'keys' => array_keys($structured_data),
        ]);

        if (!class_exists('IDokladProcessor_PDFCoAIParserEnhanced')) {
            return new WP_REST_Response([
                'error' => 'iDoklad data transformer is unavailable.',
            ], 500);
        }

        $parser = new IDokladProcessor_PDFCoAIParserEnhanced();
        $transform_result = $parser->transform_structured_data($structured_data, 'rest_api_chatgpt');
        $idoklad_data = $transform_result['data'];
        $idoklad_validation = $transform_result['validation'];

        $this->log('Invoice AI REST: Transformed data for iDoklad', [
            'document_number' => $idoklad_data['DocumentNumber'] ?? '',
            'items_count'     => isset($idoklad_data['Items']) ? count($idoklad_data['Items']) : 0,
            'is_valid'        => $idoklad_validation['is_valid'] ?? false,
        ]);

        if (!$transform_result['success']) {
            return new WP_REST_Response([
                'error' => 'OpenAI payload failed iDoklad validation.',
                'validation' => $idoklad_validation,
                'parsed' => $structured_data,
                'idoklad_payload' => $idoklad_data,
            ], 422);
        }

        $partner_resolution = $this->resolve_partner_for_preview($idoklad_data, $idoklad_validation);
        $analysis = $this->build_parse_analysis($structured_data, $idoklad_data, $idoklad_validation, $partner_resolution);

        $idoklad_response = null;
        if ($send_to_idoklad) {
            if (!class_exists('IDokladProcessor_IDokladAPIV3Integration')) {
                return new WP_REST_Response([
                    'error' => 'iDoklad integration class not available.',
                    'parsed' => $structured_data,
                    'idoklad_payload' => $idoklad_data,
                    'validation' => $idoklad_validation,
                ], 500);
            }

            $client_id = get_option('idoklad_client_id');
            $client_secret = get_option('idoklad_client_secret');

            if (empty($client_id) || empty($client_secret)) {
                return new WP_REST_Response([
                    'error' => 'Missing iDoklad API credentials.',
                    'parsed' => $structured_data,
                    'idoklad_payload' => $idoklad_data,
                    'validation' => $idoklad_validation,
                ], 500);
            }

            try {
                $idoklad_api = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
                $idoklad_response = $idoklad_api->create_invoice_complete_workflow($idoklad_data);

                $this->log('Invoice AI REST: Invoice created in iDoklad', [
                    'document_number' => $idoklad_data['DocumentNumber'] ?? '',
                ]);
            } catch (Exception $e) {
                $this->log('Invoice AI REST: Failed to create invoice in iDoklad', [
                    'error' => $e->getMessage(),
                ]);

                return new WP_REST_Response([
                    'error' => 'Failed to create invoice in iDoklad: ' . $e->getMessage(),
                    'parsed' => $structured_data,
                    'idoklad_payload' => $idoklad_data,
                    'validation' => $idoklad_validation,
                ], 500);
            }
        }

        return new WP_REST_Response([
            'parsed' => $structured_data,
            'idoklad_payload' => $idoklad_data,
            'validation' => $idoklad_validation,
            'idoklad_response' => $idoklad_response,
            'created_in_idoklad' => $send_to_idoklad && !empty($idoklad_response),
            'partner_resolution' => $partner_resolution,
            'analysis' => $analysis,
            'openai_prompt_id' => $use_hosted_prompt ? $prompt_id : null,
            'openai_prompt_version' => $use_hosted_prompt ? $prompt_version : null,
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

    private function call_openai_responses($api_key, $prompt_config, $file_id = null) {
        $prompt_id = isset($prompt_config['prompt_id']) ? trim((string) $prompt_config['prompt_id']) : '';
        $prompt_version = isset($prompt_config['prompt_version']) ? trim((string) $prompt_config['prompt_version']) : '';
        $system_prompt = isset($prompt_config['system_prompt']) ? (string) $prompt_config['system_prompt'] : '';
        $user_instruction = isset($prompt_config['user_instruction']) ? (string) $prompt_config['user_instruction'] : '';
        $use_hosted_prompt = !empty($prompt_id);

        if ($use_hosted_prompt) {
            $user_message = [
                'role'    => 'user',
                'content' => [],
            ];

            if ($user_instruction !== '') {
                $user_message['content'][] = [
                    'type' => 'text',
                    'text' => $user_instruction,
                ];
            }

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

            $payload = [
                'model'             => $this->model,
                'prompt'            => [
                    'id' => $prompt_id,
                ],
                'input'             => [
                    $user_message,
                ],
                'temperature'       => 0,
                'max_output_tokens' => 1024,
                'metadata'          => [
                    'source' => 'idoklad-invoice-processor',
                ],
            ];

            if ($prompt_version !== '') {
                $payload['prompt']['version'] = $prompt_version;
            }
        } else {
            $input_messages = [];

            if ($system_prompt !== '') {
                $input_messages[] = [
                    'role'    => 'system',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $system_prompt,
                        ],
                    ],
                ];
            }

            $user_message = [
                'role'    => 'user',
                'content' => [],
            ];

            if ($user_instruction !== '') {
                $user_message['content'][] = [
                    'type' => 'text',
                    'text' => $user_instruction,
                ];
            }

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
                'model'             => $this->model,
                'input'             => $input_messages,
                'temperature'       => 0,
                'max_output_tokens' => 1024,
                'metadata'          => [
                    'source' => 'idoklad-invoice-processor',
                ],
            ];
        }

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

    private function resolve_partner_for_preview(&$idoklad_data, &$idoklad_validation) {
        $result = array(
            'partner_id' => null,
            'source' => null,
            'attempted' => false,
            'warnings' => array(),
        );

        if (!empty($idoklad_data['PartnerId'])) {
            $result['partner_id'] = intval($idoklad_data['PartnerId']);
            $result['source'] = 'payload';
            $idoklad_data['PartnerId'] = $result['partner_id'];
            return $result;
        }

        if (!isset($idoklad_data['partner_data']) || !is_array($idoklad_data['partner_data']) || empty($idoklad_data['partner_data'])) {
            $result['warnings'][] = 'Partner data unavailable - partner lookup skipped.';
            return $result;
        }

        $client_id = get_option('idoklad_client_id');
        $client_secret = get_option('idoklad_client_secret');

        if (empty($client_id) || empty($client_secret)) {
            $result['warnings'][] = 'iDoklad API credentials missing - partner lookup skipped.';
            return $result;
        }

        try {
            $integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $result['warnings'][] = 'Failed to initialise iDoklad integration for partner lookup: ' . $error_message;
            $result['error'] = $error_message;
            return $result;
        }

        $resolution = $integration->resolve_partner_id_for_preview($idoklad_data);

        if (isset($resolution['attempted'])) {
            $result['attempted'] = (bool) $resolution['attempted'];
        }

        if (isset($resolution['source'])) {
            $result['source'] = $resolution['source'];
        }

        if (!empty($resolution['warnings']) && is_array($resolution['warnings'])) {
            $result['warnings'] = array_merge($result['warnings'], $resolution['warnings']);
        }

        if (isset($resolution['error'])) {
            $result['error'] = $resolution['error'];
        }

        if (!empty($resolution['partner_id'])) {
            $result['partner_id'] = intval($resolution['partner_id']);
            $idoklad_data['PartnerId'] = $result['partner_id'];
        }

        if (!isset($idoklad_validation['warnings']) || !is_array($idoklad_validation['warnings'])) {
            $idoklad_validation['warnings'] = array();
        }

        if (!empty($result['partner_id'])) {
            $warning_message = ($result['source'] === 'rest_lookup')
                ? 'PartnerId resolved via REST lookup: ' . $result['partner_id']
                : 'PartnerId provided in payload: ' . $result['partner_id'];

            if (!in_array($warning_message, $idoklad_validation['warnings'], true)) {
                $idoklad_validation['warnings'][] = $warning_message;
            }
        } elseif ($result['attempted']) {
            $warning_message = 'Partner lookup attempted but no matching contact was found.';

            if (!in_array($warning_message, $idoklad_validation['warnings'], true)) {
                $idoklad_validation['warnings'][] = $warning_message;
            }
        }

        $this->log('Invoice AI REST: Partner resolution result', array(
            'partner_id' => $result['partner_id'],
            'source' => $result['source'],
            'attempted' => $result['attempted'],
            'warnings' => $result['warnings'],
            'error' => $result['error'] ?? null,
        ));

        $result['warnings'] = $this->normalize_unique_strings($result['warnings']);

        return $result;
    }

    private function build_parse_analysis($structured_data, $idoklad_data, $validation, $partner_resolution) {
        $currency_code = $this->determine_currency_code($structured_data, $idoklad_data);
        $currency_id = isset($idoklad_data['CurrencyId']) ? intval($idoklad_data['CurrencyId']) : null;
        $prices = $this->build_price_summary($structured_data, $idoklad_data);
        $items = $this->format_items_for_analysis($idoklad_data);
        $notes = $this->collect_notes($structured_data, $idoklad_data);

        $warnings = array();

        if (!empty($validation['warnings']) && is_array($validation['warnings'])) {
            $warnings = array_merge($warnings, $validation['warnings']);
        }

        if (!empty($partner_resolution['warnings']) && is_array($partner_resolution['warnings'])) {
            $warnings = array_merge($warnings, $partner_resolution['warnings']);
        }

        $date_of_issue = $idoklad_data['DateOfIssue'] ?? null;
        $date_of_taxing = $idoklad_data['DateOfTaxing'] ?? null;
        $date_of_maturity = $idoklad_data['DateOfMaturity'] ?? null;
        $taxing_assumed = false;

        if (empty($date_of_taxing) && !empty($date_of_issue)) {
            $date_of_taxing = $date_of_issue;
            $taxing_assumed = true;
            $warnings[] = 'DateOfTaxing missing - assumed equal to DateOfIssue for preview output.';
        } elseif (empty($date_of_taxing)) {
            $warnings[] = 'DateOfTaxing missing - provide a value before export.';
        }

        if (!empty($partner_resolution['partner_id']) && ($partner_resolution['source'] ?? '') === 'rest_lookup') {
            $warnings[] = 'PartnerId resolved via REST lookup: ' . intval($partner_resolution['partner_id']) . '.';
        }

        $warnings = $this->normalize_unique_strings($warnings);

        $checklist = $this->build_checklist(
            $idoklad_data,
            $currency_code,
            $currency_id,
            $prices,
            count($items),
            $partner_resolution,
            $date_of_issue,
            $date_of_taxing,
            $date_of_maturity,
            $taxing_assumed
        );

        $partner_details = $this->build_partner_details($structured_data, $idoklad_data);

        $invoice = array(
            'DocumentNumber' => $idoklad_data['DocumentNumber'] ?? null,
            'DateOfIssue' => $date_of_issue,
            'DateOfTaxing' => $date_of_taxing,
            'DateOfMaturity' => $date_of_maturity,
            'VariableSymbol' => $idoklad_data['VariableSymbol'] ?? null,
            'OrderNumber' => $this->fetch_from_sources(
                array(
                    $structured_data,
                    $structured_data['Invoice'] ?? array(),
                ),
                array('OrderNumber', 'order_number', 'OrderNo')
            ) ?? ($idoklad_data['OrderNumber'] ?? null),
            'CurrencyCode' => $currency_code,
            'CurrencyId' => $currency_id,
            'ExchangeRate' => $this->safe_float($idoklad_data['ExchangeRate'] ?? null, 4),
            'ExchangeRateAmount' => $this->safe_float($idoklad_data['ExchangeRateAmount'] ?? null, 4),
            'PartnerId' => isset($idoklad_data['PartnerId']) && $idoklad_data['PartnerId'] !== null ? intval($idoklad_data['PartnerId']) : null,
            'Partner' => $partner_details,
            'Prices' => $prices,
            'Items' => $items,
            'Notes' => $notes,
            'Warnings' => $warnings,
        );

        return array(
            'Checklist' => $checklist,
            'Invoice' => $invoice,
        );
    }

    private function build_checklist($idoklad_data, $currency_code, $currency_id, $prices, $item_count, $partner_resolution, $date_of_issue, $date_of_taxing, $date_of_maturity, $taxing_assumed) {
        $checklist = array();
        $checklist[] = 'Parse invoice to identify mandatory fields: dates, supplier, customer, totals, items.';

        if (!empty($idoklad_data['DocumentNumber'])) {
            $checklist[] = "Set DocumentNumber -> '" . $idoklad_data['DocumentNumber'] . "'.";
        } else {
            $checklist[] = 'DocumentNumber missing – numeric sequence will assign a value during export.';
        }

        $date_segments = array();

        if (!empty($date_of_issue)) {
            $date_segments[] = 'DateOfIssue = ' . $date_of_issue;
        }

        if (!empty($date_of_maturity)) {
            $date_segments[] = 'DateOfMaturity = ' . $date_of_maturity;
        }

        if (!empty($date_of_taxing)) {
            $segment = 'DateOfTaxing = ' . $date_of_taxing;
            if ($taxing_assumed) {
                $segment .= ' (assumed)';
            }
            $date_segments[] = $segment;
        }

        if (!empty($date_segments)) {
            $checklist[] = 'Dates validated: ' . implode('; ', $date_segments) . '.';
        }

        if ($currency_code || $currency_id) {
            $currency_message = 'Currency ' . ($currency_code ?: 'N/A');

            if ($currency_id) {
                $currency_message .= ' (Id ' . $currency_id . ')';
            }

            $exchange_rate = $this->safe_float($idoklad_data['ExchangeRate'] ?? null, 4);

            if ($exchange_rate !== null && abs($exchange_rate - 1.0) > 0.0001) {
                $currency_message .= ' with exchange rate ' . $this->format_decimal($exchange_rate, 4);
            } else {
                $currency_message .= ' detected';
            }

            $checklist[] = $currency_message . '.';
        }

        if ($prices['TotalWithoutVat'] !== null || $prices['TotalVat'] !== null || $prices['TotalWithVat'] !== null) {
            $totals = array();

            if ($prices['TotalWithoutVat'] !== null) {
                $totals[] = 'without VAT ' . $this->format_decimal($prices['TotalWithoutVat']);
            }

            if ($prices['TotalVat'] !== null) {
                $totals[] = 'VAT ' . $this->format_decimal($prices['TotalVat']);
            }

            if ($prices['TotalWithVat'] !== null) {
                $totals[] = 'with VAT ' . $this->format_decimal($prices['TotalWithVat']);
            }

            $checklist[] = 'Totals reconciled: ' . implode(', ', $totals) . '.';
        } else {
            $checklist[] = 'Totals not supplied – confirm invoice amounts before export.';
        }

        if ($item_count > 0) {
            $checklist[] = sprintf('Prepared %d invoice item(s) for the iDoklad payload.', $item_count);
        } else {
            $checklist[] = 'No invoice items detected – add at least one item manually.';
        }

        if (!empty($partner_resolution['partner_id'])) {
            if (($partner_resolution['source'] ?? '') === 'rest_lookup') {
                $checklist[] = 'PartnerId resolved via REST lookup (ID ' . intval($partner_resolution['partner_id']) . ').';
            } else {
                $checklist[] = 'PartnerId provided directly in parsed data (ID ' . intval($partner_resolution['partner_id']) . ').';
            }
        } elseif (!empty($idoklad_data['PartnerName'])) {
            if (!empty($partner_resolution['attempted'])) {
                $checklist[] = 'Partner lookup attempted but requires manual confirmation before export.';
            } else {
                $checklist[] = 'PartnerId pending – REST lookup will resolve the contact before export.';
            }
        } else {
            $checklist[] = 'Partner identification missing – default partner or manual entry required.';
        }

        return $checklist;
    }

    private function build_partner_details($structured_data, $idoklad_data) {
        $sources = $this->get_partner_sources($structured_data);

        $partner_name = $idoklad_data['PartnerName'] ?? null;
        if (empty($partner_name)) {
            $partner_name = $this->fetch_from_sources($sources, array('PartnerName', 'partner_name', 'SupplierName', 'supplier_name', 'CustomerName', 'customer_name', 'CompanyName', 'company_name'));
        }

        $identification = $idoklad_data['PartnerIdentificationNumber'] ?? null;
        if (empty($identification)) {
            $identification = $this->fetch_from_sources($sources, array('IdentificationNumber', 'identification_number', 'VatNumber', 'vat_number', 'ICO', 'ico'));
        }

        $vat_number = $this->fetch_from_sources($sources, array('VatIdentificationNumber', 'vat_identification_number', 'VatNumber', 'vat_number', 'SupplierVatNumber', 'supplier_vat_number'));
        if (empty($vat_number) && !empty($idoklad_data['partner_data']['vat'] ?? null)) {
            $vat_number = $idoklad_data['partner_data']['vat'];
        }

        $address_full = $this->build_partner_address($sources, $idoklad_data);

        return array(
            'PartnerName' => $partner_name ?: null,
            'IdentificationNumber' => $identification ?: null,
            'VatIdentificationNumber' => $vat_number ?: null,
            'AddressFull' => $address_full,
        );
    }

    private function build_partner_address($sources, $idoklad_data) {
        $parts = array();

        if (!empty($idoklad_data['PartnerAddress'])) {
            $parts[] = $idoklad_data['PartnerAddress'];
        }

        if (isset($idoklad_data['partner_data']) && is_array($idoklad_data['partner_data'])) {
            $partner_data = $idoklad_data['partner_data'];

            if (!empty($partner_data['address'])) {
                $parts[] = $partner_data['address'];
            }

            $city_line = trim(((string) ($partner_data['postal_code'] ?? '')) . ' ' . ((string) ($partner_data['city'] ?? '')));
            if ($city_line !== '') {
                $parts[] = $city_line;
            }
        }

        $country = $this->fetch_from_sources($sources, array('Country', 'country', 'PartnerCountry', 'partner_country', 'SupplierCountry', 'supplier_country'));
        if (empty($country) && isset($idoklad_data['partner_data']['country'])) {
            $country = $idoklad_data['partner_data']['country'];
        }

        if (!empty($country)) {
            $parts[] = $country;
        }

        $parts = $this->normalize_unique_strings($parts);

        return !empty($parts) ? implode(', ', $parts) : null;
    }

    private function build_price_summary($structured_data, $idoklad_data) {
        $total_without_vat = $this->safe_float($this->fetch_price_value($structured_data, array('TotalWithoutVat', 'total_without_vat', 'NetTotal', 'net_total', 'NetAmount', 'net_amount')));
        $total_vat = $this->safe_float($this->fetch_price_value($structured_data, array('TotalVat', 'total_vat', 'VatAmount', 'vat_amount', 'TaxAmount', 'tax_amount')));
        $total_with_vat = $this->safe_float($this->fetch_price_value($structured_data, array('TotalWithVat', 'total_with_vat', 'TotalAmount', 'total_amount', 'GrandTotal', 'grand_total')));

        if ($total_with_vat === null && $total_without_vat !== null && $total_vat !== null) {
            $total_with_vat = round($total_without_vat + $total_vat, 2);
        }

        if ($total_without_vat === null && isset($idoklad_data['Items']) && is_array($idoklad_data['Items'])) {
            $items_sum = 0.0;
            $has_amount = false;

            foreach ($idoklad_data['Items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $quantity = $this->safe_float($item['Amount'] ?? null);
                $unit_price = $this->safe_float($item['UnitPrice'] ?? null);

                if ($quantity !== null && $unit_price !== null) {
                    $items_sum += $quantity * $unit_price;
                    $has_amount = true;
                }
            }

            if ($has_amount) {
                $total_without_vat = round($items_sum, 2);
            }
        }

        if ($total_vat === null && $total_with_vat !== null && $total_without_vat !== null) {
            $total_vat = round($total_with_vat - $total_without_vat, 2);
        }

        return array(
            'TotalWithoutVat' => $total_without_vat,
            'TotalVat' => $total_vat,
            'TotalWithVat' => $total_with_vat,
        );
    }

    private function format_items_for_analysis($idoklad_data) {
        if (!isset($idoklad_data['Items']) || !is_array($idoklad_data['Items'])) {
            return array();
        }

        $items = array();

        foreach ($idoklad_data['Items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $quantity = $this->safe_float($item['Amount'] ?? null);
            $discount = $this->safe_float($item['DiscountPercentage'] ?? 0);

            $items[] = array(
                'Name' => $item['Name'] ?? '',
                'Description' => isset($item['Description']) ? $item['Description'] : null,
                'Quantity' => $quantity !== null ? $quantity : 1.0,
                'Unit' => $item['Unit'] ?? null,
                'UnitPrice' => $this->safe_float($item['UnitPrice'] ?? null),
                'PriceType' => isset($item['PriceType']) ? intval($item['PriceType']) : null,
                'VatRateType' => isset($item['VatRateType']) ? intval($item['VatRateType']) : null,
                'VatRate' => $this->safe_float($item['VatRate'] ?? null),
                'IsTaxMovement' => isset($item['IsTaxMovement']) ? (bool) $item['IsTaxMovement'] : false,
                'DiscountPercentage' => $discount !== null ? $discount : 0.0,
            );
        }

        return $items;
    }

    private function collect_notes($structured_data, $idoklad_data) {
        $notes = array();

        if (!empty($idoklad_data['Notes']) && is_array($idoklad_data['Notes'])) {
            $notes = array_merge($notes, $idoklad_data['Notes']);
        }

        if (!empty($idoklad_data['Note']) && is_string($idoklad_data['Note'])) {
            $notes[] = $idoklad_data['Note'];
        }

        $structured_notes = $this->fetch_from_sources(
            array(
                $structured_data,
                $structured_data['Invoice'] ?? array(),
            ),
            array('Notes', 'notes')
        );

        if (is_array($structured_notes)) {
            $notes = array_merge($notes, $structured_notes);
        } elseif (is_string($structured_notes)) {
            $notes[] = $structured_notes;
        }

        return $this->normalize_unique_strings($notes);
    }

    private function determine_currency_code($structured_data, $idoklad_data) {
        $sources = array($structured_data);

        if (isset($structured_data['Prices']) && is_array($structured_data['Prices'])) {
            $sources[] = $structured_data['Prices'];
        }

        $currency = $this->fetch_from_sources($sources, array('CurrencyCode', 'currency_code', 'Currency', 'currency'));

        if (is_array($currency)) {
            $currency = reset($currency);
        }

        if (is_string($currency) && $currency !== '') {
            return strtoupper(trim($currency));
        }

        if (!empty($idoklad_data['CurrencyCode'])) {
            return strtoupper(trim((string) $idoklad_data['CurrencyCode']));
        }

        if (isset($idoklad_data['CurrencyId'])) {
            $map = array(
                1 => 'CZK',
                2 => 'EUR',
                3 => 'USD',
                4 => 'GBP',
            );

            $currency_id = intval($idoklad_data['CurrencyId']);
            if (isset($map[$currency_id])) {
                return $map[$currency_id];
            }
        }

        return null;
    }

    private function get_partner_sources($structured_data) {
        $sources = array();

        if (is_array($structured_data)) {
            $sources[] = $structured_data;

            if (isset($structured_data['Partner']) && is_array($structured_data['Partner'])) {
                $sources[] = $structured_data['Partner'];
            }

            if (isset($structured_data['partner']) && is_array($structured_data['partner'])) {
                $sources[] = $structured_data['partner'];
            }
        }

        return $sources;
    }

    private function fetch_from_sources($sources, $keys) {
        foreach ($sources as $source) {
            if (!is_array($source) || empty($source)) {
                continue;
            }

            $value = $this->fetch_value($source, $keys);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function fetch_price_value($structured_data, $keys) {
        $sources = array();

        if (is_array($structured_data)) {
            $sources[] = $structured_data;

            if (isset($structured_data['Prices']) && is_array($structured_data['Prices'])) {
                $sources[] = $structured_data['Prices'];
            }
        }

        return $this->fetch_from_sources($sources, $keys);
    }

    private function fetch_value($data, $possible_keys) {
        if (!is_array($data)) {
            return null;
        }

        foreach ($possible_keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }

        $normalized = array();

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $key_string = strtolower((string) $key);
            $normalized[$key_string] = $value;
            $normalized[preg_replace('/[\s_\-]/', '', $key_string)] = $value;
        }

        foreach ($possible_keys as $key) {
            $lower = strtolower((string) $key);

            if (isset($normalized[$lower])) {
                return $normalized[$lower];
            }

            $stripped = preg_replace('/[\s_\-]/', '', $lower);
            if (isset($normalized[$stripped])) {
                return $normalized[$stripped];
            }
        }

        return null;
    }

    private function safe_float($value, $decimals = 2) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(array(' ', ','), array('', '.'), $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        $float_value = (float) $value;

        if ($decimals === null) {
            return $float_value;
        }

        return round($float_value, $decimals);
    }

    private function format_decimal($value, $decimals = 2) {
        if ($value === null) {
            return null;
        }

        return number_format((float) $value, $decimals, '.', '');
    }

    private function normalize_unique_strings($values) {
        $normalized = array();

        foreach ((array) $values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $string = trim((string) $value);

            if ($string === '') {
                continue;
            }

            if (!in_array($string, $normalized, true)) {
                $normalized[] = $string;
            }
        }

        return $normalized;
    }

    private function parse_openai_json($response_text) {
        if (empty($response_text)) {
            return new WP_Error('openai_invalid_response', 'OpenAI response was empty.');
        }

        $clean = trim($response_text);
        $clean = preg_replace('/^```json\s*/', '', $clean);
        $clean = preg_replace('/```$/', '', $clean);
        $clean = trim($clean);

        $data = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new WP_Error('openai_invalid_json', 'OpenAI response did not contain valid JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    private function log($message, $context = array()) {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }
}

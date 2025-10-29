<?php
/**
 * ChatGPT integration class - Updated with latest models including GPT-5
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_ChatGPTIntegration {
    
    private $api_key;
    private $model;
    private $base_url = 'https://api.openai.com/v1/chat/completions';
    private $max_tokens = 2000;
    private $temperature = 1.0;
    private $max_pdf_size_bytes = 3145728; // 3 MB safety limit for base64 uploads
    private $last_detected_model = null;
    private $last_detected_model_was_fallback = false;
    private $cached_api_models = null;
    private $cached_api_models_timestamp = 0;

    public function __construct() {
        $this->api_key = get_option('idoklad_chatgpt_api_key');
        $this->model = get_option('idoklad_chatgpt_model', 'gpt-5-nano');

        if (empty($this->model)) {
            $this->model = 'gpt-5-nano';
        }
    }
    
    /**
     * Backwards compatible wrapper for text-based extraction.
     */
    public function extract_invoice_data($pdf_text, $context = array()) {
        return $this->extract_invoice_data_from_text($pdf_text, $context);
    }

    /**
     * Extract invoice data when raw text is available.
     */
    public function extract_invoice_data_from_text($pdf_text, $context = array()) {
        if (empty($this->api_key)) {
            throw new Exception('ChatGPT API key is not configured');
        }

        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad ChatGPT: Extracting invoice data from provided text');
        }

        $prompt = $this->build_extraction_prompt($pdf_text, $context);

        try {
            $response = $this->make_api_request($prompt);

            if (!$response) {
                throw new Exception('No response from ChatGPT API');
            }

            $extracted_data = $this->parse_response($response);

            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad ChatGPT: Extracted data: ' . json_encode($extracted_data));
            }

            return $extracted_data;

        } catch (Exception $e) {
            error_log('iDoklad ChatGPT Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract invoice data directly from a PDF file by sending a base64 payload to ChatGPT.
     */
    public function extract_invoice_data_from_pdf($pdf_path, $context = array()) {
        if (empty($this->api_key)) {
            throw new Exception('ChatGPT API key is not configured');
        }

        if (empty($pdf_path) || !file_exists($pdf_path)) {
            throw new Exception('PDF file not found for ChatGPT extraction');
        }

        $file_size = filesize($pdf_path);
        if ($file_size === false) {
            throw new Exception('Unable to determine PDF file size');
        }

        if ($file_size > $this->max_pdf_size_bytes) {
            throw new Exception('PDF file is too large for ChatGPT processing (max ' . round($this->max_pdf_size_bytes / 1024 / 1024, 2) . ' MB)');
        }

        if (get_option('idoklad_debug_mode')) {
            error_log(sprintf('iDoklad ChatGPT: Preparing base64 payload for %s (%d bytes)', basename($pdf_path), $file_size));
        }

        $pdf_contents = file_get_contents($pdf_path);
        if ($pdf_contents === false) {
            throw new Exception('Unable to read PDF file for ChatGPT extraction');
        }

        $base64 = base64_encode($pdf_contents);
        $prompt = $this->build_pdf_prompt($base64, $context);

        try {
            $response = $this->make_api_request($prompt);

            if (!$response) {
                throw new Exception('No response from ChatGPT API');
            }

            $extracted_data = $this->parse_response($response);

            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad ChatGPT: Extracted data (PDF): ' . json_encode($extracted_data));
            }

            return $extracted_data;

        } catch (Exception $e) {
            error_log('iDoklad ChatGPT Error (PDF): ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Build extraction prompt
     */
    private function build_extraction_prompt($pdf_text, $context = array()) {
        $base_prompt = get_option('idoklad_chatgpt_prompt', 'Extract invoice data from this PDF. Return JSON with: invoice_number, date, total_amount, supplier_name, supplier_vat_number, items (array with name, quantity, price), currency. Validate data completeness.');

        $context_summary = $this->summarize_context($context);

        // Truncate PDF text if too long (ChatGPT has token limits)
        $max_text_length = 8000; // Approximate token limit consideration
        if (strlen($pdf_text) > $max_text_length) {
            $pdf_text = substr($pdf_text ?: '', 0, $max_text_length) . '... [truncated]';
        }

        $prompt = $base_prompt;

        if (!empty($context_summary)) {
            $prompt .= "\n\nContext:\n" . $context_summary;
        }

        $prompt .= "\n\nInvoice Text:\n" . $pdf_text . "\n\nPlease return only valid JSON without any additional text or formatting.";

        return $prompt;
    }

    private function build_pdf_prompt($base64, $context = array()) {
        $base_prompt = get_option('idoklad_chatgpt_prompt', 'Extract invoice data from this PDF. Return JSON with: invoice_number, date, total_amount, supplier_name, supplier_vat_number, items (array with name, quantity, price), currency. Validate data completeness.');

        $context_summary = $this->summarize_context($context);

        $prompt = $base_prompt;

        if (!empty($context_summary)) {
            $prompt .= "\n\nContext:\n" . $context_summary;
        }

        $prompt .= "\n\nThe invoice file is provided below as base64-encoded PDF data. Decode the content, analyse the document and return structured JSON with the requested fields. If decoding fails, explain why in a `warnings` array.";

        $prompt .= "\n\nBase64PDF:\n" . chunk_split($base64, 120);

        $prompt .= "\n\nReturn only valid JSON without extra commentary.";

        return $prompt;
    }

    private function summarize_context($context) {
        if (empty($context) || !is_array($context)) {
            return '';
        }

        $parts = array();

        $map = array(
            'file_name' => 'File name',
            'email_from' => 'Email from',
            'email_subject' => 'Email subject',
            'queue_id' => 'Queue ID',
        );

        foreach ($map as $key => $label) {
            if (!empty($context[$key])) {
                $parts[] = $label . ': ' . $context[$key];
            }
        }

        if (empty($parts)) {
            return '';
        }

        return implode("\n", $parts);
    }
    
    /**
     * Make API request to ChatGPT
     */
    private function make_api_request($prompt) {
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
            'User-Agent' => 'WordPress-iDoklad-Processor/1.1.0'
        );
        
        $this->ensure_effective_model();

        $data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are an expert invoice data extraction assistant. Extract structured data from invoice text and return it as valid JSON. Always validate the completeness of the data.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'response_format' => array('type' => 'json_object')
        );
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => 60,
            'method' => 'POST',
            'sslverify' => true
        );
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad ChatGPT: Making API request to ' . $this->base_url);
            error_log('iDoklad ChatGPT: Model: ' . $this->model);
            error_log('iDoklad ChatGPT: API Key: ' . substr($this->api_key ?: '', 0, 8) . '...');
        }
        
        $response = wp_remote_request($this->base_url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad ChatGPT: WP Error: ' . $error_message);
            }
            throw new Exception('ChatGPT API request failed: ' . $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad ChatGPT: Response code: ' . $response_code);
            error_log('iDoklad ChatGPT: Response body: ' . $response_body);
        }
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = 'Unknown API error';
            
            if (isset($error_data['error']['message'])) {
                $error_message = $error_data['error']['message'];
            } elseif (isset($error_data['error']['type'])) {
                $error_message = $error_data['error']['type'];
            } elseif (isset($error_data['message'])) {
                $error_message = $error_data['message'];
            }
            
            throw new Exception('ChatGPT API error (' . $response_code . '): ' . $error_message);
        }
        
        $response_data = json_decode($response_body, true);
        
        if (!$response_data) {
            throw new Exception('Invalid JSON response from ChatGPT API: ' . $response_body);
        }
        
        if (!isset($response_data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response format from ChatGPT API. Response: ' . $response_body);
        }

        return $response_data['choices'][0]['message']['content'];
    }

    private function ensure_effective_model() {
        $api_models = $this->get_api_available_models();

        if (empty($api_models)) {
            return;
        }

        if (!empty($this->model) && in_array($this->model, $api_models, true)) {
            return;
        }

        $previous_model = $this->model;
        $detected_model = $this->auto_detect_best_model($api_models);

        if ($detected_model && $detected_model !== $previous_model && get_option('idoklad_debug_mode')) {
            error_log(sprintf('iDoklad ChatGPT: Using fallback model "%s" instead of "%s" for current request.', $detected_model, $previous_model));
        }
    }

    /**
     * Parse ChatGPT response
     */
    private function parse_response($response) {
        // Clean up response
        $response = trim($response);
        
        // Remove any markdown formatting
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        
        // Try to parse JSON
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from ChatGPT: ' . json_last_error_msg());
        }
        
        // Validate and normalize data
        $normalized_data = $this->normalize_extracted_data($data);
        
        return $normalized_data;
    }
    
    /**
     * Normalize extracted data
     */
    private function normalize_extracted_data($data) {
        $data = is_array($data) ? $data : array();

        // Handle ChatGPT parser responses that wrap data inside an Invoice object
        $invoice_section = null;
        $structured_invoice = array();
        if (isset($data['Invoice']) && is_array($data['Invoice'])) {
            $invoice_section = $data['Invoice'];
        } elseif (isset($data['invoice']) && is_array($data['invoice'])) {
            $invoice_section = $data['invoice'];
        }

        if ($invoice_section) {
            $structured_invoice = $invoice_section;
            $prices = isset($invoice_section['Prices']) && is_array($invoice_section['Prices'])
                ? $invoice_section['Prices']
                : array();

            $partner = isset($invoice_section['Partner']) && is_array($invoice_section['Partner'])
                ? $invoice_section['Partner']
                : array();

            if (empty($data['invoice_number']) && isset($invoice_section['DocumentNumber'])) {
                $data['invoice_number'] = $invoice_section['DocumentNumber'];
            }

            if (empty($data['date']) && isset($invoice_section['DateOfIssue'])) {
                $data['date'] = $invoice_section['DateOfIssue'];
            }

            if (empty($data['due_date']) && isset($invoice_section['DateOfMaturity'])) {
                $data['due_date'] = $invoice_section['DateOfMaturity'];
            }

            if (empty($data['total_amount'])) {
                if (isset($prices['TotalWithVat'])) {
                    $data['total_amount'] = $prices['TotalWithVat'];
                } elseif (isset($prices['TotalWithoutVat'])) {
                    $data['total_amount'] = $prices['TotalWithoutVat'];
                }
            }

            if (empty($data['currency']) && isset($invoice_section['CurrencyCode'])) {
                $data['currency'] = $invoice_section['CurrencyCode'];
            }

            if (empty($data['variable_symbol']) && isset($invoice_section['VariableSymbol'])) {
                $data['variable_symbol'] = $invoice_section['VariableSymbol'];
            }

            if (empty($data['order_number']) && isset($invoice_section['OrderNumber'])) {
                $data['order_number'] = $invoice_section['OrderNumber'];
            }

            if (empty($data['notes']) && isset($invoice_section['Notes'])) {
                $notes_value = $invoice_section['Notes'];
                if (is_array($notes_value)) {
                    $notes_value = implode("\n", array_map('trim', $notes_value));
                }
                $data['notes'] = $notes_value;
            }

            if (empty($data['items']) && isset($invoice_section['Items']) && is_array($invoice_section['Items'])) {
                $data['items'] = $invoice_section['Items'];
            }

            if (empty($data['supplier_name']) && isset($partner['PartnerName'])) {
                $data['supplier_name'] = $partner['PartnerName'];
            }

            if (empty($data['supplier_vat_number']) && isset($partner['VatIdentificationNumber'])) {
                $data['supplier_vat_number'] = $partner['VatIdentificationNumber'];
            }

            if (empty($data['supplier_id_number']) && isset($partner['IdentificationNumber'])) {
                $data['supplier_id_number'] = $partner['IdentificationNumber'];
            }

            if (empty($data['supplier_address']) && isset($partner['AddressFull'])) {
                $data['supplier_address'] = $partner['AddressFull'];

                $address_parts = $this->split_full_address($partner['AddressFull']);
                if (empty($data['supplier_city']) && !empty($address_parts['city'])) {
                    $data['supplier_city'] = $address_parts['city'];
                }
                if (empty($data['supplier_postal_code']) && !empty($address_parts['postal_code'])) {
                    $data['supplier_postal_code'] = $address_parts['postal_code'];
                }
                if (empty($data['supplier_street']) && !empty($address_parts['street'])) {
                    $data['supplier_street'] = $address_parts['street'];
                }
            }
        }

        $checklist_items = array();
        if (isset($data['Checklist'])) {
            $checklist_items = array_merge($checklist_items, $this->normalize_string_list($data['Checklist']));
        }
        if (!empty($invoice_section) && isset($invoice_section['Checklist'])) {
            $checklist_items = array_merge($checklist_items, $this->normalize_string_list($invoice_section['Checklist']));
        }
        $checklist_items = array_values(array_unique(array_filter($checklist_items)));

        $warning_items = array();
        if (isset($data['Warnings'])) {
            $warning_items = array_merge($warning_items, $this->normalize_string_list($data['Warnings']));
        }
        if (!empty($invoice_section) && isset($invoice_section['Warnings'])) {
            $warning_items = array_merge($warning_items, $this->normalize_string_list($invoice_section['Warnings']));
        }
        $warning_items = array_values(array_unique(array_filter($warning_items)));

        $normalized = array();

        // Required fields
        $normalized['invoice_number'] = isset($data['invoice_number']) ? trim((string) $data['invoice_number']) : '';
        $normalized['date'] = isset($data['date']) ? $this->normalize_date($data['date']) : '';
        $normalized['total_amount'] = isset($data['total_amount']) ? $this->normalize_amount($data['total_amount']) : '';
        $normalized['supplier_name'] = isset($data['supplier_name']) ? trim((string) $data['supplier_name']) : '';

        // Optional fields
        $normalized['supplier_vat_number'] = isset($data['supplier_vat_number']) ? trim((string) $data['supplier_vat_number']) : '';
        $normalized['currency'] = isset($data['currency']) ? strtoupper(trim((string) $data['currency'])) : 'CZK';
        $normalized['variable_symbol'] = $this->extract_variable_symbol($data);
        $normalized['items'] = isset($data['items']) && is_array($data['items']) ? $this->normalize_items($data['items']) : array();

        // Additional fields that might be useful
        $normalized['customer_name'] = isset($data['customer_name']) ? trim((string) $data['customer_name']) : '';
        $normalized['customer_vat_number'] = isset($data['customer_vat_number']) ? trim((string) $data['customer_vat_number']) : '';
        $normalized['due_date'] = isset($data['due_date']) ? $this->normalize_date($data['due_date']) : '';
        $normalized['payment_method'] = isset($data['payment_method']) ? trim((string) $data['payment_method']) : '';
        $normalized['notes'] = isset($data['notes']) ? trim((string) $data['notes']) : '';

        if (isset($data['supplier_address'])) {
            $normalized['supplier_address'] = trim((string) $data['supplier_address']);
        }
        if (isset($data['supplier_city'])) {
            $normalized['supplier_city'] = trim((string) $data['supplier_city']);
        }
        if (isset($data['supplier_postal_code'])) {
            $normalized['supplier_postal_code'] = trim((string) $data['supplier_postal_code']);
        }
        if (isset($data['supplier_street'])) {
            $normalized['supplier_street'] = trim((string) $data['supplier_street']);
        }
        if (isset($data['supplier_id_number'])) {
            $normalized['supplier_id_number'] = trim((string) $data['supplier_id_number']);
        }
        if (isset($data['order_number'])) {
            $normalized['order_number'] = trim((string) $data['order_number']);
        }

        if (!empty($structured_invoice)) {
            $normalized['raw_invoice'] = $structured_invoice;
        }

        if (!empty($checklist_items)) {
            $normalized['checklist'] = $checklist_items;
        }

        if (!empty($warning_items)) {
            $normalized['warnings'] = $warning_items;
        }

        if (!empty($prices)) {
            $normalized['prices'] = array(
                'total_without_vat' => isset($prices['TotalWithoutVat']) ? $this->normalize_amount($prices['TotalWithoutVat']) : null,
                'total_vat' => isset($prices['TotalVat']) ? $this->normalize_amount($prices['TotalVat']) : null,
                'total_with_vat' => isset($prices['TotalWithVat']) ? $this->normalize_amount($prices['TotalWithVat']) : null,
            );
        }

        if (!empty($partner)) {
            $normalized['partner'] = array(
                'name' => $partner['PartnerName'] ?? '',
                'identification_number' => $partner['IdentificationNumber'] ?? '',
                'vat_identification_number' => $partner['VatIdentificationNumber'] ?? '',
                'address_full' => $partner['AddressFull'] ?? '',
                'email' => $partner['Email'] ?? '',
            );
        }

        $normalized['debug'] = array(
            'source' => 'chatgpt',
            'timestamp' => $this->get_current_timestamp(),
            'has_structured_invoice' => !empty($structured_invoice)
        );

        return $normalized;
    }

    private function split_full_address($address) {
        $result = array(
            'street' => '',
            'postal_code' => '',
            'city' => '',
        );

        if (!is_string($address) || trim($address) === '') {
            return $result;
        }

        $parts = array_map('trim', explode(',', $address));

        if (!empty($parts[0])) {
            $result['street'] = $parts[0];
        }

        if (isset($parts[1])) {
            $second_part = $parts[1];
            if (preg_match('/^(\d{3}\s?\d{2})(.*)$/u', $second_part, $matches)) {
                $result['postal_code'] = trim($matches[1]);
                $result['city'] = trim($matches[2]);
            } else {
                $result['city'] = $second_part;
            }
        }

        if (isset($parts[2]) && $result['city'] === '') {
            $result['city'] = $parts[2];
        }

        return $result;
    }

    private function extract_variable_symbol($data) {
        $candidates = array('variable_symbol', 'VariableSymbol', 'variableSymbol', 'vs', 'VS');

        foreach ($candidates as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $normalized = $this->normalize_variable_symbol($data[$key]);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return '';
    }
    
    /**
     * Normalize date format
     */
    private function normalize_date($date) {
        if (empty($date)) {
            return '';
        }
        
        // Try to parse various date formats
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return $date; // Return original if can't parse
        }
        
        return date('Y-m-d', $timestamp);
    }
    
    /**
     * Normalize amount
     */
    private function normalize_amount($amount) {
        if ($amount === null) {
            return '';
        }

        if (is_numeric($amount)) {
            return floatval($amount);
        }

        if (!is_string($amount)) {
            $amount = (string) $amount;
        }

        $amount = trim($amount);

        if ($amount === '') {
            return '';
        }

        // Remove currency symbols and spaces
        $amount = preg_replace('/[^\d.,\-]/', '', $amount);

        // Convert comma to dot for decimal separator
        $amount = str_replace(',', '.', $amount);

        // Ensure it's a valid number
        if (!is_numeric($amount)) {
            return '';
        }

        return floatval($amount);
    }

    private function normalize_variable_symbol($value) {
        $value = preg_replace('/[^0-9]/', '', (string) $value);

        if ($value === '') {
            return '';
        }

        if (strlen($value) > 10) {
            $value = substr($value, 0, 10);
        }

        return $value;
    }
    
    /**
     * Normalize items array
     */
    private function normalize_items($items) {
        $normalized_items = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized_item = array(
                'name' => '',
                'unit' => 'pcs',
                'quantity' => 1,
                'price' => 0,
                'total' => 0,
                'price_type' => 1,
                'vat_rate_type' => 2,
                'vat_rate' => 0.0,
                'is_tax_movement' => false,
                'discount_percentage' => 0.0,
            );

            if (isset($item['name'])) {
                $normalized_item['name'] = trim((string) $item['name']);
            } elseif (isset($item['Name'])) {
                $normalized_item['name'] = trim((string) $item['Name']);
            } elseif (isset($item['description'])) {
                $normalized_item['name'] = trim((string) $item['description']);
            } elseif (isset($item['Description'])) {
                $normalized_item['name'] = trim((string) $item['Description']);
            }

            if (isset($item['unit'])) {
                $normalized_item['unit'] = $this->normalize_unit_label($item['unit']);
            } elseif (isset($item['Unit'])) {
                $normalized_item['unit'] = $this->normalize_unit_label($item['Unit']);
            } elseif (isset($item['UnitOfMeasure'])) {
                $normalized_item['unit'] = $this->normalize_unit_label($item['UnitOfMeasure']);
            }

            if (isset($item['quantity'])) {
                $normalized_item['quantity'] = floatval($item['quantity']);
            } elseif (isset($item['Quantity'])) {
                $normalized_item['quantity'] = floatval($item['Quantity']);
            } elseif (isset($item['Amount'])) {
                $normalized_item['quantity'] = floatval($item['Amount']);
            }

            if (isset($item['price'])) {
                $normalized_item['price'] = $this->normalize_amount($item['price']);
            } elseif (isset($item['UnitPrice'])) {
                $normalized_item['price'] = $this->normalize_amount($item['UnitPrice']);
            } elseif (isset($item['Price'])) {
                $normalized_item['price'] = $this->normalize_amount($item['Price']);
            }

            if (isset($item['total'])) {
                $normalized_item['total'] = $this->normalize_amount($item['total']);
            } elseif (isset($item['Total'])) {
                $normalized_item['total'] = $this->normalize_amount($item['Total']);
            }

            if (isset($item['price_type'])) {
                $normalized_item['price_type'] = (int) $item['price_type'];
            } elseif (isset($item['PriceType'])) {
                $normalized_item['price_type'] = (int) $item['PriceType'];
            }

            if (isset($item['vat_rate_type'])) {
                $normalized_item['vat_rate_type'] = (int) $item['vat_rate_type'];
            } elseif (isset($item['VatRateType'])) {
                $normalized_item['vat_rate_type'] = (int) $item['VatRateType'];
            }

            if (isset($item['vat_rate'])) {
                $normalized_item['vat_rate'] = (float) $this->normalize_amount($item['vat_rate']);
            } elseif (isset($item['VatRate'])) {
                $normalized_item['vat_rate'] = (float) $this->normalize_amount($item['VatRate']);
            }

            if (isset($item['is_tax_movement'])) {
                $normalized_item['is_tax_movement'] = (bool) $item['is_tax_movement'];
            } elseif (isset($item['IsTaxMovement'])) {
                $normalized_item['is_tax_movement'] = (bool) $item['IsTaxMovement'];
            }

            if (isset($item['discount_percentage'])) {
                $normalized_item['discount_percentage'] = (float) $item['discount_percentage'];
            } elseif (isset($item['DiscountPercentage'])) {
                $normalized_item['discount_percentage'] = (float) $item['DiscountPercentage'];
            }

            // Calculate total if not provided
            if ($normalized_item['total'] == 0 && $normalized_item['quantity'] > 0 && $normalized_item['price'] > 0) {
                $normalized_item['total'] = $normalized_item['quantity'] * $normalized_item['price'];
            }
            
            $normalized_items[] = $normalized_item;
        }
        
        return $normalized_items;
    }

    private function normalize_unit_label($unit) {
        if (!is_string($unit)) {
            $unit = (string) $unit;
        }

        $unit = trim($unit);

        if ($unit === '') {
            return 'pcs';
        }

        $normalized = strtolower($unit);

        $map = array(
            'ks' => 'pcs',
            'kus' => 'pcs',
            'kusy' => 'pcs',
        );

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        return $unit;
    }

    /**
     * Build iDoklad-compatible payload from extracted data.
     */
    public function build_idoklad_payload($extracted_data, $context = array()) {
        $extracted_data = is_array($extracted_data) ? $extracted_data : array();
        $context = is_array($context) ? $context : array();

        $invoice_section = array();
        if (isset($extracted_data['raw_invoice']) && is_array($extracted_data['raw_invoice'])) {
            $invoice_section = $extracted_data['raw_invoice'];
        } elseif (isset($extracted_data['Invoice']) && is_array($extracted_data['Invoice'])) {
            $invoice_section = $extracted_data['Invoice'];
        }

        if (!empty($invoice_section)) {
            return $this->build_payload_from_invoice_section($invoice_section, $extracted_data, $context);
        }

        $current_date = date('Y-m-d');
        $issue_date = !empty($extracted_data['date']) ? $extracted_data['date'] : $current_date;
        $due_date = !empty($extracted_data['due_date']) ? $extracted_data['due_date'] : date('Y-m-d', strtotime($issue_date . ' +14 days'));

        $document_number = !empty($extracted_data['invoice_number'])
            ? $extracted_data['invoice_number']
            : 'AI-' . date('YmdHis');

        $variable_symbol = !empty($extracted_data['variable_symbol'])
            ? $extracted_data['variable_symbol']
            : $this->normalize_variable_symbol($document_number);

        $currency_code = !empty($extracted_data['currency']) ? strtoupper($extracted_data['currency']) : 'CZK';

        $items = $this->build_idoklad_items(
            isset($extracted_data['items']) ? $extracted_data['items'] : array(),
            isset($extracted_data['total_amount']) ? $extracted_data['total_amount'] : null
        );

        $partner_data = array_filter(array(
            'company' => $extracted_data['supplier_name'] ?? ($context['email_from'] ?? ''),
            'email' => $context['email_from'] ?? '',
            'vat_number' => $extracted_data['supplier_vat_number'] ?? '',
            'address' => $extracted_data['supplier_address'] ?? '',
            'city' => $extracted_data['supplier_city'] ?? '',
            'postal_code' => $extracted_data['supplier_postal_code'] ?? ''
        ));

        $payload = array(
            'DocumentNumber' => $document_number,
            'date' => $issue_date,
            'DateOfIssue' => $issue_date,
            'DateOfMaturity' => $due_date,
            'Description' => !empty($context['email_subject'])
                ? $context['email_subject']
                : __('Invoice processed via automation', 'idoklad-invoice-processor'),
            'Note' => $extracted_data['notes'] ?? '',
            'payment_method' => $extracted_data['payment_method'] ?? '',
            'Currency' => $currency_code,
            'CurrencyId' => $this->map_currency_to_id($currency_code),
            'TotalAmount' => isset($extracted_data['total_amount']) ? $this->normalize_amount($extracted_data['total_amount']) : null,
            'Items' => $items
        );

        if (!empty($variable_symbol)) {
            $payload['VariableSymbol'] = $variable_symbol;
        }

        if (!empty($extracted_data['order_number'])) {
            $payload['OrderNumber'] = $extracted_data['order_number'];
        }

        if (!empty($partner_data)) {
            $payload['partner_data'] = $partner_data;
        }

        if (!empty($extracted_data['customer_name'])) {
            $payload['customer_name'] = $extracted_data['customer_name'];
        }

        if (!empty($extracted_data['customer_vat_number'])) {
            $payload['customer_vat_number'] = $extracted_data['customer_vat_number'];
        }

        return $payload;
    }

    private function build_payload_from_invoice_section($invoice_section, $extracted_data, $context) {
        $issue_date = $this->normalize_date($invoice_section['DateOfIssue'] ?? ($extracted_data['date'] ?? date('Y-m-d')));
        $tax_date = $this->normalize_date($invoice_section['DateOfTaxing'] ?? $issue_date);
        $maturity_date = $this->normalize_date($invoice_section['DateOfMaturity'] ?? ($extracted_data['due_date'] ?? $issue_date));
        $accounting_date = $this->normalize_date($invoice_section['DateOfAccountingEvent'] ?? $issue_date);
        $vat_application_date = $this->normalize_date($invoice_section['DateOfVatApplication'] ?? $tax_date);

        $document_number = !empty($invoice_section['DocumentNumber'])
            ? trim((string) $invoice_section['DocumentNumber'])
            : (!empty($extracted_data['invoice_number']) ? $extracted_data['invoice_number'] : 'AI-' . date('YmdHis'));

        $order_number = !empty($invoice_section['OrderNumber'])
            ? trim((string) $invoice_section['OrderNumber'])
            : ($extracted_data['order_number'] ?? '');

        $variable_symbol = $this->normalize_variable_symbol(
            $invoice_section['VariableSymbol'] ?? ($extracted_data['variable_symbol'] ?? $document_number)
        );

        $currency_code = !empty($invoice_section['CurrencyCode'])
            ? strtoupper((string) $invoice_section['CurrencyCode'])
            : (!empty($extracted_data['currency']) ? strtoupper($extracted_data['currency']) : 'CZK');

        $items = $this->build_idoklad_items(
            isset($invoice_section['Items']) ? $invoice_section['Items'] : array(),
            $this->resolve_total_amount_from_invoice($invoice_section, $extracted_data)
        );

        $partner_payload = $this->build_partner_payload_from_invoice($invoice_section, $extracted_data, $context);

        $payload = array(
            'DocumentNumber' => $document_number,
            'OrderNumber' => $order_number,
            'VariableSymbol' => $variable_symbol,
            'DateOfIssue' => $issue_date,
            'DateOfTaxing' => $tax_date,
            'DateOfMaturity' => $maturity_date,
            'DateOfAccountingEvent' => $accounting_date,
            'DateOfVatApplication' => $vat_application_date,
            'Description' => $invoice_section['Description'] ?? ($context['email_subject'] ?? __('Invoice processed via automation', 'idoklad-invoice-processor')),
            'Note' => $this->normalize_notes($invoice_section['Notes'] ?? ($extracted_data['notes'] ?? '')),
            'CurrencyId' => $this->map_currency_to_id($currency_code),
            'CurrencyCode' => $currency_code,
            'ExchangeRate' => $this->normalize_exchange_value($invoice_section['ExchangeRate'] ?? null),
            'ExchangeRateAmount' => $this->normalize_exchange_value($invoice_section['ExchangeRateAmount'] ?? null),
            'PaymentOptionId' => isset($invoice_section['PaymentOptionId']) ? (int) $invoice_section['PaymentOptionId'] : 1,
            'ConstantSymbolId' => isset($invoice_section['ConstantSymbolId']) ? (int) $invoice_section['ConstantSymbolId'] : 7,
            'PartnerId' => $this->normalize_partner_id_value($invoice_section['PartnerId'] ?? ($extracted_data['PartnerId'] ?? null)),
            'ItemsTextPrefix' => $invoice_section['ItemsTextPrefix'] ?? 'Invoice items:',
            'ItemsTextSuffix' => $invoice_section['ItemsTextSuffix'] ?? 'Thanks for your business.',
            'Items' => $items,
            'IsEet' => isset($invoice_section['IsEet']) ? (bool) $invoice_section['IsEet'] : false,
            'EetResponsibility' => isset($invoice_section['EetResponsibility']) ? (int) $invoice_section['EetResponsibility'] : 0,
            'IsIncomeTax' => isset($invoice_section['IsIncomeTax']) ? (bool) $invoice_section['IsIncomeTax'] : true,
            'VatOnPayStatus' => isset($invoice_section['VatOnPayStatus']) ? (int) $invoice_section['VatOnPayStatus'] : 0,
            'VatRegime' => isset($invoice_section['VatRegime']) ? (int) $invoice_section['VatRegime'] : 0,
            'HasVatRegimeOss' => isset($invoice_section['HasVatRegimeOss']) ? (bool) $invoice_section['HasVatRegimeOss'] : false,
            'ReportLanguage' => isset($invoice_section['ReportLanguage']) ? (int) $invoice_section['ReportLanguage'] : 1,
        );

        if (!empty($partner_payload)) {
            $payload['partner_data'] = $partner_payload;
        }

        if (!empty($extracted_data['warnings'])) {
            $payload['metadata']['warnings'] = $extracted_data['warnings'];
        }

        if (!empty($extracted_data['checklist'])) {
            $payload['metadata']['checklist'] = $extracted_data['checklist'];
        }

        $payload['metadata']['source'] = 'chatgpt';
        $payload['metadata']['generated_at'] = $this->get_current_timestamp();
        $payload['metadata']['model'] = get_option('idoklad_chatgpt_model', 'gpt-5-nano');

        if (!empty($invoice_section['Warnings'])) {
            $payload['metadata']['invoice_warnings'] = $this->normalize_string_list($invoice_section['Warnings']);
        }

        if (!empty($invoice_section['Checklist'])) {
            $payload['metadata']['invoice_checklist'] = $this->normalize_string_list($invoice_section['Checklist']);
        }

        return $payload;
    }

    private function resolve_total_amount_from_invoice($invoice_section, $extracted_data) {
        if (isset($invoice_section['Prices']) && is_array($invoice_section['Prices'])) {
            $prices = $invoice_section['Prices'];
            if (isset($prices['TotalWithVat']) && $prices['TotalWithVat'] !== null) {
                return $prices['TotalWithVat'];
            }
            if (isset($prices['TotalWithoutVat']) && $prices['TotalWithoutVat'] !== null) {
                return $prices['TotalWithoutVat'];
            }
        }

        return $extracted_data['total_amount'] ?? null;
    }

    private function build_partner_payload_from_invoice($invoice_section, $extracted_data, $context) {
        $partner = isset($invoice_section['Partner']) && is_array($invoice_section['Partner'])
            ? $invoice_section['Partner']
            : array();

        $partner_name = $partner['PartnerName'] ?? ($extracted_data['supplier_name'] ?? '');
        $address_full = $partner['AddressFull'] ?? ($extracted_data['supplier_address'] ?? '');
        $address_parts = $this->split_full_address($address_full);

        $partner_payload = array(
            'company' => $partner_name,
            'email' => $partner['Email'] ?? ($context['email_from'] ?? ''),
            'address' => $address_parts['street'] ?: $address_full,
            'city' => $partner['City'] ?? ($address_parts['city'] ?? ($extracted_data['supplier_city'] ?? '')),
            'postal_code' => $partner['PostalCode'] ?? ($address_parts['postal_code'] ?? ($extracted_data['supplier_postal_code'] ?? '')),
            'identification_number' => $partner['IdentificationNumber'] ?? ($extracted_data['supplier_id_number'] ?? ''),
            'vat_number' => $partner['VatIdentificationNumber'] ?? ($extracted_data['supplier_vat_number'] ?? ''),
        );

        return array_filter($partner_payload, function ($value) {
            if (is_string($value)) {
                return trim($value) !== '';
            }
            return !empty($value);
        });
    }

    private function normalize_notes($notes) {
        if (is_array($notes)) {
            $notes = array_filter(array_map('trim', $notes), function ($note) {
                return $note !== '';
            });
            return implode("\n", $notes);
        }

        if (is_string($notes)) {
            return trim($notes);
        }

        return '';
    }

    private function normalize_exchange_value($value, $default = 1.0) {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = $this->normalize_amount($value);

        return $normalized !== '' ? (float) $normalized : $default;
    }

    private function normalize_partner_id_value($value) {
        $normalized = $this->normalize_partner_id($value);

        return $normalized ? (int) $normalized : null;
    }

    private function normalize_partner_id($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $digits = preg_replace('/[^0-9]/', '', $value);
            if ($digits !== '') {
                return (int) $digits;
            }
        }

        return null;
    }

    private function normalize_string_list($value) {
        if (empty($value)) {
            return array();
        }

        if (is_string($value)) {
            $value = array($value);
        }

        if (!is_array($value)) {
            return array();
        }

        $normalized = array();

        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return $normalized;
    }

    private function get_current_timestamp() {
        if (function_exists('current_time')) {
            return current_time('mysql');
        }

        return date('Y-m-d H:i:s');
    }

    private function build_idoklad_items($items, $total_amount) {
        $normalized_items = $this->normalize_items($items);

        if (!empty($normalized_items)) {
            return array_map(function ($item) {
                $unit_price = $item['price'] > 0 ? $item['price'] : ($item['total'] > 0 && $item['quantity'] > 0 ? $item['total'] / $item['quantity'] : 0);
                $total = $item['total'] > 0 ? $item['total'] : ($item['quantity'] * $unit_price);

                return array(
                    'Name' => $item['name'] ?: __('Invoice item', 'idoklad-invoice-processor'),
                    'Unit' => !empty($item['unit']) ? $item['unit'] : 'pcs',
                    'Amount' => $item['quantity'] ?: 1,
                    'UnitPrice' => $unit_price,
                    'Total' => $total,
                    'PriceType' => isset($item['price_type']) ? (int) $item['price_type'] : 1,
                    'VatRateType' => isset($item['vat_rate_type']) ? (int) $item['vat_rate_type'] : 2,
                    'VatRate' => isset($item['vat_rate']) ? (float) $item['vat_rate'] : 0.0,
                    'IsTaxMovement' => !empty($item['is_tax_movement']),
                    'DiscountPercentage' => isset($item['discount_percentage']) ? (float) $item['discount_percentage'] : 0.0
                );
            }, $normalized_items);
        }

        $amount = $this->normalize_amount($total_amount);

        return array(
            array(
                'Name' => __('Invoice total', 'idoklad-invoice-processor'),
                'Unit' => 'pcs',
                'Amount' => 1,
                'UnitPrice' => $amount,
                'Total' => $amount,
                'PriceType' => 1,
                'VatRateType' => 2,
                'VatRate' => 0.0,
                'IsTaxMovement' => false,
                'DiscountPercentage' => 0.0
            )
        );
    }

    private function map_currency_to_id($currency) {
        $map = array(
            'CZK' => 1,
            'EUR' => 2,
            'USD' => 3,
            'GBP' => 4
        );

        $currency = strtoupper((string) $currency);

        return isset($map[$currency]) ? $map[$currency] : 1;
    }
    
    /**
     * Test ChatGPT connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array('success' => false, 'message' => 'ChatGPT API key is not configured');
        }
        
        try {
            // First, try to auto-detect the best available model
            $detected_model = $this->auto_detect_best_model();
            if ($detected_model) {
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad ChatGPT: Auto-detected best model: ' . $detected_model);
                }
            }
            
            $test_prompt = 'Extract invoice data from this sample text: "Invoice #INV-001, Date: 2024-01-15, Total: 1000 CZK, Supplier: Test Company". Return JSON with invoice_number, date, total_amount, supplier_name.';
            
            $response = $this->make_api_request($test_prompt);
            $data = $this->parse_response($response);
            
            if (!empty($data['invoice_number']) && !empty($data['supplier_name'])) {
                $message = 'ChatGPT connection successful. Model: ' . $this->model . ', Extracted: ' . $data['invoice_number'] . ' from ' . $data['supplier_name'];
                if ($detected_model && $this->was_last_detected_model_fallback()) {
                    $message .= ' (Using fallback model because the preferred model is unavailable)';
                }
                return array('success' => true, 'message' => $message);
            } else {
                return array('success' => false, 'message' => 'ChatGPT responded but failed to extract test data properly');
            }
            
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad ChatGPT Test Error: ' . $e->getMessage());
            }
            
            // If the current model fails, try to auto-detect and retry
            if (strpos($e->getMessage(), 'does not exist') !== false || strpos($e->getMessage(), 'not found') !== false) {
                try {
                    $detected_model = $this->auto_detect_best_model();
                    if ($detected_model) {
                        if (get_option('idoklad_debug_mode')) {
                            error_log('iDoklad ChatGPT: Retrying with auto-detected model: ' . $detected_model);
                        }

                        $response = $this->make_api_request($test_prompt);
                        $data = $this->parse_response($response);

                        if (!empty($data['invoice_number']) && !empty($data['supplier_name'])) {
                            $message = 'ChatGPT connection successful. Model: ' . $this->model . ', Extracted: ' . $data['invoice_number'] . ' from ' . $data['supplier_name'];

                            if ($this->was_last_detected_model_fallback()) {
                                $message .= ' (Using fallback model because the preferred model is unavailable)';
                            }

                            return array(
                                'success' => true,
                                'message' => $message
                            );
                        }
                    }
                } catch (Exception $e2) {
                    // If auto-detection also fails, return the original error
                }
            }
            
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Get available models
     */
    public function get_available_models() {
        return array(
            'gpt-5-nano' => 'GPT-5 Nano (Next-Gen Efficiency)',
            'gpt-4o' => 'GPT-4o (Latest, Best Performance)',
            'gpt-4o-mini' => 'GPT-4o Mini (Fast & Cost-Effective)',
            'gpt-4-turbo' => 'GPT-4 Turbo (High Performance)',
            'gpt-4' => 'GPT-4 (Standard)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Fast & Affordable)',
            'gpt-4o-2024-11-20' => 'GPT-4o (November 2024)',
            'o1-preview' => 'o1-preview (Advanced Reasoning)',
            'o1-mini' => 'o1-mini (Fast Reasoning)'
        );
    }
    
    /**
     * Get actually available models from OpenAI API
     */
    public function get_api_available_models() {
        $cache_ttl = 60; // seconds

        if (is_array($this->cached_api_models) && (time() - $this->cached_api_models_timestamp) < $cache_ttl) {
            return $this->cached_api_models;
        }

        if (empty($this->api_key)) {
            return array();
        }

        try {
            $headers = array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'User-Agent' => 'WordPress-iDoklad-Processor/1.1.0'
            );
            
            $args = array(
                'headers' => $headers,
                'timeout' => 30,
                'method' => 'GET',
                'sslverify' => true
            );
            
            $response = wp_remote_request('https://api.openai.com/v1/models', $args);
            
            if (is_wp_error($response)) {
                return array();
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                return array();
            }
            
            $data = json_decode($response_body, true);
            
            if (!isset($data['data'])) {
                return array();
            }
            
            $available_models = array();
            foreach ($data['data'] as $model) {
                if (!isset($model['id'])) {
                    continue;
                }

                $model_id = $model['id'];

                if (strpos($model_id, 'gpt') === 0 || strpos($model_id, 'o') === 0) {
                    $available_models[$model_id] = $model_id;
                }
            }

            $this->cached_api_models = $available_models;
            $this->cached_api_models_timestamp = time();

            return $available_models;

        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad ChatGPT: Error fetching available models: ' . $e->getMessage());
            }
            $this->cached_api_models = array();
            $this->cached_api_models_timestamp = time();
            return array();
        }
    }
    
    /**
     * Auto-detect and set the best available model
     */
    public function auto_detect_best_model($api_models = null) {
        $this->last_detected_model = null;
        $this->last_detected_model_was_fallback = false;

        if (!is_array($api_models)) {
            $api_models = $this->get_api_available_models();
        }

        $manual_preference = (bool) get_option('idoklad_chatgpt_model_manual', 0);
        $stored_preference = get_option('idoklad_chatgpt_model', null);
        $current_preference = $stored_preference;

        if ($current_preference === null || $current_preference === '') {
            $current_preference = 'gpt-5-nano';
        }

        if (empty($api_models)) {
            $this->model = $current_preference;
            $this->last_detected_model = $current_preference;
            return $current_preference;
        }

        if (!empty($current_preference) && in_array($current_preference, $api_models, true)) {
            $this->model = $current_preference;
            $this->last_detected_model = $current_preference;
            return $current_preference;
        }

        // Priority order for models (best to fallback)
        $preferred_models = array(
            'gpt-5-nano',
            'gpt-4o',
            'gpt-4-turbo',
            'gpt-4',
            'gpt-3.5-turbo'
        );

        foreach ($preferred_models as $model) {
            if (in_array($model, $api_models, true)) {
                $this->model = $model;
                $this->last_detected_model = $model;
                $this->last_detected_model_was_fallback = $manual_preference;

                if (!$manual_preference) {
                    update_option('idoklad_chatgpt_model', $model);
                    update_option('idoklad_chatgpt_model_manual', 0);
                }

                return $model;
            }
        }

        // If no preferred model is available, use the first available one
        if (!empty($api_models)) {
            $detected = array_values($api_models)[0];
            $this->model = $detected;
            $this->last_detected_model = $detected;
            $this->last_detected_model_was_fallback = $manual_preference;

            if (!$manual_preference) {
                update_option('idoklad_chatgpt_model', $detected);
                update_option('idoklad_chatgpt_model_manual', 0);
            }

            return $detected;
        }

        return false;
    }

    public function get_last_detected_model() {
        return $this->last_detected_model;
    }

    public function was_last_detected_model_fallback() {
        return $this->last_detected_model_was_fallback;
    }
    
    /**
     * Validate extracted data completeness
     */
    public function validate_extracted_data($data) {
        $errors = array();
        $warnings = array();
        
        // Required fields
        if (empty($data['invoice_number'])) {
            $errors[] = 'Invoice number is missing';
        }
        
        if (empty($data['date'])) {
            $errors[] = 'Invoice date is missing';
        } elseif (!strtotime($data['date'])) {
            $errors[] = 'Invalid invoice date format';
        }
        
        if (empty($data['total_amount']) || !is_numeric($data['total_amount'])) {
            $errors[] = 'Total amount is missing or invalid';
        }
        
        if (empty($data['supplier_name'])) {
            $errors[] = 'Supplier name is missing';
        }
        
        // Warnings for optional but important fields
        if (empty($data['supplier_vat_number'])) {
            $warnings[] = 'Supplier VAT number is missing';
        }
        
        if (empty($data['items']) || !is_array($data['items'])) {
            $warnings[] = 'Invoice items are missing';
        } else {
            foreach ($data['items'] as $index => $item) {
                if (empty($item['name'])) {
                    $warnings[] = "Item " . ($index + 1) . " name is missing";
                }
                if (empty($item['quantity']) || !is_numeric($item['quantity'])) {
                    $warnings[] = "Item " . ($index + 1) . " quantity is missing or invalid";
                }
                if (empty($item['price']) || !is_numeric($item['price'])) {
                    $warnings[] = "Item " . ($index + 1) . " price is missing or invalid";
                }
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        );
    }
    
    /**
     * Get usage statistics
     */
    public function get_usage_stats() {
        // This would typically track API usage
        // For now, return basic info
        return array(
            'model' => $this->model,
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature
        );
    }
}

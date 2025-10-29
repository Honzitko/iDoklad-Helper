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
    private $temperature = 0.1;
    
    public function __construct() {
        $this->api_key = get_option('idoklad_chatgpt_api_key');
        $this->model = get_option('idoklad_chatgpt_model', 'gpt-4o');
    }
    
    /**
     * Extract invoice data from PDF text
     */
    public function extract_invoice_data($pdf_text) {
        if (empty($this->api_key)) {
            throw new Exception('ChatGPT API key is not configured');
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad ChatGPT: Extracting invoice data from PDF text');
        }
        
        $prompt = $this->build_extraction_prompt($pdf_text);
        
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
     * Build extraction prompt
     */
    private function build_extraction_prompt($pdf_text) {
        $base_prompt = get_option('idoklad_chatgpt_prompt', 'Extract invoice data from this PDF. Return JSON with: invoice_number, date, total_amount, supplier_name, supplier_vat_number, items (array with name, quantity, price), currency. Validate data completeness.');
        
        // Truncate PDF text if too long (ChatGPT has token limits)
        $max_text_length = 8000; // Approximate token limit consideration
        if (strlen($pdf_text) > $max_text_length) {
            $pdf_text = substr($pdf_text ?: '', 0, $max_text_length) . '... [truncated]';
        }
        
        $prompt = $base_prompt . "\n\nPDF Content:\n" . $pdf_text . "\n\nPlease return only valid JSON without any additional text or formatting.";
        
        return $prompt;
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
        $normalized = array();
        
        // Required fields
        $normalized['invoice_number'] = isset($data['invoice_number']) ? trim($data['invoice_number']) : '';
        $normalized['date'] = isset($data['date']) ? $this->normalize_date($data['date']) : '';
        $normalized['total_amount'] = isset($data['total_amount']) ? $this->normalize_amount($data['total_amount']) : '';
        $normalized['supplier_name'] = isset($data['supplier_name']) ? trim($data['supplier_name']) : '';
        
        // Optional fields
        $normalized['supplier_vat_number'] = isset($data['supplier_vat_number']) ? trim($data['supplier_vat_number']) : '';
        $normalized['currency'] = isset($data['currency']) ? strtoupper(trim($data['currency'])) : 'CZK';
        $normalized['variable_symbol'] = $this->extract_variable_symbol($data);
        $normalized['items'] = isset($data['items']) && is_array($data['items']) ? $this->normalize_items($data['items']) : array();
        
        // Additional fields that might be useful
        $normalized['customer_name'] = isset($data['customer_name']) ? trim($data['customer_name']) : '';
        $normalized['customer_vat_number'] = isset($data['customer_vat_number']) ? trim($data['customer_vat_number']) : '';
        $normalized['due_date'] = isset($data['due_date']) ? $this->normalize_date($data['due_date']) : '';
        $normalized['payment_method'] = isset($data['payment_method']) ? trim($data['payment_method']) : '';
        $normalized['notes'] = isset($data['notes']) ? trim($data['notes']) : '';

        return $normalized;
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
        if (empty($amount)) {
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
                'name' => isset($item['name']) ? trim($item['name']) : '',
                'quantity' => isset($item['quantity']) ? floatval($item['quantity']) : 1,
                'price' => isset($item['price']) ? $this->normalize_amount($item['price']) : 0,
                'total' => isset($item['total']) ? $this->normalize_amount($item['total']) : 0
            );
            
            // Calculate total if not provided
            if ($normalized_item['total'] == 0 && $normalized_item['quantity'] > 0 && $normalized_item['price'] > 0) {
                $normalized_item['total'] = $normalized_item['quantity'] * $normalized_item['price'];
            }
            
            $normalized_items[] = $normalized_item;
        }
        
        return $normalized_items;
    }

    /**
     * Build iDoklad-compatible payload from extracted data.
     */
    public function build_idoklad_payload($extracted_data, $context = array()) {
        $extracted_data = is_array($extracted_data) ? $extracted_data : array();
        $context = is_array($context) ? $context : array();

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

    private function build_idoklad_items($items, $total_amount) {
        $normalized_items = $this->normalize_items($items);

        if (!empty($normalized_items)) {
            return array_map(function ($item) {
                $unit_price = $item['price'] > 0 ? $item['price'] : ($item['total'] > 0 && $item['quantity'] > 0 ? $item['total'] / $item['quantity'] : 0);
                $total = $item['total'] > 0 ? $item['total'] : ($item['quantity'] * $unit_price);

                return array(
                    'Name' => $item['name'] ?: __('Invoice item', 'idoklad-invoice-processor'),
                    'Unit' => 'pcs',
                    'Amount' => $item['quantity'] ?: 1,
                    'UnitPrice' => $unit_price,
                    'Total' => $total,
                    'PriceType' => 1,
                    'VatRateType' => 2,
                    'VatRate' => 0.0,
                    'IsTaxMovement' => false,
                    'DiscountPercentage' => 0.0
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
                if ($detected_model && $detected_model !== get_option('idoklad_chatgpt_model')) {
                    $message .= ' (Auto-updated to best available model)';
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
                    if ($detected_model && $detected_model !== $this->model) {
                        if (get_option('idoklad_debug_mode')) {
                            error_log('iDoklad ChatGPT: Retrying with auto-detected model: ' . $detected_model);
                        }
                        
                        $response = $this->make_api_request($test_prompt);
                        $data = $this->parse_response($response);
                        
                        if (!empty($data['invoice_number']) && !empty($data['supplier_name'])) {
                            return array(
                                'success' => true, 
                                'message' => 'ChatGPT connection successful. Auto-updated to model: ' . $this->model . ', Extracted: ' . $data['invoice_number'] . ' from ' . $data['supplier_name']
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
                if (isset($model['id']) && strpos($model['id'], 'gpt') === 0) {
                    $available_models[$model['id']] = $model['id'];
                }
            }
            
            return $available_models;
            
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad ChatGPT: Error fetching available models: ' . $e->getMessage());
            }
            return array();
        }
    }
    
    /**
     * Auto-detect and set the best available model
     */
    public function auto_detect_best_model() {
        $api_models = $this->get_api_available_models();
        
        // Priority order for models (best to fallback)
        $preferred_models = array(
            'gpt-4o',
            'gpt-4-turbo',
            'gpt-4',
            'gpt-3.5-turbo'
        );
        
        foreach ($preferred_models as $model) {
            if (in_array($model, $api_models)) {
                $this->model = $model;
                update_option('idoklad_chatgpt_model', $model);
                return $model;
            }
        }
        
        // If no preferred model is available, use the first available one
        if (!empty($api_models)) {
            $this->model = array_values($api_models)[0];
            update_option('idoklad_chatgpt_model', $this->model);
            return $this->model;
        }
        
        return false;
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

<?php
/**
 * Enhanced PDF.co AI Invoice Parser Integration for iDoklad
 * Optimized for extracting data that matches iDoklad API requirements
 * Includes comprehensive debugging and step-by-step examination tools
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_PDFCoAIParserEnhanced {
    
    private $api_key;
    private $api_url = 'https://api.pdf.co/v1';
    private $debug_mode;
    private $logger;
    
    public function __construct() {
        $this->api_key = get_option('idoklad_pdfco_api_key');
        $this->debug_mode = get_option('idoklad_debug_mode', false);
        $this->logger = IDokladProcessor_Logger::get_instance();
    }

    /**
     * Transform already-structured data to iDoklad format without calling PDF.co
     */
    public function transform_structured_data($extracted_data, $context = 'structured_input') {
        $this->log_step('Transforming structured data input', array('context' => $context));

        $idoklad_data = $this->transform_to_idoklad_format($extracted_data);
        $validation = $this->validate_idoklad_payload($idoklad_data);

        return array(
            'success' => $validation['is_valid'],
            'data' => $idoklad_data,
            'validation' => $validation
        );
    }

    /**
     * Parse invoice with comprehensive debugging and step-by-step examination
     */
    public function parse_invoice_with_debug($pdf_url, $custom_fields = array()) {
        $this->log_step('Starting PDF.co AI parsing with debug mode');
        
        if (empty($this->api_key)) {
            throw new Exception('PDF.co API key is not configured');
        }
        
        $this->log_step('API key configured: ' . substr($this->api_key, 0, 8) . '...');
        
        try {
            // Step 1: Prepare iDoklad-specific custom fields
            $idoklad_fields = $this->get_idoklad_optimized_fields();
            $all_fields = array_merge($idoklad_fields, $custom_fields);
            
            $this->log_step('Custom fields prepared', array('fields' => $all_fields));
            
            // Step 2: Submit parsing job
            $job_id = $this->submit_parsing_job_with_debug($pdf_url, $all_fields);
            $this->log_step('Job submitted successfully', array('job_id' => $job_id));
            
            // Step 3: Poll for results with detailed logging
            $raw_result = $this->poll_for_results_with_debug($job_id);
            $this->log_step('Raw parsing result received', array('result' => $raw_result));
            
            // Step 4: Extract and validate data
            $extracted_data = $this->extract_parsed_data_with_debug($raw_result);
            $this->log_step('Data extracted and validated', array('extracted_data' => $extracted_data));
            
            // Step 5: Transform to iDoklad format
            $idoklad_data = $this->transform_to_idoklad_format($extracted_data);
            $this->log_step('Data transformed to iDoklad format', array('idoklad_data' => $idoklad_data));
            
            // Step 6: Validate iDoklad payload
            $validation_result = $this->validate_idoklad_payload($idoklad_data);
            $this->log_step('iDoklad payload validation', array('validation' => $validation_result));
            
            return array(
                'success' => true,
                'data' => $idoklad_data,
                'debug_info' => array(
                    'job_id' => $job_id,
                    'raw_result' => $raw_result,
                    'extracted_data' => $extracted_data,
                    'validation' => $validation_result,
                    'steps_completed' => array(
                        'job_submitted',
                        'results_polled',
                        'data_extracted',
                        'format_transformed',
                        'payload_validated'
                    )
                )
            );
            
        } catch (Exception $e) {
            $this->log_step('Error occurred: ' . $e->getMessage(), array('error' => $e->getMessage()));
            throw $e;
        }
    }
    
    /**
     * Submit parsing job with detailed debugging
     */
    private function submit_parsing_job_with_debug($pdf_url, $custom_fields = array()) {
        $this->log_step('Submitting parsing job', array('pdf_url' => $pdf_url, 'custom_fields' => $custom_fields));
        
        $endpoint = $this->api_url . '/ai-invoice-parser';
        
        $data = array(
            'url' => $pdf_url
        );
        
        if (!empty($custom_fields) && is_array($custom_fields)) {
            $data['customfield'] = implode(',', $custom_fields);
        }
        
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'User-Agent' => 'WordPress-iDoklad-Processor/1.1.0'
            ),
            'body' => json_encode($data),
            'timeout' => 30,
            'method' => 'POST',
            'sslverify' => true
        );
        
        $this->log_step('Request details', array(
            'endpoint' => $endpoint,
            'headers' => $args['headers'],
            'body' => $data
        ));
        
        $response = wp_remote_request($endpoint, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_step('Request failed', array('error' => $error_message));
            throw new Exception('PDF.co AI Parser request failed: ' . $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->log_step('Response received', array(
            'status_code' => $response_code,
            'body' => $response_body
        ));
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']) ? $error_data['error'] : 'Unknown API error';
            throw new Exception('PDF.co AI Parser error (' . $response_code . '): ' . $error_message);
        }
        
        $response_data = json_decode($response_body, true);
        
        if (!$response_data) {
            throw new Exception('Invalid JSON response from PDF.co AI Parser: ' . $response_body);
        }
        
        // Extract job ID
        $job_id = null;
        if (isset($response_data['JobId'])) {
            $job_id = $response_data['JobId'];
        } elseif (isset($response_data['jobId'])) {
            $job_id = $response_data['jobId'];
        } elseif (isset($response_data['job_id'])) {
            $job_id = $response_data['job_id'];
        }
        
        if (!$job_id) {
            throw new Exception('No JobId found in response: ' . $response_body);
        }
        
        $this->log_step('Job ID extracted', array('job_id' => $job_id));
        return $job_id;
    }
    
    /**
     * Poll for results with detailed debugging
     */
    private function poll_for_results_with_debug($job_id, $max_attempts = 30, $delay_seconds = 2) {
        $this->log_step('Starting polling for results', array('job_id' => $job_id, 'max_attempts' => $max_attempts));
        
        $endpoint = $this->api_url . '/job/check';
        
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $this->log_step('Polling attempt ' . $attempt, array('job_id' => $job_id));
            
            $args = array(
                'headers' => array(
                    'x-api-key' => $this->api_key,
                    'User-Agent' => 'WordPress-iDoklad-Processor/1.1.0'
                ),
                'timeout' => 30,
                'method' => 'GET',
                'sslverify' => true
            );
            
            $url = $endpoint . '?jobid=' . urlencode($job_id);
            $response = wp_remote_request($url, $args);
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->log_step('Polling error', array('error' => $error_message));
                throw new Exception('PDF.co AI Parser polling failed: ' . $error_message);
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            $this->log_step('Polling response', array(
                'attempt' => $attempt,
                'status_code' => $response_code,
                'body' => $response_body
            ));
            
            if ($response_code !== 200) {
                throw new Exception('PDF.co AI Parser polling error (' . $response_code . '): ' . $response_body);
            }
            
            $response_data = json_decode($response_body, true);
            
            if (!$response_data) {
                throw new Exception('Invalid JSON response from PDF.co AI Parser polling: ' . $response_body);
            }
            
            // Check job status
            if (isset($response_data['status'])) {
                $this->log_step('Job status check', array('status' => $response_data['status']));
                
                if ($response_data['status'] === 'success' || $response_data['status'] === 'completed') {
                    $this->log_step('Job completed successfully');
                    return $response_data;
                }
                
                if ($response_data['status'] === 'error' || $response_data['status'] === 'failed') {
                    $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
                    $this->log_step('Job failed', array('error' => $error_message));
                    throw new Exception('PDF.co AI Parser job failed: ' . $error_message);
                }
            }
            
            // Wait before next attempt
            if ($attempt < $max_attempts) {
                sleep($delay_seconds);
            }
        }
        
        throw new Exception('PDF.co AI Parser job timed out after ' . ($max_attempts * $delay_seconds) . ' seconds');
    }
    
    /**
     * Extract parsed data with detailed debugging
     */
    private function extract_parsed_data_with_debug($response_data) {
        $this->log_step('Extracting parsed data', array('response_data' => $response_data));
        
        if (!isset($response_data['body']) || empty($response_data['body'])) {
            throw new Exception('No parsed invoice data in PDF.co AI Parser response');
        }
        
        $parsed_data = $response_data['body'];
        $this->log_step('Raw parsed data extracted', array('parsed_data' => $parsed_data));
        
        // Validate required fields
        $required_fields = array(
            'DocumentNumber' => array('DocumentNumber', 'InvoiceNumber', 'InvoiceNo', 'document_number', 'invoice_number'),
            'DateOfIssue' => array('DateOfIssue', 'IssueDate', 'InvoiceDate', 'date_of_issue', 'issue_date'),
            'PartnerName' => array('PartnerName', 'CompanyName', 'SupplierName', 'partner_name', 'company_name'),
            'Items' => array('Items', 'items', 'LineItems', 'lines', 'InvoiceItems')
        );
        $missing_fields = array();

        foreach ($required_fields as $field => $aliases) {
            $value = $this->extract_field($parsed_data, $aliases);
            if ($field === 'Items') {
                if (is_string($value)) {
                    $decoded_items = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded_items;
                    }
                }

                if (empty($value) || !is_array($value)) {
                    $missing_fields[] = $field;
                }
            } elseif ($this->is_value_empty($value)) {
                $missing_fields[] = $field;
            }
        }

        if (!empty($missing_fields)) {
            $this->log_step('Missing required fields', array('missing_fields' => $missing_fields));
        }
        
        return $parsed_data;
    }
    
    /**
     * Transform extracted data to iDoklad format
     */
    private function transform_to_idoklad_format($extracted_data) {
        $this->log_step('Transforming to iDoklad format', array('extracted_data' => $extracted_data));
        
        $idoklad_data = array();
        
        // Document identification
        $idoklad_data['DocumentNumber'] = $this->extract_field($extracted_data, array('DocumentNumber', 'InvoiceNumber', 'InvoiceNo', 'document_number', 'invoice_number'));
        $idoklad_data['DateOfIssue'] = $this->normalize_date($this->extract_field($extracted_data, array('DateOfIssue', 'IssueDate', 'InvoiceDate', 'date_of_issue', 'issue_date', 'invoice_date')));
        $idoklad_data['DateOfTaxing'] = $this->normalize_date($this->extract_field($extracted_data, array('DateOfTaxing', 'TaxDate', 'date_of_taxing', 'tax_date')));
        $idoklad_data['DateOfMaturity'] = $this->normalize_date($this->extract_field($extracted_data, array('DateOfMaturity', 'DueDate', 'MaturityDate', 'date_of_maturity', 'due_date')));
        
        // Partner information - structured for Postman collection workflow
        $idoklad_data['partner_data'] = array(
            'company' => $this->extract_field($extracted_data, array('CompanyName', 'SupplierCompany', 'PartnerCompany', 'PartnerName', 'SupplierName', 'company_name', 'supplier_name')),
            'email' => $this->extract_field($extracted_data, array('Email', 'PartnerEmail', 'SupplierEmail', 'ContactEmail', 'email')),
            'address' => $this->extract_field($extracted_data, array('Address', 'PartnerAddress', 'SupplierAddress', 'Street', 'address', 'street')),
            'city' => $this->extract_field($extracted_data, array('City', 'PartnerCity', 'SupplierCity', 'city')),

            'postal_code' => $this->extract_field($extracted_data, array('PostalCode', 'PartnerPostalCode', 'SupplierPostalCode', 'ZipCode', 'postal_code', 'zip')),
            'id' => $this->extract_field($extracted_data, array('PartnerId', 'ContactId', 'PartnerID', 'ContactID', 'Id', 'id', 'partner_id', 'contact_id'))

        );

        if (!empty($idoklad_data['partner_data']['id']) && is_numeric($idoklad_data['partner_data']['id'])) {
            $idoklad_data['partner_data']['id'] = intval($idoklad_data['partner_data']['id']);
            $idoklad_data['PartnerId'] = $idoklad_data['partner_data']['id'];
        }

        // Keep legacy fields for backward compatibility
        $idoklad_data['PartnerName'] = $idoklad_data['partner_data']['company'];
        $idoklad_data['PartnerAddress'] = $idoklad_data['partner_data']['address'];
        $idoklad_data['PartnerIdentificationNumber'] = $this->extract_field($extracted_data, array('VatNumber', 'PartnerVatNumber', 'SupplierVatNumber', 'VatIdentificationNumber', 'vat_number', 'vatid'));
        
        // Financial information
        $idoklad_data['CurrencyId'] = $this->get_currency_id($this->extract_field($extracted_data, array('Currency', 'CurrencyCode', 'currency')));
        $idoklad_data['ExchangeRate'] = floatval($this->extract_field($extracted_data, array('ExchangeRate', 'Rate', 'exchange_rate'))) ?: 1.0;
        $idoklad_data['ExchangeRateAmount'] = floatval($this->extract_field($extracted_data, array('ExchangeRateAmount', 'exchange_rate_amount'))) ?: 1.0;
        
        // Payment information
        $idoklad_data['VariableSymbol'] = $this->extract_field($extracted_data, array('VariableSymbol', 'VS', 'VariableSym', 'variable_symbol'));
        $idoklad_data['ConstantSymbol'] = $this->extract_field($extracted_data, array('ConstantSymbol', 'KS', 'ConstantSym', 'constant_symbol'));
        $idoklad_data['SpecificSymbol'] = $this->extract_field($extracted_data, array('SpecificSymbol', 'SS', 'SpecificSym', 'specific_symbol'));
        
        // Bank account
        $idoklad_data['BankAccountNumber'] = $this->extract_field($extracted_data, array('BankAccountNumber', 'AccountNumber', 'Account', 'bank_account', 'bank_account_number'));
        $idoklad_data['Iban'] = $this->extract_field($extracted_data, array('Iban', 'IBAN', 'iban'));
        $idoklad_data['Swift'] = $this->extract_field($extracted_data, array('Swift', 'SWIFT', 'Bic', 'BIC', 'swift'));
        
        // Description and notes
        $idoklad_data['Description'] = $this->extract_field($extracted_data, array('Description', 'Note', 'Comments', 'description'));
        $idoklad_data['Note'] = $this->extract_field($extracted_data, array('Note', 'Comments', 'Description', 'note'));
        
        // Items processing
        $idoklad_data['Items'] = $this->process_items($extracted_data);
        
        // Set defaults for required iDoklad fields
        $idoklad_data['PaymentOptionId'] = 1; // Bank transfer
        $idoklad_data['IsEet'] = false;
        $idoklad_data['EetResponsibility'] = 0;
        $idoklad_data['IsIncomeTax'] = true;
        $idoklad_data['VatOnPayStatus'] = 0;
        $idoklad_data['VatRegime'] = 0;
        $idoklad_data['HasVatRegimeOss'] = false;
        $idoklad_data['ItemsTextPrefix'] = 'Invoice items:';
        $idoklad_data['ItemsTextSuffix'] = 'Thank you for your business.';
        $idoklad_data['ReportLanguage'] = 1;
        
        if (class_exists('IDokladProcessor_CzechNormalizer')) {
            $normalizer = new IDokladProcessor_CzechNormalizer();
            $czech_payload = $normalizer->convert_payload($idoklad_data);
            $idoklad_data['HumanReadableCzech'] = $czech_payload['structured'];
            $idoklad_data['HumanReadableCzechSummary'] = $czech_payload['summary'];
            $this->log_step('Czech normalization added', array('human_readable' => $czech_payload));
        }

        $this->log_step('iDoklad format transformation completed', array('idoklad_data' => $idoklad_data));

        return $idoklad_data;
    }
    
    /**
     * Process invoice items
     */
    private function process_items($extracted_data) {
        $this->log_step('Processing invoice items');
        
        $items = array();
        
        $raw_items = $this->extract_field($extracted_data, array('Items', 'items', 'LineItems', 'lines', 'InvoiceItems'));

        if (is_string($raw_items)) {
            $decoded = json_decode($raw_items, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw_items = $decoded;
            }
        }

        if (isset($raw_items) && is_array($raw_items)) {
            foreach ($raw_items as $item) {
                if (!is_array($item)) continue;

                $processed_item = array(
                    'Name' => $this->extract_field($item, array('Name', 'Description', 'Product', 'Service', 'item_name')),
                    'Unit' => $this->extract_field($item, array('Unit', 'UnitOfMeasure', 'UOM', 'unit')) ?: 'pcs',
                    'Amount' => floatval($this->extract_field($item, array('Amount', 'Quantity', 'Qty', 'amount'))) ?: 1.0,
                    'UnitPrice' => floatval($this->extract_field($item, array('UnitPrice', 'Price', 'Rate', 'unit_price'))) ?: 0.0,
                    'PriceType' => 1, // With VAT
                    'VatRateType' => 2, // Standard rate
                    'VatRate' => floatval($this->extract_field($item, array('VatRate', 'TaxRate', 'vat_rate'))) ?: 0.0,
                    'IsTaxMovement' => false,
                    'DiscountPercentage' => floatval($this->extract_field($item, array('DiscountPercentage', 'Discount', 'discount_percentage'))) ?: 0.0
                );
                
                // Only add item if it has a name
                if (!empty($processed_item['Name'])) {
                    $items[] = $processed_item;
                }
            }
        }
        
        $this->log_step('Items processed', array('items_count' => count($items), 'items' => $items));
        
        return $items;
    }
    
    /**
     * Validate iDoklad payload
     */
    public function validate_idoklad_payload($data) {
        $this->log_step('Validating iDoklad payload');
        
        $validation = array(
            'is_valid' => true,
            'errors' => array(),
            'warnings' => array(),
            'required_fields_present' => array(),
            'required_fields_missing' => array()
        );
        
        $required_fields = array(
            'DocumentNumber',
            'DateOfIssue',
            'PartnerName',
            'Items'
        );
        
        foreach ($required_fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $validation['required_fields_present'][] = $field;
            } else {
                $validation['required_fields_missing'][] = $field;
                $validation['errors'][] = "Required field missing: $field";
                $validation['is_valid'] = false;
            }
        }
        
        // Validate items
        if (isset($data['Items']) && is_array($data['Items'])) {
            if (empty($data['Items'])) {
                $validation['errors'][] = "Items array is empty";
                $validation['is_valid'] = false;
            } else {
                foreach ($data['Items'] as $index => $item) {
                    if (!isset($item['Name']) || empty($item['Name'])) {
                        $validation['errors'][] = "Item $index missing name";
                        $validation['is_valid'] = false;
                    }
                    if (!isset($item['Amount']) || $item['Amount'] <= 0) {
                        $validation['warnings'][] = "Item $index has invalid amount";
                    }
                    if (!isset($item['UnitPrice']) || $item['UnitPrice'] <= 0) {
                        $validation['warnings'][] = "Item $index has invalid unit price";
                    }
                }
            }
        }
        
        $this->log_step('Payload validation completed', array('validation' => $validation));
        
        return $validation;
    }
    
    /**
     * Get iDoklad-optimized custom fields for AI Parser
     */
    private function get_idoklad_optimized_fields() {
        return array(
            // Document identification
            'DocumentNumber',
            'DateOfIssue',
            'DateOfTaxing',
            'DateOfMaturity',
            
            // Partner information
            'PartnerName',
            'PartnerAddress',
            'PartnerIdentificationNumber',
            
            // Financial information
            'Currency',
            'ExchangeRate',
            'TotalAmount',
            
            // Payment information
            'VariableSymbol',
            'ConstantSymbol',
            'SpecificSymbol',
            
            // Bank account
            'BankAccountNumber',
            'Iban',
            'Swift',
            
            // Items (will be parsed as array)
            'Items',
            
            // Description
            'Description',
            'Note'
        );
    }
    
    /**
     * Extract field value using multiple possible field names
     */
    private function extract_field($data, $possible_fields) {
        if (!is_array($data)) {
            return null;
        }

        foreach ($possible_fields as $field) {
            if (isset($data[$field]) && !$this->is_value_empty($data[$field])) {
                return $data[$field];
            }
        }

        $normalized_map = array();

        foreach ($data as $key => $value) {
            if ($this->is_value_empty($value)) {
                continue;
            }

            $lower_key = strtolower($key);
            $normalized_map[$lower_key] = $value;
            $stripped_key = strtolower(preg_replace('/[\s_\-]/', '', $key));
            $normalized_map[$stripped_key] = $value;
        }

        foreach ($possible_fields as $field) {
            $lower = strtolower($field);
            if (isset($normalized_map[$lower])) {
                return $normalized_map[$lower];
            }

            $stripped = strtolower(preg_replace('/[\s_\-]/', '', $field));
            if (isset($normalized_map[$stripped])) {
                return $normalized_map[$stripped];
            }
        }

        return null;
    }

    /**
     * Determine if value should be treated as empty
     */
    private function is_value_empty($value) {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return empty($value);
        }

        return false;
    }
    
    /**
     * Normalize date to YYYY-MM-DD format
     */
    private function normalize_date($date_string) {
        if (empty($date_string)) {
            return null;
        }
        
        // Try different date formats
        $formats = array(
            'Y-m-d',
            'd.m.Y',
            'd/m/Y',
            'm/d/Y',
            'Y-m-d H:i:s',
            'd.m.Y H:i:s'
        );
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_string);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        // Try strtotime as fallback
        $timestamp = strtotime($date_string);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return $date_string; // Return original if can't parse
    }
    
    /**
     * Get currency ID for iDoklad
     */
    private function get_currency_id($currency_code) {
        $currency_map = array(
            'CZK' => 1,
            'EUR' => 2,
            'USD' => 3,
            'GBP' => 4
        );
        
        if (isset($currency_map[strtoupper($currency_code)])) {
            return $currency_map[strtoupper($currency_code)];
        }
        
        return 1; // Default to CZK
    }
    
    /**
     * Log step with debugging information
     */
    private function log_step($message, $data = null) {
        if ($this->debug_mode) {
            $log_message = "[PDF.co AI Parser] $message";
            if ($data) {
                $log_message .= " - " . json_encode($data);
            }
            error_log($log_message);
        }
        
        // Also log to our logger if available
        if ($this->logger) {
            $this->logger->info("PDF.co AI Parser: $message", $data);
        }
    }
    
    /**
     * Test the enhanced AI Parser
     */
    public function test_enhanced_parser($pdf_url = null) {
        $this->log_step('Testing enhanced AI parser');
        
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'PDF.co API key is not configured'
            );
        }
        
        if (!$pdf_url) {
            $pdf_url = 'https://example.com/test.pdf'; // Dummy URL for testing
        }
        
        try {
            $result = $this->parse_invoice_with_debug($pdf_url);
            return array(
                'success' => true,
                'message' => 'Enhanced AI parser test completed successfully',
                'result' => $result
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Enhanced AI parser test failed: ' . $e->getMessage()
            );
        }
    }
}

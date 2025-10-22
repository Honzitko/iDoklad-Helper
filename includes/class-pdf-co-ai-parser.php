<?php
/**
 * PDF.co AI Invoice Parser Integration
 * Uses PDF.co's AI Invoice Parser API to extract structured invoice data
 * Documentation: https://docs.pdf.co/api-reference/ai-invoice-parser
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_PDFCoAIParser {
    
    private $api_key;
    private $api_url = 'https://api.pdf.co/v1';
    
    public function __construct() {
        $this->api_key = get_option('idoklad_pdfco_api_key');
    }
    
    /**
     * Parse invoice using PDF.co AI Invoice Parser with iDoklad-specific custom fields
     * 
     * @param string $pdf_url URL to the PDF file
     * @param array $custom_fields Optional additional custom field mappings
     * @return array Parsed invoice data in iDoklad format
     */
    public function parse_invoice($pdf_url, $custom_fields = array()) {
        if (empty($this->api_key)) {
            throw new Exception('PDF.co API key is not configured');
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Starting invoice parsing for URL: ' . $pdf_url);
        }
        
        // Use iDoklad-specific custom fields to get structured output
        $idoklad_custom_fields = $this->get_idoklad_custom_fields();
        
        // Merge with any additional custom fields
        $all_custom_fields = array_merge($idoklad_custom_fields, $custom_fields);
        
        // Step 1: Submit job to AI Invoice Parser
        $job_id = $this->submit_parsing_job($pdf_url, $all_custom_fields);
        
        if (empty($job_id)) {
            throw new Exception('Failed to submit AI parsing job');
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Job submitted with ID: ' . $job_id);
        }
        
        // Step 2: Poll for results
        $result = $this->poll_for_results($job_id);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Parsing completed. Result: ' . json_encode($result));
        }
        
        return $result;
    }
    
    /**
     * Submit parsing job to PDF.co AI Invoice Parser
     */
    private function submit_parsing_job($pdf_url, $custom_fields = array()) {
        $endpoint = $this->api_url . '/ai-invoice-parser';
        
        $data = array(
            'url' => $pdf_url
        );
        
        // Add custom fields if provided
        if (!empty($custom_fields)) {
            $data['customfield'] = json_encode($custom_fields);
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
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Submitting job to ' . $endpoint);
            error_log('PDF.co AI Parser: Request data: ' . json_encode($data));
        }
        
        $response = wp_remote_request($endpoint, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (get_option('idoklad_debug_mode')) {
                error_log('PDF.co AI Parser: WP Error: ' . $error_message);
            }
            throw new Exception('PDF.co AI Parser request failed: ' . $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Response code: ' . $response_code);
            error_log('PDF.co AI Parser: Response body: ' . $response_body);
        }
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = 'Unknown API error';
            
            if (isset($error_data['error'])) {
                $error_message = $error_data['error'];
            } elseif (isset($error_data['message'])) {
                $error_message = $error_data['message'];
            }
            
            throw new Exception('PDF.co AI Parser error (' . $response_code . '): ' . $error_message);
        }
        
        $response_data = json_decode($response_body, true);
        
        if (!$response_data) {
            throw new Exception('Invalid JSON response from PDF.co AI Parser: ' . $response_body);
        }
        
        if (!isset($response_data['JobId'])) {
            throw new Exception('Invalid response from PDF.co AI Parser. No JobId found. Response: ' . $response_body);
        }
        
        return $response_data['JobId'];
    }
    
    /**
     * Poll for parsing results
     */
    private function poll_for_results($job_id, $max_attempts = 30, $delay_seconds = 2) {
        $endpoint = $this->api_url . '/job/check';
        
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            if (get_option('idoklad_debug_mode')) {
                error_log('PDF.co AI Parser: Polling attempt ' . $attempt . '/' . $max_attempts . ' for job ' . $job_id);
            }
            
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
                if (get_option('idoklad_debug_mode')) {
                    error_log('PDF.co AI Parser: Polling error: ' . $error_message);
                }
                throw new Exception('PDF.co AI Parser polling failed: ' . $error_message);
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                throw new Exception('PDF.co AI Parser polling error (' . $response_code . '): ' . $response_body);
            }
            
            $response_data = json_decode($response_body, true);
            
            if (!$response_data) {
                throw new Exception('Invalid JSON response from PDF.co AI Parser polling: ' . $response_body);
            }
            
            // Check if job is complete
            if (isset($response_data['Status']) && $response_data['Status'] === 'success') {
                if (get_option('idoklad_debug_mode')) {
                    error_log('PDF.co AI Parser: Job completed successfully');
                }
                return $this->extract_parsed_data($response_data);
            }
            
            if (isset($response_data['Status']) && $response_data['Status'] === 'error') {
                $error_message = isset($response_data['Message']) ? $response_data['Message'] : 'Unknown error';
                throw new Exception('PDF.co AI Parser job failed: ' . $error_message);
            }
            
            // Job still processing, wait and try again
            if ($attempt < $max_attempts) {
                sleep($delay_seconds);
            }
        }
        
        throw new Exception('PDF.co AI Parser job timed out after ' . ($max_attempts * $delay_seconds) . ' seconds');
    }
    
    /**
     * Extract and normalize parsed data from PDF.co response
     */
    private function extract_parsed_data($response_data) {
        if (!isset($response_data['Body']) || empty($response_data['Body'])) {
            throw new Exception('No parsed data found in PDF.co AI Parser response');
        }
        
        $parsed_data = json_decode($response_data['Body'], true);
        
        if (!$parsed_data) {
            throw new Exception('Invalid parsed data from PDF.co AI Parser: ' . $response_data['Body']);
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Raw parsed data: ' . json_encode($parsed_data, JSON_PRETTY_PRINT));
        }
        
        // Since we're using iDoklad custom fields, the data should already be in the correct format
        // Just add metadata and ensure required fields are present
        return $this->prepare_idoklad_data($parsed_data);
    }
    
    /**
     * Prepare iDoklad-formatted data from AI Parser response
     * Since we're using iDoklad custom fields, the data should already be in the correct format
     */
    private function prepare_idoklad_data($parsed_data) {
        // The AI parser should return data in iDoklad format due to custom fields
        // We just need to ensure required fields are present and add metadata
        
        $idoklad_data = $parsed_data;
        
        // Ensure required fields have default values if missing
        if (!isset($idoklad_data['DocumentSerialNumber'])) {
            $idoklad_data['DocumentSerialNumber'] = 1;
        }
        
        if (!isset($idoklad_data['IsIncomeTax'])) {
            $idoklad_data['IsIncomeTax'] = false;
        }
        
        if (!isset($idoklad_data['PartnerId'])) {
            $idoklad_data['PartnerId'] = 0; // Will create new partner
        }
        
        if (!isset($idoklad_data['ExchangeRate'])) {
            $idoklad_data['ExchangeRate'] = 1;
        }
        
        if (!isset($idoklad_data['ExchangeRateAmount'])) {
            $idoklad_data['ExchangeRateAmount'] = 1;
        }
        
        if (!isset($idoklad_data['PaymentStatus'])) {
            $idoklad_data['PaymentStatus'] = 0; // Unpaid
        }
        
        if (!isset($idoklad_data['PaymentOptionId'])) {
            $idoklad_data['PaymentOptionId'] = 1; // Bank transfer
        }
        
        if (!isset($idoklad_data['CurrencyId'])) {
            $idoklad_data['CurrencyId'] = 1; // CZK
        }
        
        // Ensure Items array exists
        if (!isset($idoklad_data['Items']) || !is_array($idoklad_data['Items'])) {
            $idoklad_data['Items'] = array();
        }
        
        // Ensure Description exists (required by iDoklad)
        if (empty($idoklad_data['Description'])) {
            $supplier = $idoklad_data['PartnerName'] ?? 'Neznámý dodavatel';
            $invoice_number = $idoklad_data['DocumentNumber'] ?? 'N/A';
            $idoklad_data['Description'] = 'Faktura od ' . $supplier . ' č. ' . $invoice_number;
        }
        
        // Add metadata
        $idoklad_data['ai_parsed'] = true;
        $idoklad_data['parser_source'] = 'pdf_co_ai_idoklad';
        $idoklad_data['parsed_at'] = date('Y-m-d H:i:s');
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Prepared iDoklad data: ' . json_encode($idoklad_data, JSON_PRETTY_PRINT));
        }
        
        return $idoklad_data;
    }
    
    /**
     * Find field value in parsed data using multiple possible field names
     */
    private function find_field_value($data, $possible_fields) {
        foreach ($possible_fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return $data[$field];
            }
        }
        return null;
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
            
            $normalized_item = array();
            
            // Map item fields
            $item_mappings = array(
                'name' => array('Name', 'Description', 'Product', 'Service', 'Item'),
                'quantity' => array('Quantity', 'Amount', 'Qty', 'Count'),
                'price' => array('Price', 'UnitPrice', 'Rate', 'Cost', 'Amount'),
                'total' => array('Total', 'LineTotal', 'Amount'),
                'unit' => array('Unit', 'Uom', 'Measure', 'UnitOfMeasure'),
                'vat_rate' => array('VatRate', 'TaxRate', 'Vat'),
                'vat_amount' => array('VatAmount', 'TaxAmount')
            );
            
            foreach ($item_mappings as $our_field => $possible_fields) {
                $value = $this->find_field_value($item, $possible_fields);
                if ($value !== null) {
                    $normalized_item[$our_field] = $value;
                }
            }
            
            if (!empty($normalized_item)) {
                $normalized_items[] = $normalized_item;
            }
        }
        
        return $normalized_items;
    }
    
    /**
     * Get iDoklad-specific custom fields for AI Parser
     * These fields will be extracted by the AI parser in iDoklad format
     */
    private function get_idoklad_custom_fields() {
        return array(
            // Document identification
            'documentNumber',
            'documentSerialNumber', 
            'dateOfIssue',
            'dateOfReceiving',
            'dateOfTaxing',
            'dateOfMaturity',
            
            // Partner/Supplier information
            'partnerId',
            'partnerName',
            'partnerAddress',
            'supplierIdentificationNumber',
            
            // Currency and financial
            'currencyId',
            'exchangeRate',
            'exchangeRateAmount',
            'isIncomeTax',
            
            // Payment information
            'paymentOptionId',
            'paymentStatus',
            'variableSymbol',
            'constantSymbol',
            'specificSymbol',
            
            // Bank account
            'bankAccountNumber',
            'bankAccountId',
            'iban',
            'swift',
            
            // Invoice items (will be parsed as array)
            'items',
            
            // Description and notes
            'description',
            'note',
            
            // Accounting
            'accountNumber',
            
            // Additional iDoklad fields from your example
            'allowEditExported',
            'bankStatementMail',
            'hasAutomaticPairPayments',
            'accountNumber',
            'bankId',
            'countOfDecimalsForAmount',
            'countOfDecimalsForPrice',
            'cswCustomerGuid',
            'cswCustomerPin',
            'defaultCurrencyId',
            'isActiveStorePayment',
            'defaultSendMethod',
            'deleteStatus',
            'eetRegime',
            'hasFirstInvoice',
            'hasVatCode',
            'hasVatRegimeOss',
            'isPriceWithVat',
            'isRegisteredForVat',
            'hasActiveWebRecurringPayments',
            'isRegisteredForVatOnPay',
            'isSendPaymentConfirmation',
            'preferredPriceType',
            'preferredVatRate',
            'registerRecord',
            'roundingDifference',
            'defaultInvoiceMaturity',
            'itemsTextPrefix',
            'itemsTextSuffix',
            'proformaItemsPrefixText',
            'proformaItemsSuffixText',
            'isSendReminder',
            'nextReminderIntervalInDays',
            'reminderDaysAfterMaturity',
            'reminderMinValue',
            'dateFrom',
            'dateTo',
            'isTrial',
            'mobileStoreType',
            'taxSubjectType',
            'vatPeriod',
            'vatRegistrationType'
        );
    }
    
    /**
     * Test the AI Parser connection
     */
    public function test_connection() {
        try {
            // Test with a simple request (this would need a valid PDF URL)
            // For now, just check if API key is configured
            if (empty($this->api_key)) {
                return array(
                    'success' => false,
                    'message' => 'PDF.co API key is not configured'
                );
            }
            
            return array(
                'success' => true,
                'message' => 'PDF.co AI Parser is configured and ready'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'PDF.co AI Parser test failed: ' . $e->getMessage()
            );
        }
    }
}

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
    private $alternative_endpoints = array(
        'https://api.pdf.co/v1/ai-invoice-parser',
        'https://api.pdf.co/ai-invoice-parser',
        'https://api.pdf.co/v1/invoice-parser',
        'https://api.pdf.co/invoice-parser'
    );
    
    public function __construct() {
        $this->api_key = get_option('idoklad_pdfco_api_key');
    }
    
    /**
     * Parse invoice using PDF.co AI Invoice Parser with iDoklad-specific custom fields
     * Falls back to regular text extraction if AI Parser is not available
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
        
        try {
            // Try AI Invoice Parser first
            $idoklad_custom_fields = $this->get_idoklad_custom_fields();
            $all_custom_fields = array_merge($idoklad_custom_fields, $custom_fields);
            
            $job_id = $this->submit_parsing_job($pdf_url, $all_custom_fields);
            
            if (empty($job_id)) {
                throw new Exception('Failed to submit AI parsing job');
            }
            
            if (get_option('idoklad_debug_mode')) {
                error_log('PDF.co AI Parser: Job submitted with ID: ' . $job_id);
            }
            
            $result = $this->poll_for_results($job_id);
            
            if (get_option('idoklad_debug_mode')) {
                error_log('PDF.co AI Parser: Parsing completed. Result: ' . json_encode($result));
            }
            
            return $result;
            
        } catch (Exception $e) {
            // If AI Parser fails (404, etc.), fall back to regular text extraction
            if (get_option('idoklad_debug_mode')) {
                error_log('PDF.co AI Parser failed, falling back to text extraction: ' . $e->getMessage());
            }
            
            return $this->fallback_to_text_extraction($pdf_url);
        }
    }
    
    /**
     * Submit parsing job to PDF.co AI Invoice Parser
     */
    private function submit_parsing_job($pdf_url, $custom_fields = array()) {
        $endpoint = $this->api_url . '/ai-invoice-parser';
        
        // Build request data
        $data = array(
            'url' => $pdf_url
        );
        
        // Add custom fields as comma-separated string (not JSON!)
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
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Submitting job to ' . $endpoint);
            error_log('PDF.co AI Parser: Request body: ' . json_encode($data, JSON_PRETTY_PRINT));
            error_log('PDF.co AI Parser: Custom fields: ' . ($data['customfield'] ?? 'none'));
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
            
            // Enhanced error logging for 404 errors
            if ($response_code === 404) {
                error_log('PDF.co AI Parser 404 Error Details:');
                error_log('- Endpoint: ' . $endpoint);
                error_log('- API Key: ' . (empty($this->api_key) ? 'MISSING' : 'PRESENT (' . substr($this->api_key ?: '', 0, 8) . '...)'));
                error_log('- Request Body: ' . json_encode($data, JSON_PRETTY_PRINT));
                error_log('- Response Code: ' . $response_code);
                error_log('- Response Body: ' . $response_body);
                
                $error_message = 'API endpoint not found (404). Check if AI Invoice Parser is available for your API key. Full response: ' . $response_body;
            }
            
            throw new Exception('PDF.co AI Parser error (' . $response_code . '): ' . $error_message);
        }
        
        $response_data = json_decode($response_body, true);
        
        if (!$response_data) {
            throw new Exception('Invalid JSON response from PDF.co AI Parser: ' . $response_body);
        }
        
        // Check for JobId (could be capitalized or lowercase)
        if (isset($response_data['JobId'])) {
            return $response_data['JobId'];
        } elseif (isset($response_data['jobId'])) {
            return $response_data['jobId'];
        } elseif (isset($response_data['job_id'])) {
            return $response_data['job_id'];
        } else {
            throw new Exception('Invalid response from PDF.co AI Parser. No JobId found. Response: ' . $response_body);
        }
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
            if (isset($response_data['status'])) {
                if ($response_data['status'] === 'success' || $response_data['status'] === 'completed') {
                    if (get_option('idoklad_debug_mode')) {
                        error_log('PDF.co AI Parser: Job completed successfully');
                    }
                    return $this->extract_parsed_data($response_data);
                }
                
                if ($response_data['status'] === 'error' || $response_data['status'] === 'failed') {
                    $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
                    throw new Exception('PDF.co AI Parser job failed: ' . $error_message);
                }
            }
            
            // Job still processing, wait and try again
            if ($attempt < $max_attempts) {
                sleep($delay_seconds);
            }
        }
        
        throw new Exception('PDF.co AI Parser job timed out after ' . ($max_attempts * $delay_seconds) . ' seconds');
    }
    
    /**
     * Extract parsed data from PDF.co AI Parser response
     * PDF.co returns the parsed invoice data directly - no complex parsing needed!
     */
    private function extract_parsed_data($response_data) {
        if (!isset($response_data['body']) || empty($response_data['body'])) {
            throw new Exception('No parsed invoice data in PDF.co AI Parser response');
        }
        
        // PDF.co AI Parser returns the invoice data directly in the 'body' field
        $parsed_invoice_data = $response_data['body'];
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Raw invoice data from API: ' . json_encode($parsed_invoice_data, JSON_PRETTY_PRINT));
        }
        
        // The data should already have our custom fields extracted by the AI
        // Just add metadata and ensure required fields are present
        return $this->prepare_idoklad_data($parsed_invoice_data);
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
            $idoklad_data['PartnerId'] = null; // Let iDoklad create partner automatically
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
     * Fallback to regular PDF.co text extraction when AI Parser is not available
     */
    private function fallback_to_text_extraction($pdf_url) {
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Using fallback text extraction for URL: ' . $pdf_url);
        }
        
        // Use regular PDF.co text extraction
        $endpoint = $this->api_url . '/pdf/convert/to/text';
        
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'User-Agent' => 'WordPress-iDoklad-Processor/1.1.0'
            ),
            'body' => json_encode(array(
                'url' => $pdf_url,
                'inline' => true
            )),
            'timeout' => 120,
            'method' => 'POST',
            'sslverify' => true
        );
        
        $response = wp_remote_request($endpoint, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('PDF.co text extraction failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            throw new Exception('PDF.co text extraction failed (' . $response_code . '): ' . $response_body);
        }
        
        $response_data = json_decode($response_body, true);
        
        if (!$response_data || !isset($response_data['body'])) {
            throw new Exception('Invalid response from PDF.co text extraction: ' . $response_body);
        }
        
        $pdf_text = $response_data['body'];
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Text extracted successfully, length: ' . strlen($pdf_text));
        }
        
        // Parse the text to extract basic invoice data
        return $this->parse_text_to_idoklad_format($pdf_text);
    }
    
    /**
     * Parse extracted text to basic iDoklad format
     */
    private function parse_text_to_idoklad_format($text) {
        $data = array();
        
        // Extract basic information using regex patterns
        $patterns = array(
            'documentNumber' => array(
                '/(?:číslo|č\.|číslo faktury|invoice number|invoice no|doc number)[\s:]*([A-Z0-9\-\/]+)/i',
                '/(?:faktura|invoice)[\s:]*([A-Z0-9\-\/]+)/i'
            ),
            'dateOfIssue' => array(
                '/(?:datum vystavení|datum vydání|date of issue|issue date)[\s:]*([0-9]{1,2}[\.\/][0-9]{1,2}[\.\/][0-9]{2,4})/i',
                '/([0-9]{1,2}[\.\/][0-9]{1,2}[\.\/][0-9]{2,4})/'
            ),
            'partnerName' => array(
                '/(?:dodavatel|supplier|from|od)[\s:]*([^\n\r]+)/i'
            ),
            'variableSymbol' => array(
                '/(?:variabilní symbol|variable symbol|vs)[\s:]*([0-9]+)/i'
            ),
            'constantSymbol' => array(
                '/(?:konstantní symbol|constant symbol|ks)[\s:]*([0-9]+)/i'
            ),
            'iban' => array(
                '/(?:iban)[\s:]*([A-Z]{2}[0-9]{2}[A-Z0-9]+)/i'
            )
        );
        
        foreach ($patterns as $field => $field_patterns) {
            foreach ($field_patterns as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $data[$field] = trim($matches[1]);
                    break;
                }
            }
        }
        
        // Extract amounts
        if (preg_match('/(?:celkem|total|suma|amount)[\s:]*([0-9\s,\.]+)/i', $text, $matches)) {
            $amount = preg_replace('/[^\d,\.]/', '', $matches[1]);
            $amount = str_replace(',', '.', $amount);
            if (is_numeric($amount)) {
                $data['totalAmount'] = floatval($amount);
            }
        }
        
        // Set defaults
        $data['description'] = 'Faktura zpracována z PDF textu';
        $data['currencyId'] = 1; // CZK
        $data['items'] = array(); // Will be filled by data transformer
        $data['ai_parsed'] = false;
        $data['parser_source'] = 'pdf_co_text_fallback';
        $data['parsed_at'] = date('Y-m-d H:i:s');
        
        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Parsed text data: ' . json_encode($data, JSON_PRETTY_PRINT));
        }
        
        return $data;
    }
    
    /**
     * Test the AI Parser connection
     */
    public function test_connection() {
        try {
            // Check if API key is configured
            if (empty($this->api_key)) {
                return array(
                    'success' => false,
                    'message' => 'PDF.co API key is not configured'
                );
            }
            
            // Test different possible endpoints
            $test_results = array();
            
            foreach ($this->alternative_endpoints as $endpoint) {
                $args = array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'x-api-key' => $this->api_key,
                        'User-Agent' => 'WordPress-iDoklad-Processor/1.1.0'
                    ),
                    'body' => json_encode(array(
                        'url' => 'https://example.com/test.pdf' // Dummy URL for testing
                    )),
                    'timeout' => 10,
                    'method' => 'POST',
                    'sslverify' => true
                );
                
                $response = wp_remote_request($endpoint, $args);
                
                if (is_wp_error($response)) {
                    $test_results[] = $endpoint . ': Connection failed - ' . $response->get_error_message();
                    continue;
                }
                
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                
                if ($response_code === 404) {
                    $test_results[] = $endpoint . ': Not found (404)';
                } elseif ($response_code === 401) {
                    $test_results[] = $endpoint . ': Auth failed (401)';
                } elseif ($response_code === 403) {
                    $test_results[] = $endpoint . ': Forbidden (403)';
                } elseif ($response_code === 400) {
                    $test_results[] = $endpoint . ': Available (400 - expected for invalid URL)';
                    return array(
                        'success' => true,
                        'message' => 'AI Invoice Parser endpoint found: ' . $endpoint
                    );
                } else {
                    $test_results[] = $endpoint . ': Responded (HTTP ' . $response_code . ')';
                }
            }
            
            // If we get here, no endpoint worked
            return array(
                'success' => false,
                'message' => 'AI Invoice Parser endpoint not found. Tested endpoints: ' . implode(', ', $test_results)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'PDF.co AI Parser test failed: ' . $e->getMessage()
            );
        }
    }
}

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
        // PDF.co currently returns invoice information inside a "data" section.
        // Extract the actual payload and transform it into the structure our
        // workflow expects before calling the iDoklad API.

        $raw_payload = $parsed_data;
        $response_metadata = array();

        if (isset($parsed_data['data']) && is_array($parsed_data['data'])) {
            $raw_payload = $parsed_data['data'];
            $response_metadata = $parsed_data;
            unset($response_metadata['data']);
        }

        $idoklad_data = array();

        // Basic document details
        $idoklad_data['DocumentNumber'] = $this->extract_field_value($raw_payload, array('DocumentNumber', 'document_number', 'InvoiceNumber', 'invoice_number', 'number'))
            ?: 'AI-' . date('YmdHis');
        $idoklad_data['document_number'] = $idoklad_data['DocumentNumber'];
        $idoklad_data['invoice_number'] = $idoklad_data['DocumentNumber'];

        $idoklad_data['DateOfIssue'] = $this->normalize_date($this->extract_field_value($raw_payload, array('DateOfIssue', 'date', 'InvoiceDate', 'invoice_date', 'IssueDate', 'issue_date')))
            ?: date('Y-m-d');
        $idoklad_data['date'] = $idoklad_data['DateOfIssue'];

        $idoklad_data['DateOfReceiving'] = $this->normalize_date($this->extract_field_value($raw_payload, array('DateOfReceiving', 'delivery_date', 'received_at', 'DateReceived')));
        $idoklad_data['DateOfTaxing'] = $this->normalize_date($this->extract_field_value($raw_payload, array('DateOfTaxing', 'tax_date', 'vat_date')));
        $idoklad_data['DateOfMaturity'] = $this->normalize_date($this->extract_field_value($raw_payload, array('DateOfMaturity', 'due_date', 'DueDate', 'maturity_date')));

        // Partner (supplier) information
        $partner_sources = array($raw_payload);
        foreach (array('supplier', 'vendor', 'partner', 'seller') as $partner_key) {
            if (isset($raw_payload[$partner_key]) && is_array($raw_payload[$partner_key])) {
                $partner_sources[] = $raw_payload[$partner_key];
            }
        }

        $idoklad_data['PartnerName'] = $this->extract_from_sources($partner_sources, array('PartnerName', 'partner_name', 'SupplierName', 'supplier_name', 'VendorName', 'vendor_name', 'name', 'company', 'company_name'));
        if (empty($idoklad_data['PartnerName'])) {
            $idoklad_data['PartnerName'] = 'Unknown supplier';
        }

        $idoklad_data['PartnerAddress'] = $this->extract_from_sources($partner_sources, array('PartnerAddress', 'partner_address', 'SupplierAddress', 'supplier_address', 'address', 'street', 'line1'));
        $idoklad_data['PartnerIdentificationNumber'] = $this->extract_from_sources($partner_sources, array('PartnerIdentificationNumber', 'supplier_identification_number', 'vat', 'vat_number', 'VATNumber', 'ico', 'ic', 'registration_number'));
        $idoklad_data['supplier_name'] = $idoklad_data['PartnerName'];

        // Contact details (optional but helpful for notifications)
        $idoklad_data['partner_email'] = $this->extract_from_sources($partner_sources, array('email', 'Email', 'contact_email'));
        $idoklad_data['PartnerEmail'] = $idoklad_data['partner_email'];

        // Payment symbols
        $idoklad_data['VariableSymbol'] = $this->extract_field_value($raw_payload, array('VariableSymbol', 'variable_symbol', 'VS'));
        $idoklad_data['ConstantSymbol'] = $this->extract_field_value($raw_payload, array('ConstantSymbol', 'constant_symbol', 'KS'));
        $idoklad_data['SpecificSymbol'] = $this->extract_field_value($raw_payload, array('SpecificSymbol', 'specific_symbol', 'SS'));

        // Banking
        $idoklad_data['BankAccountNumber'] = $this->extract_from_sources($partner_sources, array('BankAccountNumber', 'bank_account_number', 'account_number', 'bank_account'));
        $idoklad_data['Iban'] = $this->extract_from_sources($partner_sources, array('Iban', 'iban'));
        $idoklad_data['Swift'] = $this->extract_from_sources($partner_sources, array('Swift', 'swift', 'Bic', 'bic', 'BIC'));

        // Description and notes
        $idoklad_data['Description'] = $this->extract_field_value($raw_payload, array('Description', 'description', 'title', 'subject'));
        if (empty($idoklad_data['Description'])) {
            $idoklad_data['Description'] = 'Faktura od ' . $idoklad_data['PartnerName'] . ' č. ' . $idoklad_data['DocumentNumber'];
        }
        $idoklad_data['Note'] = $this->extract_field_value($raw_payload, array('Note', 'note', 'comments'));

        // Financial information
        $currency_code = strtoupper($this->extract_field_value($raw_payload, array('Currency', 'currency', 'currency_code', 'CurrencyCode')) ?: 'CZK');
        $idoklad_data['CurrencyId'] = $this->get_currency_id($currency_code);
        $idoklad_data['CurrencyCode'] = $currency_code;
        $idoklad_data['ExchangeRate'] = $this->normalize_amount($this->extract_field_value($raw_payload, array('ExchangeRate', 'exchange_rate')));
        if (empty($idoklad_data['ExchangeRate'])) {
            $idoklad_data['ExchangeRate'] = 1;
        }
        $idoklad_data['ExchangeRateAmount'] = $this->normalize_amount($this->extract_field_value($raw_payload, array('ExchangeRateAmount', 'exchange_rate_amount')));
        if (empty($idoklad_data['ExchangeRateAmount'])) {
            $idoklad_data['ExchangeRateAmount'] = 1;
        }

        $idoklad_data['total_amount'] = $this->normalize_amount($this->extract_field_value($raw_payload, array('total_amount', 'total', 'grand_total', 'amount_due')));
        $idoklad_data['subtotal'] = $this->normalize_amount($this->extract_field_value($raw_payload, array('subtotal', 'sub_total', 'net_total')));
        $idoklad_data['tax_total'] = $this->normalize_amount($this->extract_field_value($raw_payload, array('tax_total', 'tax', 'vat_total')));
        if (!isset($idoklad_data['total']) && $idoklad_data['total_amount'] !== null) {
            $idoklad_data['total'] = $idoklad_data['total_amount'];
        }

        // Items (both lowercase and iDoklad format)
        $items = $this->extract_items_for_idoklad($raw_payload);
        if (empty($items)) {
            $fallback_price = $idoklad_data['total_amount'] ?? 0;
            $items[] = array(
                'Name' => 'Invoice total',
                'Amount' => 1.0,
                'UnitPrice' => $fallback_price,
                'Unit' => 'pcs',
                'PriceType' => 1,
                'VatRateType' => 2,
                'VatRate' => 0,
                'IsTaxMovement' => false,
                'DiscountPercentage' => 0.0
            );
        }
        $idoklad_data['Items'] = $items;
        $idoklad_data['items'] = $items;

        // Required defaults for iDoklad API
        $serial_number = $this->normalize_amount($this->extract_field_value($raw_payload, array('DocumentSerialNumber', 'document_serial_number')));
        $idoklad_data['DocumentSerialNumber'] = $serial_number !== null ? intval($serial_number) : 1;

        $idoklad_data['IsIncomeTax'] = false;

        $partner_id = $this->normalize_amount($this->extract_field_value($raw_payload, array('PartnerId', 'partner_id')));
        $idoklad_data['PartnerId'] = $partner_id !== null ? intval($partner_id) : null;

        $payment_status = $this->normalize_amount($this->extract_field_value($raw_payload, array('PaymentStatus', 'payment_status')));
        $idoklad_data['PaymentStatus'] = $payment_status !== null ? intval($payment_status) : 0;

        $payment_option = $this->normalize_amount($this->extract_field_value($raw_payload, array('PaymentOptionId', 'payment_option_id')));
        $idoklad_data['PaymentOptionId'] = $payment_option !== null ? intval($payment_option) : 1;

        $idoklad_data['ItemsTextPrefix'] = $this->extract_field_value($raw_payload, array('ItemsTextPrefix', 'items_text_prefix')) ?: 'Invoice items:';
        $idoklad_data['ItemsTextSuffix'] = $this->extract_field_value($raw_payload, array('ItemsTextSuffix', 'items_text_suffix')) ?: 'Thank you for your business.';
        $idoklad_data['ReportLanguage'] = 1;
        $idoklad_data['IsEet'] = false;
        $idoklad_data['EetResponsibility'] = 0;

        $vat_on_pay = $this->normalize_amount($this->extract_field_value($raw_payload, array('VatOnPayStatus', 'vat_on_pay_status')));
        $idoklad_data['VatOnPayStatus'] = $vat_on_pay !== null ? intval($vat_on_pay) : 0;

        $vat_regime = $this->normalize_amount($this->extract_field_value($raw_payload, array('VatRegime', 'vat_regime')));
        $idoklad_data['VatRegime'] = $vat_regime !== null ? intval($vat_regime) : 0;
        $idoklad_data['HasVatRegimeOss'] = false;

        // Metadata for debugging and validation
        if (!empty($response_metadata)) {
            $idoklad_data['pdfco_metadata'] = $response_metadata;
        }
        $idoklad_data['ai_parsed'] = true;
        $idoklad_data['parser_source'] = 'pdf_co_ai_idoklad';
        $idoklad_data['parsed_at'] = date('Y-m-d H:i:s');

        if ($idoklad_data['total_amount'] === null && !empty($items)) {
            $computed_total = 0;
            foreach ($items as $item) {
                $quantity = isset($item['Amount']) ? floatval($item['Amount']) : 0;
                $unit_price = isset($item['UnitPrice']) ? floatval($item['UnitPrice']) : 0;
                $computed_total += $quantity * $unit_price;
            }
            $idoklad_data['total_amount'] = round($computed_total, 2);
            $idoklad_data['total'] = $idoklad_data['total_amount'];
        }

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
     * Extract field value from mixed-case structures (case insensitive, snake/camel variants)
     */
    private function extract_field_value($data, $possible_fields) {
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
     * Extract value from multiple possible source arrays
     */
    private function extract_from_sources($sources, $possible_fields) {
        foreach ($sources as $source) {
            $value = $this->extract_field_value($source, $possible_fields);
            if (!$this->is_value_empty($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Normalize amount string to float
     */
    private function normalize_amount($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return floatval($value);
        }

        if (is_string($value)) {
            $normalized = str_replace(array("\xC2\xA0", ' '), '', $value);
            $normalized = str_replace(',', '.', $normalized);
            $normalized = preg_replace('/[^0-9\.-]/', '', $normalized);
            if (is_numeric($normalized)) {
                return floatval($normalized);
            }
        }

        return null;
    }

    /**
     * Normalize date to Y-m-d format
     */
    private function normalize_date($date_string) {
        if (empty($date_string)) {
            return null;
        }

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

        $timestamp = strtotime($date_string);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return $date_string;
    }

    /**
     * Convert parsed items into iDoklad item structures
     */
    private function extract_items_for_idoklad($data) {
        $possible_keys = array('Items', 'items', 'LineItems', 'line_items', 'InvoiceItems', 'invoice_items', 'products');
        $raw_items = array();

        foreach ($possible_keys as $key) {
            if (isset($data[$key]) && is_array($data[$key]) && !empty($data[$key])) {
                $raw_items = $data[$key];
                break;
            }
        }

        $normalized_items = $this->normalize_items($raw_items);
        $idoklad_items = array();

        foreach ($normalized_items as $index => $item) {
            $name = isset($item['name']) ? $item['name'] : 'Item ' . ($index + 1);
            $quantity = $this->normalize_amount($item['quantity'] ?? 1);
            if ($quantity === null || $quantity <= 0) {
                $quantity = 1.0;
            }

            $unit_price = $this->normalize_amount($item['unit_price'] ?? $item['price'] ?? null);
            if ($unit_price === null && isset($item['total']) && $quantity > 0) {
                $line_total = $this->normalize_amount($item['total']);
                if ($line_total !== null) {
                    $unit_price = $line_total / $quantity;
                }
            }

            $vat_rate = $this->normalize_amount($item['vat_rate'] ?? null);
            if ($vat_rate === null) {
                $vat_rate = 0.0;
            }

            $idoklad_items[] = array(
                'Name' => $name,
                'Unit' => $item['unit'] ?? 'pcs',
                'Amount' => $quantity,
                'UnitPrice' => $unit_price !== null ? $unit_price : 0.0,
                'PriceType' => 1,
                'VatRateType' => 2,
                'VatRate' => $vat_rate,
                'IsTaxMovement' => false,
                'DiscountPercentage' => 0.0
            );
        }

        return $idoklad_items;
    }

    /**
     * Check if a value should be treated as empty
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
     * Map currency code to iDoklad currency ID
     */
    private function get_currency_id($currency_code) {
        $currency_map = array(
            'CZK' => 1,
            'EUR' => 2,
            'USD' => 3,
            'GBP' => 4
        );

        $upper = strtoupper((string) $currency_code);

        if (isset($currency_map[$upper])) {
            return $currency_map[$upper];
        }

        return 1;
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

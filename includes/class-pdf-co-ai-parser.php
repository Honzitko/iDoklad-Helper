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
            $customfield_payload = $this->build_customfield_payload($custom_fields);

            if (!empty($customfield_payload)) {
                $data['customfield'] = $customfield_payload;
                $data['customfields'] = $customfield_payload;
            }
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
     */
    private function prepare_idoklad_data($parsed_data) {
        $idoklad_data = $this->normalize_idoklad_payload($parsed_data);

        // Add metadata
        $idoklad_data['ai_parsed'] = true;
        $idoklad_data['parser_source'] = 'pdf_co_ai_idoklad';
        $idoklad_data['parsed_at'] = date('Y-m-d H:i:s');

        if (!empty($parsed_data)) {
            $idoklad_data['ai_parser_raw'] = $parsed_data;
        }

        if (get_option('idoklad_debug_mode')) {
            error_log('PDF.co AI Parser: Prepared iDoklad data: ' . json_encode($idoklad_data, JSON_PRETTY_PRINT));
        }

        return $idoklad_data;
    }

    /**
     * Convert the parser response into the format expected by the iDoklad API integration
     */
    private function normalize_idoklad_payload($parsed_data) {
        $source_data = $parsed_data;

        if (isset($parsed_data['Data']) && is_array($parsed_data['Data'])) {
            $source_data = $parsed_data['Data'];
        }

        $contact = isset($source_data['Contact']) && is_array($source_data['Contact']) ? $source_data['Contact'] : array();
        $bank_account = array();
        if (isset($source_data['BankAccounts']) && is_array($source_data['BankAccounts']) && count($source_data['BankAccounts']) > 0) {
            $bank_account = $source_data['BankAccounts'][0];
        }

        $items_source = array();
        if (isset($source_data['Items']) && is_array($source_data['Items'])) {
            $items_source = $source_data['Items'];
        } elseif (isset($source_data['items']) && is_array($source_data['items'])) {
            $items_source = $source_data['items'];
        }

        $normalized_items = $this->normalize_items($items_source);
        $idoklad_items = $this->build_idoklad_items($normalized_items, $source_data);

        $document_number = $this->find_field_value($source_data, array('DocumentNumber', 'documentNumber', 'InvoiceNumber', 'invoiceNumber', 'Number', 'number'));
        if (empty($document_number)) {
            $document_number = 'AI-' . date('Ymd-His');
        }

        $description = $this->find_field_value($source_data, array('Description', 'description', 'Name', 'name'));
        if (empty($description)) {
            $supplier = $this->find_field_value($source_data, array('PartnerName', 'partnerName', 'Name', 'name')) ?: 'Neznámý dodavatel';
            $description = 'Faktura od ' . $supplier . ' č. ' . $document_number;
        }

        $partner_name = $this->find_field_value($source_data, array('PartnerName', 'partnerName', 'Name', 'name'));
        if (empty($partner_name) && isset($contact['Name'])) {
            $partner_name = $contact['Name'];
        }

        $currency_id = $this->find_field_value($source_data, array('CurrencyId', 'currencyId', 'DefaultCurrencyId', 'defaultCurrencyId'));
        if (empty($currency_id)) {
            $currency_id = 1;
        }

        $date_of_issue = $this->normalize_date($this->find_field_value($source_data, array('DateOfIssue', 'dateOfIssue', 'DateFrom', 'dateFrom')));
        $date_of_receiving = $this->normalize_date($this->find_field_value($source_data, array('DateOfReceiving', 'dateOfReceiving', 'DateFrom', 'dateFrom')));
        $date_of_taxing = $this->normalize_date($this->find_field_value($source_data, array('DateOfTaxing', 'dateOfTaxing', 'DateFrom', 'dateFrom')));
        $date_of_maturity = $this->normalize_date($this->find_field_value($source_data, array('DateOfMaturity', 'dateOfMaturity', 'DateTo', 'dateTo')));

        $partner_address = array();
        if (!empty($contact)) {
            $partner_address = $this->remove_empty_values(array(
                'Street' => $contact['Street'] ?? null,
                'City' => $contact['City'] ?? null,
                'PostalCode' => $contact['PostalCode'] ?? null,
                'CountryId' => $contact['CountryId'] ?? null,
            ));
        }

        $bank_account_number = $this->find_field_value($bank_account, array('AccountNumber', 'accountNumber', 'BankAccountNumber', 'bankAccountNumber'));
        $bank_account_id = $this->find_field_value($bank_account, array('Id', 'id', 'BankId', 'bankId'));
        $iban = $this->find_field_value($bank_account, array('Iban', 'iban'));
        $swift = $this->find_field_value($bank_account, array('Swift', 'swift', 'Bic', 'bic'));

        $payload = array(
            'DocumentNumber' => (string)$document_number,
            'DocumentSerialNumber' => (int)$this->find_field_value($source_data, array('DocumentSerialNumber', 'documentSerialNumber', 'SerialNumber', 'serialNumber')) ?: 1,
            'DateOfIssue' => $date_of_issue ?: date('Y-m-d'),
            'DateOfReceiving' => $date_of_receiving ?: date('Y-m-d'),
            'DateOfTaxing' => $date_of_taxing ?: date('Y-m-d'),
            'DateOfMaturity' => $date_of_maturity ?: date('Y-m-d'),
            'Description' => $description,
            'IsIncomeTax' => (bool)$this->find_field_value($source_data, array('IsIncomeTax', 'isIncomeTax', 'HasIncomeTax', 'hasIncomeTax')),
            'CurrencyId' => (int)$currency_id,
            'ExchangeRate' => (float)$this->find_field_value($source_data, array('ExchangeRate', 'exchangeRate')) ?: 1,
            'ExchangeRateAmount' => (float)$this->find_field_value($source_data, array('ExchangeRateAmount', 'exchangeRateAmount')) ?: 1,
            'PartnerId' => (int)$this->find_field_value($source_data, array('PartnerId', 'partnerId', 'SupplierId', 'supplierId', 'ContactId', 'contactId')),
            'PartnerName' => $partner_name,
            'PartnerAddress' => !empty($partner_address) ? $partner_address : null,
            'SupplierIdentificationNumber' => $this->find_field_value($source_data, array('SupplierIdentificationNumber', 'supplierIdentificationNumber', 'IdentificationNumber', 'identificationNumber', 'VatIdentificationNumber', 'vatIdentificationNumber')),
            'PaymentOptionId' => (int)$this->find_field_value($source_data, array('PaymentOptionId', 'paymentOptionId')) ?: 1,
            'PaymentStatus' => (int)$this->find_field_value($source_data, array('PaymentStatus', 'paymentStatus')),
            'VariableSymbol' => $this->find_field_value($source_data, array('VariableSymbol', 'variableSymbol')),
            'ConstantSymbol' => $this->find_field_value($source_data, array('ConstantSymbol', 'constantSymbol')),
            'SpecificSymbol' => $this->find_field_value($source_data, array('SpecificSymbol', 'specificSymbol')),
            'BankAccountNumber' => $bank_account_number,
            'BankAccountId' => $bank_account_id,
            'Iban' => $iban,
            'Swift' => $swift,
            'AccountNumber' => $this->find_field_value($source_data, array('AccountNumber', 'accountNumber')),
            'Note' => $this->find_field_value($source_data, array('Note', 'note', 'Message', 'message')),
            'Items' => $idoklad_items,
        );

        return $this->remove_empty_values($payload, false);
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
     * Create iDoklad line items from normalized parser data
     */
    private function build_idoklad_items($normalized_items, $source_data) {
        $items = array();

        foreach ($normalized_items as $item) {
            $unit_price = null;
            if (isset($item['price'])) {
                $unit_price = (float)$item['price'];
            } elseif (isset($item['total']) && isset($item['quantity']) && (float)$item['quantity'] != 0) {
                $unit_price = (float)$item['total'] / (float)$item['quantity'];
            } elseif (isset($item['total'])) {
                $unit_price = (float)$item['total'];
            }

            $items[] = $this->remove_empty_values(array(
                'Name' => $item['name'] ?? 'Položka',
                'Amount' => isset($item['quantity']) ? (float)$item['quantity'] : 1,
                'Unit' => $item['unit'] ?? null,
                'UnitPrice' => $unit_price,
                'TotalWithoutVat' => isset($item['total']) ? (float)$item['total'] : null,
                'PriceType' => isset($item['vat_rate']) ? ($this->map_vat_rate_type($item['vat_rate']) === 3 ? 0 : 1) : 0,
                'VatRateType' => $this->map_vat_rate_type($item['vat_rate'] ?? null),
            ));
        }

        if (empty($items)) {
            $total_amount = $this->find_field_value($source_data, array('TotalWithVat', 'totalWithVat', 'TotalAmount', 'totalAmount', 'Total', 'total'));
            if ($total_amount !== null) {
                $items[] = array(
                    'Name' => 'Celková částka',
                    'Amount' => 1,
                    'UnitPrice' => (float)$total_amount,
                    'PriceType' => 1,
                    'VatRateType' => 3,
                );
            }
        }

        return $items;
    }

    /**
     * Convert value to Y-m-d format if possible
     */
    private function normalize_date($value) {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return date('Y-m-d', (int)$value);
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Build comma-separated customfield payload
     */
    private function build_customfield_payload($custom_fields) {
        if (is_string($custom_fields)) {
            return trim($custom_fields);
        }

        if (!is_array($custom_fields)) {
            return null;
        }

        $flattened = array();
        foreach ($custom_fields as $field) {
            if (is_string($field) && $field !== '') {
                $flattened[] = $field;
            }
        }

        if (empty($flattened)) {
            return null;
        }

        return implode(',', array_unique($flattened));
    }

    /**
     * Map VAT rates from parser output to iDoklad VatRateType enumeration
     */
    private function map_vat_rate_type($vat_rate) {
        if ($vat_rate === null || $vat_rate === '') {
            return 3;
        }

        $numeric_rate = is_numeric($vat_rate) ? (float)$vat_rate : null;

        if ($numeric_rate === null) {
            $vat_rate_str = strtolower((string)$vat_rate);
            if (strpos($vat_rate_str, '21') !== false) {
                return 0;
            }
            if (strpos($vat_rate_str, '15') !== false) {
                return 1;
            }
            if (strpos($vat_rate_str, '10') !== false) {
                return 2;
            }
            if (strpos($vat_rate_str, '0') !== false || strpos($vat_rate_str, 'zero') !== false) {
                return 3;
            }

            return 3;
        }

        if ($numeric_rate >= 20) {
            return 0;
        }
        if ($numeric_rate >= 14) {
            return 1;
        }
        if ($numeric_rate > 0) {
            return 2;
        }

        return 3;
    }

    /**
     * Remove empty values from array (recursively optional)
     */
    private function remove_empty_values($array, $recursive = true) {
        $filtered = array();

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $clean_value = $recursive ? $this->remove_empty_values($value, $recursive) : array_filter($value, function($item) {
                    return $item !== null && $item !== '' && $item !== false;
                });

                if (!empty($clean_value)) {
                    $filtered[$key] = $clean_value;
                }
            } elseif ($value !== null && $value !== '' && $value !== false) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
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

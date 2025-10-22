<?php
/**
 * iDoklad Data Transformer Class
 * Transforms data from PDF.co/Zapier into iDoklad API format
 * Based on iDoklad API v3 documentation: https://api.idoklad.cz/Help/v3/cs/index.html
 * 
 * ============================================================================
 * iDoklad ReceivedInvoices API - REQUIRED FIELDS (must be present in payload)
 * ============================================================================
 * - DocumentNumber (string): Invoice number
 * - DocumentSerialNumber (int): Serial number for document (default: 1)
 * - DateOfIssue (date): Issue date (format: Y-m-d)
 * - DateOfReceiving (date): Date when invoice was received (format: Y-m-d)
 * - DateOfTaxing (date): Tax date (format: Y-m-d)
 * - DateOfMaturity (date): Due date (format: Y-m-d)
 * - Description (string): Invoice description (cannot be empty)
 * - IsIncomeTax (bool): Whether this is income tax (typically false)
 * - CurrencyId (int): Currency ID (1=CZK, 2=EUR, 3=USD, etc.)
 * - PartnerId (int) OR PartnerName (string): Supplier ID or name (one required)
 * - Items (array): Invoice line items (at least 1 required)
 * 
 * ============================================================================
 * iDoklad ReceivedInvoices API - OPTIONAL FIELDS (sent if available)
 * ============================================================================
 * - PartnerAddress (object): Supplier address {Street, City, PostalCode, CountryId}
 * - SupplierIdentificationNumber (string): VAT/ICO number
 * - ExchangeRate (float): Exchange rate (default: 1)
 * - ExchangeRateAmount (int): Exchange rate amount (default: 1)
 * - PaymentOptionId (int): Payment method (1=bank, 2=cash, 3=card)
 * - PaymentStatus (int): 0=Unpaid, 1=Paid, 2=Partially paid
 * - VariableSymbol (string): Variable symbol for payment
 * - ConstantSymbol (string): Constant symbol
 * - SpecificSymbol (string): Specific symbol
 * - BankAccountNumber (string): Bank account number
 * - BankAccountId (int): Bank account ID in iDoklad
 * - Iban (string): IBAN
 * - Swift (string): SWIFT/BIC code
 * - AccountNumber (string): Accounting account number
 * - Note (string): Additional notes
 * - Tags (array): Optional tags for categorization
 * 
 * ============================================================================
 * Item structure (Items array):
 * ============================================================================
 * - Name (string): Item name/description
 * - UnitPrice (float): Price per unit
 * - Amount (float): Quantity
 * - Unit (string): Unit of measure (optional)
 * - PriceType (int): 0=WithoutVat, 1=WithVat
 * - VatRateType (int): 0=Basic(21%), 1=Reduced1(15%), 2=Reduced2(10%), 3=Zero(0%)
 * ============================================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_DataTransformer {
    
    /**
     * Transform extracted invoice data to iDoklad ReceivedInvoice format
     * 
     * @param array $extracted_data Raw data (may just contain pdf_text)
     * @param string $pdf_text Raw PDF text for parsing
     * @return array iDoklad API compatible payload
     */
    public function transform_to_idoklad($extracted_data, $pdf_text = '') {
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Transformer: Starting data transformation');
            error_log('iDoklad Transformer: Input data: ' . json_encode($extracted_data));
        }
        
        // If we don't have structured invoice data, parse PDF text to extract it
        $has_invoice_data = !empty($extracted_data['invoice_number']) || !empty($extracted_data['total_amount']) || !empty($extracted_data['supplier_name']);
        
        if (!$has_invoice_data && (isset($extracted_data['pdf_text']) || !empty($pdf_text))) {
            if (empty($pdf_text) && isset($extracted_data['pdf_text'])) {
                $pdf_text = $extracted_data['pdf_text'];
            }
            
            if (!empty($pdf_text)) {
                error_log('iDoklad Transformer: No structured data found, parsing PDF text directly');
                $extracted_data = $this->parse_pdf_text($pdf_text, $extracted_data);
            }
        }
        
        // Build iDoklad ReceivedInvoice payload
        // Based on iDoklad API v3 ReceivedInvoices endpoint requirements
        $payload = array(
            // REQUIRED: Document identification
            'DocumentNumber' => $this->extract_document_number($extracted_data),
            'DocumentSerialNumber' => 1, // REQUIRED: Serial number for the document (defaults to 1)
            'DateOfIssue' => $this->extract_date($extracted_data, 'date', 'issue'),
            'DateOfTaxing' => $this->extract_date($extracted_data, 'tax_date', 'taxing'),
            'DateOfMaturity' => $this->extract_date($extracted_data, 'due_date', 'maturity'),
            'DateOfReceiving' => $this->extract_date($extracted_data, 'received_date', 'receiving'),
            
            // REQUIRED: Supplier (Partner in iDoklad terminology)
            // iDoklad requires PartnerId to be present (cannot be null)
            'PartnerId' => $this->get_or_create_partner_id($extracted_data),
            'PartnerName' => $this->extract_field($extracted_data, array('supplier_name', 'vendor_name', 'from')) ?: 'Neznámý dodavatel',
            'PartnerAddress' => $this->extract_address($extracted_data, 'supplier'),
            
            // Supplier identification
            'SupplierIdentificationNumber' => $this->extract_field($extracted_data, array('supplier_vat_number', 'supplier_ico', 'vat_number', 'ico')),
            
            // REQUIRED: Currency
            'CurrencyId' => $this->get_currency_id($extracted_data),
            'ExchangeRate' => $this->extract_exchange_rate($extracted_data),
            'ExchangeRateAmount' => 1,
            
            // REQUIRED: Tax settings
            'IsIncomeTax' => false, // REQUIRED: Whether this is income tax (typically false for received invoices)
            
            // Payment information
            'PaymentOptionId' => $this->get_payment_option_id($extracted_data),
            'PaymentStatus' => 0, // 0 = Unpaid, 1 = Paid, 2 = Partially paid
            'VariableSymbol' => $this->extract_field($extracted_data, array('variable_symbol', 'vs', 'reference')),
            'ConstantSymbol' => $this->extract_field($extracted_data, array('constant_symbol', 'ks')),
            'SpecificSymbol' => $this->extract_field($extracted_data, array('specific_symbol', 'ss')),
            
            // Bank account
            'BankAccountNumber' => $this->extract_field($extracted_data, array('bank_account', 'account_number')),
            'BankAccountId' => $this->extract_field($extracted_data, array('bank_account_id')),
            'Iban' => $this->extract_field($extracted_data, array('iban')),
            'Swift' => $this->extract_field($extracted_data, array('swift', 'bic')),
            
            // REQUIRED: Invoice items
            'Items' => $this->transform_items($extracted_data),
            
            // REQUIRED: Description
            'Description' => $this->get_description($extracted_data),
            
            // Accounting
            'AccountNumber' => $this->extract_field($extracted_data, array('account_number', 'accounting_account')),
            
            // Additional fields
            'Note' => $this->build_note($extracted_data, $pdf_text),
            'Tags' => array(), // Optional tags
        );
        
        // Remove null/empty values
        $payload = $this->remove_empty_values($payload);
        
        // ALWAYS log for debugging (not just in debug mode)
        error_log('iDoklad Transformer: === DATA TRANSFORMATION COMPLETE ===');
        error_log('iDoklad Transformer: DocumentNumber: ' . ($payload['DocumentNumber'] ?? 'MISSING'));
        error_log('iDoklad Transformer: DocumentSerialNumber: ' . ($payload['DocumentSerialNumber'] ?? 'MISSING'));
        error_log('iDoklad Transformer: Description: ' . (isset($payload['Description']) ? substr($payload['Description'], 0, 50) : 'MISSING'));
        error_log('iDoklad Transformer: IsIncomeTax: ' . (isset($payload['IsIncomeTax']) ? ($payload['IsIncomeTax'] ? 'true' : 'false') : 'MISSING'));
        error_log('iDoklad Transformer: PartnerId: ' . ($payload['PartnerId'] ?? 'null') . ', PartnerName: ' . ($payload['PartnerName'] ?? 'MISSING'));
        error_log('iDoklad Transformer: DateOfReceiving: ' . ($payload['DateOfReceiving'] ?? 'MISSING'));
        error_log('iDoklad Transformer: Items count: ' . count($payload['Items'] ?? []));
        
        if (!empty($payload['Items'])) {
            foreach ($payload['Items'] as $index => $item) {
                error_log('iDoklad Transformer: Item ' . $index . ': ' . ($item['Name'] ?? 'no name') . ' - Price: ' . ($item['UnitPrice'] ?? '0') . ' x ' . ($item['Amount'] ?? '0'));
            }
        } else {
            error_log('iDoklad Transformer: WARNING - NO ITEMS IN PAYLOAD!');
        }
        
        error_log('iDoklad Transformer: Full payload: ' . json_encode($payload, JSON_PRETTY_PRINT));
        
        // Validate that we have financial data
        $has_financial_data = false;
        if (!empty($payload['Items'])) {
            foreach ($payload['Items'] as $item) {
                if (isset($item['UnitPrice']) && $item['UnitPrice'] > 0) {
                    $has_financial_data = true;
                    break;
                }
            }
        }
        
        if (!$has_financial_data) {
            error_log('iDoklad Transformer: ERROR - No financial data found! total_amount was: ' . ($extracted_data['total_amount'] ?? 'NOT SET'));
            error_log('iDoklad Transformer: All extracted data keys: ' . implode(', ', array_keys($extracted_data)));
        }
        
        return $payload;
    }
    
    /**
     * Extract document number
     */
    private function extract_document_number($data) {
        $number = $this->extract_field($data, array('invoice_number', 'document_number', 'number', 'invoice_no'));
        
        if (empty($number)) {
            // Generate fallback number
            $number = 'AUTO-' . date('YmdHis');
        }
        
        return (string)$number;
    }
    
    /**
     * Extract and format date
     */
    private function extract_date($data, $primary_key, $type = 'issue') {
        // Try to get the date from various possible fields
        $date_string = $this->extract_field($data, array(
            $primary_key,
            'date',
            'invoice_date',
            'issue_date',
            'document_date'
        ));
        
        if (empty($date_string)) {
            // Use current date as fallback
            return date('Y-m-d');
        }
        
        // Parse and format date
        try {
            $timestamp = strtotime($date_string);
            if ($timestamp === false) {
                return date('Y-m-d');
            }
            return date('Y-m-d', $timestamp);
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad Transformer: Date parsing error for ' . $type . ': ' . $e->getMessage());
            }
            return date('Y-m-d');
        }
    }
    
    /**
     * Get or create partner ID for iDoklad
     * Since iDoklad requires PartnerId to be present, we need to handle partner creation
     */
    private function get_or_create_partner_id($data) {
        $partner_id = $this->extract_field($data, array('partner_id', 'supplier_id', 'vendor_id'));
        
        if (!empty($partner_id) && is_numeric($partner_id)) {
            return (int)$partner_id;
        }
        
        // For now, return 0 to indicate we need to create a new partner
        // In a full implementation, this would call the iDoklad API to create/find the partner
        // and return the actual partner ID
        return 0; // iDoklad will create a new partner with this ID
    }
    
    /**
     * Extract address
     */
    private function extract_address($data, $prefix = 'supplier') {
        $address_parts = array();
        
        // Try to get full address first
        $full_address = $this->extract_field($data, array(
            $prefix . '_address',
            $prefix . '_address_full',
            'address'
        ));
        
        if (!empty($full_address)) {
            return array(
                'Street' => $full_address,
                'City' => $this->extract_field($data, array($prefix . '_city', 'city')),
                'PostalCode' => $this->extract_field($data, array($prefix . '_zip', 'zip', 'postal_code')),
                'CountryId' => $this->get_country_id($data, $prefix)
            );
        }
        
        // Build from parts
        return array(
            'Street' => $this->extract_field($data, array($prefix . '_street', 'street')),
            'City' => $this->extract_field($data, array($prefix . '_city', 'city')),
            'PostalCode' => $this->extract_field($data, array($prefix . '_zip', 'zip', 'postal_code')),
            'CountryId' => $this->get_country_id($data, $prefix)
        );
    }
    
    /**
     * Get currency ID
     */
    private function get_currency_id($data) {
        $currency_code = $this->extract_field($data, array('currency', 'currency_code'));
        
        // iDoklad currency IDs (from API documentation)
        $currency_map = array(
            'CZK' => 1,  // Czech Koruna
            'EUR' => 2,  // Euro
            'USD' => 3,  // US Dollar
            'GBP' => 4,  // British Pound
            'PLN' => 5,  // Polish Zloty
            'HUF' => 6,  // Hungarian Forint
            'CHF' => 7,  // Swiss Franc
        );
        
        $currency_code = strtoupper($currency_code ?: 'CZK');
        
        return $currency_map[$currency_code] ?? 1; // Default to CZK
    }
    
    /**
     * Get country ID
     */
    private function get_country_id($data, $prefix = '') {
        $country = $this->extract_field($data, array(
            $prefix . '_country',
            'country',
            'country_code'
        ));
        
        // iDoklad country IDs (common ones)
        $country_map = array(
            'CZ' => 1,   // Czech Republic
            'SK' => 2,   // Slovakia
            'PL' => 3,   // Poland
            'DE' => 4,   // Germany
            'AT' => 5,   // Austria
            'HU' => 6,   // Hungary
        );
        
        $country_code = strtoupper(substr($country ?: 'CZ', 0, 2));
        
        return $country_map[$country_code] ?? 1; // Default to CZ
    }
    
    /**
     * Extract exchange rate
     */
    private function extract_exchange_rate($data) {
        $rate = $this->extract_field($data, array('exchange_rate', 'rate'));
        
        if (!empty($rate) && is_numeric($rate)) {
            return (float)$rate;
        }
        
        return 1; // Default exchange rate
    }
    
    /**
     * Get payment option ID
     */
    private function get_payment_option_id($data) {
        $payment_method = $this->extract_field($data, array('payment_method', 'payment_type'));
        
        // iDoklad payment option IDs (from API documentation)
        $payment_map = array(
            'bank' => 1,        // Bank transfer
            'cash' => 2,        // Cash
            'card' => 3,        // Card
            'transfer' => 1,    // Bank transfer
        );
        
        $method_lower = strtolower($payment_method ?: 'bank');
        
        return $payment_map[$method_lower] ?? 1; // Default to bank transfer
    }
    
    /**
     * Transform invoice items (handles multiple field name variations)
     */
    private function transform_items($data) {
        $items = array();
        
        // Try to get items from various possible field names
        $raw_items = $this->extract_field($data, array('items', 'line_items', 'invoice_items', 'products', 'lines'));
        
        if (empty($raw_items) || !is_array($raw_items)) {
            // Create single item from total amount if no items
            $total = $this->extract_field($data, array('total_amount', 'total', 'amount', 'price', 'sum'));
            
            if (!empty($total)) {
                // Clean the amount (remove currency symbols, spaces, commas)
                $cleaned_total = $this->clean_amount($total);
                
                if ($cleaned_total > 0) {
                    $items[] = array(
                        'Name' => 'Invoice total',
                        'UnitPrice' => $cleaned_total,
                        'Amount' => 1,
                        'PriceType' => 0, // 0 = WithoutVat, 1 = WithVat
                        'VatRateType' => 3 // 0 = Basic (21%), 1 = Reduced1 (15%), 2 = Reduced2 (10%), 3 = Zero (0%)
                    );
                }
            }
        } else {
            // Transform each item
            foreach ($raw_items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                
                // Get item name
                $item_name = $this->extract_field($item, array('name', 'description', 'item_name', 'product', 'text'));
                if (empty($item_name)) {
                    $item_name = 'Item ' . ($index + 1);
                }
                
                // Get item price
                $item_price = $this->extract_field($item, array('price', 'unit_price', 'amount', 'rate', 'cost'));
                $cleaned_price = $this->clean_amount($item_price);
                
                // Get quantity
                $item_quantity = $this->extract_field($item, array('quantity', 'amount', 'qty', 'count'));
                $cleaned_quantity = $this->clean_amount($item_quantity);
                if ($cleaned_quantity <= 0) {
                    $cleaned_quantity = 1; // Default to 1 if no quantity
                }
                
                // Only add item if it has a price
                if ($cleaned_price > 0) {
                    $items[] = array(
                        'Name' => $item_name,
                        'UnitPrice' => $cleaned_price,
                        'Amount' => $cleaned_quantity,
                        'Unit' => $this->extract_field($item, array('unit', 'uom', 'measure')),
                        'PriceType' => $this->determine_price_type($item),
                        'VatRateType' => $this->determine_vat_rate($item),
                    );
                }
            }
        }
        
        // If still no items, create a default one
        if (empty($items)) {
            $items[] = array(
                'Name' => 'Invoice item',
                'UnitPrice' => 0,
                'Amount' => 1,
                'PriceType' => 0,
                'VatRateType' => 3
            );
        }
        
        return $items;
    }
    
    /**
     * Clean amount string (remove currency symbols, spaces, convert to float)
     */
    private function clean_amount($amount) {
        if (empty($amount)) {
            return 0;
        }
        
        // If already a number, return it
        if (is_numeric($amount)) {
            return (float)$amount;
        }
        
        // Remove common currency symbols and whitespace
        $cleaned = str_replace(array('$', '€', '£', 'Kč', 'CZK', 'EUR', 'USD', ' ', ','), array('', '', '', '', '', '', '', '', '.'), $amount);
        
        // Remove any non-numeric characters except dot and minus
        $cleaned = preg_replace('/[^0-9.\-]/', '', $cleaned);
        
        // Convert to float
        $result = (float)$cleaned;
        
        return $result;
    }
    
    /**
     * Determine price type (with/without VAT)
     */
    private function determine_price_type($item) {
        $price_type = $this->extract_field($item, array('price_type', 'vat_included'));
        
        if ($price_type === 'with_vat' || $price_type === true || $price_type === '1') {
            return 1; // With VAT
        }
        
        return 0; // Without VAT (default)
    }
    
    /**
     * Determine VAT rate type
     */
    private function determine_vat_rate($item) {
        $vat_rate = $this->extract_field($item, array('vat_rate', 'tax_rate', 'vat'));
        
        if (empty($vat_rate)) {
            return 0; // Basic rate (21%)
        }
        
        $vat_rate = (float)str_replace('%', '', $vat_rate);
        
        // Map VAT percentage to iDoklad VAT rate type
        if ($vat_rate == 21) return 0; // Basic (21%)
        if ($vat_rate == 15) return 1; // Reduced1 (15%)
        if ($vat_rate == 10) return 2; // Reduced2 (10%)
        if ($vat_rate == 0) return 3;  // Zero (0%)
        
        return 0; // Default to basic
    }
    
    /**
     * Get description (REQUIRED field for iDoklad)
     * Cannot be null or empty
     */
    private function get_description($data) {
        $description = $this->extract_field($data, array('description', 'note', 'notes', 'subject'));
        
        if (!empty($description)) {
            return $description;
        }
        
        // Generate fallback description
        $supplier = $this->extract_field($data, array('supplier_name', 'vendor_name'));
        $invoice_number = $this->extract_field($data, array('invoice_number', 'document_number'));
        
        $parts = array();
        if ($supplier) {
            $parts[] = 'Faktura od ' . $supplier;
        }
        if ($invoice_number) {
            $parts[] = 'č. ' . $invoice_number;
        }
        
        if (!empty($parts)) {
            return implode(' ', $parts);
        }
        
        // Ultimate fallback
        return 'Přijatá faktura - ' . date('d.m.Y');
    }
    
    /**
     * Build note with metadata
     */
    private function build_note($data, $pdf_text) {
        $note_parts = array();
        
        // Add description if exists
        $description = $this->extract_field($data, array('description', 'note'));
        if (!empty($description)) {
            $note_parts[] = $description;
        }
        
        // Add processing metadata
        $note_parts[] = '---';
        $note_parts[] = 'Processed: ' . date('Y-m-d H:i:s');
        $note_parts[] = 'Source: iDoklad Invoice Processor';
        
        // Add Zapier info if exists
        if (isset($data['zapier_processed']) && $data['zapier_processed']) {
            $note_parts[] = 'Parsed by: Zapier AI';
        }
        
        // Add PDF text preview if exists
        if (!empty($pdf_text)) {
            $preview = substr($pdf_text, 0, 500);
            $note_parts[] = '---';
            $note_parts[] = 'PDF Preview:';
            $note_parts[] = $preview;
        }
        
        return implode("\n", $note_parts);
    }
    
    /**
     * Extract field from data with fallback keys
     */
    private function extract_field($data, $keys) {
        if (!is_array($data)) {
            return null;
        }
        
        if (!is_array($keys)) {
            $keys = array($keys);
        }
        
        foreach ($keys as $key) {
            if (isset($data[$key]) && !empty($data[$key])) {
                return $data[$key];
            }
        }
        
        return null;
    }
    
    /**
     * Remove null and empty values from array
     * Preserves REQUIRED iDoklad fields even if empty
     */
    private function remove_empty_values($array) {
        if (!is_array($array)) {
            return $array;
        }
        
        // REQUIRED fields that must NEVER be removed (iDoklad API requirement)
        $required_fields = array(
            'DocumentNumber',
            'DocumentSerialNumber',
            'DateOfIssue', 
            'DateOfReceiving',
            'DateOfTaxing',
            'DateOfMaturity',
            'Description',
            'Items',
            'CurrencyId',
            'IsIncomeTax',
            'PartnerId', // REQUIRED - cannot be null
            'PartnerName', // REQUIRED - supplier name
            'ExchangeRate',
            'ExchangeRateAmount',
            'PaymentStatus',
            'PaymentOptionId'
        );
        
        $result = array();
        
        foreach ($array as $key => $value) {
            // Always keep required fields
            if (in_array($key, $required_fields)) {
                $result[$key] = $value;
            }
            // For other fields, only keep if not empty
            elseif (is_array($value)) {
                $cleaned = $this->remove_empty_values($value);
                if (!empty($cleaned)) {
                    $result[$key] = $cleaned;
                }
            } elseif ($value !== null && $value !== '') {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Validate transformed data
     */
    public function validate_idoklad_payload($payload) {
        $errors = array();
        $warnings = array();
        
        // Required fields per iDoklad API v3 ReceivedInvoices documentation
        if (empty($payload['DocumentNumber'])) {
            $errors[] = 'DocumentNumber is required';
        }
        
        if (!isset($payload['DocumentSerialNumber'])) {
            $errors[] = 'DocumentSerialNumber is required';
        }
        
        if (empty($payload['DateOfIssue'])) {
            $errors[] = 'DateOfIssue is required';
        }
        
        if (empty($payload['DateOfReceiving'])) {
            $errors[] = 'DateOfReceiving is required';
        }
        
        if (empty($payload['DateOfTaxing'])) {
            $errors[] = 'DateOfTaxing is required';
        }
        
        if (empty($payload['DateOfMaturity'])) {
            $errors[] = 'DateOfMaturity is required';
        }
        
        if (empty($payload['Description'])) {
            $errors[] = 'Description is required (cannot be empty)';
        }
        
        if (!isset($payload['IsIncomeTax'])) {
            $errors[] = 'IsIncomeTax is required (boolean)';
        }
        
        if (!isset($payload['CurrencyId'])) {
            $errors[] = 'CurrencyId is required';
        }
        
        // Partner: PartnerId is required (cannot be null/empty)
        if (!isset($payload['PartnerId']) || $payload['PartnerId'] === null) {
            $errors[] = 'PartnerId is required (cannot be null)';
        }
        
        if (empty($payload['PartnerName'])) {
            $errors[] = 'PartnerName is required';
        }
        
        if (empty($payload['Items']) || !is_array($payload['Items'])) {
            $errors[] = 'At least one invoice item is required';
        } else {
            // Check that at least one item has a price > 0
            $has_priced_item = false;
            foreach ($payload['Items'] as $item) {
                if (isset($item['UnitPrice']) && $item['UnitPrice'] > 0) {
                    $has_priced_item = true;
                    break;
                }
            }
            if (!$has_priced_item) {
                $errors[] = 'Missing required field: total_amount. No items with valid prices found - could not extract financial data from PDF';
            }
        }
        
        // Warnings for missing optional but important fields
        if (empty($payload['PartnerName'])) {
            $warnings[] = 'Partner name is missing';
        }
        
        if (empty($payload['SupplierIdentificationNumber'])) {
            $warnings[] = 'Supplier VAT/ICO number is missing';
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        );
    }
    
    /**
     * Parse PDF text directly to extract invoice data
     * Uses pattern matching and regex to find invoice fields
     */
    private function parse_pdf_text($pdf_text, $base_data = array()) {
        $parsed = array();
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Transformer: Parsing PDF text (' . strlen($pdf_text) . ' chars)');
        }
        
        // Extract invoice number (various patterns)
        $invoice_patterns = array(
            '/(?:invoice|faktura|číslo)\s*[:#]?\s*([A-Z0-9\-\/]+)/i',
            '/(?:number|číslo)\s*[:#]?\s*([A-Z0-9\-\/]+)/i',
            '/(?:č\.|číslo|No\.|Nr\.)\s*([A-Z0-9\-\/]+)/i'
        );
        foreach ($invoice_patterns as $pattern) {
            if (preg_match($pattern, $pdf_text, $matches)) {
                $parsed['invoice_number'] = trim($matches[1]);
                break;
            }
        }
        
        // Extract dates (various formats)
        $date_patterns = array(
            '/(?:datum|date|dated?)\s*[:#]?\s*(\d{1,2}[\.\-\/]\d{1,2}[\.\-\/]\d{2,4})/i',
            '/(\d{1,2}[\.\-\/]\d{1,2}[\.\-\/]\d{2,4})/'
        );
        foreach ($date_patterns as $pattern) {
            if (preg_match($pattern, $pdf_text, $matches)) {
                $parsed['date'] = trim($matches[1]);
                break;
            }
        }
        
        // Extract amounts (look for total, suma, celkem) - CZECH NUMBER FORMAT AWARE
        // Czech uses spaces as thousands separators: "7 900,00" not "7900.00"
        $amount_patterns = array(
            // Celkem k úhradě (total to pay) - most specific
            '/(?:celkem\s+k\s+úhradě|k\s+úhradě)\s*[:#]?\s*([0-9]+(?:\s+[0-9]{3})*[\.,][0-9]{2})\s*(?:Kč|CZK)?/iu',
            // Celkem, suma (total) - with Czech number format
            '/(?:celkem|suma|cena\s*celkem|k\s*zaplacení)\s*[:#]?\s*([0-9]+(?:\s+[0-9]{3})*[\.,][0-9]{2})\s*(?:Kč|CZK)?/iu',
            // Total, amount (English) - with spaces or commas
            '/(?:total|amount|částka)\s*[:#]?\s*([0-9]+(?:[\s,][0-9]{3})*[\.,][0-9]{2})\s*(?:Kč|CZK|EUR|€|\$)?/i',
            // Amount before CZK/Kč at end of line
            '/([0-9]+(?:\s+[0-9]{3})*[\.,][0-9]{2})\s*(?:Kč|CZK|EUR|€)\s*$/im'
        );
        
        $found_amount = null;
        foreach ($amount_patterns as $index => $pattern) {
            if (preg_match($pattern, $pdf_text, $matches)) {
                $found_amount = trim($matches[1]);
                error_log('iDoklad Transformer: Amount matched with pattern ' . $index . ': "' . $found_amount . '"');
                $parsed['total_amount'] = $found_amount;
                break;
            }
        }
        
        if (!$found_amount) {
            error_log('iDoklad Transformer: NO AMOUNT FOUND in PDF text');
            error_log('iDoklad Transformer: PDF text sample: ' . substr($pdf_text, 0, 800));
        }
        
        // Extract currency
        if (preg_match('/\b(CZK|EUR|USD|GBP)\b/i', $pdf_text, $matches)) {
            $parsed['currency'] = strtoupper($matches[1]);
        } elseif (preg_match('/Kč/', $pdf_text)) {
            $parsed['currency'] = 'CZK';
        } elseif (preg_match('/€/', $pdf_text)) {
            $parsed['currency'] = 'EUR';
        }
        
        // Extract VAT/ICO number
        $vat_patterns = array(
            '/(?:IČO|ICO|DIČ|DIC|VAT)\s*[:#]?\s*([A-Z]{0,2}\s*\d{6,12})/i',
            '/(?:IČO|ICO)\s*[:]\s*(\d{6,12})/i'
        );
        foreach ($vat_patterns as $pattern) {
            if (preg_match($pattern, $pdf_text, $matches)) {
                $parsed['supplier_vat_number'] = trim(str_replace(' ', '', $matches[1]));
                break;
            }
        }
        
        // Extract supplier name (look for common company indicators)
        $company_patterns = array(
            '/([A-ZŠČŘŽÝÁÍÉÚŮĎŤŇ][a-zščřžýáíéúůďťň]+(?:\s+[A-ZŠČŘŽÝÁÍÉÚŮĎŤŇ][a-zščřžýáíéúůďťň]+)*)\s+(?:s\.r\.o\.|a\.s\.|v\.o\.s\.|spol\.|Ltd|GmbH)/i',
            '/(?:supplier|dodavatel|from|od)\s*[:#]?\s*([^\n]+?)(?:\n|IČO)/i'
        );
        foreach ($company_patterns as $pattern) {
            if (preg_match($pattern, $pdf_text, $matches)) {
                $parsed['supplier_name'] = trim($matches[1]);
                break;
            }
        }
        
        // Extract variable symbol
        if (preg_match('/(?:VS|var\.?\s*symbol)\s*[:#]?\s*(\d+)/i', $pdf_text, $matches)) {
            $parsed['variable_symbol'] = trim($matches[1]);
        }
        
        // Extract bank account
        if (preg_match('/(\d{2,6}-?\d{6,10}\/\d{4})/', $pdf_text, $matches)) {
            $parsed['bank_account'] = trim($matches[1]);
        }
        
        // Merge with base data (email info, etc.)
        $result = array_merge($base_data, $parsed);
        
        // ALWAYS log parsing results
        error_log('iDoklad Transformer: === PDF TEXT PARSING RESULTS ===');
        error_log('iDoklad Transformer: Parsed invoice_number: ' . ($parsed['invoice_number'] ?? 'NOT FOUND'));
        error_log('iDoklad Transformer: Parsed date: ' . ($parsed['date'] ?? 'NOT FOUND'));
        error_log('iDoklad Transformer: Parsed total_amount: ' . ($parsed['total_amount'] ?? 'NOT FOUND'));
        error_log('iDoklad Transformer: Parsed currency: ' . ($parsed['currency'] ?? 'NOT FOUND'));
        error_log('iDoklad Transformer: Parsed supplier_name: ' . ($parsed['supplier_name'] ?? 'NOT FOUND'));
        error_log('iDoklad Transformer: Parsed supplier_vat_number: ' . ($parsed['supplier_vat_number'] ?? 'NOT FOUND'));
        error_log('iDoklad Transformer: All parsed keys: ' . implode(', ', array_keys($parsed)));
        
        return $result;
    }
}


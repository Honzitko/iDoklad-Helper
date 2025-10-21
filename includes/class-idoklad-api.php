<?php
/**
 * iDoklad API integration class - Fixed with proper authentication
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_IDokladAPI {
    
    private $api_url;
    private $client_id;
    private $client_secret;
    private $access_token;
    private $token_expires_at;
    private $user_id;
    
    public function __construct($user_credentials = null) {
        if ($user_credentials) {
            $this->api_url = $user_credentials->idoklad_api_url ?: 'https://api.idoklad.cz/v3';
            $this->client_id = $user_credentials->idoklad_client_id;
            $this->client_secret = $user_credentials->idoklad_client_secret;
            $this->user_id = $user_credentials->idoklad_user_id;
        } else {
            // Fallback to global settings (deprecated)
            $this->api_url = get_option('idoklad_api_url', 'https://api.idoklad.cz/v3');
            $this->client_id = get_option('idoklad_client_id');
            $this->client_secret = get_option('idoklad_client_secret');
        }
    }
    
    /**
     * Get access token using OAuth 2.0 Client Credentials flow
     */
    public function get_access_token() {
        // Check if we have a valid cached token
        if ($this->access_token && $this->token_expires_at && time() < $this->token_expires_at) {
            return $this->access_token;
        }
        
        if (empty($this->client_id) || empty($this->client_secret)) {
            throw new Exception('iDoklad API credentials are not configured for this user');
        }
        
        // OAuth 2.0 Client Credentials flow endpoint
        $token_url = 'https://app.idoklad.cz/identity/server/connect/token';
        
        // OAuth 2.0 client credentials flow parameters
        $data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'scope' => 'idoklad_api'
        );
        
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress-iDoklad-Processor/1.1.0'
            ),
            'body' => http_build_query($data),
            'timeout' => 30,
            'method' => 'POST',
            'sslverify' => true
        );
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad API: Requesting OAuth token from ' . $token_url);
            error_log('iDoklad API: Request data: ' . http_build_query($data));
            error_log('iDoklad API: Client ID: ' . $this->client_id);
            error_log('iDoklad API: Client Secret: ' . substr($this->client_secret, 0, 8) . '...');
        }
        
        $response = wp_remote_request($token_url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: WP Error: ' . $error_message);
            }
            throw new Exception('iDoklad OAuth token request failed: ' . $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad API: OAuth Response code: ' . $response_code);
            error_log('iDoklad API: OAuth Response body: ' . $response_body);
        }
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = 'Unknown OAuth error';
            
            if (isset($error_data['error'])) {
                $error_message = $error_data['error'];
                if (isset($error_data['error_description'])) {
                    $error_message .= ': ' . $error_data['error_description'];
                }
            } elseif (isset($error_data['message'])) {
                $error_message = $error_data['message'];
            }
            
            throw new Exception('iDoklad OAuth error (' . $response_code . '): ' . $error_message);
        }
        
        $token_data = json_decode($response_body, true);
        
        if (!$token_data) {
            throw new Exception('Invalid JSON response from iDoklad OAuth: ' . $response_body);
        }
        
        if (!isset($token_data['access_token'])) {
            throw new Exception('Invalid OAuth token response from iDoklad. Response: ' . $response_body);
        }
        
        $this->access_token = $token_data['access_token'];
        $this->token_expires_at = time() + (isset($token_data['expires_in']) ? $token_data['expires_in'] : 3600);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad API: OAuth access token obtained successfully');
        }
        
        return $this->access_token;
    }
    
    /**
     * Make authenticated API request with Bearer token
     */
    private function make_api_request($endpoint, $method = 'GET', $data = null, $store_response = false) {
        $access_token = $this->get_access_token();
        
        $url = $this->api_url . $endpoint;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'WordPress-iDoklad-Processor/1.1.0'
        );
        
        $args = array(
            'headers' => $headers,
            'method' => $method,
            'timeout' => 30,
            'sslverify' => true
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad API: Making authenticated request to ' . $url . ' with method ' . $method);
            error_log('iDoklad API: Using Bearer token: ' . substr($access_token, 0, 20) . '...');
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Request failed: ' . $error_message);
            }
            
            // Store error response for diagnostics
            if ($store_response) {
                update_option('idoklad_last_api_response', array(
                    'error' => true,
                    'message' => $error_message,
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'request_data' => $data,
                    'timestamp' => time()
                ), false);
            }
            
            throw new Exception('iDoklad API request failed: ' . $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad API: Response code: ' . $response_code);
            error_log('iDoklad API: Response body: ' . $response_body);
        }
        
        // Store response for diagnostics
        if ($store_response) {
            $response_data = json_decode($response_body, true);
            update_option('idoklad_last_api_response', array(
                'response_code' => $response_code,
                'response_body' => $response_body,
                'response_data' => $response_data,
                'endpoint' => $endpoint,
                'method' => $method,
                'request_data' => $data,
                'timestamp' => time(),
                'headers' => wp_remote_retrieve_headers($response)->getAll()
            ), false);
        }
        
        if ($response_code >= 400) {
            $error_data = json_decode($response_body, true);
            $error_message = 'API request failed';
            $error_details = array();
            
            if (isset($error_data['Message'])) {
                $error_message = $error_data['Message'];
            } elseif (isset($error_data['message'])) {
                $error_message = $error_data['message'];
            } elseif (isset($error_data['error'])) {
                $error_message = $error_data['error'];
            } elseif (isset($error_data['error_description'])) {
                $error_message = $error_data['error_description'];
            }
            
            // iDoklad often returns validation errors in ModelState
            if (isset($error_data['ModelState']) && is_array($error_data['ModelState'])) {
                foreach ($error_data['ModelState'] as $field => $errors) {
                    if (is_array($errors)) {
                        $error_details[] = $field . ': ' . implode(', ', $errors);
                    }
                }
            }
            
            // Add validation errors if present
            if (!empty($error_details)) {
                $error_message .= ' | Validation errors: ' . implode(' | ', $error_details);
            }
            
            // Include full response for debugging
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Full error response: ' . $response_body);
                if ($data) {
                    error_log('iDoklad API: Request data was: ' . json_encode($data, JSON_PRETTY_PRINT));
                }
            }
            
            throw new Exception('iDoklad API error (' . $response_code . '): ' . $error_message);
        }
        
        return json_decode($response_body, true);
    }
    
    /**
     * Create invoice with response capture (for testing/diagnostics)
     */
    public function create_invoice_with_response($extracted_data) {
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad API: Creating received invoice (expense) with data: ' . json_encode($extracted_data));
        }
        
        try {
            // For received invoices, use the ReceivedInvoices endpoint
            $supplier_id = $this->get_or_create_supplier($extracted_data);
            
            // Build received invoice data
            $invoice_data = $this->build_received_invoice_data($extracted_data, $supplier_id);
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Sending payload: ' . json_encode($invoice_data, JSON_PRETTY_PRINT));
            }
            
            // Create received invoice with response capture
            $response = $this->make_api_request('/ReceivedInvoices', 'POST', $invoice_data, true);
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Received invoice created successfully with ID: ' . (isset($response['Id']) ? $response['Id'] : 'unknown'));
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log('iDoklad API Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create invoice from extracted data
     * Note: This creates a RECEIVED invoice (expense) - use create_issued_invoice for invoices you send out
     */
    public function create_invoice($extracted_data) {
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad API: Creating received invoice (expense) with data: ' . json_encode($extracted_data));
        }
        
        try {
            // For received invoices, use the ReceivedInvoices endpoint
            // This is for invoices you RECEIVE from suppliers
            $supplier_id = $this->get_or_create_supplier($extracted_data);
            
            // Build received invoice data
            $invoice_data = $this->build_received_invoice_data($extracted_data, $supplier_id);
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Sending payload: ' . json_encode($invoice_data, JSON_PRETTY_PRINT));
            }
            
            // Create received invoice
            $response = $this->make_api_request('/ReceivedInvoices', 'POST', $invoice_data);
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Received invoice created successfully with ID: ' . (isset($response['Id']) ? $response['Id'] : 'unknown'));
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log('iDoklad API Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create ISSUED invoice (for invoices you send to customers)
     */
    public function create_issued_invoice($extracted_data) {
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad API: Creating issued invoice with data: ' . json_encode($extracted_data));
        }
        
        try {
            $customer_id = $this->get_or_create_supplier($extracted_data);
            $invoice_data = $this->build_invoice_data($extracted_data, $customer_id);
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Sending payload: ' . json_encode($invoice_data, JSON_PRETTY_PRINT));
            }
            
            $response = $this->make_api_request('/IssuedInvoices', 'POST', $invoice_data);
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Issued invoice created successfully with ID: ' . (isset($response['Id']) ? $response['Id'] : 'unknown'));
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log('iDoklad API Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create expense from extracted data
     */
    public function create_expense($extracted_data) {
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad API: Creating expense with data: ' . json_encode($extracted_data));
        }
        
        try {
            // First, get or create supplier
            $supplier_id = $this->get_or_create_supplier($extracted_data);
            
            // Build expense data
            $expense_data = $this->build_expense_data($extracted_data, $supplier_id);
            
            // Create expense
            $response = $this->make_api_request('/Expenses', 'POST', $expense_data);
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Expense created successfully with ID: ' . (isset($response['Id']) ? $response['Id'] : 'unknown'));
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log('iDoklad API Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get or create supplier
     */
    private function get_or_create_supplier($extracted_data) {
        $supplier_name = $extracted_data['supplier_name'];
        
        if (empty($supplier_name)) {
            throw new Exception('Supplier name is required');
        }
        
        // Try to find existing supplier
        try {
            $suppliers = $this->make_api_request('/Contacts?filter=Name~eq~' . urlencode($supplier_name));
            
            if (!empty($suppliers['Data']) && count($suppliers['Data']) > 0) {
                $supplier = $suppliers['Data'][0];
                return $supplier['Id'];
            }
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Could not search for existing supplier: ' . $e->getMessage());
            }
        }
        
        // Create new supplier
        $supplier_data = array(
            'Name' => $supplier_name,
            'IsCompany' => true,
            'CountryId' => 1, // Czech Republic - you might want to make this configurable
            'IsActive' => true
        );
        
        // Add VAT number if available
        if (!empty($extracted_data['supplier_vat_number'])) {
            $supplier_data['IdentificationNumber'] = $extracted_data['supplier_vat_number'];
        }
        
        $supplier_response = $this->make_api_request('/Contacts', 'POST', $supplier_data);
        
        return $supplier_response['Id'];
    }
    
    /**
     * Build RECEIVED invoice data for API (invoices you receive from suppliers)
     */
    private function build_received_invoice_data($extracted_data, $supplier_id) {
        // Ensure date is in correct format (Y-m-d)
        $date = date('Y-m-d', strtotime($extracted_data['date']));
        $due_date = !empty($extracted_data['due_date']) ? date('Y-m-d', strtotime($extracted_data['due_date'])) : $date;
        
        $invoice_data = array(
            'PartnerId' => $supplier_id,
            'DocumentNumber' => $extracted_data['invoice_number'] ?? 'INV-' . time(),
            'DateOfIssue' => $date,
            'DateOfTaxing' => $date,
            'DateOfMaturity' => $due_date,
            'DateOfPayment' => null, // Not paid yet
            'CurrencyId' => $this->get_currency_id($extracted_data['currency'] ?? 'CZK'),
            'ExchangeRate' => 1,
            'ExchangeRateAmount' => 1,
            'PaymentStatus' => 0, // Unpaid
            'Items' => array()
        );
        
        // Add items
        if (!empty($extracted_data['items']) && is_array($extracted_data['items'])) {
            foreach ($extracted_data['items'] as $item) {
                $invoice_item = array(
                    'Name' => $item['name'] ?? 'Item',
                    'Amount' => floatval($item['quantity'] ?? 1),
                    'UnitPrice' => floatval($item['price'] ?? 0),
                    'VatRateType' => 1, // Standard VAT rate (21% in CZ)
                    'PriceType' => 0 // 0 = Without VAT, 1 = With VAT
                );
                
                $invoice_data['Items'][] = $invoice_item;
            }
        } else {
            // If no items, create a single item with the total amount
            $invoice_data['Items'][] = array(
                'Name' => 'Invoice total',
                'Amount' => 1,
                'UnitPrice' => floatval($extracted_data['total_amount'] ?? 0),
                'VatRateType' => 1,
                'PriceType' => 0 // Assuming price is without VAT
            );
        }
        
        // Add notes if available
        if (!empty($extracted_data['notes'])) {
            $invoice_data['Note'] = $extracted_data['notes'];
        }
        
        return $invoice_data;
    }
    
    /**
     * Build ISSUED invoice data for API (invoices you send to customers)
     */
    private function build_invoice_data($extracted_data, $customer_id) {
        $date = date('Y-m-d', strtotime($extracted_data['date']));
        $due_date = !empty($extracted_data['due_date']) ? date('Y-m-d', strtotime($extracted_data['due_date'])) : $date;
        
        $invoice_data = array(
            'PartnerId' => $customer_id,
            'DocumentNumber' => $extracted_data['invoice_number'] ?? 'INV-' . time(),
            'DateOfIssue' => $date,
            'DateOfTaxing' => $date,
            'DateOfMaturity' => $due_date,
            'CurrencyId' => $this->get_currency_id($extracted_data['currency'] ?? 'CZK'),
            'ExchangeRate' => 1,
            'ExchangeRateAmount' => 1,
            'IsEet' => false,
            'Items' => array()
        );
        
        // Add items
        if (!empty($extracted_data['items']) && is_array($extracted_data['items'])) {
            foreach ($extracted_data['items'] as $item) {
                $invoice_item = array(
                    'Name' => $item['name'] ?? 'Item',
                    'Amount' => floatval($item['quantity'] ?? 1),
                    'UnitPrice' => floatval($item['price'] ?? 0),
                    'VatRateType' => 1,
                    'PriceType' => 0
                );
                
                $invoice_data['Items'][] = $invoice_item;
            }
        } else {
            $invoice_data['Items'][] = array(
                'Name' => 'Invoice total',
                'Amount' => 1,
                'UnitPrice' => floatval($extracted_data['total_amount'] ?? 0),
                'VatRateType' => 1,
                'PriceType' => 0
            );
        }
        
        if (!empty($extracted_data['notes'])) {
            $invoice_data['Note'] = $extracted_data['notes'];
        }
        
        return $invoice_data;
    }
    
    /**
     * Build expense data for API
     */
    private function build_expense_data($extracted_data, $supplier_id) {
        $expense_data = array(
            'PartnerId' => $supplier_id,
            'DocumentNumber' => $extracted_data['invoice_number'],
            'DateOfIssue' => $extracted_data['date'],
            'DateOfTaxing' => $extracted_data['date'],
            'CurrencyId' => $this->get_currency_id($extracted_data['currency']),
            'ExchangeRate' => 1,
            'ExchangeRateAmount' => 1,
            'IsEet' => false,
            'Items' => array()
        );
        
        // Add items
        if (!empty($extracted_data['items']) && is_array($extracted_data['items'])) {
            foreach ($extracted_data['items'] as $item) {
                $expense_item = array(
                    'Name' => $item['name'],
                    'Amount' => $item['quantity'],
                    'UnitPrice' => $item['price'],
                    'VatRateType' => 1, // Standard VAT rate
                    'IsTaxMovement' => true
                );
                
                $expense_data['Items'][] = $expense_item;
            }
        } else {
            // If no items, create a single item with the total amount
            $expense_data['Items'][] = array(
                'Name' => 'Expense total',
                'Amount' => 1,
                'UnitPrice' => $extracted_data['total_amount'],
                'VatRateType' => 1,
                'IsTaxMovement' => true
            );
        }
        
        // Add notes if available
        if (!empty($extracted_data['notes'])) {
            $expense_data['Note'] = $extracted_data['notes'];
        }
        
        return $expense_data;
    }
    
    /**
     * Get currency ID from currency code
     */
    private function get_currency_id($currency_code) {
        // Default currency mapping - you might want to make this configurable
        $currency_mapping = array(
            'CZK' => 1,
            'EUR' => 2,
            'USD' => 3,
            'GBP' => 4
        );
        
        $currency_code = strtoupper($currency_code);
        
        return isset($currency_mapping[$currency_code]) ? $currency_mapping[$currency_code] : 1; // Default to CZK
    }
    
    /**
     * Test API connection using OAuth
     */
    public function test_connection() {
        try {
            // First, get the OAuth access token
            $access_token = $this->get_access_token();
            
            if (empty($access_token)) {
                throw new Exception('Failed to obtain access token');
            }
            
            // Test the connection by making a simple API call
            // Try to get user info or company info
            try {
                $user_info = $this->make_api_request('/Users/Current');
                return array(
                    'success' => true, 
                    'message' => 'OAuth connection successful. User: ' . (isset($user_info['UserName']) ? $user_info['UserName'] : 'Unknown')
                );
            } catch (Exception $e) {
                // If user info fails, try a different endpoint
                try {
                    $company_info = $this->make_api_request('/Companies/Current');
                    return array(
                        'success' => true, 
                        'message' => 'OAuth connection successful. Company: ' . (isset($company_info['Name']) ? $company_info['Name'] : 'Unknown')
                    );
                } catch (Exception $e2) {
                    // If both fail, but we got the token, that's still a success
                    return array(
                        'success' => true, 
                        'message' => 'OAuth token obtained successfully, but API endpoints may require additional permissions'
                    );
                }
            }
            
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Connection test failed: ' . $e->getMessage());
            }
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Get API status and limits
     */
    public function get_api_status() {
        try {
            $response = $this->make_api_request('/Account/GetCurrentUser');
            return array(
                'connected' => true,
                'user' => $response
            );
        } catch (Exception $e) {
            return array(
                'connected' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get available currencies
     */
    public function get_currencies() {
        try {
            $response = $this->make_api_request('/Currencies');
            return $response;
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Could not fetch currencies: ' . $e->getMessage());
            }
            return array();
        }
    }
    
    /**
     * Get VAT rates
     */
    public function get_vat_rates() {
        try {
            $response = $this->make_api_request('/VatRates');
            return $response;
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Could not fetch VAT rates: ' . $e->getMessage());
            }
            return array();
        }
    }
}

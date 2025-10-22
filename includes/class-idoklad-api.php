<?php
/**
 * iDoklad API integration class - Fixed with proper authentication
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/mervit/idoklad-v3/Exceptions/IDokladException.php';
require_once __DIR__ . '/vendor/mervit/idoklad-v3/Endpoint.php';
require_once __DIR__ . '/vendor/mervit/idoklad-v3/Client.php';

use Mervit\iDoklad\Client as IDokladClient;
use Mervit\iDoklad\Exceptions\IDokladException;

class IDokladProcessor_IDokladAPI {

    private $api_url;
    private $client_id;
    private $client_secret;
    private $access_token;
    private $token_expires_at;
    private $user_id;
    /** @var IDokladClient|null */
    private $client;
    
    public function __construct($user_credentials = null) {
        if ($user_credentials) {
            $this->api_url = $this->normalize_api_url($user_credentials->idoklad_api_url ?? null);
            $this->client_id = $user_credentials->idoklad_client_id;
            $this->client_secret = $user_credentials->idoklad_client_secret;
            $this->user_id = $user_credentials->idoklad_user_id;
        } else {
            // Fallback to global settings (deprecated)
            $this->api_url = $this->normalize_api_url(get_option('idoklad_api_url'));
            $this->client_id = get_option('idoklad_client_id');
            $this->client_secret = get_option('idoklad_client_secret');
        }
    }

    /**
     * Lazily build an iDoklad client leveraging the bundled SDK adapter.
     *
     * @return IDokladClient
     */
    private function get_client() {
        if ($this->client instanceof IDokladClient) {
            return $this->client;
        }

        $timeout = 30;
        if (function_exists('get_option')) {
            $timeout = intval(get_option('idoklad_http_timeout', $timeout));
        }

        $config = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'user_id' => $this->user_id,
            'timeout' => max(5, $timeout),
            'logger' => array($this, 'log_debug'),
        );

        $this->client = new IDokladClient($this->api_url ?: 'https://api.idoklad.cz/api/v3', $config);

        return $this->client;
    }

    /**
     * Debug logger used by the embedded SDK when WordPress debug mode is enabled.
     *
     * @param string $message
     * @param array<string,mixed> $context
     * @return void
     */
    public function log_debug($message, array $context = array()) {
        if (!function_exists('get_option') || !get_option('idoklad_debug_mode')) {
            return;
        }

        $formatted = 'iDoklad API: ' . $message;
        if (!empty($context)) {
            $formatted .= ' ' . (function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context));
        }

        error_log($formatted);
    }

    /**
     * Ensure the API base URL always targets the documented /api/v3 endpoints.
     */
    private function normalize_api_url($api_url) {
        if (empty($api_url)) {
            $api_url = 'https://api.idoklad.cz/api/v3';
        }

        $api_url = trim($api_url);

        // Handle accidental copies of the public documentation URL
        // (e.g. https://api.idoklad.cz/Help/v3/cs/index.html) by
        // converting them back to the real API base path.
        if (stripos($api_url, '/help/') !== false) {
            $parts = parse_url($api_url);

            if ($parts && isset($parts['scheme'], $parts['host'])) {
                return $parts['scheme'] . '://' . $parts['host'] . '/api/v3';
            }

            return 'https://api.idoklad.cz/api/v3';
        }

        $api_url = rtrim($api_url, '/');

        if (preg_match('#/api/v\d+$#', $api_url)) {
            return $api_url;
        }

        if (preg_match('#/v(\d+)$#', $api_url)) {
            return preg_replace('#/v(\d+)$#', '/api/v$1', $api_url);
        }

        if (preg_match('#/api$#', $api_url)) {
            return $api_url . '/v3';
        }

        return $api_url . '/api/v3';
    }
    
    /**
     * Get access token using OAuth 2.0 Client Credentials flow
     */
    public function get_access_token() {
        if ($this->access_token && $this->token_expires_at && time() < $this->token_expires_at - 30) {
            return $this->access_token;
        }

        try {
            $access_token = $this->get_client()->getAccessToken();
            $this->access_token = $access_token;
            $last_response = $this->client ? $this->client->getLastResponseInfo() : array();
            if (!empty($last_response['headers']['expires']) && empty($this->token_expires_at)) {
                $this->token_expires_at = strtotime($last_response['headers']['expires']);
            } elseif (empty($this->token_expires_at)) {
                $this->token_expires_at = time() + 3600;
            }

            return $access_token;
        } catch (IDokladException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * Make authenticated API request with Bearer token
     */
    private function make_api_request($endpoint, $method = 'GET', $data = null, $store_response = false) {
        $client = $this->get_client();

        $options = array();
        if ($data !== null && in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
            $options['json'] = $data;
        }

        try {
            $response = $client->request($method, $endpoint, $options);

            if ($store_response && function_exists('update_option')) {
                $info = $client->getLastResponseInfo();
                update_option('idoklad_last_api_response', array(
                    'response_code' => $info['status_code'] ?? null,
                    'response_body' => $info['body'] ?? null,
                    'response_data' => $response,
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'request_data' => $data,
                    'timestamp' => time(),
                    'headers' => $info['headers'] ?? array(),
                ), false);
            }

            return $response;
        } catch (IDokladException $e) {
            $info = $client->getLastResponseInfo();

            if ($store_response && function_exists('update_option')) {
                update_option('idoklad_last_api_response', array(
                    'error' => true,
                    'message' => $e->getMessage(),
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'request_data' => $data,
                    'timestamp' => time(),
                    'response_code' => $info['status_code'] ?? null,
                    'response_body' => $info['body'] ?? null,
                    'headers' => $info['headers'] ?? array(),
                ), false);
            }

            $context = $e->getContext();
            $this->log_debug('Request failed', array(
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $e->getMessage(),
                'context' => $context,
            ));

            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Generic helper to list resources through the SDK.
     *
     * @param string $resource
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     */
    public function list_resource($resource, array $params = array()) {
        $endpoint = '/' . ltrim($resource, '/');

        $raw_query = array();
        if (isset($params['filter_raw'])) {
            $raw_query[] = 'filter=' . $params['filter_raw'];
            unset($params['filter_raw']);
        }

        if (isset($params['raw_query'])) {
            $raw_query[] = ltrim($params['raw_query'], '?&');
            unset($params['raw_query']);
        }

        if (!empty($params)) {
            $raw_query[] = http_build_query($params);
        }

        if (!empty($raw_query)) {
            $endpoint .= '?' . implode('&', array_filter($raw_query));
        }

        return $this->get_client()->request('GET', $endpoint);
    }

    /**
     * Generic helper to create a resource using the SDK.
     *
     * @param string $resource
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public function create_resource($resource, array $payload) {
        return $this->get_client()->request('POST', '/' . ltrim($resource, '/'), array('json' => $payload));
    }

    /**
     * Generic helper to update a resource by identifier.
     *
     * @param string $resource
     * @param int|string $id
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public function update_resource($resource, $id, array $payload) {
        $endpoint = '/' . trim($resource, '/') . '/' . $id;
        return $this->get_client()->request('PUT', $endpoint, array('json' => $payload));
    }

    /**
     * Delete a resource via the API.
     *
     * @param string $resource
     * @param int|string $id
     * @return array<string,mixed>|null
     */
    public function delete_resource($resource, $id) {
        $endpoint = '/' . trim($resource, '/') . '/' . $id;
        return $this->get_client()->request('DELETE', $endpoint);
    }

    /**
     * Convenience wrappers for common documents.
     */
    public function list_sales_invoices(array $params = array()) {
        return $this->list_resource('IssuedInvoices', $params);
    }

    public function list_received_invoices(array $params = array()) {
        return $this->list_resource('ReceivedInvoices', $params);
    }

    public function list_expenses(array $params = array()) {
        return $this->list_resource('Expenses', $params);
    }

    public function list_contacts(array $params = array()) {
        return $this->list_resource('Contacts', $params);
    }

    /**
     * Download a PDF representation of a document.
     *
     * @param string $resource
     * @param int|string $id
     * @return string|null
     */
    public function download_document_pdf($resource, $id) {
        $endpoint = '/' . trim($resource, '/') . '/' . $id . '/Pdf';
        return $this->get_client()->request('GET', $endpoint, array(
            'headers' => array('Accept' => 'application/pdf'),
            'decode' => false,
        ));
    }

    /**
     * Ensure an iDoklad contact exists for the supplied email address.
     *
     * @param string $email
     * @param array<string,mixed> $contact_data
     * @return array<string,mixed>|null
     * @throws Exception
     */
    public function ensure_contact_for_email($email, array $contact_data = array()) {
        $email = trim($email);
        if (empty($email)) {
            throw new Exception('Email address is required to synchronise contacts');
        }

        try {
            $lookup = $this->list_resource('Contacts', array(
                'filter_raw' => 'Email~eq~' . rawurlencode($email),
                'pageSize' => 1,
            ));

            if (!empty($lookup['Data'][0])) {
                return $lookup['Data'][0];
            }
        } catch (Exception $e) {
            $this->log_debug('Contact lookup failed', array('email' => $email, 'error' => $e->getMessage()));
        }

        $payload = array(
            'Name' => $contact_data['name'] ?? $email,
            'Email' => $email,
            'IsCompany' => (bool) ($contact_data['is_company'] ?? false),
            'CountryId' => $contact_data['country_id'] ?? 1,
            'Phone' => $contact_data['phone'] ?? null,
            'Note' => $contact_data['note'] ?? null,
            'IdentificationNumber' => $contact_data['identification_number'] ?? null,
            'VatNumber' => $contact_data['vat_number'] ?? null,
        );

        $payload = array_filter($payload, function ($value) {
            return $value !== null && $value !== '';
        });

        $created = $this->create_resource('Contacts', $payload);

        return $created;
    }

    /**
     * Record an email interaction for traceability.
     *
     * @param array<string,mixed> $email_meta
     * @param array<int,array<string,mixed>> $attachments
     * @param string $status
     * @return void
     */
    public function record_email_activity(array $email_meta, array $attachments = array(), $status = 'received') {
        $email_address = $email_meta['from'] ?? ($email_meta['email_from'] ?? null);
        if (empty($email_address)) {
            return;
        }

        $note = $this->format_email_note($email_meta, $attachments, $status);

        try {
            $contact = $this->ensure_contact_for_email($email_address, array(
                'name' => $email_meta['sender_name'] ?? ($email_meta['name'] ?? $email_address),
                'note' => $email_meta['subject'] ?? '',
            ));

            $contact_id = is_array($contact) ? ($contact['Id'] ?? null) : null;

            if ($contact_id) {
                try {
                    $this->make_api_request('/Contacts/' . $contact_id . '/Notes', 'POST', array('Note' => $note));
                } catch (Exception $note_exception) {
                    $this->log_debug('Unable to attach note to contact', array(
                        'contact_id' => $contact_id,
                        'error' => $note_exception->getMessage(),
                    ));
                }
            }
        } catch (Exception $e) {
            $this->log_debug('Failed to record email activity in iDoklad', array(
                'email' => $email_address,
                'error' => $e->getMessage(),
            ));
        }

        if (function_exists('do_action')) {
            do_action('idoklad_email_activity_recorded', $email_meta, $attachments, $status);
        }
    }

    /**
     * Build a readable note body summarising the email interaction.
     *
     * @param array<string,mixed> $email_meta
     * @param array<int,array<string,mixed>> $attachments
     * @param string $status
     * @return string
     */
    private function format_email_note(array $email_meta, array $attachments, $status) {
        $lines = array();
        $lines[] = 'Email status: ' . ucfirst($status);

        if (!empty($email_meta['subject'])) {
            $lines[] = 'Subject: ' . $email_meta['subject'];
        }

        if (!empty($email_meta['email_id'])) {
            $lines[] = 'Message ID: ' . $email_meta['email_id'];
        }

        if (!empty($email_meta['received_at'])) {
            $lines[] = 'Received at: ' . $email_meta['received_at'];
        }

        if (!empty($attachments)) {
            $lines[] = 'Attachments:';
            foreach ($attachments as $attachment) {
                $label = $attachment['name'] ?? ($attachment['filename'] ?? 'file');
                $size = isset($attachment['size']) ? $this->human_readable_file_size($attachment['size']) : null;
                $lines[] = ' - ' . $label . ($size ? ' (' . $size . ')' : '');
            }
        }

        if (!empty($email_meta['document_number'])) {
            $lines[] = 'Document number: ' . $email_meta['document_number'];
        }

        if (!empty($email_meta['notes'])) {
            $lines[] = 'Notes: ' . $email_meta['notes'];
        }

        return implode("\n", $lines);
    }

    /**
     * Convert file size to a readable format.
     *
     * @param int $bytes
     * @return string
     */
    private function human_readable_file_size($bytes) {
        $bytes = (int) $bytes;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = array('B', 'KB', 'MB', 'GB');
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $value = $bytes / pow(1024, $power);

        return round($value, 2) . ' ' . $units[$power];
    }
    
    /**
     * Create invoice with response capture (for testing/diagnostics)
     * Note: This accepts already-transformed data from the DataTransformer
     */
    public function create_invoice_with_response($idoklad_payload) {
        error_log('iDoklad API: Creating received invoice with payload (test mode): ' . json_encode($idoklad_payload, JSON_PRETTY_PRINT));
        
        try {
            // The payload is already in iDoklad format from the DataTransformer
            // Create received invoice with response capture
            $response = $this->make_api_request('/ReceivedInvoices', 'POST', $idoklad_payload, true);
            
            error_log('iDoklad API: Received invoice created successfully with ID: ' . (isset($response['Id']) ? $response['Id'] : 'unknown'));
            
            return $response;
            
        } catch (Exception $e) {
            error_log('iDoklad API Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create invoice from transformed iDoklad payload
     * Note: This accepts already-transformed data from the DataTransformer
     */
    public function create_invoice($idoklad_payload) {
        error_log('iDoklad API: Creating received invoice with payload: ' . json_encode($idoklad_payload, JSON_PRETTY_PRINT));
        
        try {
            // The payload is already in iDoklad format from the DataTransformer
            // Just send it to the API
            $response = $this->make_api_request('/ReceivedInvoices', 'POST', $idoklad_payload);
            
            error_log('iDoklad API: Received invoice created successfully with ID: ' . (isset($response['Id']) ? $response['Id'] : 'unknown'));
            
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
     * Get or create supplier contact in iDoklad.
     *
     * Exposed publicly so other components (like the email monitor) can ensure
     * that a valid PartnerId exists before attempting to create a received
     * invoice. The API rejects payloads where PartnerId is 0 or missing, so we
     * always create or look up the supplier first.
     */
    public function get_or_create_supplier($extracted_data) {
        $supplier_name = $extracted_data['supplier_name'];
        
        if (empty($supplier_name)) {
            throw new Exception('Supplier name is required');
        }
        
        // Try to find existing supplier
        try {
            $suppliers = $this->list_resource('Contacts', array(
                'filter_raw' => 'Name~eq~' . rawurlencode($supplier_name),
                'pageSize' => 1,
            ));

            if (!empty($suppliers['Data']) && count($suppliers['Data']) > 0) {
                $supplier = $suppliers['Data'][0];
                return $supplier['Id'];
            }
        } catch (Exception $e) {
            if (function_exists('get_option') && get_option('idoklad_debug_mode')) {
                error_log('iDoklad API: Could not search for existing supplier: ' . $e->getMessage());
            }
        }

        // Create new supplier
        $supplier_data = array(
            'Name' => $supplier_name,
            'IsCompany' => true,
            'CountryId' => 1, // Czech Republic - configurable via transformer if needed
            'IsActive' => true
        );

        // Add VAT number if available
        if (!empty($extracted_data['supplier_vat_number'])) {
            $supplier_data['IdentificationNumber'] = $extracted_data['supplier_vat_number'];
        }

        if (!empty($extracted_data['supplier_email'])) {
            $supplier_data['Email'] = $extracted_data['supplier_email'];
        }

        $supplier_response = $this->create_resource('Contacts', $supplier_data);

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

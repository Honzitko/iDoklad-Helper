<?php
/**
 * Complete iDoklad API v3 Integration
 * Production-grade integration following exact specifications
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_IDokladAPIV3Integration {
    
    private $client_id;
    private $client_secret;
    private $access_token;
    private $base_url = 'https://api.idoklad.cz/v3';
    private $auth_url = 'https://identity.idoklad.cz/server/connect/token';
    private $logger;
    private $database;
    
    public function __construct($client_id, $client_secret) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->logger = IDokladProcessor_Logger::get_instance();
        $this->database = new IDokladProcessor_Database();
        
        if (empty($this->client_id) || empty($this->client_secret)) {
            throw new Exception('Client ID and Client Secret are required for iDoklad API integration');
        }
    }
    
    /**
     * Complete integration workflow: Authentication → Numeric Sequence → Create Invoice
     * @param array $invoice_data - Invoice data to create
     * @return array - Complete response with invoice details
     */
    public function create_invoice_complete_workflow($invoice_data = null) {
        try {
            $this->logger->info('Starting complete iDoklad API v3 integration workflow');
            
            // Step 1: Authentication
            $this->authenticate();
            
            // Step 2: (Optional) Create Partner if needed
            $partner_id = $this->create_partner_if_needed($invoice_data);
            
            // Step 3: Resolve NumericSequence for issued invoices
            $numeric_sequence_data = $this->resolve_numeric_sequence();
            
            // Step 4: Create Issued Invoice
            $invoice_response = $this->create_issued_invoice($numeric_sequence_data, $partner_id, $invoice_data);
            
            $this->logger->info('Complete iDoklad API v3 integration workflow completed successfully');
            
            return $invoice_response;
            
        } catch (Exception $e) {
            $this->logger->info('Error in complete iDoklad API v3 integration workflow: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Step 1: Authentication using OAuth2 Client Credentials
     */
    private function authenticate() {
        $this->logger->info('Step 1: Authenticating with iDoklad API using OAuth2 Client Credentials');
        
        $request_body = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'scope' => 'idoklad_api'
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => http_build_query($request_body),
            'timeout' => 30
        );
        
        $response = wp_remote_request($this->auth_url, $args);
        
        if (is_wp_error($response)) {
            $error_message = 'Authentication request failed: ' . $response->get_error_message();
            $this->log_authentication(null, 0, $error_message);
            throw new Exception($error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Log authentication response
        $this->log_authentication($response_data, $response_code);
        
        if ($response_code !== 200) {
            $error_message = isset($response_data['error_description']) ? $response_data['error_description'] : 'Authentication failed';
            throw new Exception('Authentication failed with status ' . $response_code . ': ' . $error_message);
        }
        
        if (!isset($response_data['access_token'])) {
            throw new Exception('Access token not found in authentication response');
        }
        
        $this->access_token = $response_data['access_token'];
        $this->logger->info('Authentication successful. Access token obtained.');
        
        return $this->access_token;
    }
    
    /**
     * Step 2: (Optional) Create Partner - Following Postman collection exactly
     */
    private function create_partner_if_needed($invoice_data) {
        $this->logger->info('Step 2: (Optional) Creating partner');

        // If no partner data provided, use default partner ID
        if (empty($invoice_data) || !isset($invoice_data['partner_data'])) {
            $this->logger->info('No partner data provided, using default partner ID: 22429105');
            return 22429105;
        }

        $direct_partner_id = $this->extract_direct_partner_id($invoice_data);

        if ($direct_partner_id) {
            $this->logger->info('Partner ID provided in invoice data: ' . $direct_partner_id);
            return $direct_partner_id;
        }

        $partner_data = $invoice_data['partner_data'];

        if ($this->is_partner_data_empty($partner_data)) {
            $this->logger->info('Partner data empty or missing details, using default partner ID: 22429105');
            return 22429105;
        }

        $existing_partner_id = $this->find_existing_partner($partner_data);

        if ($existing_partner_id) {
            $this->logger->info('Using existing partner ID: ' . $existing_partner_id);
            return $existing_partner_id;
        }

        // Build partner payload exactly as in Postman collection
        $partner_payload = array(
            'CompanyName' => $partner_data['company'] ?? 'AUTO TEST COMPANY s.r.o.',
            'Email' => $partner_data['email'] ?? 'autotest+partner@example.com',
            'CountryId' => 1,
            'Street' => $partner_data['address'] ?? 'Test 1',
            'City' => $partner_data['city'] ?? 'Praha',
            'PostalCode' => $partner_data['postal_code'] ?? '11000'
        );
        
        $this->logger->info('Creating partner with payload: ' . json_encode($partner_payload));
        
        $url = $this->base_url . '/Contacts';
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($partner_payload),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->info('Partner creation failed: ' . $error_message, 'error');
            $this->log_partner_creation($partner_payload, null, 0, $error_message);
            return 22429105; // Fallback to default
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (!in_array($response_code, array(200, 201))) {
            $error_message = isset($response_data['Message']) ? $response_data['Message'] : $response_body;
            $this->logger->error('Partner creation failed with status ' . $response_code . ': ' . $error_message);
            $this->log_partner_creation($partner_payload, $response_data, $response_code, $error_message);
            return 22429105; // Fallback to default
        }

        $this->log_partner_creation($partner_payload, $response_data, $response_code);

        // Extract partner ID exactly as in Postman collection
        $partner_id = null;
        $partner_inner = null;

        if (isset($response_data['Data'])) {
            $partner_inner = $response_data['Data'];
        } elseif (isset($response_data['data'])) {
            $partner_inner = $response_data['data'];
        } else {
            $partner_inner = $response_data;
        }

        if (is_array($partner_inner)) {
            if (isset($partner_inner['Id'])) {
                $partner_id = $partner_inner['Id'];
            } elseif (isset($partner_inner['id'])) {
                $partner_id = $partner_inner['id'];
            } elseif (isset($partner_inner[0]['Id'])) {
                $partner_id = $partner_inner[0]['Id'];
            } elseif (isset($partner_inner[0]['id'])) {
                $partner_id = $partner_inner[0]['id'];
            }
        }

        if (!$partner_id && isset($response_data['Id'])) {
            $partner_id = $response_data['Id'];
        } elseif (!$partner_id && isset($response_data['id'])) {
            $partner_id = $response_data['id'];
        }

        if ($partner_id) {
            $this->logger->info('Partner created with ID: ' . $partner_id);
            return $partner_id;
        } else {
            $this->logger->info('Partner creation response did not contain ID, using default', 'warning');
            return 22429105;
        }
    }

    /**
     * Resolve partner ID without creating a new partner. Used for preview flows.
     *
     * @param array $invoice_data Invoice payload prepared for iDoklad.
     * @return array{
     *     partner_id: int|null,
     *     source: string|null,
     *     attempted: bool,
     *     warnings: array<int, string>,
     *     error?: string
     * }
     */
    public function resolve_partner_id_for_preview($invoice_data) {
        $result = array(
            'partner_id' => null,
            'source' => null,
            'attempted' => false,
            'warnings' => array(),
        );

        $direct_partner_id = $this->extract_direct_partner_id($invoice_data);

        if ($direct_partner_id) {
            $result['partner_id'] = $direct_partner_id;
            $result['source'] = 'payload';
            return $result;
        }

        if (empty($invoice_data) || !isset($invoice_data['partner_data']) || $this->is_partner_data_empty($invoice_data['partner_data'])) {
            $result['warnings'][] = 'Partner data missing - unable to perform REST lookup.';
            return $result;
        }

        try {
            $this->authenticate();
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $result['warnings'][] = 'Authentication failed for partner lookup: ' . $error_message;
            $result['error'] = $error_message;
            return $result;
        }

        $result['attempted'] = true;

        try {
            $existing_partner_id = $this->find_existing_partner($invoice_data['partner_data']);

            if ($existing_partner_id) {
                $result['partner_id'] = $existing_partner_id;
                $result['source'] = 'rest_lookup';
            } else {
                $result['warnings'][] = 'REST lookup did not return a matching partner.';
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $result['warnings'][] = 'Partner lookup error: ' . $error_message;
            $result['error'] = $error_message;
        }

        return $result;
    }

    
    /**
     * Step 3: Resolve NumericSequence for issued invoices
     */
    private function resolve_numeric_sequence() {
        $this->logger->info('Step 3: Resolving NumericSequence for issued invoices');
        
        $url = $this->base_url . '/NumericSequences';
        
        $args = array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_numeric_sequence(null, 0, $error_message);
            throw new Exception('NumericSequences request failed: ' . $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($response_code !== 200) {
            $error_message = isset($response_data['Message']) ? $response_data['Message'] : 'Failed to retrieve numeric sequences';
            $this->log_numeric_sequence($response_data, $response_code, $error_message);
            throw new Exception('NumericSequences request failed with status ' . $response_code . ': ' . $error_message);
        }

        $this->log_numeric_sequence($response_data, $response_code);
        
        // Find the NumericSequence following Postman logic
        $numeric_sequence = null;
        $list = array();
        
        // Handle different response structures
        if (isset($response_data['Data']) && isset($response_data['Data']['Items'])) {
            $list = $response_data['Data']['Items'];
        } elseif (isset($response_data['data']) && isset($response_data['data']['items'])) {
            $list = $response_data['data']['items'];
        } elseif (isset($response_data['Items'])) {
            $list = $response_data['Items'];
        } elseif (isset($response_data['items'])) {
            $list = $response_data['items'];
        } elseif (isset($response_data['Data'])) {
            $list = is_array($response_data['Data']) ? $response_data['Data'] : array($response_data['Data']);
        } elseif (isset($response_data['data'])) {
            $list = is_array($response_data['data']) ? $response_data['data'] : array($response_data['data']);
        } else {
            $list = is_array($response_data) ? $response_data : array($response_data);
        }
        
        if (empty($list) || !is_array($list)) {
            throw new Exception('No numeric sequences returned.');
        }
        
        // Find sequence: first try DocumentType = 0 AND IsDefault, then DocumentType = 0, then first available
        foreach ($list as $sequence) {
            if (isset($sequence['DocumentType']) && $sequence['DocumentType'] === 0 && isset($sequence['IsDefault']) && $sequence['IsDefault']) {
                $numeric_sequence = $sequence;
                break;
            }
        }
        
        if (!$numeric_sequence) {
            foreach ($list as $sequence) {
                if (isset($sequence['DocumentType']) && $sequence['DocumentType'] === 0) {
                    $numeric_sequence = $sequence;
                    break;
                }
            }
        }
        
        if (!$numeric_sequence) {
            $numeric_sequence = $list[0]; // Fallback to first available
        }
        
        if (!$numeric_sequence || !isset($numeric_sequence['Id'])) {
            throw new Exception('No IssuedInvoices numeric sequence found.');
        }
        
        $numeric_sequence_id = $numeric_sequence['Id'];
        
        // Handle different field names for last number (following Postman logic)
        $last_number = 0;
        if (isset($numeric_sequence['LastNumber'])) {
            $last_number = intval($numeric_sequence['LastNumber']);
        } elseif (isset($numeric_sequence['LastDocumentSerialNumber'])) {
            $last_number = intval($numeric_sequence['LastDocumentSerialNumber']);
        }
        
        $document_serial_number = $last_number + 1;
        
        $this->logger->info('NumericSequence resolved: ID=' . $numeric_sequence_id . ', LastNumber=' . $last_number . ', NextNumber=' . $document_serial_number);
        
        return array(
            'NumericSequenceId' => $numeric_sequence_id,
            'DocumentSerialNumber' => $document_serial_number,
            'LastNumber' => $last_number
        );
    }
    
    /**
     * Step 4: Create Issued Invoice
     */
    private function create_issued_invoice($numeric_sequence_data, $partner_id, $custom_invoice_data = null) {
        $this->logger->info('Step 4: Creating issued invoice');
        
        // Use custom invoice data if provided, otherwise use default test data
        if ($custom_invoice_data) {
            $invoice_payload = $this->build_custom_invoice_payload($custom_invoice_data, $numeric_sequence_data, $partner_id);
        } else {
            $invoice_payload = $this->build_default_invoice_payload($numeric_sequence_data, $partner_id);
        }
        
        $url = $this->base_url . '/IssuedInvoices';
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($invoice_payload),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = 'IssuedInvoice creation request failed: ' . $response->get_error_message();
            $this->log_invoice_creation($invoice_payload, null, 0, $error_message);
            throw new Exception($error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Log invoice creation response
        $this->log_invoice_creation($invoice_payload, $response_data, $response_code);
        
        if ($response_code !== 200 && $response_code !== 201) {
            $error_message = isset($response_data['Message']) ? $response_data['Message'] : 'Failed to create issued invoice';
            throw new Exception('IssuedInvoice creation failed with status ' . $response_code . ': ' . $error_message);
        }
        
        // Handle different response structures (following Postman logic)
        $invoice_id = null;
        $document_number = null;
        
        if (isset($response_data['Data'])) {
            $invoice_inner = $response_data['Data'];
        } elseif (isset($response_data['data'])) {
            $invoice_inner = $response_data['data'];
        } else {
            $invoice_inner = $response_data;
        }

        if (is_array($invoice_inner)) {
            if (isset($invoice_inner['Id'])) {
                $invoice_id = $invoice_inner['Id'];
            } elseif (isset($invoice_inner['id'])) {
                $invoice_id = $invoice_inner['id'];
            } elseif (isset($invoice_inner[0]['Id'])) {
                $invoice_id = $invoice_inner[0]['Id'];
            } elseif (isset($invoice_inner[0]['id'])) {
                $invoice_id = $invoice_inner[0]['id'];
            }
        }

        if (!$invoice_id && isset($response_data['Id'])) {
            $invoice_id = $response_data['Id'];
        } elseif (!$invoice_id && isset($response_data['id'])) {
            $invoice_id = $response_data['id'];
        }

        if (is_array($invoice_inner)) {
            if (isset($invoice_inner['DocumentNumber'])) {
                $document_number = $invoice_inner['DocumentNumber'];
            } elseif (isset($invoice_inner['documentNumber'])) {
                $document_number = $invoice_inner['documentNumber'];
            } elseif (isset($invoice_inner[0]['DocumentNumber'])) {
                $document_number = $invoice_inner[0]['DocumentNumber'];
            } elseif (isset($invoice_inner[0]['documentNumber'])) {
                $document_number = $invoice_inner[0]['documentNumber'];
            }
        }

        if (!$document_number && isset($response_data['DocumentNumber'])) {
            $document_number = $response_data['DocumentNumber'];
        } elseif (!$document_number && isset($response_data['documentNumber'])) {
            $document_number = $response_data['documentNumber'];
        }
        
        $result = array(
            'success' => true,
            'status_code' => $response_code,
            'invoice_id' => $invoice_id,
            'document_number' => $document_number,
            'response_data' => $response_data
        );
        
        $this->logger->info('Issued invoice created successfully: ID=' . $invoice_id . ', DocumentNumber=' . $document_number);
        
        return $result;
    }
    
    /**
     * Build default invoice payload matching Postman collection exactly
     */
    private function build_default_invoice_payload($numeric_sequence_data, $partner_id) {
        $current_date = '2025-10-22';
        $maturity_date = '2025-11-05';
        $year = date('Y');
        $serial = $numeric_sequence_data['DocumentSerialNumber'];
        $doc_num = $year . str_pad($serial, 4, '0', STR_PAD_LEFT);
        $order_num = 'PO-' . $year . '-' . str_pad($serial, 2, '0', STR_PAD_LEFT);
        
        return array(
            'PartnerId' => $partner_id,
            'Description' => 'Consulting and license (API)',
            'Note' => 'Auto test via Postman',
            'OrderNumber' => $order_num,
            'VariableSymbol' => $doc_num,
            
            'DateOfIssue' => $current_date,
            'DateOfTaxing' => $current_date,
            'DateOfMaturity' => $maturity_date,
            'DateOfAccountingEvent' => $current_date,
            'DateOfVatApplication' => $current_date,
            
            'CurrencyId' => 1,
            'ExchangeRate' => 1.0,
            'ExchangeRateAmount' => 1.0,
            
            'PaymentOptionId' => 1,
            'ConstantSymbolId' => 7,
            
            'NumericSequenceId' => $numeric_sequence_data['NumericSequenceId'],
            'DocumentSerialNumber' => $numeric_sequence_data['DocumentSerialNumber'],
            
            'IsEet' => false,
            'EetResponsibility' => 0,
            'IsIncomeTax' => true,
            'VatOnPayStatus' => 0,
            'VatRegime' => 0,
            'HasVatRegimeOss' => false,
            
            'ItemsTextPrefix' => 'Invoice items:',
            'ItemsTextSuffix' => 'Thanks for your business.',
            
            'Items' => array(
                array(
                    'Name' => 'Consulting service',
                    'Unit' => 'hour',
                    'Amount' => 2.0,
                    'UnitPrice' => 1500.0,
                    'PriceType' => 1,
                    'VatRateType' => 2,
                    'VatRate' => 0.0,
                    'IsTaxMovement' => false,
                    'DiscountPercentage' => 0.0
                )
            ),
            
            'ReportLanguage' => 1
        );
    }
    
    /**
     * Build custom invoice payload from provided data
     */
    private function build_custom_invoice_payload($invoice_data, $numeric_sequence_data, $partner_id) {
        $current_date = date('Y-m-d');
        $maturity_date = isset($invoice_data['maturity_date']) ? $invoice_data['maturity_date'] : date('Y-m-d', strtotime($current_date . ' +14 days'));
        $serial = $numeric_sequence_data['DocumentSerialNumber'];
        $year = date('Y');

        $document_number = $this->get_invoice_value($invoice_data, array('DocumentNumber', 'document_number', 'InvoiceNumber', 'invoice_number'));
        if (empty($document_number)) {
            $document_number = $year . str_pad($serial, 4, '0', STR_PAD_LEFT);
        }

        $order_number = $this->get_invoice_value($invoice_data, array('OrderNumber', 'order_number'));
        if (empty($order_number)) {
            $order_number = 'PO-' . $year . '-' . str_pad($serial, 2, '0', STR_PAD_LEFT);
        }

        $payload = array(
            'PartnerId' => $partner_id,
            'Description' => $this->get_invoice_value($invoice_data, array('Description', 'description'), 'Invoice created via API integration'),
            'Note' => $this->get_invoice_value($invoice_data, array('Note', 'note'), 'Automatic invoice created through API integration'),
            'OrderNumber' => $order_number,
            'VariableSymbol' => $this->get_invoice_value($invoice_data, array('VariableSymbol', 'variable_symbol'), $document_number),

            'DocumentNumber' => $document_number,
            'DateOfIssue' => $this->normalize_invoice_date($this->get_invoice_value($invoice_data, array('DateOfIssue', 'date_of_issue', 'IssueDate', 'date')), $current_date),
            'DateOfTaxing' => $this->normalize_invoice_date($this->get_invoice_value($invoice_data, array('DateOfTaxing', 'date_of_taxing', 'TaxDate')), $current_date),
            'DateOfMaturity' => $this->normalize_invoice_date($this->get_invoice_value($invoice_data, array('DateOfMaturity', 'date_of_maturity', 'DueDate')), $maturity_date),
            'DateOfAccountingEvent' => $this->normalize_invoice_date($this->get_invoice_value($invoice_data, array('DateOfAccountingEvent', 'date_of_accounting_event')), $current_date),
            'DateOfVatApplication' => $this->normalize_invoice_date($this->get_invoice_value($invoice_data, array('DateOfVatApplication', 'date_of_vat_application')), $current_date),

            'CurrencyId' => $this->normalize_currency_id($invoice_data),
            'ExchangeRate' => (float) $this->get_invoice_value($invoice_data, array('ExchangeRate', 'exchange_rate'), 1.0),
            'ExchangeRateAmount' => (float) $this->get_invoice_value($invoice_data, array('ExchangeRateAmount', 'exchange_rate_amount'), 1.0),

            'PaymentOptionId' => (int) $this->get_invoice_value($invoice_data, array('PaymentOptionId', 'payment_option_id'), 1),
            'ConstantSymbolId' => (int) $this->get_invoice_value($invoice_data, array('ConstantSymbolId', 'constant_symbol_id'), 7),

            'NumericSequenceId' => $numeric_sequence_data['NumericSequenceId'],
            'DocumentSerialNumber' => $numeric_sequence_data['DocumentSerialNumber'],

            'IsEet' => (bool) $this->get_invoice_value($invoice_data, array('IsEet', 'is_eet'), false),
            'EetResponsibility' => (int) $this->get_invoice_value($invoice_data, array('EetResponsibility', 'eet_responsibility'), 0),
            'IsIncomeTax' => (bool) $this->get_invoice_value($invoice_data, array('IsIncomeTax', 'is_income_tax'), true),
            'VatOnPayStatus' => (int) $this->get_invoice_value($invoice_data, array('VatOnPayStatus', 'vat_on_pay_status'), 0),
            'VatRegime' => (int) $this->get_invoice_value($invoice_data, array('VatRegime', 'vat_regime'), 0),
            'HasVatRegimeOss' => (bool) $this->get_invoice_value($invoice_data, array('HasVatRegimeOss', 'has_vat_regime_oss'), false),

            'ItemsTextPrefix' => $this->get_invoice_value($invoice_data, array('ItemsTextPrefix', 'items_text_prefix'), 'Invoice items:'),
            'ItemsTextSuffix' => $this->get_invoice_value($invoice_data, array('ItemsTextSuffix', 'items_text_suffix'), 'Thanks for your business.'),

            'Items' => $this->normalize_invoice_items($invoice_data),
            'ReportLanguage' => (int) $this->get_invoice_value($invoice_data, array('ReportLanguage', 'report_language'), 1)
        );

        $payment_status = $this->get_invoice_value($invoice_data, array('PaymentStatus', 'payment_status'));
        if (!$this->is_value_empty($payment_status)) {
            $payload['PaymentStatus'] = (int) $payment_status;
        }

        $date_of_payment = $this->get_invoice_value($invoice_data, array('DateOfPayment', 'date_of_payment'));
        if (!$this->is_value_empty($date_of_payment)) {
            $payload['DateOfPayment'] = $this->normalize_invoice_date($date_of_payment, null);
        }

        return $payload;
    }
    
    /**
     * Build default items array matching Postman collection
     */
    private function build_default_items() {
        return array(
            array(
                'Name' => 'Consulting service',
                'Unit' => 'hour',
                'Amount' => 2.0,
                'UnitPrice' => 1500.0,
                'PriceType' => 1,
                'VatRateType' => 2,
                'VatRate' => 0.0,
                'IsTaxMovement' => false,
                'DiscountPercentage' => 0.0
            )
        );
    }

    private function get_invoice_value($data, $keys, $default = null) {
        if (!is_array($keys)) {
            $keys = array($keys);
        }

        if (!is_array($data)) {
            return $default;
        }

        foreach ($keys as $key) {
            if (isset($data[$key]) && !$this->is_value_empty($data[$key])) {
                return $data[$key];
            }

            $lower_key = strtolower($key);
            foreach ($data as $data_key => $value) {
                if ($this->is_value_empty($value)) {
                    continue;
                }

                if (strtolower($data_key) === $lower_key) {
                    return $value;
                }
            }
        }

        return $default;
    }

    private function normalize_invoice_date($value, $default = null) {
        if ($this->is_value_empty($value)) {
            return $default;
        }

        $formats = array('Y-m-d', 'd.m.Y', 'd/m/Y', 'm/d/Y', 'Y-m-d H:i:s', 'd.m.Y H:i:s');

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date instanceof DateTime) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return $default ?: $value;
    }

    private function normalize_currency_id($invoice_data) {
        $currency_value = $this->get_invoice_value($invoice_data, array('CurrencyId', 'currency_id'));

        if (is_numeric($currency_value)) {
            return (int) $currency_value;
        }

        $currency_code = $this->get_invoice_value($invoice_data, array('Currency', 'currency'));
        $map = array(
            'CZK' => 1,
            'EUR' => 2,
            'USD' => 3,
            'GBP' => 4
        );

        if (!empty($currency_code)) {
            $upper = strtoupper($currency_code);
            if (isset($map[$upper])) {
                return $map[$upper];
            }
        }

        return 1;
    }

    private function normalize_invoice_items($invoice_data) {
        $items = $this->get_invoice_value($invoice_data, array('Items', 'items', 'LineItems', 'line_items'));

        if (!is_array($items) || empty($items)) {
            return $this->build_default_items();
        }

        $normalized = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = $this->get_invoice_value($item, array('Name', 'name', 'Description', 'description'));

            if ($this->is_value_empty($name)) {
                continue;
            }

            $normalized[] = array(
                'Name' => $name,
                'Unit' => $this->get_invoice_value($item, array('Unit', 'unit', 'UnitOfMeasure', 'unit_of_measure'), 'pcs'),
                'Amount' => (float) $this->get_invoice_value($item, array('Amount', 'amount', 'Quantity', 'quantity'), 1.0),
                'UnitPrice' => (float) $this->get_invoice_value($item, array('UnitPrice', 'unit_price', 'Price', 'price'), 0.0),
                'PriceType' => (int) $this->get_invoice_value($item, array('PriceType', 'price_type'), 1),
                'VatRateType' => (int) $this->get_invoice_value($item, array('VatRateType', 'vat_rate_type'), 2),
                'VatRate' => (float) $this->get_invoice_value($item, array('VatRate', 'vat_rate', 'TaxRate', 'tax_rate'), 0.0),
                'IsTaxMovement' => (bool) $this->get_invoice_value($item, array('IsTaxMovement', 'is_tax_movement'), false),
                'DiscountPercentage' => (float) $this->get_invoice_value($item, array('DiscountPercentage', 'discount_percentage'), 0.0)
            );
        }

        return !empty($normalized) ? $normalized : $this->build_default_items();
    }

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
     * Test the complete integration
     */
    public function test_integration() {
        try {
            $this->logger->info('Testing complete iDoklad API v3 integration');
            
            $result = $this->create_invoice_complete_workflow();
            
            $this->logger->info('Integration test completed successfully');
            $this->logger->info('Invoice ID: ' . $result['invoice_id']);
            $this->logger->info('Document Number: ' . $result['document_number']);
            $this->logger->info('Status Code: ' . $result['status_code']);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Integration test failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get access token (for external use)
     */
    public function get_access_token() {
        if (empty($this->access_token)) {
            $this->authenticate();
        }
        return $this->access_token;
    }
    
    /**
     * Make authenticated API request
     */
    public function make_authenticated_request($endpoint, $method = 'GET', $data = null) {
        if (empty($this->access_token)) {
            $this->authenticate();
        }
        
        $url = $this->base_url . '/' . ltrim($endpoint, '/');
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        return array(
            'status_code' => $response_code,
            'data' => $response_data,
            'raw_body' => $response_body
        );
    }

    /**
     * Extract partner ID directly supplied within invoice data structure
     */
    private function extract_direct_partner_id($invoice_data) {
        if (empty($invoice_data) || !is_array($invoice_data)) {
            return null;
        }

        $candidate_values = array();

        if (isset($invoice_data['partner_data']) && is_array($invoice_data['partner_data'])) {
            $partner_candidates = array('id', 'Id', 'ID', 'partner_id', 'PartnerId', 'PartnerID', 'contact_id', 'ContactId', 'ContactID');

            foreach ($partner_candidates as $key) {
                if (isset($invoice_data['partner_data'][$key])) {
                    $candidate_values[] = $invoice_data['partner_data'][$key];
                }
            }
        }

        $top_level_candidates = array('PartnerId', 'partner_id', 'ContactId', 'contact_id');

        foreach ($top_level_candidates as $key) {
            if (isset($invoice_data[$key])) {
                $candidate_values[] = $invoice_data[$key];
            }
        }

        foreach ($candidate_values as $value) {
            $normalized = $this->normalize_partner_id($value);
            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * Attempt to find an existing partner before creating a new one
     */
    private function find_existing_partner($partner_data) {
        if (empty($partner_data) || !is_array($partner_data)) {
            return null;
        }

        $lookup_fields = array();

        if (!empty($partner_data['email'])) {
            $lookup_fields[] = array('field' => 'Email', 'value' => $partner_data['email']);
        }

        if (!empty($partner_data['company'])) {
            $lookup_fields[] = array('field' => 'CompanyName', 'value' => $partner_data['company']);
        }

        if (empty($lookup_fields)) {
            return null;
        }

        foreach ($lookup_fields as $criteria) {
            $value = is_string($criteria['value']) ? trim($criteria['value']) : $criteria['value'];

            if (empty($value)) {
                continue;
            }

            $filter_value = $criteria['field'] . '~eq~' . $value;
            $query = http_build_query(array('filter' => $filter_value));
            $url = $this->base_url . '/Contacts?' . $query;

            $this->logger->info('Searching for existing partner using ' . strtolower($criteria['field']) . ': ' . $value);

            $args = array(
                'method' => 'GET',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30
            );

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->log_partner_lookup($criteria, null, 0, $error_message);
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            $this->log_partner_lookup($criteria, $response_data, $status_code);

            if ($status_code !== 200 || empty($response_data)) {
                continue;
            }

            $records = $this->gather_partner_records_with_ids($response_data);

            if (empty($records)) {
                continue;
            }

            $normalized_lookup_value = $criteria['field'] === 'Email'
                ? $this->normalize_email($value)
                : strtolower($value);

            foreach ($records as $record) {
                $partner_id = $this->extract_partner_id_from_record($record);

                if (!$partner_id) {
                    continue;
                }

                if ($criteria['field'] === 'Email') {
                    $record_email = $this->normalize_email($record['Email'] ?? $record['email'] ?? null);
                    if ($record_email && $record_email === $normalized_lookup_value) {
                        return $partner_id;
                    }
                } else {
                    $record_company = strtolower(trim($record['CompanyName'] ?? $record['companyName'] ?? $record['Company'] ?? ''));
                    if (!empty($record_company) && $record_company === $normalized_lookup_value) {
                        return $partner_id;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Determine if partner data contains any usable information
     */
    private function is_partner_data_empty($partner_data) {
        if (empty($partner_data) || !is_array($partner_data)) {
            return true;
        }

        foreach ($partner_data as $value) {
            if (is_array($value)) {
                if (!$this->is_partner_data_empty($value)) {
                    return false;
                }
                continue;
            }

            if (is_string($value)) {
                if (trim($value) !== '') {
                    return false;
                }
                continue;
            }

            if (is_numeric($value) && intval($value) > 0) {
                return false;
            }

            if (!empty($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize partner ID values
     */
    private function normalize_partner_id($value) {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $int_value = intval($value);

        return $int_value > 0 ? $int_value : null;
    }

    /**
     * Normalize email for comparison
     */
    private function normalize_email($email) {
        if (empty($email) || !is_string($email)) {
            return null;
        }

        return strtolower(trim($email));
    }

    /**
     * Extract unique partner records containing IDs from response data
     */
    private function gather_partner_records_with_ids($data) {
        if (!is_array($data)) {
            return array();
        }

        $records = array();
        $stack = array($data);

        while (!empty($stack)) {
            $current = array_pop($stack);

            if (!is_array($current)) {
                continue;
            }

            if ($this->extract_partner_id_from_record($current)) {
                $records[] = $current;
            }

            foreach ($current as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        $unique_records = array();
        $seen_ids = array();

        foreach ($records as $record) {
            $partner_id = $this->extract_partner_id_from_record($record);

            if ($partner_id && !isset($seen_ids[$partner_id])) {
                $seen_ids[$partner_id] = true;
                $unique_records[] = $record;
            }
        }

        return $unique_records;
    }

    /**
     * Extract partner ID from a record structure
     */
    private function extract_partner_id_from_record($record) {
        if (!is_array($record)) {
            return null;
        }

        $candidate_keys = array('Id', 'id', 'ID', 'PartnerId', 'partner_id', 'PartnerID', 'ContactId', 'contact_id', 'ContactID');

        foreach ($candidate_keys as $key) {
            if (isset($record[$key])) {
                $normalized = $this->normalize_partner_id($record[$key]);
                if ($normalized) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    /**
     * Log iDoklad API response to database
     */
    private function log_api_response($operation, $request_data, $response_data, $status_code, $error_message = null) {
        try {
            $log_data = array(
                'email_from' => 'system@idoklad-integration',
                'email_subject' => 'iDoklad API ' . ucfirst($operation),
                'attachment_name' => 'API Request',
                'processing_status' => $status_code >= 200 && $status_code < 300 ? 'success' : 'failed',
                'extracted_data' => array(
                    'operation' => $operation,
                    'request_data' => $request_data,
                    'timestamp' => current_time('mysql')
                ),
                'api_response' => array(
                    'status_code' => $status_code,
                    'response_data' => $response_data,
                    'error_message' => $error_message,
                    'timestamp' => current_time('mysql')
                ),
                'error_message' => $error_message
            );
            
            $this->database->add_log($log_data);
            
        } catch (Exception $e) {
            $this->logger->info('Failed to log API response: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Log invoice creation with detailed response data
     */
    public function log_invoice_creation($invoice_data, $response_data, $status_code, $error_message = null) {
        $this->log_api_response('invoice_creation', $invoice_data, $response_data, $status_code, $error_message);
    }

    /**
     * Log partner lookup attempts with detailed response data
     */
    public function log_partner_lookup($criteria, $response_data, $status_code, $error_message = null) {
        $request_data = array(
            'lookup_field' => $criteria['field'] ?? '',
            'lookup_value' => $criteria['value'] ?? ''
        );

        $this->log_api_response('partner_lookup', $request_data, $response_data, $status_code, $error_message);
    }

    /**
     * Log partner creation with detailed response data
     */
    public function log_partner_creation($partner_data, $response_data, $status_code, $error_message = null) {
        $this->log_api_response('partner_creation', $partner_data, $response_data, $status_code, $error_message);
    }
    
    /**
     * Log authentication with response data
     */
    public function log_authentication($response_data, $status_code, $error_message = null) {
        $this->log_api_response('authentication', array('client_id' => substr($this->client_id, 0, 8) . '...'), $response_data, $status_code, $error_message);
    }
    
    /**
     * Log numeric sequence retrieval with response data
     */
    public function log_numeric_sequence($response_data, $status_code, $error_message = null) {
        $this->log_api_response('numeric_sequence', array(), $response_data, $status_code, $error_message);
    }
}

<?php
/**
 * iDoklad API integration rebuilt from scratch to follow the exact
 * authentication and invoice-creation flow required by the live API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_IDokladAPI {

    /** @var string */
    private $client_id;

    /** @var string */
    private $client_secret;

    /** @var string|null */
    private $access_token;

    /** @var int|null */
    private $token_expires_at;

    /** @var int */
    private $http_timeout = 30;

    /** @var string */
    private $identity_url = 'https://identity.idoklad.cz/server/connect/token';

    /** @var string */
    private $api_base_url = 'https://api.idoklad.cz/v3';

    /**
     * Constructor accepts optional user credentials; falls back to global
     * WordPress options when executed within the plugin.
     *
     * @param object|null $user_credentials
     */
    public function __construct($user_credentials = null) {
        if ($user_credentials) {
            $this->client_id = $user_credentials->idoklad_client_id ?? '';
            $this->client_secret = $user_credentials->idoklad_client_secret ?? '';
        } else {
            if (function_exists('get_option')) {
                $this->client_id = (string) get_option('idoklad_client_id', '');
                $this->client_secret = (string) get_option('idoklad_client_secret', '');
                $timeout = (int) get_option('idoklad_http_timeout', $this->http_timeout);
                if ($timeout > 0) {
                    $this->http_timeout = $timeout;
                }
            }
        }
    }

    /**
     * Public alias maintained for backwards compatibility. The received
     * context array may include PartnerId or partner details; all other data is
     * ignored so the payload matches the mandated structure.
     *
     * @param array $context
     * @return array
     * @throws Exception
     */
    public function create_invoice(array $context = array()) {
        return $this->create_issued_invoice($context);
    }

    /**
     * Alias preserved for diagnostic tooling. Returns the same structure as
     * create_invoice().
     *
     * @param array $context
     * @return array
     * @throws Exception
     */
    public function create_invoice_with_response(array $context = array()) {
        return $this->create_issued_invoice($context);
    }

    /**
     * Execute the full integration flow required by iDoklad:
     * 1. Authenticate via OAuth 2.0 client credentials
     * 2. Optionally create a partner/contact if PartnerId is not available
     * 3. Resolve the numeric sequence for issued invoices and compute the next
     *    document serial number
     * 4. Create the issued invoice using the exact payload mandated by the
     *    specification, dynamically injecting the numeric sequence ID and the
     *    computed serial number.
     *
     * @param array $context Optional data used to resolve PartnerId or create a
     *                       new partner.
     * @return array Structured response containing the HTTP status code, raw
     *               body, parsed data and message where available.
     * @throws Exception When authentication or any API call fails.
     */
    public function create_issued_invoice(array $context = array()) {
        $this->ensure_credentials();
        $this->ensure_access_token();

        $partner_id = $this->resolve_partner_id($context);
        $sequence = $this->resolve_numeric_sequence();

        $payload = $this->build_issued_invoice_payload(
            $partner_id,
            $sequence['NumericSequenceId'],
            $sequence['DocumentSerialNumber']
        );

        $this->log('Creating issued invoice', array(
            'partner_id' => $partner_id,
            'numeric_sequence_id' => $sequence['NumericSequenceId'],
            'document_serial_number' => $sequence['DocumentSerialNumber'],
        ));

        $response = $this->send_json_request('POST', $this->api_base_url . '/IssuedInvoices', $payload);
        $decoded = $response['json'];

        if ($response['status_code'] >= 400) {
            $message = $this->extract_error_message($decoded, $response['body']);
            $this->log('Issued invoice creation failed', array(
                'status_code' => $response['status_code'],
                'message' => $message,
                'payload' => $payload,
                'response' => $decoded,
            ));

            throw new Exception($message ?: 'Failed to create issued invoice', $response['status_code']);
        }

        $data = is_array($decoded) && isset($decoded['Data']) ? $decoded['Data'] : $decoded;
        $message = is_array($decoded) && isset($decoded['Message']) ? $decoded['Message'] : null;

        $result = array(
            'StatusCode' => $response['status_code'],
            'Data' => $data,
            'RawResponse' => $decoded,
        );

        if ($message !== null) {
            $result['Message'] = $message;
        }

        $this->log('Issued invoice created', array(
            'status_code' => $response['status_code'],
            'invoice_id' => $data['Id'] ?? null,
            'document_number' => $data['DocumentNumber'] ?? null,
        ));

        return $result;
    }

    /**
     * Simple diagnostic helper used in the admin UI to verify credentials.
     *
     * @return array{success:bool,message:string}
     */
    public function test_connection() {
        try {
            $this->ensure_credentials();
            $this->fetch_access_token(true);

            return array(
                'success' => true,
                'message' => 'OAuth connection successful. Access token obtained.',
            );
        } catch (Exception $exception) {
            return array(
                'success' => false,
                'message' => $exception->getMessage(),
            );
        }
    }

    /**
     * Legacy hook maintained for compatibility with the email monitor. The new
     * integration no longer mirrors contact notes in iDoklad, so this method
     * simply records structured information to the debug log when enabled.
     *
     * @param array $email_meta
     * @param array $attachments
     * @param string $status
     * @return void
     */
    public function record_email_activity(array $email_meta, array $attachments = array(), $status = 'received') {
        $context = array(
            'status' => $status,
            'email' => $email_meta['from'] ?? ($email_meta['email_from'] ?? null),
            'subject' => $email_meta['subject'] ?? null,
            'message_id' => $email_meta['email_id'] ?? ($email_meta['id'] ?? null),
            'attachments' => array_map(function ($attachment) {
                return $attachment['name'] ?? ($attachment['filename'] ?? 'file');
            }, $attachments),
        );

        $this->log('Email activity recorded locally', $context);
    }

    /**
     * Ensure the class has credentials to operate with.
     *
     * @return void
     * @throws Exception
     */
    private function ensure_credentials() {
        if (empty($this->client_id) || empty($this->client_secret)) {
            throw new Exception('iDoklad client credentials are missing.');
        }
    }

    /**
     * Reuse cached token where possible, otherwise obtain a new one.
     *
     * @return void
     * @throws Exception
     */
    private function ensure_access_token() {
        if ($this->access_token && $this->token_expires_at && time() < ($this->token_expires_at - 30)) {
            return;
        }

        $this->fetch_access_token(false);
    }

    /**
     * Perform the OAuth 2.0 client credentials flow.
     *
     * @param bool $force_refresh When true a new token is requested even if one
     *                            is cached.
     * @return void
     * @throws Exception
     */
    private function fetch_access_token($force_refresh = false) {
        if (!$force_refresh && $this->access_token && $this->token_expires_at && time() < ($this->token_expires_at - 30)) {
            return;
        }

        $body = http_build_query(array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'scope' => 'idoklad_api',
        ));

        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        );

        $response = $this->http_request('POST', $this->identity_url, $headers, $body);
        $decoded = json_decode($response['body'], true);

        if ($decoded === null) {
            throw new Exception('Unable to decode authentication response from iDoklad.');
        }

        if ($response['status_code'] >= 400) {
            $message = $this->extract_error_message($decoded, $response['body']);
            throw new Exception($message ?: 'Failed to authenticate with iDoklad.', $response['status_code']);
        }

        if (empty($decoded['access_token'])) {
            throw new Exception('Authentication response did not contain an access token.');
        }

        $this->access_token = $decoded['access_token'];
        $expires_in = isset($decoded['expires_in']) ? (int) $decoded['expires_in'] : 3600;
        $this->token_expires_at = time() + max(30, $expires_in);
    }

    /**
     * Decide which PartnerId to use for the invoice. If none is supplied, create
     * a new partner from the provided context. When insufficient data is
     * available, fall back to the sample PartnerId specified in the payload.
     *
     * @param array $context
     * @return int
     */
    private function resolve_partner_id(array $context) {
        $candidate = null;

        foreach (array('PartnerId', 'partner_id', 'partnerId') as $key) {
            if (isset($context[$key]) && $context[$key]) {
                $candidate = (int) $context[$key];
                break;
            }
        }

        if ($candidate) {
            return $candidate;
        }

        $partner_payload = $this->extract_partner_payload($context);

        if (!empty($partner_payload)) {
            try {
                return $this->create_partner($partner_payload);
            } catch (Exception $exception) {
                $this->log('Partner creation failed, falling back to default PartnerId', array(
                    'error' => $exception->getMessage(),
                ));
            }
        }

        return 22429105;
    }

    /**
     * Build partner payload if sufficient context is available.
     *
     * @param array $context
     * @return array<string,mixed>
     */
    private function extract_partner_payload(array $context) {
        $source = array();
        if (isset($context['Partner']) && is_array($context['Partner'])) {
            $source = $context['Partner'];
        }

        $company_name = trim($source['CompanyName'] ?? ($context['PartnerName'] ?? ($context['supplier_name'] ?? '')));
        $email = trim($source['Email'] ?? ($context['PartnerEmail'] ?? ($context['email'] ?? '')));
        $street = trim($source['Street'] ?? ($context['Street'] ?? ''));
        $city = trim($source['City'] ?? ($context['City'] ?? ''));
        $postal_code = trim($source['PostalCode'] ?? ($context['PostalCode'] ?? ''));
        $country_id = isset($source['CountryId']) ? (int) $source['CountryId'] : (isset($context['CountryId']) ? (int) $context['CountryId'] : 1);

        if ($company_name === '') {
            return array();
        }

        $payload = array(
            'CompanyName' => $company_name,
            'CountryId' => $country_id ?: 1,
        );

        if ($email !== '') {
            $payload['Email'] = $email;
        }

        if ($street !== '') {
            $payload['Street'] = $street;
        }

        if ($city !== '') {
            $payload['City'] = $city;
        }

        if ($postal_code !== '') {
            $payload['PostalCode'] = $postal_code;
        }

        return $payload;
    }

    /**
     * Create a partner/contact using the API and return its identifier.
     *
     * @param array<string,mixed> $payload
     * @return int
     * @throws Exception
     */
    private function create_partner(array $payload) {
        $this->ensure_access_token();

        $response = $this->send_json_request('POST', $this->api_base_url . '/Contacts', $payload);
        $decoded = $response['json'];

        if ($response['status_code'] >= 400) {
            $message = $this->extract_error_message($decoded, $response['body']);
            throw new Exception($message ?: 'Failed to create partner in iDoklad.', $response['status_code']);
        }

        $data = is_array($decoded) && isset($decoded['Data']) ? $decoded['Data'] : $decoded;

        if (!isset($data['Id'])) {
            throw new Exception('Contact creation response did not include an Id.');
        }

        $this->log('Partner created', array('partner_id' => $data['Id']));

        return (int) $data['Id'];
    }

    /**
     * Retrieve the numeric sequence for issued invoices and determine the next
     * serial number.
     *
     * @return array{NumericSequenceId:int,DocumentSerialNumber:int}
     * @throws Exception
     */
    private function resolve_numeric_sequence() {
        $this->ensure_access_token();

        $response = $this->send_json_request('GET', $this->api_base_url . '/NumericSequences');
        $decoded = $response['json'];

        if ($response['status_code'] >= 400) {
            $message = $this->extract_error_message($decoded, $response['body']);
            throw new Exception($message ?: 'Failed to resolve numeric sequences.', $response['status_code']);
        }

        $sequences = array();
        if (isset($decoded['Data']) && is_array($decoded['Data'])) {
            $sequences = $decoded['Data'];
        } elseif (is_array($decoded)) {
            $sequences = $decoded;
        }

        foreach ($sequences as $sequence) {
            if (isset($sequence['DocumentType']) && (int) $sequence['DocumentType'] === 0) {
                $numeric_sequence_id = (int) ($sequence['Id'] ?? 0);
                $last_number = isset($sequence['LastNumber']) ? (int) $sequence['LastNumber'] : 0;

                if ($numeric_sequence_id <= 0) {
                    break;
                }

                return array(
                    'NumericSequenceId' => $numeric_sequence_id,
                    'DocumentSerialNumber' => $last_number + 1,
                );
            }
        }

        throw new Exception('Issued invoice numeric sequence could not be resolved.');
    }

    /**
     * Build the issued invoice payload defined by the specification while
     * injecting dynamic numbering details.
     *
     * @param int $partner_id
     * @param int $numeric_sequence_id
     * @param int $document_serial_number
     * @return array<string,mixed>
     */
    private function build_issued_invoice_payload($partner_id, $numeric_sequence_id, $document_serial_number) {
        return array(
            'PartnerId' => $partner_id,
            'Description' => 'Consulting and license services (API integration test)',
            'Note' => 'Automatic test invoice created through API integration',
            'OrderNumber' => 'PO-20251023-01',
            'VariableSymbol' => '20250001',
            'DateOfIssue' => '2025-10-23',
            'DateOfTaxing' => '2025-10-23',
            'DateOfMaturity' => '2025-11-06',
            'DateOfAccountingEvent' => '2025-10-23',
            'DateOfVatApplication' => '2025-10-23',
            'CurrencyId' => 1,
            'ExchangeRate' => 1.0,
            'ExchangeRateAmount' => 1.0,
            'PaymentOptionId' => 1,
            'ConstantSymbolId' => 7,
            'NumericSequenceId' => $numeric_sequence_id,
            'DocumentSerialNumber' => $document_serial_number,
            'IsEet' => false,
            'EetResponsibility' => 0,
            'IsIncomeTax' => true,
            'VatOnPayStatus' => 0,
            'VatRegime' => 0,
            'HasVatRegimeOss' => false,
            'ItemsTextPrefix' => 'Invoice items:',
            'ItemsTextSuffix' => 'Thank you for your cooperation.',
            'Items' => array(
                array(
                    'Name' => 'Consulting service',
                    'Description' => 'Consulting work for October',
                    'Code' => 'CONSULT001',
                    'ItemType' => 0,
                    'Unit' => 'hour',
                    'Amount' => 2.0,
                    'UnitPrice' => 1500.0,
                    'PriceType' => 1,
                    'VatRateType' => 2,
                    'VatRate' => 0.0,
                    'IsTaxMovement' => false,
                    'DiscountPercentage' => 0.0,
                ),
                array(
                    'Name' => 'Software license fee',
                    'Description' => 'Monthly license',
                    'Code' => 'LIC001',
                    'ItemType' => 0,
                    'Unit' => 'pcs',
                    'Amount' => 1.0,
                    'UnitPrice' => 499.0,
                    'PriceType' => 1,
                    'VatRateType' => 2,
                    'VatRate' => 0.0,
                    'IsTaxMovement' => false,
                    'DiscountPercentage' => 0.0,
                ),
            ),
            'ReportLanguage' => 1,
        );
    }

    /**
     * Perform an HTTP request and return the raw response.
     *
     * @param string $method
     * @param string $url
     * @param array<string,string> $headers
     * @param string|null $body
     * @return array{status_code:int,body:string,headers:array}
     * @throws Exception
     */
    private function http_request($method, $url, array $headers = array(), $body = null) {
        $method = strtoupper($method);

        if (function_exists('wp_remote_request')) {
            $args = array(
                'method' => $method,
                'timeout' => $this->http_timeout,
                'headers' => $headers,
                'body' => $body,
            );

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                throw new Exception('HTTP request to iDoklad failed: ' . $response->get_error_message());
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);
            $response_body = (string) wp_remote_retrieve_body($response);
            $response_headers = wp_remote_retrieve_headers($response);
            $response_headers = is_array($response_headers) ? $response_headers : (array) $response_headers;

            return array(
                'status_code' => $status_code,
                'body' => $response_body,
                'headers' => $response_headers,
            );
        }

        if (!function_exists('curl_init')) {
            throw new Exception('Neither WordPress HTTP API nor cURL is available.');
        }

        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->http_timeout);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $formatted_headers = array();
        foreach ($headers as $header => $value) {
            $formatted_headers[] = $header . ': ' . $value;
        }

        if (!empty($formatted_headers)) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, $formatted_headers);
        }

        $response_body = curl_exec($handle);

        if ($response_body === false) {
            $error_message = curl_error($handle);
            curl_close($handle);
            throw new Exception('cURL error while calling iDoklad: ' . $error_message);
        }

        $status_code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        return array(
            'status_code' => $status_code,
            'body' => (string) $response_body,
            'headers' => array(),
        );
    }

    /**
     * Send a JSON request with the current access token attached.
     *
     * @param string $method
     * @param string $url
     * @param array|null $payload
     * @return array{status_code:int,body:string,headers:array,json:array}
     * @throws Exception
     */
    private function send_json_request($method, $url, ?array $payload = null) {
        $headers = array(
            'Accept' => 'application/json',
        );

        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            $body = $this->encode_json($payload);
        } else {
            $body = null;
        }

        if ($this->access_token) {
            $headers['Authorization'] = 'Bearer ' . $this->access_token;
        }

        $response = $this->http_request($method, $url, $headers, $body);
        $decoded = array();

        if ($response['body'] !== '') {
            $decoded = json_decode($response['body'], true);

            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to decode JSON response from iDoklad: ' . json_last_error_msg());
            }
        }

        $response['json'] = $decoded;

        return $response;
    }

    /**
     * Encode payload as JSON while preserving floats.
     *
     * @param array $payload
     * @return string
     * @throws Exception
     */
    private function encode_json(array $payload) {
        if (function_exists('wp_json_encode')) {
            $encoded = wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
        } else {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        if ($encoded === false) {
            throw new Exception('Failed to encode JSON payload: ' . json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * Extract a human-friendly error message from an API response.
     *
     * @param array|null $decoded
     * @param string $raw_body
     * @return string|null
     */
    private function extract_error_message($decoded, $raw_body) {
        if (is_array($decoded)) {
            if (!empty($decoded['Message']) && is_string($decoded['Message'])) {
                return $decoded['Message'];
            }

            if (!empty($decoded['error_description']) && is_string($decoded['error_description'])) {
                return $decoded['error_description'];
            }

            if (!empty($decoded['error']) && is_string($decoded['error'])) {
                return $decoded['error'];
            }
        }

        return $raw_body ?: null;
    }

    /**
     * Centralised logging helper.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    private function log($message, array $context = array()) {
        $should_log = true;

        if (function_exists('get_option')) {
            $should_log = (bool) get_option('idoklad_debug_mode');
        }

        if (!$should_log) {
            return;
        }

        $output = 'iDoklad API: ' . $message;

        if (!empty($context)) {
            $encoded = function_exists('wp_json_encode') ? wp_json_encode($context, JSON_UNESCAPED_UNICODE) : json_encode($context, JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $output .= ' ' . $encoded;
            }
        }

        error_log($output);
    }
}

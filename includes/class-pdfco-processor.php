<?php
/**
 * PDF.co processor used for extracting text from invoices.
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_PDFCoProcessor {

    const API_BASE_URL = 'https://api.pdf.co/v1';

    private $api_key;

    public function __construct() {
        $this->api_key = trim((string) get_option('idoklad_pdfco_api_key'));
    }

    /**
     * Determine if PDF.co processing is enabled and configured.
     */
    public function is_enabled() {
        $flag = get_option('idoklad_use_pdfco', true);

        if (is_string($flag)) {
            $flag = in_array(strtolower($flag), array('1', 'true', 'yes', 'on'), true);
        }

        return !empty($this->api_key) && (bool) $flag;
    }

    /**
     * Extract raw text from a PDF using PDF.co.
     */
    public function extract_text($pdf_path, $context = array()) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found: ' . $pdf_path);
        }

        if (!$this->is_enabled()) {
            throw new Exception(__('PDF.co integration is disabled or the API key is missing.', 'idoklad-invoice-processor'));
        }

        $file_contents = file_get_contents($pdf_path);
        if ($file_contents === false) {
            throw new Exception('Unable to read PDF file: ' . $pdf_path);
        }

        $request_body = array(
            'async' => false,
            'inline' => true,
            'encrypt' => false,
            'name' => basename($pdf_path),
            'file' => base64_encode($file_contents),
        );

        if (get_option('idoklad_debug_mode')) {
            $debug_context = $context;
            $debug_context['pdf_name'] = $request_body['name'];
            $debug_context['pdf_size'] = strlen($file_contents);
            error_log('iDoklad PDF.co: Submitting text extraction request - ' . json_encode($debug_context));
        }

        $response_data = $this->request('/pdf/convert/to/text', $request_body);
        $text = $this->extract_text_from_response($response_data);

        if (empty($text)) {
            throw new Exception(__('PDF.co returned an empty response when extracting text.', 'idoklad-invoice-processor'));
        }

        return $text;
    }

    /**
     * Return basic metadata about the PDF file and processing backend.
     */
    public function get_metadata($pdf_path) {
        $metadata = array(
            'method' => 'PDF.co Cloud Parser',
            'enabled' => $this->is_enabled(),
        );

        if (file_exists($pdf_path)) {
            $metadata['file_name'] = basename($pdf_path);
            $metadata['file_size'] = filesize($pdf_path);
            $metadata['last_modified'] = filemtime($pdf_path);
        }

        return $metadata;
    }

    /**
     * Estimate the number of pages in the PDF. Used only for diagnostics.
     */
    public function get_page_count($pdf_path) {
        if (!file_exists($pdf_path)) {
            return null;
        }

        $contents = @file_get_contents($pdf_path);
        if ($contents === false) {
            return null;
        }

        if (preg_match_all('/\/Type\s*\/Page\b/', $contents, $matches)) {
            return count($matches[0]);
        }

        return null;
    }

    /**
     * Perform a lightweight connectivity check with PDF.co.
     */
    public function test_connection() {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('PDF.co is disabled or API key missing.', 'idoklad-invoice-processor'),
            );
        }

        try {
            $response = $this->request('/pdf/info', array('url' => 'https://bytescout-com.s3.amazonaws.com/files/demo-files/pdfco/sample.pdf'));
            return array(
                'success' => true,
                'message' => __('Connection successful! PDF.co API is reachable.', 'idoklad-invoice-processor'),
                'data' => $response,
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }

    private function extract_text_from_response($response_data) {
        if (isset($response_data['body']) && is_string($response_data['body'])) {
            return $response_data['body'];
        }

        if (isset($response_data['text']) && is_string($response_data['text'])) {
            return $response_data['text'];
        }

        if (!empty($response_data['url'])) {
            $download = wp_remote_get($response_data['url'], array('timeout' => 60));

            if (is_wp_error($download)) {
                throw new Exception('Failed to download PDF.co result: ' . $download->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($download);
            $body = wp_remote_retrieve_body($download);

            if ($code < 200 || $code >= 300) {
                throw new Exception('PDF.co result download failed with status ' . $code);
            }

            return $body;
        }

        return '';
    }

    private function request($path, $payload) {
        if (empty($this->api_key)) {
            throw new Exception(__('PDF.co API key is not configured.', 'idoklad-invoice-processor'));
        }

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 90,
        );

        $url = self::API_BASE_URL . $path;
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('PDF.co request failed: ' . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            $error_message = $this->extract_error_message($body);
            throw new Exception(sprintf(__('PDF.co request failed (%1$d): %2$s', 'idoklad-invoice-processor'), $status, $error_message));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new Exception('Invalid JSON response from PDF.co.');
        }

        if (isset($decoded['error']) && $decoded['error']) {
            $message = isset($decoded['message']) ? $decoded['message'] : __('Unknown PDF.co API error.', 'idoklad-invoice-processor');
            throw new Exception($message);
        }

        return $decoded;
    }

    private function extract_error_message($body) {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (!empty($decoded['message'])) {
                return $decoded['message'];
            }

            if (!empty($decoded['error'])) {
                if (is_array($decoded['error']) && !empty($decoded['error']['message'])) {
                    return $decoded['error']['message'];
                }

                if (is_string($decoded['error'])) {
                    return $decoded['error'];
                }
            }
        }

        return __('Unknown PDF.co API error.', 'idoklad-invoice-processor');
    }
}

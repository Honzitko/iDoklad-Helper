<?php
/**
 * Lightweight PDF.co integration for extracting text from invoices.
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_PDFCoProcessor {

    private $api_key;
    private $api_url = 'https://api.pdf.co/v1';
    private $logger;

    public function __construct() {
        $this->api_key = get_option('idoklad_pdfco_api_key');
        $this->logger = class_exists('IDokladProcessor_Logger') ? IDokladProcessor_Logger::get_instance() : null;
    }

    public function extract_text($pdf_path, $context = array()) {
        if (empty($this->api_key)) {
            throw new Exception(__('PDF.co API key is not configured.', 'idoklad-invoice-processor'));
        }

        if (empty($pdf_path) || !file_exists($pdf_path)) {
            throw new Exception(__('PDF file not found for PDF.co extraction.', 'idoklad-invoice-processor'));
        }

        $contents = file_get_contents($pdf_path);
        if ($contents === false) {
            throw new Exception(__('Unable to read PDF file for PDF.co extraction.', 'idoklad-invoice-processor'));
        }

        $payload = array(
            'file' => base64_encode($contents),
            'name' => basename($pdf_path),
        );

        return $this->send_text_request($payload, $context);
    }

    public function extract_text_from_url($pdf_url, $context = array()) {
        if (empty($this->api_key)) {
            throw new Exception(__('PDF.co API key is not configured.', 'idoklad-invoice-processor'));
        }

        if (empty($pdf_url)) {
            throw new Exception(__('PDF URL is missing for PDF.co extraction.', 'idoklad-invoice-processor'));
        }

        $payload = array(
            'url' => $pdf_url,
        );

        return $this->send_text_request($payload, $context);
    }

    private function send_text_request($payload, $context = array()) {
        $endpoint = $this->api_url . '/pdf/convert/to/text';

        $request_body = array_merge(array(
            'async' => false,
            'inline' => true,
            'encrypt' => false,
            'pages' => '',
        ), $payload);

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
            ),
            'body' => wp_json_encode($request_body),
            'timeout' => 60,
        );

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            $this->log_error('PDF.co request failed: ' . $response->get_error_message(), $context);
            throw new Exception(__('PDF.co request failed.', 'idoklad-invoice-processor') . ' ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $message = isset($data['message']) ? $data['message'] : $body;
            $this->log_error(sprintf('PDF.co API error (%d): %s', $code, $message), $context);
            throw new Exception(sprintf(__('PDF.co API error (%d): %s', 'idoklad-invoice-processor'), $code, $message));
        }

        if (!$data || !empty($data['error'])) {
            $message = isset($data['message']) ? $data['message'] : __('Unknown PDF.co error', 'idoklad-invoice-processor');
            $this->log_error('PDF.co error: ' . $message, $context);
            throw new Exception(__('PDF.co failed to extract text: ', 'idoklad-invoice-processor') . $message);
        }

        if (isset($data['body']) && is_string($data['body'])) {
            $text = $data['body'];
        } elseif (isset($data['text']) && is_string($data['text'])) {
            $text = $data['text'];
        } else {
            $this->log_error('PDF.co response missing text body.', $context, $data);
            throw new Exception(__('PDF.co response did not include extracted text.', 'idoklad-invoice-processor'));
        }

        $this->log_info('PDF.co extraction successful', array_merge($context, array(
            'text_length' => strlen($text),
        )));

        return $text;
    }

    private function log_error($message, $context = array(), $data = null) {
        if (!$this->logger) {
            return;
        }

        $this->logger->error($message, array_merge($context ?: array(), array(
            'pdfco_response' => $data,
        )));
    }

    private function log_info($message, $context = array()) {
        if (!$this->logger) {
            return;
        }

        $this->logger->info($message, $context ?: array());
    }
}

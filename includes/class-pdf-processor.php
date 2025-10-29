<?php
/**
 * Simplified PDF processing class
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-parser-native.php';

class IDokladProcessor_PDFProcessor {

    private $native_parser;

    public function __construct() {
        $this->native_parser = new IDokladProcessor_NativePDFParser();
    }

    /**
     * Extract plain text from a PDF file using the native parser only.
     */
    public function extract_text($pdf_path) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found: ' . $pdf_path);
        }

        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF Processor: Extracting text from ' . $pdf_path);
        }

        $text = $this->native_parser->extract_text($pdf_path);
        $text = $this->clean_extracted_text($text);

        if (empty($text)) {
            throw new Exception('No text could be extracted from PDF');
        }

        return $text;
    }

    public function get_metadata($pdf_path) {
        return $this->native_parser->get_metadata($pdf_path);
    }

    public function get_page_count($pdf_path) {
        return $this->native_parser->get_page_count($pdf_path);
    }

    public function test_parsing_methods() {
        return array(
            'native_parser' => array(
                'available' => true,
                'name' => 'Native PHP Parser',
                'description' => __('Pure PHP extraction (no external services)', 'idoklad-invoice-processor'),
                'category' => 'Text Extraction'
            )
        );
    }

    /**
     * Clean extracted text for downstream processing.
     */
    private function clean_extracted_text($text) {
        $text = (string) $text;
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = trim($text);

        return $text;
    }
}

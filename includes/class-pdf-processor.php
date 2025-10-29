<?php
/**
 * PDF processing wrapper that delegates to the PDF.co processor.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdfco-processor.php';

class IDokladProcessor_PDFProcessor {

    private $pdfco_processor;

    public function __construct() {
        $this->pdfco_processor = new IDokladProcessor_PDFCoProcessor();
    }

    /**
     * Extract plain text from a PDF file using the PDF.co cloud service.
     */
    public function extract_text($pdf_path, $context = array()) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found: ' . $pdf_path);
        }

        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF Processor: Extracting text via PDF.co from ' . $pdf_path);
        }

        $text = $this->pdfco_processor->extract_text($pdf_path, $context);
        $text = $this->clean_extracted_text($text);

        if (empty($text)) {
            throw new Exception('No text could be extracted from PDF');
        }

        return $text;
    }

    public function get_metadata($pdf_path) {
        return $this->pdfco_processor->get_metadata($pdf_path);
    }

    public function get_page_count($pdf_path) {
        return $this->pdfco_processor->get_page_count($pdf_path);
    }

    public function test_parsing_methods() {
        return array(
            'pdfco' => array(
                'available' => $this->pdfco_processor->is_enabled(),
                'name' => 'PDF.co Cloud Parser',
                'description' => __('Cloud-based extraction with OCR (requires API key)', 'idoklad-invoice-processor'),
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

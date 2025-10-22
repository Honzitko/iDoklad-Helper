<?php
/**
 * PDF processing class - Enhanced with native PHP parsing
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include native PHP PDF parser
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-parser-native.php';

class IDokladProcessor_PDFProcessor {
    
    private $temp_dir;
    private $native_parser;
    private $use_native_parser_first;
    private $use_pdfco;
    
    public function __construct() {
        $this->temp_dir = sys_get_temp_dir();
        $this->native_parser = new IDokladProcessor_NativePDFParser();
        // Use native parser first by default (no external dependencies)
        $this->use_native_parser_first = get_option('idoklad_use_native_parser_first', true);
        // Use PDF.co as primary processor (replaces all other methods)
        $this->use_pdfco = get_option('idoklad_use_pdfco', true);
    }
    
    /**
     * Extract text from PDF file
     * 
     * @param string $pdf_path Path to the PDF file
     * @param int|null $queue_id Optional queue ID for logging
     */
    public function extract_text($pdf_path, $queue_id = null) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found: ' . $pdf_path);
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF Processor: Extracting text from ' . $pdf_path);
        }
        
        // ONLY use PDF.co - NO FALLBACKS!
        // If PDF.co fails, the entire process STOPS
        if ($queue_id) {
            IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Processing: Using PDF.co cloud service', null, false);
        }
        
        require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdfco-processor.php';
        $pdfco = new IDokladProcessor_PDFCoProcessor();
        $text = $pdfco->extract_text($pdf_path, $queue_id);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF Processor: PDF.co extracted ' . strlen($text) . ' characters');
        }
        
        // Clean up extracted text
        $text = $this->clean_extracted_text($text);
        
        return $text;
    }
    
    /**
     * Extract text using native PHP parser
     */
    private function extract_with_native_parser($pdf_path) {
        try {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad PDF Processor: Trying native PHP parser');
            }
            
            $text = $this->native_parser->extract_text($pdf_path);
            
            if (!empty($text)) {
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad PDF Processor: Native PHP parser succeeded');
                }
                return $text;
            }
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad PDF Processor: Native PHP parser failed: ' . $e->getMessage());
            }
        }
        
        return '';
    }
    
    /**
     * Extract text using pdftotext command
     */
    private function extract_with_pdftotext($pdf_path) {
        if (!function_exists('exec')) {
            return '';
        }
        
        $output_file = $this->temp_dir . '/pdf_text_' . uniqid() . '.txt';
        $command = "pdftotext -layout \"$pdf_path\" \"$output_file\" 2>/dev/null";
        
        exec($command, $output, $return_code);
        
        if ($return_code === 0 && file_exists($output_file)) {
            $text = file_get_contents($output_file);
            unlink($output_file);
            return $text;
        }
        
        return '';
    }
    
    /**
     * Extract text using Poppler utilities
     */
    private function extract_with_poppler($pdf_path) {
        if (!function_exists('exec')) {
            return '';
        }
        
        $output_file = $this->temp_dir . '/pdf_text_' . uniqid() . '.txt';
        $command = "pdftotext -enc UTF-8 \"$pdf_path\" \"$output_file\" 2>/dev/null";
        
        exec($command, $output, $return_code);
        
        if ($return_code === 0 && file_exists($output_file)) {
            $text = file_get_contents($output_file);
            unlink($output_file);
            return $text;
        }
        
        return '';
    }
    
    /**
     * Extract text using Ghostscript
     */
    private function extract_with_ghostscript($pdf_path) {
        if (!function_exists('exec')) {
            return '';
        }
        
        $output_file = $this->temp_dir . '/pdf_text_' . uniqid() . '.txt';
        $command = "gs -dNODISPLAY -dNOPAUSE -dBATCH -sDEVICE=txtwrite -sOutputFile=\"$output_file\" \"$pdf_path\" 2>/dev/null";
        
        exec($command, $output, $return_code);
        
        if ($return_code === 0 && file_exists($output_file)) {
            $text = file_get_contents($output_file);
            unlink($output_file);
            return $text;
        }
        
        return '';
    }
    
    /**
     * Clean extracted text
     */
    private function clean_extracted_text($text) {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Normalize line breaks
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        
        // Remove empty lines
        $text = preg_replace('/\n\s*\n/', "\n", $text);
        
        // Trim whitespace
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Get PDF metadata
     */
    public function get_metadata($pdf_path) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found: ' . $pdf_path);
        }
        
        try {
            return $this->native_parser->get_metadata($pdf_path);
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad PDF Processor: Could not extract metadata: ' . $e->getMessage());
            }
            return array();
        }
    }
    
    /**
     * Get page count
     */
    public function get_page_count($pdf_path) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found: ' . $pdf_path);
        }
        
        try {
            return $this->native_parser->get_page_count($pdf_path);
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad PDF Processor: Could not get page count: ' . $e->getMessage());
            }
            return 0;
        }
    }
    
    /**
     * Test PDF parsing capabilities
     */
    public function test_parsing_methods() {
        $results = array(
            'native_parser' => array(
                'available' => true,
                'name' => 'Native PHP Parser (No dependencies)',
                'description' => 'Pure PHP implementation, works on any server',
                'category' => 'Text Extraction'
            ),
            'pdftotext' => array(
                'available' => $this->check_command_available('pdftotext'),
                'name' => 'pdftotext',
                'description' => 'Command-line tool from Poppler utils',
                'category' => 'Text Extraction'
            ),
            'ghostscript' => array(
                'available' => $this->check_command_available('gs'),
                'name' => 'Ghostscript',
                'description' => 'Command-line PostScript/PDF interpreter',
                'category' => 'Text Extraction'
            )
        );
        
        // OCR is now handled by PDF.co automatically
        
        return $results;
    }
    
    /**
     * Test OCR capabilities (now handled by PDF.co)
     */
    public function test_ocr_capabilities() {
        return array(
            'enabled' => true,
            'message' => 'OCR is handled automatically by PDF.co'
        );
        
        // Legacy code removed - PDF.co handles OCR automatically
    }
    
    /**
     * Check if a command is available
     */
    private function check_command_available($command) {
        if (!function_exists('exec')) {
            return false;
        }
        
        $output = array();
        $return_var = 0;
        
        exec("which $command 2>/dev/null", $output, $return_var);
        
        return $return_var === 0 && !empty($output);
    }
    
    /**
     * Get PDF information for diagnostics
     */
    public function get_pdf_info($pdf_path) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found: ' . $pdf_path);
        }
        
        $info = array(
            'file_size' => filesize($pdf_path),
            'file_size_formatted' => $this->format_bytes(filesize($pdf_path)),
            'readable' => is_readable($pdf_path),
            'metadata' => $this->get_metadata($pdf_path),
            'page_count' => $this->get_page_count($pdf_path),
            'parsing_methods' => $this->test_parsing_methods()
        );
        
        return $info;
    }
    
    /**
     * Format bytes to human-readable format
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

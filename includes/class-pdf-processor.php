<?php
/**
 * PDF processing class - Enhanced with native PHP parsing
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include native PHP PDF parser and OCR processor
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-parser-native.php';
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-ocr-processor.php';

class IDokladProcessor_PDFProcessor {
    
    private $temp_dir;
    private $native_parser;
    private $ocr_processor;
    private $use_native_parser_first;
    private $enable_ocr;
    
    public function __construct() {
        $this->temp_dir = sys_get_temp_dir();
        $this->native_parser = new IDokladProcessor_NativePDFParser();
        $this->ocr_processor = new IDokladProcessor_OCRProcessor();
        // Use native parser first by default (no external dependencies)
        $this->use_native_parser_first = get_option('idoklad_use_native_parser_first', true);
        // Enable OCR for scanned PDFs
        $this->enable_ocr = get_option('idoklad_enable_ocr', true);
        // Use PDF.co as primary processor (replaces all other methods)
        $this->use_pdfco = get_option('idoklad_use_pdfco', true);
    }
    
    private $use_pdfco;
    
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
        
        // If PDF.co is enabled, use it exclusively (it handles both regular PDFs and OCR)
        if ($this->use_pdfco) {
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Processing: Using PDF.co cloud service', null, false);
            }
            
            try {
                require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdfco-processor.php';
                $pdfco = new IDokladProcessor_PDFCoProcessor();
                $text = $pdfco->extract_text($pdf_path, $queue_id);
                
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad PDF Processor: PDF.co extracted ' . strlen($text) . ' characters');
                }
                
                return $this->clean_extracted_text($text);
                
            } catch (Exception $e) {
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF.co failed: ' . $e->getMessage(), null, false);
                }
                
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad PDF Processor: PDF.co failed, will try fallback methods: ' . $e->getMessage());
                }
                // If PDF.co fails, continue with fallback methods below
            }
        }
        
        $text = '';
        $methods_used = array();
        
        // Try different methods to extract text
        if ($this->use_native_parser_first) {
            // Try native PHP parser first (no external dependencies)
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: Trying native PHP parser', null, false);
            }
            
            $text = $this->extract_with_native_parser($pdf_path);
            if (!empty($text)) {
                $methods_used[] = 'native PHP parser';
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: Native PHP parser succeeded', array(
                        'characters' => strlen($text)
                    ), false);
                }
            } else {
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: Native PHP parser failed/empty', null, false);
                }
            }
        }
        
        // Fallback to command-line tools if native parser fails or is disabled
        if (empty($text)) {
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: Trying pdftotext', null, false);
            }
            $text = $this->extract_with_pdftotext($pdf_path);
            if (!empty($text)) {
                $methods_used[] = 'pdftotext';
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: pdftotext succeeded', array('characters' => strlen($text)), false);
                }
            } else {
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: pdftotext not available/failed', null, false);
                }
            }
        }
        
        if (empty($text)) {
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: Trying poppler', null, false);
            }
            $text = $this->extract_with_poppler($pdf_path);
            if (!empty($text)) {
                $methods_used[] = 'poppler';
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: poppler succeeded', array('characters' => strlen($text)), false);
                }
            } else {
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: poppler not available/failed', null, false);
                }
            }
        }
        
        if (empty($text)) {
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: Trying ghostscript', null, false);
            }
            $text = $this->extract_with_ghostscript($pdf_path);
            if (!empty($text)) {
                $methods_used[] = 'ghostscript';
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: ghostscript succeeded', array('characters' => strlen($text)), false);
                }
            } else {
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: ghostscript not available/failed', null, false);
                }
            }
        }
        
        // If native parser wasn't tried first, try it as a last resort
        if (empty($text) && !$this->use_native_parser_first) {
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: Trying native PHP parser (fallback)', null, false);
            }
            $text = $this->extract_with_native_parser($pdf_path);
            if (!empty($text)) {
                $methods_used[] = 'native PHP parser (fallback)';
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: Native PHP parser succeeded', array('characters' => strlen($text)), false);
                }
            }
        }
        
        // Check if we got very little text (might be a scanned PDF)
        if (strlen(trim($text)) < 50 && $this->enable_ocr) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad PDF Processor: Very little text extracted (' . strlen($text) . ' chars). Checking if PDF is scanned...');
            }
            
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: Very little text extracted, checking for scanned PDF', array(
                    'characters' => strlen($text)
                ), false);
            }
            
            // Check if it's a scanned PDF
            if ($this->ocr_processor->is_scanned_pdf($pdf_path, $text)) {
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad PDF Processor: PDF appears to be scanned. Attempting OCR...');
                }
                
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: PDF is scanned, attempting OCR', null, false);
                }
                
                try {
                    $ocr_text = $this->ocr_processor->extract_text_from_scanned_pdf($pdf_path);
                    
                    if (!empty($ocr_text)) {
                        $text = $ocr_text;
                        $methods_used[] = 'OCR (scanned PDF)';
                        
                        if ($queue_id) {
                            IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: OCR succeeded', array(
                                'characters' => strlen($ocr_text)
                            ), false);
                        }
                        
                        if (get_option('idoklad_debug_mode')) {
                            error_log('iDoklad PDF Processor: OCR successful. Extracted ' . strlen($text) . ' characters');
                        }
                    } else {
                        if ($queue_id) {
                            IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: OCR returned empty text', null, false);
                        }
                    }
                } catch (Exception $e) {
                    if (get_option('idoklad_debug_mode')) {
                        error_log('iDoklad PDF Processor: OCR failed: ' . $e->getMessage());
                    }
                    if ($queue_id) {
                        IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: OCR failed', array(
                            'error' => $e->getMessage()
                        ), false);
                    }
                }
            } else {
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Parsing: PDF does not appear to be scanned', null, false);
                }
            }
        }
        
        if (empty($text)) {
            throw new Exception('Could not extract text from PDF. The PDF might be image-based, encrypted, or corrupted. If this is a scanned PDF, please ensure OCR is enabled and Tesseract is installed.');
        }
        
        // Clean up extracted text
        $text = $this->clean_extracted_text($text);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF Processor: Extracted ' . strlen($text) . ' characters of text using: ' . implode(', ', $methods_used));
        }
        
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
        
        // Add OCR methods
        if ($this->enable_ocr) {
            $ocr_methods = $this->ocr_processor->test_ocr_methods();
            foreach ($ocr_methods as $key => $method) {
                $results['ocr_' . $key] = array(
                    'available' => $method['available'],
                    'name' => $method['name'],
                    'description' => $method['description'],
                    'category' => 'OCR (Scanned PDFs)',
                    'enabled' => $method['enabled']
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Test OCR capabilities
     */
    public function test_ocr_capabilities() {
        if (!$this->enable_ocr) {
            return array(
                'enabled' => false,
                'message' => 'OCR is disabled in settings'
            );
        }
        
        $ocr_methods = $this->ocr_processor->test_ocr_methods();
        
        $has_pdf_converter = (
            $ocr_methods['imagemagick']['available'] ||
            $ocr_methods['ghostscript']['available'] ||
            $ocr_methods['imagick_extension']['available']
        );
        
        $has_ocr_engine = (
            $ocr_methods['tesseract']['available'] ||
            $ocr_methods['ocr_space']['available'] ||
            $ocr_methods['google_vision']['available']
        );
        
        return array(
            'enabled' => true,
            'can_process_scanned_pdfs' => $has_pdf_converter && $has_ocr_engine,
            'has_pdf_converter' => $has_pdf_converter,
            'has_ocr_engine' => $has_ocr_engine,
            'methods' => $ocr_methods
        );
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

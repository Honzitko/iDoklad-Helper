<?php
/**
 * OCR Processor Class - Extract text from scanned PDFs and images
 * Supports multiple OCR methods including local Tesseract and cloud services
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_OCRProcessor {
    
    private $temp_dir;
    private $tesseract_path;
    private $tesseract_lang;
    private $use_tesseract;
    private $use_cloud_ocr;
    private $cloud_ocr_service;
    
    public function __construct() {
        $this->temp_dir = sys_get_temp_dir();
        $this->tesseract_path = get_option('idoklad_tesseract_path', 'tesseract');
        $this->tesseract_lang = get_option('idoklad_tesseract_lang', 'ces+eng'); // Czech + English
        $this->use_tesseract = get_option('idoklad_use_tesseract', true);
        $this->use_cloud_ocr = get_option('idoklad_use_cloud_ocr', false);
        $this->cloud_ocr_service = get_option('idoklad_cloud_ocr_service', 'none');
    }
    
    /**
     * Extract text from scanned PDF or image
     */
    public function extract_text_from_scanned_pdf($pdf_path) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found: ' . $pdf_path);
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad OCR Processor: Processing scanned PDF ' . $pdf_path);
        }
        
        try {
            // Step 1: Convert PDF pages to images
            $image_files = $this->convert_pdf_to_images($pdf_path);
            
            if (empty($image_files)) {
                throw new Exception('Could not convert PDF to images');
            }
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR Processor: Converted PDF to ' . count($image_files) . ' images');
            }
            
            // Step 2: Run OCR on each image
            $all_text = '';
            foreach ($image_files as $index => $image_file) {
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad OCR Processor: Processing page ' . ($index + 1));
                }
                
                $page_text = $this->extract_text_from_image($image_file);
                $all_text .= $page_text . "\n\n--- Page " . ($index + 1) . " ---\n\n";
                
                // Clean up temporary image file
                @unlink($image_file);
            }
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR Processor: Extracted ' . strlen($all_text) . ' characters via OCR');
            }
            
            return trim($all_text);
            
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR Processor Error: ' . $e->getMessage());
            }
            throw $e;
        }
    }
    
    /**
     * Extract text from a single image file
     */
    public function extract_text_from_image($image_path) {
        if (!file_exists($image_path)) {
            throw new Exception('Image file not found: ' . $image_path);
        }
        
        $text = '';
        
        // Try different OCR methods in order of preference
        if ($this->use_tesseract) {
            $text = $this->ocr_with_tesseract($image_path);
        }
        
        if (empty($text) && $this->use_cloud_ocr) {
            $text = $this->ocr_with_cloud_service($image_path);
        }
        
        if (empty($text)) {
            throw new Exception('Could not extract text from image using any OCR method');
        }
        
        return $text;
    }
    
    /**
     * Convert PDF to images using ImageMagick or Ghostscript
     */
    private function convert_pdf_to_images($pdf_path) {
        $images = array();
        
        // Try ImageMagick first (convert command)
        if ($this->check_command_available('convert')) {
            $images = $this->convert_pdf_with_imagemagick($pdf_path);
        }
        
        // Try Ghostscript if ImageMagick failed
        if (empty($images) && $this->check_command_available('gs')) {
            $images = $this->convert_pdf_with_ghostscript($pdf_path);
        }
        
        // Try PHP Imagick extension
        if (empty($images) && extension_loaded('imagick')) {
            $images = $this->convert_pdf_with_imagick_extension($pdf_path);
        }
        
        return $images;
    }
    
    /**
     * Convert PDF to images using ImageMagick command
     */
    private function convert_pdf_with_imagemagick($pdf_path) {
        if (!function_exists('exec')) {
            return array();
        }
        
        $output_pattern = $this->temp_dir . '/pdf_page_' . uniqid() . '_%03d.png';
        
        // Use ImageMagick to convert PDF to PNG images
        $command = sprintf(
            'convert -density 300 "%s" -quality 100 "%s" 2>&1',
            escapeshellarg($pdf_path),
            $output_pattern
        );
        
        exec($command, $output, $return_code);
        
        if ($return_code !== 0) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: ImageMagick conversion failed: ' . implode("\n", $output));
            }
            return array();
        }
        
        // Find all generated image files
        $pattern_for_glob = str_replace('%03d', '*', $output_pattern);
        $images = glob($pattern_for_glob);
        
        return $images ? $images : array();
    }
    
    /**
     * Convert PDF to images using Ghostscript
     */
    private function convert_pdf_with_ghostscript($pdf_path) {
        if (!function_exists('exec')) {
            return array();
        }
        
        $output_pattern = $this->temp_dir . '/pdf_page_' . uniqid() . '_%03d.png';
        
        $command = sprintf(
            'gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r300 -sOutputFile="%s" "%s" 2>&1',
            $output_pattern,
            escapeshellarg($pdf_path)
        );
        
        exec($command, $output, $return_code);
        
        if ($return_code !== 0) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: Ghostscript conversion failed: ' . implode("\n", $output));
            }
            return array();
        }
        
        // Find all generated image files
        $pattern_for_glob = str_replace('%03d', '*', $output_pattern);
        $images = glob($pattern_for_glob);
        
        return $images ? $images : array();
    }
    
    /**
     * Convert PDF to images using PHP Imagick extension
     */
    private function convert_pdf_with_imagick_extension($pdf_path) {
        if (!extension_loaded('imagick')) {
            return array();
        }
        
        try {
            $imagick = new Imagick();
            $imagick->setResolution(300, 300);
            $imagick->readImage($pdf_path);
            
            $images = array();
            $num_pages = $imagick->getNumberImages();
            
            for ($i = 0; $i < $num_pages; $i++) {
                $imagick->setIteratorIndex($i);
                $imagick->setImageFormat('png');
                $imagick->setImageCompressionQuality(100);
                
                $output_file = $this->temp_dir . '/pdf_page_' . uniqid() . '_' . $i . '.png';
                $imagick->writeImage($output_file);
                $images[] = $output_file;
            }
            
            $imagick->clear();
            $imagick->destroy();
            
            return $images;
            
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: Imagick conversion failed: ' . $e->getMessage());
            }
            return array();
        }
    }
    
    /**
     * Perform OCR using Tesseract
     */
    private function ocr_with_tesseract($image_path) {
        if (!function_exists('exec')) {
            return '';
        }
        
        if (!$this->check_command_available($this->tesseract_path)) {
            return '';
        }
        
        $output_file = $this->temp_dir . '/tesseract_output_' . uniqid();
        
        // Run Tesseract OCR
        $command = sprintf(
            '%s "%s" "%s" -l %s 2>&1',
            $this->tesseract_path,
            escapeshellarg($image_path),
            $output_file,
            $this->tesseract_lang
        );
        
        exec($command, $output, $return_code);
        
        // Tesseract adds .txt extension automatically
        $output_txt_file = $output_file . '.txt';
        
        if (file_exists($output_txt_file)) {
            $text = file_get_contents($output_txt_file);
            @unlink($output_txt_file);
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: Tesseract extracted ' . strlen($text) . ' characters');
            }
            
            return $text;
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad OCR: Tesseract failed: ' . implode("\n", $output));
        }
        
        return '';
    }
    
    /**
     * Perform OCR using cloud service (OCR.space, Google Vision, etc.)
     */
    private function ocr_with_cloud_service($image_path) {
        if ($this->cloud_ocr_service === 'ocr_space') {
            return $this->ocr_with_ocr_space($image_path);
        }
        
        if ($this->cloud_ocr_service === 'google_vision') {
            return $this->ocr_with_google_vision($image_path);
        }
        
        return '';
    }
    
    /**
     * OCR using OCR.space API (free tier available)
     * Enhanced version with better error handling and multiple upload methods
     */
    private function ocr_with_ocr_space($image_path) {
        $api_key = get_option('idoklad_ocr_space_api_key');
        
        if (empty($api_key)) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: OCR.space API key not configured');
            }
            return '';
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad OCR: Starting OCR.space API request for ' . basename($image_path));
        }
        
        // Try file upload method first (more reliable)
        $text = $this->ocr_space_file_upload($image_path, $api_key);
        
        // If file upload fails, try base64 method
        if (empty($text)) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: File upload failed, trying base64 method');
            }
            $text = $this->ocr_space_base64($image_path, $api_key);
        }
        
        return $text;
    }
    
    /**
     * OCR.space API using file upload
     */
    private function ocr_space_file_upload($image_path, $api_key) {
        $url = 'https://api.ocr.space/parse/image';
        
        if (!file_exists($image_path)) {
            return '';
        }
        
        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);
        $delimiter = '-------------' . $boundary;
        
        $image_data = file_get_contents($image_path);
        $filename = basename($image_path);
        
        $body = '';
        
        // Add file
        $body .= "--{$delimiter}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= $image_data . "\r\n";
        
        // Add OCR engine (2 = best quality with auto language detection)
        $body .= "--{$delimiter}\r\n";
        $body .= "Content-Disposition: form-data; name=\"OCREngine\"\r\n\r\n";
        $body .= "2\r\n";
        
        // Add scale option for better accuracy
        $body .= "--{$delimiter}\r\n";
        $body .= "Content-Disposition: form-data; name=\"scale\"\r\n\r\n";
        $body .= "true\r\n";
        
        // Add detectOrientation option
        $body .= "--{$delimiter}\r\n";
        $body .= "Content-Disposition: form-data; name=\"detectOrientation\"\r\n\r\n";
        $body .= "true\r\n";
        
        $body .= "--{$delimiter}--\r\n";
        
        $args = array(
            'headers' => array(
                'apikey' => $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $delimiter
            ),
            'body' => $body,
            'timeout' => 90,
            'method' => 'POST',
            'sslverify' => true
        );
        
        $response = wp_remote_request($url, $args);
        
        return $this->parse_ocr_space_response($response);
    }
    
    /**
     * OCR.space API using base64 encoding
     */
    private function ocr_space_base64($image_path, $api_key) {
        $url = 'https://api.ocr.space/parse/image';
        
        if (!file_exists($image_path)) {
            return '';
        }
        
        // Read and encode image
        $image_data = file_get_contents($image_path);
        $base64_image = base64_encode($image_data);
        
        // Prepare POST data (no language parameter = auto-detect)
        $post_data = array(
            'base64Image' => 'data:image/png;base64,' . $base64_image,
            'OCREngine' => '2',
            'scale' => 'true',
            'detectOrientation' => 'true'
        );
        
        $args = array(
            'headers' => array(
                'apikey' => $api_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => $post_data,
            'timeout' => 90,
            'method' => 'POST',
            'sslverify' => true
        );
        
        $response = wp_remote_request($url, $args);
        
        return $this->parse_ocr_space_response($response);
    }
    
    /**
     * Parse OCR.space API response
     */
    private function parse_ocr_space_response($response, $store_response = false) {
        if (is_wp_error($response)) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: OCR.space API error: ' . $response->get_error_message());
            }
            
            // Store error for diagnostics
            if ($store_response) {
                update_option('idoklad_last_ocr_response', array(
                    'error' => true,
                    'message' => $response->get_error_message(),
                    'timestamp' => time()
                ), false);
            }
            
            return '';
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad OCR: OCR.space response code: ' . $response_code);
        }
        
        // Store full response for diagnostics
        if ($store_response) {
            $data_decoded = json_decode($response_body, true);
            update_option('idoklad_last_ocr_response', array(
                'response_code' => $response_code,
                'response_body' => $response_body,
                'response_data' => $data_decoded,
                'timestamp' => time(),
                'headers' => wp_remote_retrieve_headers($response)->getAll()
            ), false);
        }
        
        if ($response_code !== 200) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: OCR.space returned non-200 status: ' . $response_code);
                error_log('iDoklad OCR: Response: ' . substr($response_body, 0, 500));
            }
            return '';
        }
        
        $data = json_decode($response_body, true);
        
        if (!$data) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: Failed to parse OCR.space JSON response');
            }
            return '';
        }
        
        // Check for API errors
        if (isset($data['IsErroredOnProcessing']) && $data['IsErroredOnProcessing'] === true) {
            $error_message = isset($data['ErrorMessage'][0]) ? $data['ErrorMessage'][0] : 'Unknown error';
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: OCR.space processing error: ' . $error_message);
            }
            return '';
        }
        
        // Extract text from response
        if (isset($data['ParsedResults'][0]['ParsedText'])) {
            $text = $data['ParsedResults'][0]['ParsedText'];
            
            // Get additional info
            $exit_code = isset($data['ParsedResults'][0]['FileParseExitCode']) ? $data['ParsedResults'][0]['FileParseExitCode'] : null;
            $error_details = isset($data['ParsedResults'][0]['ErrorDetails']) ? $data['ParsedResults'][0]['ErrorDetails'] : '';
            
            if ($exit_code !== 1) {
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad OCR: OCR.space parse exit code: ' . $exit_code . ', Details: ' . $error_details);
                }
            }
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: OCR.space successfully extracted ' . strlen($text) . ' characters');
                
                // Log usage info if available
                if (isset($data['ProcessingTimeInMilliseconds'])) {
                    error_log('iDoklad OCR: Processing time: ' . $data['ProcessingTimeInMilliseconds'] . 'ms');
                }
            }
            
            return $text;
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad OCR: No ParsedText found in OCR.space response');
            error_log('iDoklad OCR: Response structure: ' . print_r($data, true));
        }
        
        return '';
    }
    
    /**
     * Test OCR.space with response capture (for diagnostics)
     */
    public function test_ocr_space_with_response($image_path) {
        $api_key = get_option('idoklad_ocr_space_api_key');
        
        if (empty($api_key)) {
            throw new Exception('OCR.space API key not configured');
        }
        
        // Try file upload method first
        $text = $this->ocr_space_file_upload_with_response($image_path, $api_key);
        
        // If file upload fails, try base64 method
        if (empty($text)) {
            $text = $this->ocr_space_base64_with_response($image_path, $api_key);
        }
        
        return $text;
    }
    
    /**
     * OCR.space file upload with response capture
     */
    private function ocr_space_file_upload_with_response($image_path, $api_key) {
        $url = 'https://api.ocr.space/parse/image';
        
        if (!file_exists($image_path)) {
            throw new Exception('Image file not found: ' . $image_path);
        }
        
        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);
        $delimiter = '-------------' . $boundary;
        
        $image_data = file_get_contents($image_path);
        $filename = basename($image_path);
        
        $body = '';
        
        // Add file
        $body .= "--{$delimiter}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= $image_data . "\r\n";
        
        // Add OCR engine
        $body .= "--{$delimiter}\r\n";
        $body .= "Content-Disposition: form-data; name=\"OCREngine\"\r\n\r\n";
        $body .= "2\r\n";
        
        // Add scale option
        $body .= "--{$delimiter}\r\n";
        $body .= "Content-Disposition: form-data; name=\"scale\"\r\n\r\n";
        $body .= "true\r\n";
        
        // Add detectOrientation option
        $body .= "--{$delimiter}\r\n";
        $body .= "Content-Disposition: form-data; name=\"detectOrientation\"\r\n\r\n";
        $body .= "true\r\n";
        
        $body .= "--{$delimiter}--\r\n";
        
        $args = array(
            'headers' => array(
                'apikey' => $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $delimiter
            ),
            'body' => $body,
            'timeout' => 90,
            'method' => 'POST',
            'sslverify' => true
        );
        
        $response = wp_remote_request($url, $args);
        
        return $this->parse_ocr_space_response($response, true); // Store response
    }
    
    /**
     * OCR.space base64 with response capture
     */
    private function ocr_space_base64_with_response($image_path, $api_key) {
        $url = 'https://api.ocr.space/parse/image';
        
        if (!file_exists($image_path)) {
            throw new Exception('Image file not found: ' . $image_path);
        }
        
        // Read and encode image
        $image_data = file_get_contents($image_path);
        $base64_image = base64_encode($image_data);
        
        // Prepare POST data
        $post_data = array(
            'base64Image' => 'data:image/png;base64,' . $base64_image,
            'OCREngine' => '2',
            'scale' => 'true',
            'detectOrientation' => 'true'
        );
        
        $args = array(
            'headers' => array(
                'apikey' => $api_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => $post_data,
            'timeout' => 90,
            'method' => 'POST',
            'sslverify' => true
        );
        
        $response = wp_remote_request($url, $args);
        
        return $this->parse_ocr_space_response($response, true); // Store response
    }
    
    /**
     * OCR using Google Cloud Vision API
     */
    private function ocr_with_google_vision($image_path) {
        $api_key = get_option('idoklad_google_vision_api_key');
        
        if (empty($api_key)) {
            return '';
        }
        
        $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $api_key;
        
        $image_data = file_get_contents($image_path);
        $base64_image = base64_encode($image_data);
        
        $request_body = array(
            'requests' => array(
                array(
                    'image' => array(
                        'content' => $base64_image
                    ),
                    'features' => array(
                        array(
                            'type' => 'TEXT_DETECTION',
                            'maxResults' => 1
                        )
                    )
                )
            )
        );
        
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_body),
            'timeout' => 60,
            'method' => 'POST'
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: Google Vision API error: ' . $response->get_error_message());
            }
            return '';
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (isset($data['responses'][0]['fullTextAnnotation']['text'])) {
            $text = $data['responses'][0]['fullTextAnnotation']['text'];
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad OCR: Google Vision extracted ' . strlen($text) . ' characters');
            }
            
            return $text;
        }
        
        return '';
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
        
        // Try 'which' command (Unix/Linux/Mac)
        exec("which $command 2>/dev/null", $output, $return_var);
        
        if ($return_var === 0 && !empty($output)) {
            return true;
        }
        
        // Try 'where' command (Windows)
        exec("where $command 2>nul", $output, $return_var);
        
        return $return_var === 0 && !empty($output);
    }
    
    /**
     * Test OCR capabilities
     */
    public function test_ocr_methods() {
        $results = array(
            'tesseract' => array(
                'available' => $this->check_command_available($this->tesseract_path),
                'name' => 'Tesseract OCR',
                'description' => 'Local OCR engine (open source)',
                'enabled' => $this->use_tesseract
            ),
            'imagemagick' => array(
                'available' => $this->check_command_available('convert'),
                'name' => 'ImageMagick',
                'description' => 'PDF to image conversion',
                'enabled' => true
            ),
            'ghostscript' => array(
                'available' => $this->check_command_available('gs'),
                'name' => 'Ghostscript',
                'description' => 'Alternative PDF to image conversion',
                'enabled' => true
            ),
            'imagick_extension' => array(
                'available' => extension_loaded('imagick'),
                'name' => 'PHP Imagick Extension',
                'description' => 'PHP-based PDF to image conversion',
                'enabled' => true
            ),
            'ocr_space' => array(
                'available' => !empty(get_option('idoklad_ocr_space_api_key')),
                'name' => 'OCR.space API',
                'description' => 'Cloud OCR service (free tier available)',
                'enabled' => $this->use_cloud_ocr && $this->cloud_ocr_service === 'ocr_space'
            ),
            'google_vision' => array(
                'available' => !empty(get_option('idoklad_google_vision_api_key')),
                'name' => 'Google Cloud Vision',
                'description' => 'Google\'s OCR service',
                'enabled' => $this->use_cloud_ocr && $this->cloud_ocr_service === 'google_vision'
            )
        );
        
        return $results;
    }
    
    /**
     * Check if PDF is scanned (image-based)
     */
    public function is_scanned_pdf($pdf_path, $native_parser_text = '') {
        // If native parser extracted very little or no text, it's likely scanned
        if (strlen(trim($native_parser_text)) < 50) {
            return true;
        }
        
        // Check if PDF contains images
        $pdf_content = file_get_contents($pdf_path);
        
        // Look for image objects in PDF
        $has_images = (
            strpos($pdf_content, '/Image') !== false ||
            strpos($pdf_content, '/DCTDecode') !== false ||
            strpos($pdf_content, '/JPXDecode') !== false
        );
        
        // If it has images and little text, it's likely scanned
        if ($has_images && strlen(trim($native_parser_text)) < 200) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get OCR languages available for Tesseract
     */
    public function get_tesseract_languages() {
        if (!$this->check_command_available($this->tesseract_path)) {
            return array();
        }
        
        $command = $this->tesseract_path . ' --list-langs 2>&1';
        exec($command, $output, $return_code);
        
        $languages = array();
        $capture = false;
        
        foreach ($output as $line) {
            if (strpos($line, 'List of available languages') !== false) {
                $capture = true;
                continue;
            }
            
            if ($capture && !empty(trim($line))) {
                $lang = trim($line);
                $languages[] = $lang;
            }
        }
        
        return $languages;
    }
}


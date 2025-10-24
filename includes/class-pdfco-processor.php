<?php
/**
 * PDF.co Processor Class
 * Cloud-based PDF processing and OCR using PDF.co API
 * Replaces all other PDF processing methods
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_PDFCoProcessor {
    
    private $api_key;
    private $api_url = 'https://api.pdf.co/v1';
    
    public function __construct() {
        $this->api_key = get_option('idoklad_pdfco_api_key', '');
    }
    
    /**
     * Extract text from PDF using PDF.co (following official documentation)
     * Step 1: Upload file via /v1/file/upload
     * Step 2: Use returned URL in /v1/pdf/convert/to/text
     */
    public function extract_text($pdf_path, $queue_id = null) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found: ' . $pdf_path);
        }
        
        if (empty($this->api_key)) {
            throw new Exception('PDF.co API key is not configured');
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF.co: Extracting text from ' . $pdf_path);
        }
        
        if ($queue_id) {
            IDokladProcessor_Database::add_queue_step($queue_id, 'PDF Processing: Using PDF.co cloud service', null, false);
        }
        
        try {
            // Step 1: Upload file and get URL (per official docs)
            $file_url = $this->upload_file($pdf_path);
            
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF.co: File uploaded successfully', null, false);
            }
            
            // Step 2: Extract text using the URL
            $text = $this->extract_text_from_url($file_url);
            
            // If text is too short, it might be a scanned PDF - use OCR
            if (strlen(trim($text)) < 100) {
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad PDF.co: Text extraction returned minimal text, trying OCR');
                }
                
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF.co: Minimal text found, switching to OCR', array('text_length' => strlen($text)), false);
                }
                
                $text = $this->ocr_pdf_from_url($file_url);
            }
            
            if (empty($text)) {
                throw new Exception('PDF.co returned no text from PDF');
            }
            
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF.co: Text extraction successful', array('characters' => strlen($text)), false);
            }
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad PDF.co: Extracted ' . strlen($text) . ' characters');
            }
            
            return $text;
            
        } catch (Exception $e) {
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF.co: Extraction failed', array('error' => $e->getMessage()), false);
            }
            
            throw new Exception('PDF.co processing failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload file to PDF.co using official documented method
     * Per docs: POST to /v1/file/upload with multipart/form-data
     */
    public function upload_file($file_path) {
        $url = 'https://api.pdf.co/v1/file/upload';
        
        $boundary = wp_generate_password(24, false);
        $file_content = file_get_contents($file_path);
        $file_name = basename($file_path);
        
        // Build multipart/form-data body per PDF.co docs
        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . '"' . "\r\n";
        $body .= 'Content-Type: application/pdf' . "\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= '--' . $boundary . '--';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary
            ),
            'body' => $body,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('PDF.co upload failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF.co: Upload response: ' . $response_body);
        }
        
        if ($response_code !== 200 || empty($data['url'])) {
            $error_msg = isset($data['message']) ? $data['message'] : 'Upload failed';
            throw new Exception('PDF.co upload failed: ' . $error_msg);
        }
        
        return $data['url'];
    }
    
    /**
     * Extract text from uploaded PDF URL
     * Per docs: POST to /v1/pdf/convert/to/text with url parameter
     * Set inline=true to get text in 'body' field instead of download URL
     */
    private function extract_text_from_url($pdf_url) {
        $url = 'https://api.pdf.co/v1/pdf/convert/to/text';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'url' => $pdf_url,
                'inline' => true  // Return text in 'body' field per documentation
            )),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('PDF.co text extraction failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF.co: Text extraction response: ' . $response_body);
        }
        
        if ($response_code !== 200) {
            $error_msg = isset($data['message']) ? $data['message'] : $response_body;
            throw new Exception('PDF.co text extraction failed: ' . $error_msg);
        }
        
        // Per documentation: check 'error' field first, then get 'body' field
        if (isset($data['error']) && $data['error'] !== false) {
            $error_msg = isset($data['message']) ? $data['message'] : 'Unknown error';
            throw new Exception('PDF.co returned error: ' . $error_msg);
        }
        
        if (!isset($data['body'])) {
            throw new Exception('PDF.co response missing body field');
        }
        
        return $data['body'];
    }
    
    /**
     * OCR PDF from uploaded URL
     * Per docs: Same endpoint with OCREnabled parameter and inline=true
     */
    private function ocr_pdf_from_url($pdf_url) {
        $url = 'https://api.pdf.co/v1/pdf/convert/to/text';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'url' => $pdf_url,
                'inline' => true,  // Return text in 'body' field per documentation
                'OCREnabled' => true,
                'lang' => 'ces'  // Czech language per documentation (use 'lang' not 'ocrLanguages')
            )),
            'timeout' => 180
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('PDF.co OCR failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF.co: OCR response: ' . $response_body);
        }
        
        if ($response_code !== 200) {
            $error_msg = isset($data['message']) ? $data['message'] : $response_body;
            throw new Exception('PDF.co OCR failed: ' . $error_msg);
        }
        
        // Per documentation: check 'error' field first, then get 'body' field
        if (isset($data['error']) && $data['error'] !== false) {
            $error_msg = isset($data['message']) ? $data['message'] : 'Unknown error';
            throw new Exception('PDF.co OCR returned error: ' . $error_msg);
        }
        
        if (!isset($data['body'])) {
            throw new Exception('PDF.co OCR response missing body field');
        }
        
        return $data['body'];
    }
    
    /**
     * Get PDF metadata using PDF.co
     */
    public function get_metadata($pdf_path) {
        if (!file_exists($pdf_path)) {
            return array();
        }
        
        try {
            $uploaded_url = $this->upload_file($pdf_path);
            
            $url = $this->api_url . '/pdf/info';
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'x-api-key' => $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'url' => $uploaded_url
                )),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return array();
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            
            if (isset($data['info'])) {
                return $data['info'];
            }
            
            return array();
            
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad PDF.co: Metadata extraction failed: ' . $e->getMessage());
            }
            return array();
        }
    }
    
    /**
     * Get page count using PDF.co
     */
    public function get_page_count($pdf_path) {
        $metadata = $this->get_metadata($pdf_path);
        return isset($metadata['PageCount']) ? intval($metadata['PageCount']) : 0;
    }
    
    /**
     * Test PDF.co connection (simple ping test)
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'PDF.co API key is not configured'
            );
        }
        
        try {
            // Simple test: try to extract text from a small base64 PDF
            // Create minimal PDF in base64
            $minimal_pdf_base64 = 'JVBERi0xLjQKJeLjz9MKNCAwIG9iago8PC9UeXBlL1BhZ2UvUGFyZW50IDMgMCBSL01lZGlhQm94WzAgMCA2MTIgNzkyXS9Db250ZW50cyA1IDAgUj4+CmVuZG9iago1IDAgb2JqCjw8L0xlbmd0aCA0OD4+CnN0cmVhbQpCVAovRjEgMTIgVGYKNzAgNzAwIFRkCihUZXN0KSBUagpFVAplbmRzdHJlYW0KZW5kb2JqCjEgMCBvYmoKPDwvVHlwZS9Gb250L1N1YnR5cGUvVHlwZTEvQmFzZUZvbnQvVGltZXMtUm9tYW4+PgplbmRvYmoKMiAwIG9iago8PC9UeXBlL0NhdGFsb2cvUGFnZXMgMyAwIFI+PgplbmRvYmoKMyAwIG9iago8PC9UeXBlL1BhZ2VzL0tpZHNbNCAwIFJdL0NvdW50IDE+PgplbmRvYmoKeHJlZgowIDYKMDAwMDAwMDAwMCA2NTUzNSBmIAowMDAwMDAwMjUxIDAwMDAwIG4gCjAwMDAwMDAzMjIgMDAwMDAgbiAKMDAwMDAwMDM3MSAwMDAwMCBuIAowMDAwMDAwMDE1IDAwMDAwIG4gCjAwMDAwMDAxMTEgMDAwMDAgbiAKdHJhaWxlcgo8PC9TaXplIDYvUm9vdCAyIDAgUj4+CnN0YXJ0eHJlZgo0MjgKJSVFT0Y=';
            
            $url = $this->api_url . '/pdf/convert/to/text';
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'x-api-key' => $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'file' => $minimal_pdf_base64,
                    'inline' => true
                )),
                'timeout' => 15
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Connection failed: ' . $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            
            if (get_option('idoklad_debug_mode')) {
                error_log('PDF.co test response code: ' . $response_code);
            }
            
            if ($response_code === 200) {
                return array(
                    'success' => true,
                    'message' => 'Connection successful! PDF.co API is working.'
                );
            } elseif ($response_code === 401 || $response_code === 403) {
                return array(
                    'success' => false,
                    'message' => 'Invalid API key. Please check your PDF.co API key.'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'API test failed (' . $response_code . '): ' . ($data['message'] ?? 'Unknown error')
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Test PDF processing with response capture
     */
    public function test_pdf_processing($pdf_path) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found');
        }
        
        $start_time = microtime(true);
        
        // Upload
        $upload_start = microtime(true);
        $uploaded_url = $this->upload_file($pdf_path);
        $upload_time = round((microtime(true) - $upload_start) * 1000, 2);
        
        // Extract text
        $extract_start = microtime(true);
        $text = $this->extract_text_from_url($uploaded_url);
        $extract_time = round((microtime(true) - $extract_start) * 1000, 2);
        
        // Get metadata
        $metadata_start = microtime(true);
        $metadata = $this->get_metadata($pdf_path);
        $metadata_time = round((microtime(true) - $metadata_start) * 1000, 2);
        
        $total_time = round((microtime(true) - $start_time) * 1000, 2);
        
        return array(
            'text' => $text,
            'text_length' => strlen($text),
            'metadata' => $metadata,
            'uploaded_url' => $uploaded_url,
            'timings' => array(
                'upload_ms' => $upload_time,
                'extract_ms' => $extract_time,
                'metadata_ms' => $metadata_time,
                'total_ms' => $total_time
            )
        );
    }
}


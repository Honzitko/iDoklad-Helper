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
     * Extract text from PDF using PDF.co
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
            // First, upload the file to PDF.co
            $uploaded_url = $this->upload_file($pdf_path);
            
            if ($queue_id) {
                IDokladProcessor_Database::add_queue_step($queue_id, 'PDF.co: File uploaded successfully', array('url' => $uploaded_url), false);
            }
            
            // Try text extraction first (faster)
            $text = $this->extract_text_from_url($uploaded_url);
            
            // If text is too short, it might be a scanned PDF - use OCR
            if (strlen(trim($text)) < 100) {
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad PDF.co: Text extraction returned minimal text, trying OCR');
                }
                
                if ($queue_id) {
                    IDokladProcessor_Database::add_queue_step($queue_id, 'PDF.co: Minimal text found, switching to OCR', array('text_length' => strlen($text)), false);
                }
                
                $text = $this->ocr_pdf_from_url($uploaded_url);
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
     * Upload file to PDF.co temporary storage
     */
    private function upload_file($file_path) {
        $url = $this->api_url . '/file/upload/get-presigned-url';
        
        // Get file info
        $file_name = basename($file_path);
        
        // Request presigned URL
        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'name' => $file_name,
                'contenttype' => 'application/pdf'
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('PDF.co upload request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if ($response_code !== 200 || !isset($data['presignedUrl'])) {
            throw new Exception('PDF.co upload URL request failed: ' . ($data['message'] ?? $response_body));
        }
        
        // Upload file to presigned URL
        $file_content = file_get_contents($file_path);
        
        $upload_response = wp_remote_request($data['presignedUrl'], array(
            'method' => 'PUT',
            'headers' => array(
                'Content-Type' => 'application/pdf',
                'Content-Length' => strlen($file_content)
            ),
            'body' => $file_content,
            'timeout' => 60
        ));
        
        if (is_wp_error($upload_response)) {
            throw new Exception('PDF.co file upload failed: ' . $upload_response->get_error_message());
        }
        
        $upload_code = wp_remote_retrieve_response_code($upload_response);
        
        if ($upload_code !== 200) {
            throw new Exception('PDF.co file upload failed with status: ' . $upload_code);
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF.co: File uploaded successfully to ' . $data['url']);
        }
        
        return $data['url'];
    }
    
    /**
     * Extract text from PDF URL using PDF.co
     */
    private function extract_text_from_url($pdf_url) {
        $url = $this->api_url . '/pdf/convert/to/text';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'url' => $pdf_url,
                'inline' => true,
                'async' => false
            )),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('PDF.co text extraction request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF.co: Text extraction response: ' . $response_body);
        }
        
        if ($response_code !== 200) {
            throw new Exception('PDF.co text extraction failed: ' . ($data['message'] ?? $response_body));
        }
        
        if (!isset($data['body'])) {
            throw new Exception('PDF.co returned no text content');
        }
        
        return $data['body'];
    }
    
    /**
     * OCR PDF from URL using PDF.co
     */
    private function ocr_pdf_from_url($pdf_url) {
        $url = $this->api_url . '/pdf/convert/to/text';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'url' => $pdf_url,
                'inline' => true,
                'async' => false,
                'ocrLanguages' => 'ces,eng', // Czech and English
                'enableOCR' => true
            )),
            'timeout' => 180 // OCR can take longer
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('PDF.co OCR request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad PDF.co: OCR response: ' . substr($response_body, 0, 500));
        }
        
        if ($response_code !== 200) {
            throw new Exception('PDF.co OCR failed: ' . ($data['message'] ?? $response_body));
        }
        
        if (!isset($data['body'])) {
            throw new Exception('PDF.co OCR returned no text content');
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
     * Test PDF.co connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'PDF.co API key is not configured'
            );
        }
        
        try {
            // Test API key by checking account info
            $url = $this->api_url . '/account/credit-balance';
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'x-api-key' => $this->api_key
                ),
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
            
            if ($response_code === 200) {
                $credits = isset($data['credits']) ? $data['credits'] : 'Unknown';
                return array(
                    'success' => true,
                    'message' => 'Connection successful! Available credits: ' . $credits,
                    'credits' => $credits
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'API key invalid or API error: ' . ($data['message'] ?? $response_body)
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


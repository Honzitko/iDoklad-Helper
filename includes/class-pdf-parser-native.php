<?php
/**
 * Native PHP PDF Parser - No external dependencies or APIs
 * This class provides pure PHP-based PDF text extraction
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_NativePDFParser {
    
    private $pdf_path;
    private $pdf_content;
    private $objects = array();
    private $pages = array();
    
    /**
     * Extract text from PDF using pure PHP
     */
    public function extract_text($pdf_path) {
        if (!file_exists($pdf_path)) {
            throw new Exception('PDF file not found: ' . $pdf_path);
        }
        
        $this->pdf_path = $pdf_path;
        $this->pdf_content = file_get_contents($pdf_path);
        
        if (empty($this->pdf_content)) {
            throw new Exception('PDF file is empty or unreadable');
        }
        
        // Check if it's a valid PDF
        if (substr($this->pdf_content, 0, 4) !== '%PDF') {
            throw new Exception('Invalid PDF file format');
        }
        
        try {
            // Parse PDF structure
            $this->parse_objects();
            $this->parse_pages();
            
            // Extract text from all pages
            $text = $this->extract_text_from_pages();
            
            if (empty($text)) {
                throw new Exception('No text could be extracted from PDF');
            }
            
            return $this->clean_text($text);
            
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad Native PDF Parser Error: ' . $e->getMessage());
            }
            throw $e;
        }
    }
    
    /**
     * Parse PDF objects
     */
    private function parse_objects() {
        // Match all PDF objects (pattern: "n n obj ... endobj")
        preg_match_all('/(\d+)\s+(\d+)\s+obj\s*(.*?)\s*endobj/s', $this->pdf_content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $obj_num = $match[1];
            $obj_content = $match[3];
            
            $this->objects[$obj_num] = array(
                'content' => $obj_content,
                'raw' => $match[0]
            );
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Native PDF Parser: Found ' . count($this->objects) . ' objects');
        }
    }
    
    /**
     * Parse pages from PDF
     */
    private function parse_pages() {
        // Find the catalog and pages
        foreach ($this->objects as $obj_num => $obj_data) {
            $content = $obj_data['content'];
            
            // Check if this is a Page object
            if (preg_match('/\/Type\s*\/Page[^s]/', $content)) {
                // Extract content stream reference
                if (preg_match('/\/Contents\s+(\d+)\s+\d+\s+R/', $content, $matches)) {
                    $content_obj_num = $matches[1];
                    
                    if (isset($this->objects[$content_obj_num])) {
                        $this->pages[] = array(
                            'page_obj' => $obj_num,
                            'content_obj' => $content_obj_num,
                            'content' => $this->objects[$content_obj_num]['content']
                        );
                    }
                } elseif (preg_match('/\/Contents\s*\[([^\]]+)\]/', $content, $matches)) {
                    // Multiple content streams
                    $content_refs = $matches[1];
                    preg_match_all('/(\d+)\s+\d+\s+R/', $content_refs, $ref_matches);
                    
                    $combined_content = '';
                    foreach ($ref_matches[1] as $ref_num) {
                        if (isset($this->objects[$ref_num])) {
                            $combined_content .= $this->objects[$ref_num]['content'] . "\n";
                        }
                    }
                    
                    if (!empty($combined_content)) {
                        $this->pages[] = array(
                            'page_obj' => $obj_num,
                            'content_obj' => 'multiple',
                            'content' => $combined_content
                        );
                    }
                }
            }
        }
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Native PDF Parser: Found ' . count($this->pages) . ' pages');
        }
    }
    
    /**
     * Extract text from all pages
     */
    private function extract_text_from_pages() {
        $all_text = '';
        
        foreach ($this->pages as $page) {
            $page_text = $this->extract_text_from_content_stream($page['content']);
            
            if (!empty($page_text)) {
                $all_text .= $page_text . "\n\n";
            }
        }
        
        return $all_text;
    }
    
    /**
     * Extract text from a content stream
     */
    private function extract_text_from_content_stream($content) {
        // Decompress stream if needed
        $content = $this->decompress_stream($content);
        
        $text = '';
        
        // Method 1: Extract text between parentheses (most common)
        // PDF text operators: Tj, TJ, ', "
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $text .= $this->decode_text_array($match) . ' ';
            }
        }
        
        if (preg_match_all('/\((.*?)\)\s*Tj/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $text .= $this->decode_pdf_string($match) . ' ';
            }
        }
        
        // Method 2: Extract from single quote operator
        if (preg_match_all('/\((.*?)\)\s*\'/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $text .= $this->decode_pdf_string($match) . ' ';
            }
        }
        
        // Method 3: Extract from double quote operator
        if (preg_match_all('/\((.*?)\)\s*"/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $text .= $this->decode_pdf_string($match) . ' ';
            }
        }
        
        // Method 4: Hexadecimal strings
        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*Tj/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $text .= $this->decode_hex_string($match) . ' ';
            }
        }
        
        return $text;
    }
    
    /**
     * Decompress stream data
     */
    private function decompress_stream($content) {
        // Check if stream is compressed
        if (preg_match('/\/Filter\s*\/FlateDecode/', $content)) {
            // Extract the stream data
            if (preg_match('/stream\s*(.*?)\s*endstream/s', $content, $matches)) {
                $compressed_data = trim($matches[1]);
                
                // Try to decompress
                $decompressed = @gzuncompress($compressed_data);
                
                if ($decompressed === false) {
                    // Try alternative decompression
                    $decompressed = @gzinflate($compressed_data);
                }
                
                if ($decompressed !== false) {
                    // Replace compressed stream with decompressed data
                    $content = str_replace($matches[0], 'stream ' . $decompressed . ' endstream', $content);
                } else {
                    if (get_option('idoklad_debug_mode')) {
                        error_log('iDoklad Native PDF Parser: Failed to decompress stream');
                    }
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Decode PDF text array (used with TJ operator)
     */
    private function decode_text_array($array_content) {
        $text = '';
        
        // Extract strings from array
        preg_match_all('/\((.*?)\)/', $array_content, $matches);
        
        foreach ($matches[1] as $match) {
            $text .= $this->decode_pdf_string($match);
        }
        
        // Also check for hex strings in array
        preg_match_all('/<([0-9A-Fa-f]+)>/', $array_content, $hex_matches);
        
        foreach ($hex_matches[1] as $match) {
            $text .= $this->decode_hex_string($match);
        }
        
        return $text;
    }
    
    /**
     * Decode PDF string
     */
    private function decode_pdf_string($string) {
        // Handle escape sequences
        $string = stripcslashes($string);
        
        // Handle special PDF escape sequences
        $string = str_replace('\\n', "\n", $string);
        $string = str_replace('\\r', "\r", $string);
        $string = str_replace('\\t', "\t", $string);
        
        // Convert from PDF encoding to UTF-8 if needed
        $string = $this->convert_encoding($string);
        
        return $string;
    }
    
    /**
     * Decode hexadecimal string
     */
    private function decode_hex_string($hex) {
        $text = '';
        
        // Convert hex pairs to characters
        for ($i = 0; $i < strlen($hex); $i += 2) {
            $byte = substr($hex, $i, 2);
            $text .= chr(hexdec($byte));
        }
        
        return $this->convert_encoding($text);
    }
    
    /**
     * Convert text encoding to UTF-8
     */
    private function convert_encoding($text) {
        // Try to detect and convert encoding
        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($text, array('UTF-8', 'ISO-8859-1', 'ISO-8859-2', 'Windows-1252', 'ASCII'), true);
            
            if ($encoding && $encoding !== 'UTF-8') {
                if (function_exists('mb_convert_encoding')) {
                    $text = mb_convert_encoding($text, 'UTF-8', $encoding);
                } elseif (function_exists('iconv')) {
                    $text = iconv($encoding, 'UTF-8//IGNORE', $text);
                }
            }
        }
        
        return $text;
    }
    
    /**
     * Clean extracted text
     */
    private function clean_text($text) {
        // Remove excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Normalize line breaks
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        
        // Remove excessive line breaks
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Remove control characters except newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Trim whitespace
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Get PDF metadata
     */
    public function get_metadata($pdf_path) {
        if (!file_exists($pdf_path)) {
            return array();
        }
        
        $content = file_get_contents($pdf_path);
        $metadata = array();
        
        // Extract title
        if (preg_match('/\/Title\s*\((.*?)\)/', $content, $matches)) {
            $metadata['title'] = $this->decode_pdf_string($matches[1]);
        }
        
        // Extract author
        if (preg_match('/\/Author\s*\((.*?)\)/', $content, $matches)) {
            $metadata['author'] = $this->decode_pdf_string($matches[1]);
        }
        
        // Extract subject
        if (preg_match('/\/Subject\s*\((.*?)\)/', $content, $matches)) {
            $metadata['subject'] = $this->decode_pdf_string($matches[1]);
        }
        
        // Extract creation date
        if (preg_match('/\/CreationDate\s*\((.*?)\)/', $content, $matches)) {
            $metadata['creation_date'] = $matches[1];
        }
        
        // Extract PDF version
        if (preg_match('/%PDF-(\d+\.\d+)/', $content, $matches)) {
            $metadata['pdf_version'] = $matches[1];
        }
        
        return $metadata;
    }
    
    /**
     * Get number of pages
     */
    public function get_page_count($pdf_path) {
        if (!file_exists($pdf_path)) {
            return 0;
        }
        
        $content = file_get_contents($pdf_path);
        
        // Try to find page count in catalog
        if (preg_match('/\/Count\s+(\d+)/', $content, $matches)) {
            return (int)$matches[1];
        }
        
        // Count page objects
        $page_count = preg_match_all('/\/Type\s*\/Page[^s]/', $content);
        
        return $page_count;
    }
}


<?php
/**
 * Temporary placeholder for deleted class-idoklad-data-transformer.php
 * This file should not be referenced anymore, but exists to prevent fatal errors
 * during cache clearing on the server.
 * 
 * If you see this file being loaded, there is a caching issue on your server.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Temporary placeholder class - not functional
class IDokladProcessor_DataTransformer {
    
    public function __construct() {
        error_log('WARNING: Old DataTransformer class is being loaded. This indicates a server caching issue. Please clear your WordPress object cache and opcache.');
    }
    
    public function transform_to_idoklad($data, $pdf_text = '') {
        throw new Exception('DataTransformer class is deprecated. Please use IDokladProcessor_PDFCoAIParserEnhanced instead.');
    }
    
    public function validate_idoklad_payload($data) {
        throw new Exception('DataTransformer class is deprecated. Please use IDokladProcessor_PDFCoAIParserEnhanced instead.');
    }
}


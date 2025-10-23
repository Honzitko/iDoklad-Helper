<?php
/**
 * Test script for complete iDoklad API v3 Integration
 * This script demonstrates the full workflow as specified
 */

// Include WordPress functions (adjust path as needed)
if (!defined('ABSPATH')) {
    // For testing outside WordPress, define basic functions
    if (!function_exists('wp_remote_request')) {
        function wp_remote_request($url, $args = array()) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            if (isset($args['method'])) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $args['method']);
            }
            
            if (isset($args['headers'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $args['headers']);
            }
            
            if (isset($args['body'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']);
            }
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return array(
                'body' => $response,
                'response' => array('code' => $http_code)
            );
        }
        
        function wp_remote_retrieve_response_code($response) {
            return $response['response']['code'];
        }
        
        function wp_remote_retrieve_body($response) {
            return $response['body'];
        }
        
        function is_wp_error($response) {
            return false;
        }
    }
    
    // Simple logger for testing
    class IDokladProcessor_Logger {
        public function log($message, $level = 'info') {
            echo "[" . strtoupper($level) . "] " . date('Y-m-d H:i:s') . " - " . $message . "\n";
        }
    }
}

// Include the integration class
require_once 'includes/class-idoklad-api-v3-integration.php';

// Test configuration - Update these with your actual credentials
$client_id = 'YOUR_CLIENT_ID'; // Replace with your actual client ID
$client_secret = 'YOUR_CLIENT_SECRET'; // Replace with your actual client secret

// Environment variables (matching Postman collection)
$base_url = 'https://api.idoklad.cz/v3';
$token_url = 'https://identity.idoklad.cz/server/connect/token';

echo "=== iDoklad API v3 Complete Integration Test ===\n\n";

try {
    // Initialize the integration
    $integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
    
    echo "Integration initialized successfully.\n";
    echo "Client ID: " . substr($client_id, 0, 8) . "...\n\n";
    
    // Test the complete workflow
    echo "Starting complete integration workflow...\n\n";
    
    $result = $integration->test_integration();
    
    echo "\n=== INTEGRATION SUCCESSFUL ===\n";
    echo "Invoice ID: " . $result['invoice_id'] . "\n";
    echo "Document Number: " . $result['document_number'] . "\n";
    echo "Status Code: " . $result['status_code'] . "\n";
    
    if (isset($result['response_data']['Data']['Message'])) {
        echo "Message: " . $result['response_data']['Data']['Message'] . "\n";
    }
    
    echo "\n=== FULL RESPONSE ===\n";
    echo json_encode($result['response_data'], JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "\n=== INTEGRATION FAILED ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "1. Client ID and Client Secret are correct\n";
    echo "2. API credentials have proper permissions\n";
    echo "3. Internet connection is working\n";
    echo "4. iDoklad API is accessible\n";
}

echo "\n=== Test completed ===\n";

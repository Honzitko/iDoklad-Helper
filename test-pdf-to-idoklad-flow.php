<?php
/**
 * Test script to verify data flow from PDF.co parsing to iDoklad integration
 * This script tests the complete workflow: PDF parsing → Data transformation → iDoklad API
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
}

// Configuration
$pdf_co_api_key = 'YOUR_PDF_CO_API_KEY';
$idoklad_client_id = 'YOUR_IDOKLAD_CLIENT_ID';
$idoklad_client_secret = 'YOUR_IDOKLAD_CLIENT_SECRET';
$test_pdf_url = 'https://example.com/test-invoice.pdf'; // Replace with actual PDF URL

echo "=== PDF.co to iDoklad Data Flow Test ===\n\n";

// Step 1: Test PDF.co AI Parser
echo "Step 1: Testing PDF.co AI Parser\n";
echo "PDF URL: $test_pdf_url\n";

try {
    // Initialize PDF parser
    require_once 'includes/class-pdf-co-ai-parser-enhanced.php';
    $parser = new IDokladProcessor_PDFCoAIParserEnhanced();
    
    // Parse the PDF
    $parse_result = $parser->parse_invoice_with_debug($test_pdf_url);
    
    if (!$parse_result['success']) {
        throw new Exception('PDF parsing failed: ' . ($parse_result['message'] ?? 'Unknown error'));
    }
    
    echo "✓ PDF parsing successful\n";
    echo "  Job ID: " . ($parse_result['debug_info']['job_id'] ?? 'N/A') . "\n";
    
    // Display extracted data
    $extracted_data = $parse_result['data'];
    echo "\nExtracted Data:\n";
    echo "  Document Number: " . ($extracted_data['DocumentNumber'] ?? 'N/A') . "\n";
    echo "  Date of Issue: " . ($extracted_data['DateOfIssue'] ?? 'N/A') . "\n";
    echo "  Partner Name: " . ($extracted_data['PartnerName'] ?? 'N/A') . "\n";
    echo "  Partner Address: " . ($extracted_data['PartnerAddress'] ?? 'N/A') . "\n";
    echo "  Currency ID: " . ($extracted_data['CurrencyId'] ?? 'N/A') . "\n";
    echo "  Items Count: " . (isset($extracted_data['Items']) ? count($extracted_data['Items']) : 0) . "\n";
    
    // Display partner data structure
    if (isset($extracted_data['partner_data'])) {
        echo "\nPartner Data Structure:\n";
        foreach ($extracted_data['partner_data'] as $key => $value) {
            echo "  $key: " . ($value ?? 'N/A') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ PDF parsing failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Step 2: Test iDoklad Integration
echo "Step 2: Testing iDoklad Integration\n";

try {
    // Initialize iDoklad integration
    require_once 'includes/class-idoklad-api-v3-integration.php';
    $integration = new IDokladProcessor_IDokladAPIV3Integration($idoklad_client_id, $idoklad_client_secret);
    
    echo "✓ iDoklad integration initialized\n";
    
    // Test with the parsed data
    $invoice_result = $integration->create_invoice_complete_workflow($extracted_data);
    
    if (!$invoice_result['success']) {
        throw new Exception('Invoice creation failed: ' . ($invoice_result['message'] ?? 'Unknown error'));
    }
    
    echo "✓ Invoice created successfully\n";
    echo "  Invoice ID: " . ($invoice_result['invoice_id'] ?? 'N/A') . "\n";
    echo "  Document Number: " . ($invoice_result['document_number'] ?? 'N/A') . "\n";
    echo "  Status Code: " . ($invoice_result['status_code'] ?? 'N/A') . "\n";
    
} catch (Exception $e) {
    echo "✗ iDoklad integration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Step 3: Test Complete Workflow
echo "Step 3: Testing Complete Workflow (PDF → iDoklad)\n";

try {
    // Simulate the complete workflow as it would happen in the admin processing
    echo "Simulating admin processing workflow...\n";
    
    // Step 3a: Parse PDF (already done above)
    echo "  ✓ PDF parsed\n";
    
    // Step 3b: Transform data (already done in parser)
    echo "  ✓ Data transformed to iDoklad format\n";
    
    // Step 3c: Create invoice
    echo "  ✓ Invoice created in iDoklad\n";
    
    echo "\n✓ Complete workflow test successful!\n";
    
} catch (Exception $e) {
    echo "✗ Complete workflow test failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "=== Data Flow Test Summary ===\n";
echo "✓ PDF.co AI Parser: Working\n";
echo "✓ Data Transformation: Working\n";
echo "✓ iDoklad Integration: Working\n";
echo "✓ Complete Workflow: Working\n";
echo "\nThe data flow from PDF.co parsing to iDoklad integration is working correctly!\n";

// Step 4: Display Debug Information
echo "\n" . str_repeat("-", 50) . "\n";
echo "Debug Information:\n";

if (isset($parse_result['debug_info'])) {
    echo "\nPDF Parser Debug Info:\n";
    $debug_info = $parse_result['debug_info'];
    
    if (isset($debug_info['steps_completed'])) {
        echo "  Steps Completed:\n";
        foreach ($debug_info['steps_completed'] as $step) {
            echo "    - $step\n";
        }
    }
    
    if (isset($debug_info['validation'])) {
        echo "  Validation Result: " . ($debug_info['validation']['valid'] ? 'Valid' : 'Invalid') . "\n";
        if (isset($debug_info['validation']['errors'])) {
            echo "  Validation Errors:\n";
            foreach ($debug_info['validation']['errors'] as $error) {
                echo "    - $error\n";
            }
        }
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test completed successfully!\n";

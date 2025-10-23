<?php
/**
 * Test script to verify the complete workflow from PDF parsing to iDoklad integration
 */

// Load WordPress
require_once('../../../wp-config.php');

echo "=== Complete Workflow Test ===\n\n";

try {
    // Check if credentials are configured
    $client_id = get_option('idoklad_client_id');
    $client_secret = get_option('idoklad_client_secret');
    $pdf_co_api_key = get_option('idoklad_pdfco_api_key');
    
    echo "1. Checking configuration...\n";
    if (empty($client_id)) {
        echo "❌ iDoklad Client ID not configured\n";
        exit;
    } else {
        echo "✅ iDoklad Client ID configured: " . substr($client_id, 0, 8) . "...\n";
    }
    
    if (empty($client_secret)) {
        echo "❌ iDoklad Client Secret not configured\n";
        exit;
    } else {
        echo "✅ iDoklad Client Secret configured: " . substr($client_secret, 0, 8) . "...\n";
    }
    
    if (empty($pdf_co_api_key)) {
        echo "❌ PDF.co API key not configured\n";
        exit;
    } else {
        echo "✅ PDF.co API key configured: " . substr($pdf_co_api_key, 0, 8) . "...\n";
    }
    
    echo "\n2. Testing iDoklad API integration...\n";
    
    // Test iDoklad integration
    require_once 'includes/class-idoklad-api-v3-integration.php';
    $integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
    
    // Test with sample data
    $test_invoice_data = array(
        'description' => 'Test invoice from workflow test',
        'note' => 'Testing complete workflow',
        'items' => array(
            array(
                'Name' => 'Test service',
                'Unit' => 'hour',
                'Amount' => 1.0,
                'UnitPrice' => 100.0,
                'PriceType' => 1,
                'VatRateType' => 2,
                'VatRate' => 0.0,
                'IsTaxMovement' => false,
                'DiscountPercentage' => 0.0
            )
        )
    );
    
    echo "Creating test invoice...\n";
    $result = $integration->create_invoice_complete_workflow($test_invoice_data);
    
    if ($result['success']) {
        echo "✅ iDoklad integration test successful!\n";
        echo "   Invoice ID: " . $result['invoice_id'] . "\n";
        echo "   Document Number: " . $result['document_number'] . "\n";
        echo "   Status Code: " . $result['status_code'] . "\n";
    } else {
        echo "❌ iDoklad integration test failed: " . $result['message'] . "\n";
    }
    
    echo "\n3. Testing PDF.co parser...\n";
    
    // Test PDF.co parser
    require_once 'includes/class-pdf-co-ai-parser-enhanced.php';
    $parser = new IDokladProcessor_PDFCoAIParserEnhanced();
    
    // Use a test PDF URL (you can replace this with an actual PDF URL)
    $test_pdf_url = 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf';
    
    echo "Testing PDF.co parser with test PDF...\n";
    $parse_result = $parser->parse_invoice_with_debug($test_pdf_url);
    
    if ($parse_result['success']) {
        echo "✅ PDF.co parser test successful!\n";
        echo "   Parsed data keys: " . implode(', ', array_keys($parse_result['data'])) . "\n";
    } else {
        echo "❌ PDF.co parser test failed: " . $parse_result['message'] . "\n";
    }
    
    echo "\n4. Testing complete workflow...\n";
    
    if ($parse_result['success']) {
        echo "Testing complete workflow with parsed data...\n";
        $workflow_result = $integration->create_invoice_complete_workflow($parse_result['data']);
        
        if ($workflow_result['success']) {
            echo "✅ Complete workflow test successful!\n";
            echo "   Invoice ID: " . $workflow_result['invoice_id'] . "\n";
            echo "   Document Number: " . $workflow_result['document_number'] . "\n";
        } else {
            echo "❌ Complete workflow test failed: " . $workflow_result['message'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Test failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";

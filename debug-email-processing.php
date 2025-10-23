<?php
/**
 * Debug script to test email processing workflow step by step
 */

// Load WordPress
require_once('../../../wp-config.php');

echo "=== Email Processing Debug ===\n\n";

try {
    // Check if we have any queue items to process
    $database = new IDokladProcessor_Database();
    $queue_items = $database->get_queue_items('completed', 1);
    
    if (empty($queue_items)) {
        echo "No completed queue items found. Let's check for failed items...\n";
        $queue_items = $database->get_queue_items('failed', 1);
    }
    
    if (empty($queue_items)) {
        echo "No queue items found to test with.\n";
        echo "Please process an email first or create a test queue item.\n";
        exit;
    }
    
    $item = (array) $queue_items[0];
    echo "Testing with queue item ID: " . $item['id'] . "\n";
    echo "Status: " . $item['status'] . "\n";
    echo "Attachment path: " . ($item['attachment_path'] ?? 'None') . "\n\n";
    
    // Test Step 1: Check if PDF file exists
    echo "Step 1: Checking PDF file...\n";
    if (empty($item['attachment_path']) || !file_exists($item['attachment_path'])) {
        echo "❌ PDF attachment not found: " . ($item['attachment_path'] ?? 'No path') . "\n";
        exit;
    } else {
        echo "✅ PDF file exists: " . $item['attachment_path'] . "\n";
    }
    
    // Test Step 2: Convert to accessible URL
    echo "\nStep 2: Converting to accessible URL...\n";
    $upload_dir = wp_upload_dir();
    $upload_path = $upload_dir['basedir'];
    $upload_url = $upload_dir['baseurl'];
    
    $pdf_url = null;
    if (filter_var($item['attachment_path'], FILTER_VALIDATE_URL)) {
        $pdf_url = $item['attachment_path'];
        echo "✅ Already a URL: " . $pdf_url . "\n";
    } elseif (strpos($item['attachment_path'], $upload_path) === 0) {
        $relative_path = str_replace($upload_path, '', $item['attachment_path']);
        $pdf_url = $upload_url . $relative_path;
        echo "✅ Converted to URL: " . $pdf_url . "\n";
    } else {
        // Create temp URL
        $file_name = basename($item['attachment_path']);
        $temp_url = $upload_url . '/idoklad-temp/' . $file_name;
        $temp_dir = $upload_path . '/idoklad-temp/';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $temp_path = $temp_dir . $file_name;
        if (!file_exists($temp_path)) {
            copy($item['attachment_path'], $temp_path);
        }
        
        $pdf_url = $temp_url;
        echo "✅ Created temp URL: " . $pdf_url . "\n";
    }
    
    // Test Step 3: Check PDF.co API key
    echo "\nStep 3: Checking PDF.co API key...\n";
    $pdf_co_api_key = get_option('idoklad_pdfco_api_key');
    if (empty($pdf_co_api_key)) {
        echo "❌ PDF.co API key not configured\n";
        exit;
    } else {
        echo "✅ PDF.co API key configured: " . substr($pdf_co_api_key, 0, 8) . "...\n";
    }
    
    // Test Step 4: Test PDF.co parser
    echo "\nStep 4: Testing PDF.co parser...\n";
    require_once 'includes/class-pdf-co-ai-parser-enhanced.php';
    $parser = new IDokladProcessor_PDFCoAIParserEnhanced();
    
    try {
        $parse_result = $parser->parse_invoice_with_debug($pdf_url);
        
        if ($parse_result['success']) {
            echo "✅ PDF.co parsing successful!\n";
            echo "   Parsed data keys: " . implode(', ', array_keys($parse_result['data'])) . "\n";
            
            // Show some key data
            if (isset($parse_result['data']['DocumentNumber'])) {
                echo "   Document Number: " . $parse_result['data']['DocumentNumber'] . "\n";
            }
            if (isset($parse_result['data']['DateOfIssue'])) {
                echo "   Date of Issue: " . $parse_result['data']['DateOfIssue'] . "\n";
            }
            if (isset($parse_result['data']['partner_data']['company'])) {
                echo "   Company: " . $parse_result['data']['partner_data']['company'] . "\n";
            }
        } else {
            echo "❌ PDF.co parsing failed: " . $parse_result['message'] . "\n";
            exit;
        }
    } catch (Exception $e) {
        echo "❌ PDF.co parsing exception: " . $e->getMessage() . "\n";
        exit;
    }
    
    // Test Step 5: Check iDoklad credentials
    echo "\nStep 5: Checking iDoklad credentials...\n";
    $client_id = get_option('idoklad_client_id');
    $client_secret = get_option('idoklad_client_secret');
    
    if (empty($client_id) || empty($client_secret)) {
        echo "❌ iDoklad API credentials not configured\n";
        exit;
    } else {
        echo "✅ iDoklad credentials configured\n";
    }
    
    // Test Step 6: Test iDoklad integration
    echo "\nStep 6: Testing iDoklad integration...\n";
    require_once 'includes/class-idoklad-api-v3-integration.php';
    $integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
    
    try {
        $invoice_result = $integration->create_invoice_complete_workflow($parse_result['data']);
        
        if ($invoice_result['success']) {
            echo "✅ iDoklad integration successful!\n";
            echo "   Invoice ID: " . $invoice_result['invoice_id'] . "\n";
            echo "   Document Number: " . $invoice_result['document_number'] . "\n";
            echo "   Status Code: " . $invoice_result['status_code'] . "\n";
        } else {
            echo "❌ iDoklad integration failed: " . $invoice_result['message'] . "\n";
        }
    } catch (Exception $e) {
        echo "❌ iDoklad integration exception: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Debug failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";

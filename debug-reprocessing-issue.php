<?php
/**
 * Debug script to identify reprocessing issues
 */

echo "=== Reprocessing Debug ===\n\n";

// Test 1: Check if database methods exist
echo "Test 1: Database methods\n";
try {
    require_once 'includes/class-database.php';
    $database = new IDokladProcessor_Database();
    
    if (method_exists($database, 'get_queue_item')) {
        echo "✓ get_queue_item method exists\n";
    } else {
        echo "✗ get_queue_item method missing\n";
    }
    
    if (method_exists($database, 'update_queue_item')) {
        echo "✓ update_queue_item method exists\n";
    } else {
        echo "✗ update_queue_item method missing\n";
    }
    
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

// Test 2: Check iDoklad API credentials
echo "\nTest 2: iDoklad API credentials\n";
$client_id = get_option('idoklad_client_id');
$client_secret = get_option('idoklad_client_secret');

if (empty($client_id)) {
    echo "✗ iDoklad Client ID not configured\n";
} else {
    echo "✓ iDoklad Client ID configured: " . substr($client_id, 0, 8) . "...\n";
}

if (empty($client_secret)) {
    echo "✗ iDoklad Client Secret not configured\n";
} else {
    echo "✓ iDoklad Client Secret configured: " . substr($client_secret, 0, 8) . "...\n";
}

// Test 3: Check PDF.co API key
echo "\nTest 3: PDF.co API key\n";
$pdf_co_api_key = get_option('idoklad_pdfco_api_key');

if (empty($pdf_co_api_key)) {
    echo "✗ PDF.co API key not configured\n";
} else {
    echo "✓ PDF.co API key configured: " . substr($pdf_co_api_key, 0, 8) . "...\n";
}

// Test 4: Check if required classes exist
echo "\nTest 4: Required classes\n";
$required_classes = array(
    'IDokladProcessor_PDFCoAIParserEnhanced',
    'IDokladProcessor_IDokladAPIV3Integration'
);

foreach ($required_classes as $class) {
    if (class_exists($class)) {
        echo "✓ $class exists\n";
    } else {
        echo "✗ $class missing\n";
    }
}

// Test 5: Check queue items
echo "\nTest 5: Queue items\n";
try {
    global $wpdb;
    $table = $wpdb->prefix . 'idoklad_queue';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    echo "✓ Queue table exists with $count items\n";
    
    // Check for completed/failed items
    $completed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'completed'");
    $failed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'");
    echo "  - Completed items: $completed\n";
    echo "  - Failed items: $failed\n";
    
} catch (Exception $e) {
    echo "✗ Queue table error: " . $e->getMessage() . "\n";
}

// Test 6: Check WordPress functions
echo "\nTest 6: WordPress functions\n";
$required_functions = array(
    'get_option',
    'current_time',
    'wp_upload_dir'
);

foreach ($required_functions as $function) {
    if (function_exists($function)) {
        echo "✓ $function exists\n";
    } else {
        echo "✗ $function missing\n";
    }
}

echo "\n=== Debug Complete ===\n";
echo "\nCommon issues:\n";
echo "1. Missing iDoklad API credentials\n";
echo "2. Missing PDF.co API key\n";
echo "3. Queue items not in reprocessable state (completed/failed)\n";
echo "4. PDF files not accessible\n";
echo "5. Database connection issues\n";

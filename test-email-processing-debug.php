<?php
/**
 * Debug script for email processing page issues
 */

echo "=== Email Processing Debug ===\n\n";

// Test 1: Check if database class can be instantiated
echo "Test 1: Database class instantiation\n";
try {
    require_once 'includes/class-database.php';
    $database = new IDokladProcessor_Database();
    echo "✓ Database class instantiated successfully\n";
} catch (Exception $e) {
    echo "✗ Database class error: " . $e->getMessage() . "\n";
}

// Test 2: Check if get_queue_statistics method exists
echo "\nTest 2: get_queue_statistics method\n";
try {
    if (method_exists($database, 'get_queue_statistics')) {
        $stats = $database->get_queue_statistics();
        echo "✓ get_queue_statistics method exists and works\n";
        echo "  Stats: " . json_encode($stats) . "\n";
    } else {
        echo "✗ get_queue_statistics method does not exist\n";
    }
} catch (Exception $e) {
    echo "✗ get_queue_statistics error: " . $e->getMessage() . "\n";
}

// Test 3: Check if template file exists
echo "\nTest 3: Template file\n";
$template_file = 'templates/admin-email-processing.php';
if (file_exists($template_file)) {
    echo "✓ Template file exists: $template_file\n";
} else {
    echo "✗ Template file missing: $template_file\n";
}

// Test 4: Check if JavaScript file exists
echo "\nTest 4: JavaScript file\n";
$js_file = 'assets/email-processing.js';
if (file_exists($js_file)) {
    echo "✓ JavaScript file exists: $js_file\n";
} else {
    echo "✗ JavaScript file missing: $js_file\n";
}

// Test 5: Check WordPress functions
echo "\nTest 5: WordPress functions\n";
if (function_exists('get_option')) {
    echo "✓ get_option function exists\n";
} else {
    echo "✗ get_option function missing\n";
}

if (function_exists('wp_next_scheduled')) {
    echo "✓ wp_next_scheduled function exists\n";
} else {
    echo "✗ wp_next_scheduled function missing\n";
}

echo "\n=== Debug Complete ===\n";

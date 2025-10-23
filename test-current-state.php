<?php
/**
 * Test script to check current state and find any remaining references
 */

echo "=== Checking Current State ===\n\n";

// Check if the deleted file exists
$deleted_file = 'includes/class-idoklad-data-transformer.php';
if (file_exists($deleted_file)) {
    echo "ERROR: The deleted file still exists: $deleted_file\n";
} else {
    echo "✓ Deleted file does not exist: $deleted_file\n";
}

// Check for any remaining references in PHP files
echo "\n=== Searching for remaining references ===\n";

$php_files = glob('includes/*.php');
foreach ($php_files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'class-idoklad-data-transformer') !== false) {
        echo "FOUND REFERENCE in: $file\n";
        // Find the line number
        $lines = explode("\n", $content);
        foreach ($lines as $line_num => $line) {
            if (strpos($line, 'class-idoklad-data-transformer') !== false) {
                echo "  Line " . ($line_num + 1) . ": " . trim($line) . "\n";
            }
        }
    }
}

// Check main plugin file
$main_file = 'idoklad-invoice-processor.php';
if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    if (strpos($content, 'class-idoklad-data-transformer') !== false) {
        echo "FOUND REFERENCE in: $main_file\n";
        $lines = explode("\n", $content);
        foreach ($lines as $line_num => $line) {
            if (strpos($line, 'class-idoklad-data-transformer') !== false) {
                echo "  Line " . ($line_num + 1) . ": " . trim($line) . "\n";
            }
        }
    }
}

echo "\n=== Test Complete ===\n";

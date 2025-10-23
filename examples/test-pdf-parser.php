<?php
/**
 * Example: How to use the Native PDF Parser
 * 
 * This file demonstrates how to use the internal PDF parsing capabilities
 * of the iDoklad Helper plugin without any external APIs.
 */

// This file should only be loaded in WordPress context
if (!defined('ABSPATH')) {
    // For standalone testing, you can manually require WordPress
    // require_once('/path/to/wordpress/wp-load.php');
    exit('This file must be run within WordPress context');
}

// Ensure the plugin files are loaded
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-processor.php';
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-parser-native.php';

/**
 * Example 1: Basic text extraction
 */
function example_basic_extraction() {
    echo "<h2>Example 1: Basic Text Extraction</h2>";
    
    // Path to your PDF file
    $pdf_path = '/path/to/your/invoice.pdf';
    
    try {
        // Create PDF processor instance
        $pdf_processor = new IDokladProcessor_PDFProcessor();
        
        // Extract text from PDF
        $text = $pdf_processor->extract_text($pdf_path);
        
        echo "<p><strong>Extracted text:</strong></p>";
        echo "<pre>" . esc_html($text) . "</pre>";
        
        echo "<p><strong>Text length:</strong> " . strlen($text) . " characters</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . esc_html($e->getMessage()) . "</p>";
    }
}

/**
 * Example 2: Get PDF metadata
 */
function example_get_metadata() {
    echo "<h2>Example 2: Get PDF Metadata</h2>";
    
    $pdf_path = '/path/to/your/invoice.pdf';
    
    try {
        $pdf_processor = new IDokladProcessor_PDFProcessor();
        
        // Get PDF metadata
        $metadata = $pdf_processor->get_metadata($pdf_path);
        
        echo "<p><strong>PDF Metadata:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Title:</strong> " . esc_html($metadata['title'] ?? 'N/A') . "</li>";
        echo "<li><strong>Author:</strong> " . esc_html($metadata['author'] ?? 'N/A') . "</li>";
        echo "<li><strong>Subject:</strong> " . esc_html($metadata['subject'] ?? 'N/A') . "</li>";
        echo "<li><strong>PDF Version:</strong> " . esc_html($metadata['pdf_version'] ?? 'N/A') . "</li>";
        echo "<li><strong>Creation Date:</strong> " . esc_html($metadata['creation_date'] ?? 'N/A') . "</li>";
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . esc_html($e->getMessage()) . "</p>";
    }
}

/**
 * Example 3: Get page count
 */
function example_get_page_count() {
    echo "<h2>Example 3: Get Page Count</h2>";
    
    $pdf_path = '/path/to/your/invoice.pdf';
    
    try {
        $pdf_processor = new IDokladProcessor_PDFProcessor();
        
        // Get page count
        $page_count = $pdf_processor->get_page_count($pdf_path);
        
        echo "<p><strong>Number of pages:</strong> " . $page_count . "</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . esc_html($e->getMessage()) . "</p>";
    }
}

/**
 * Example 4: Get complete PDF information
 */
function example_get_pdf_info() {
    echo "<h2>Example 4: Get Complete PDF Information</h2>";
    
    $pdf_path = '/path/to/your/invoice.pdf';
    
    try {
        $pdf_processor = new IDokladProcessor_PDFProcessor();
        
        // Get complete PDF info
        $info = $pdf_processor->get_pdf_info($pdf_path);
        
        echo "<p><strong>File Information:</strong></p>";
        echo "<ul>";
        echo "<li><strong>File Size:</strong> " . esc_html($info['file_size_formatted']) . "</li>";
        echo "<li><strong>Readable:</strong> " . ($info['readable'] ? 'Yes' : 'No') . "</li>";
        echo "<li><strong>Page Count:</strong> " . $info['page_count'] . "</li>";
        echo "</ul>";
        
        echo "<p><strong>Available Parsing Methods:</strong></p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Method</th><th>Available</th><th>Description</th></tr>";
        foreach ($info['parsing_methods'] as $method) {
            $status = $method['available'] ? '✓ Yes' : '✗ No';
            $color = $method['available'] ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . esc_html($method['name']) . "</td>";
            echo "<td style='color: $color;'>" . $status . "</td>";
            echo "<td>" . esc_html($method['description']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . esc_html($e->getMessage()) . "</p>";
    }
}

/**
 * Example 5: Test available parsing methods
 */
function example_test_parsing_methods() {
    echo "<h2>Example 5: Test Available Parsing Methods</h2>";
    
    try {
        $pdf_processor = new IDokladProcessor_PDFProcessor();
        
        // Test which parsing methods are available
        $methods = $pdf_processor->test_parsing_methods();
        
        echo "<p><strong>Parsing Methods Status:</strong></p>";
        echo "<ul>";
        foreach ($methods as $key => $method) {
            $status = $method['available'] ? '✓ Available' : '✗ Not Available';
            $color = $method['available'] ? 'green' : 'orange';
            echo "<li style='color: $color;'>";
            echo "<strong>" . esc_html($method['name']) . ":</strong> $status<br>";
            echo "<small>" . esc_html($method['description']) . "</small>";
            echo "</li>";
        }
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . esc_html($e->getMessage()) . "</p>";
    }
}

/**
 * Example 6: Using the native parser directly
 */
function example_native_parser_direct() {
    echo "<h2>Example 6: Using Native Parser Directly</h2>";
    
    $pdf_path = '/path/to/your/invoice.pdf';
    
    try {
        // Use the native parser directly (bypassing fallbacks)
        $native_parser = new IDokladProcessor_NativePDFParser();
        
        // Extract text
        $text = $native_parser->extract_text($pdf_path);
        
        // Get metadata
        $metadata = $native_parser->get_metadata($pdf_path);
        
        // Get page count
        $page_count = $native_parser->get_page_count($pdf_path);
        
        echo "<p><strong>Using Native PHP Parser Only (no fallbacks):</strong></p>";
        echo "<ul>";
        echo "<li><strong>Pages:</strong> " . $page_count . "</li>";
        echo "<li><strong>Characters extracted:</strong> " . strlen($text) . "</li>";
        echo "<li><strong>PDF Version:</strong> " . esc_html($metadata['pdf_version'] ?? 'N/A') . "</li>";
        echo "</ul>";
        
        echo "<p><strong>First 500 characters:</strong></p>";
        echo "<pre>" . esc_html(substr($text, 0, 500)) . "...</pre>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . esc_html($e->getMessage()) . "</p>";
    }
}

/**
 * Example 7: Process multiple PDFs
 */
function example_process_multiple_pdfs() {
    echo "<h2>Example 7: Process Multiple PDFs</h2>";
    
    // Array of PDF files to process
    $pdf_files = array(
        '/path/to/invoice1.pdf',
        '/path/to/invoice2.pdf',
        '/path/to/invoice3.pdf',
    );
    
    $pdf_processor = new IDokladProcessor_PDFProcessor();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>File</th><th>Pages</th><th>Characters</th><th>Status</th></tr>";
    
    foreach ($pdf_files as $pdf_path) {
        $filename = basename($pdf_path);
        
        try {
            $text = $pdf_processor->extract_text($pdf_path);
            $page_count = $pdf_processor->get_page_count($pdf_path);
            $char_count = strlen($text);
            
            echo "<tr>";
            echo "<td>" . esc_html($filename) . "</td>";
            echo "<td>" . $page_count . "</td>";
            echo "<td>" . number_format($char_count) . "</td>";
            echo "<td style='color: green;'>✓ Success</td>";
            echo "</tr>";
            
        } catch (Exception $e) {
            echo "<tr>";
            echo "<td>" . esc_html($filename) . "</td>";
            echo "<td colspan='2'>-</td>";
            echo "<td style='color: red;'>✗ " . esc_html($e->getMessage()) . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
}

/**
 * Example 8: Extract invoice data with ChatGPT integration
 */
function example_extract_invoice_data() {
    echo "<h2>Example 8: Extract Invoice Data (PDF + ChatGPT)</h2>";
    
    $pdf_path = '/path/to/your/invoice.pdf';
    
    try {
        // Step 1: Extract text from PDF using native parser
        $pdf_processor = new IDokladProcessor_PDFProcessor();
        $pdf_text = $pdf_processor->extract_text($pdf_path);
        
        echo "<p><strong>Step 1: PDF text extracted</strong> (" . strlen($pdf_text) . " characters)</p>";
        
        // Step 2: Extract structured data using ChatGPT (if configured)
        if (get_option('idoklad_chatgpt_api_key')) {
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-chatgpt-integration.php';
            
            $chatgpt = new IDokladProcessor_ChatGPTIntegration();
            $invoice_data = $chatgpt->extract_invoice_data($pdf_text);
            
            echo "<p><strong>Step 2: Structured data extracted:</strong></p>";
            echo "<pre>" . esc_html(json_encode($invoice_data, JSON_PRETTY_PRINT)) . "</pre>";
        } else {
            echo "<p><em>ChatGPT API key not configured. Only PDF text extraction performed.</em></p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . esc_html($e->getMessage()) . "</p>";
    }
}

// Run examples (uncomment the ones you want to test)
// Note: Make sure to update the $pdf_path variables to point to actual PDF files

echo "<!DOCTYPE html>";
echo "<html><head><title>PDF Parser Examples</title></head><body>";
echo "<h1>iDoklad Helper - Native PDF Parser Examples</h1>";
echo "<p>This page demonstrates various ways to use the internal PDF parser.</p>";
echo "<hr>";

// example_basic_extraction();
// example_get_metadata();
// example_get_page_count();
// example_get_pdf_info();
example_test_parsing_methods();
// example_native_parser_direct();
// example_process_multiple_pdfs();
// example_extract_invoice_data();

echo "</body></html>";


<?php
/**
 * Example: How to use the PDF.co backed PDF processor
 *
 * This file demonstrates how to use the PDF processing capabilities
 * of the iDoklad Helper plugin that rely on the PDF.co cloud service.
 */

if (!defined('ABSPATH')) {
    exit('This file must be run within WordPress context');
}

require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-processor.php';

/**
 * Example 1: Basic text extraction
 */
function example_basic_extraction() {
    echo "<h2>Example 1: Basic Text Extraction</h2>";

    $pdf_path = '/path/to/your/invoice.pdf';

    try {
        $pdf_processor = new IDokladProcessor_PDFProcessor();
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
        $metadata = $pdf_processor->get_metadata($pdf_path);

        echo "<p><strong>PDF Metadata:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Processing Method:</strong> " . esc_html($metadata['method'] ?? 'Unknown') . "</li>";
        echo "<li><strong>File Name:</strong> " . esc_html($metadata['file_name'] ?? 'N/A') . "</li>";
        echo "<li><strong>File Size:</strong> " . esc_html(isset($metadata['file_size']) ? number_format_i18n($metadata['file_size']) . ' bytes' : 'N/A') . "</li>";
        echo "<li><strong>Last Modified:</strong> " . esc_html(isset($metadata['last_modified']) ? date_i18n('Y-m-d H:i', $metadata['last_modified']) : 'N/A') . "</li>";
        echo "<li><strong>Enabled:</strong> " . (!empty($metadata['enabled']) ? 'Yes' : 'No') . "</li>";
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
        $page_count = $pdf_processor->get_page_count($pdf_path);

        $page_text = is_null($page_count) ? 'Unknown' : $page_count;
        echo "<p><strong>Number of pages:</strong> " . esc_html($page_text) . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Error:</strong> " . esc_html($e->getMessage()) . "</p>";
    }
}

/**
 * Example 4: Test available parsing methods
 */
function example_test_parsing_methods() {
    echo "<h2>Example 4: Test Available Parsing Methods</h2>";

    try {
        $pdf_processor = new IDokladProcessor_PDFProcessor();
        $methods = $pdf_processor->test_parsing_methods();

        echo "<p><strong>Parsing Methods Status:</strong></p>";
        echo "<ul>";
        foreach ($methods as $method) {
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
 * Example 5: Process multiple PDFs
 */
function example_process_multiple_pdfs() {
    echo "<h2>Example 5: Process Multiple PDFs</h2>";

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
            echo "<td>" . esc_html(is_null($page_count) ? 'Unknown' : $page_count) . "</td>";
            echo "<td>" . number_format_i18n($char_count) . "</td>";
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
 * Example 6: Extract invoice data with ChatGPT integration
 */
function example_extract_invoice_data() {
    echo "<h2>Example 6: Extract Invoice Data (PDF.co + ChatGPT)</h2>";

    $pdf_path = '/path/to/your/invoice.pdf';

    try {
        $pdf_processor = new IDokladProcessor_PDFProcessor();
        $pdf_text = $pdf_processor->extract_text($pdf_path);

        echo "<p><strong>Step 1: PDF text extracted</strong> (" . strlen($pdf_text) . " characters)</p>";

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

// Output examples

echo "<!DOCTYPE html>";
echo "<html><head><title>PDF.co Parser Examples</title></head><body>";
echo "<h1>iDoklad Helper - PDF.co Parser Examples</h1>";
echo "<p>This page demonstrates various ways to use the PDF.co powered parser.</p>";
echo "<hr>";

// example_basic_extraction();
// example_get_metadata();
// example_get_page_count();
example_test_parsing_methods();
// example_process_multiple_pdfs();
// example_extract_invoice_data();

echo "</body></html>";

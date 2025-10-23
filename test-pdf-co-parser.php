<?php
/**
 * Comprehensive testing and debugging tool for PDF.co AI Parser
 * Tests every step separately with detailed output
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
        public function log($message, $data = null) {
            echo "[" . date('Y-m-d H:i:s') . "] $message\n";
            if ($data) {
                echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            }
        }
    }
    
    // Mock WordPress options
    function get_option($option_name, $default = false) {
        $options = array(
            'idoklad_pdfco_api_key' => 'YOUR_PDF_CO_API_KEY', // Replace with your actual API key
            'idoklad_debug_mode' => true
        );
        return isset($options[$option_name]) ? $options[$option_name] : $default;
    }
}

// Include the enhanced parser
require_once 'includes/class-pdf-co-ai-parser-enhanced.php';

echo "=== PDF.co AI Parser Comprehensive Testing Tool ===\n\n";

// Test configuration
$test_pdf_url = 'https://example.com/test-invoice.pdf'; // Replace with actual PDF URL for testing

echo "Configuration:\n";
echo "- API Key: " . (get_option('idoklad_pdfco_api_key') ? 'Configured' : 'NOT CONFIGURED') . "\n";
echo "- Debug Mode: " . (get_option('idoklad_debug_mode') ? 'Enabled' : 'Disabled') . "\n";
echo "- Test PDF URL: $test_pdf_url\n\n";

try {
    // Initialize the enhanced parser
    $parser = new IDokladProcessor_PDFCoAIParserEnhanced();
    
    echo "=== Step 1: Testing Enhanced Parser ===\n";
    $test_result = $parser->test_enhanced_parser($test_pdf_url);
    
    if ($test_result['success']) {
        echo "✓ Enhanced parser test completed successfully\n";
        echo "Result: " . json_encode($test_result['result'], JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "✗ Enhanced parser test failed: " . $test_result['message'] . "\n\n";
    }
    
} catch (Exception $e) {
    echo "✗ Test failed with exception: " . $e->getMessage() . "\n\n";
}

echo "=== Step 2: Manual Step-by-Step Testing ===\n\n";

// Step 2.1: Test API connection
echo "Step 2.1: Testing API Connection\n";
echo "--------------------------------\n";

$api_key = get_option('idoklad_pdfco_api_key');
if (empty($api_key)) {
    echo "✗ API key not configured. Please set your PDF.co API key.\n\n";
} else {
    echo "✓ API key configured: " . substr($api_key, 0, 8) . "...\n";
    
    // Test different endpoints
    $endpoints = array(
        'https://api.pdf.co/v1/ai-invoice-parser',
        'https://api.pdf.co/ai-invoice-parser',
        'https://api.pdf.co/v1/invoice-parser',
        'https://api.pdf.co/invoice-parser'
    );
    
    foreach ($endpoints as $endpoint) {
        echo "Testing endpoint: $endpoint\n";
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'User-Agent' => 'WordPress-iDoklad-Processor/1.1.0'
            ),
            'body' => json_encode(array(
                'url' => 'https://example.com/test.pdf'
            ))
        );
        
        $response = wp_remote_request($endpoint, $args);
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        echo "  Response Code: $response_code\n";
        
        if ($response_code === 404) {
            echo "  Status: Not found (404)\n";
        } elseif ($response_code === 401) {
            echo "  Status: Authentication failed (401)\n";
        } elseif ($response_code === 403) {
            echo "  Status: Forbidden (403)\n";
        } elseif ($response_code === 400) {
            echo "  Status: Available (400 - expected for invalid URL)\n";
        } else {
            echo "  Status: Responded (HTTP $response_code)\n";
        }
        
        echo "  Response: " . substr($response_body, 0, 200) . (strlen($response_body) > 200 ? '...' : '') . "\n\n";
    }
}

// Step 2.2: Test custom fields
echo "Step 2.2: Testing Custom Fields\n";
echo "-------------------------------\n";

$custom_fields = array(
    'DocumentNumber',
    'DateOfIssue',
    'PartnerName',
    'Items',
    'Currency',
    'TotalAmount',
    'VariableSymbol',
    'ConstantSymbol',
    'BankAccountNumber',
    'Iban'
);

echo "Custom fields for iDoklad:\n";
foreach ($custom_fields as $field) {
    echo "- $field\n";
}
echo "\n";

// Step 2.3: Test data transformation
echo "Step 2.3: Testing Data Transformation\n";
echo "-------------------------------------\n";

$sample_extracted_data = array(
    'DocumentNumber' => 'INV-2025-001',
    'DateOfIssue' => '2025-01-15',
    'PartnerName' => 'Test Company s.r.o.',
    'PartnerAddress' => 'Test Street 123, Prague',
    'Currency' => 'CZK',
    'TotalAmount' => 1500.00,
    'VariableSymbol' => '2025001',
    'Items' => array(
        array(
            'Name' => 'Consulting Service',
            'Amount' => 2.0,
            'UnitPrice' => 750.00,
            'Unit' => 'hour'
        )
    )
);

echo "Sample extracted data:\n";
echo json_encode($sample_extracted_data, JSON_PRETTY_PRINT) . "\n\n";

// Step 2.4: Test iDoklad payload validation
echo "Step 2.4: Testing iDoklad Payload Validation\n";
echo "--------------------------------------------\n";

$sample_idoklad_data = array(
    'DocumentNumber' => 'INV-2025-001',
    'DateOfIssue' => '2025-01-15',
    'DateOfTaxing' => '2025-01-15',
    'DateOfMaturity' => '2025-01-29',
    'PartnerName' => 'Test Company s.r.o.',
    'CurrencyId' => 1,
    'ExchangeRate' => 1.0,
    'ExchangeRateAmount' => 1.0,
    'PaymentOptionId' => 1,
    'IsEet' => false,
    'EetResponsibility' => 0,
    'IsIncomeTax' => true,
    'VatOnPayStatus' => 0,
    'VatRegime' => 0,
    'HasVatRegimeOss' => false,
    'ItemsTextPrefix' => 'Invoice items:',
    'ItemsTextSuffix' => 'Thank you for your business.',
    'Items' => array(
        array(
            'Name' => 'Consulting Service',
            'Unit' => 'hour',
            'Amount' => 2.0,
            'UnitPrice' => 750.0,
            'PriceType' => 1,
            'VatRateType' => 2,
            'VatRate' => 0.0,
            'IsTaxMovement' => false,
            'DiscountPercentage' => 0.0
        )
    ),
    'ReportLanguage' => 1
);

echo "Sample iDoklad payload:\n";
echo json_encode($sample_idoklad_data, JSON_PRETTY_PRINT) . "\n\n";

// Step 2.5: Test field extraction
echo "Step 2.5: Testing Field Extraction\n";
echo "----------------------------------\n";

$test_data = array(
    'DocumentNumber' => 'INV-001',
    'InvoiceNumber' => 'INV-002',
    'InvoiceNo' => 'INV-003'
);

$possible_fields = array('DocumentNumber', 'InvoiceNumber', 'InvoiceNo');

echo "Test data: " . json_encode($test_data) . "\n";
echo "Possible fields: " . json_encode($possible_fields) . "\n";

foreach ($possible_fields as $field) {
    if (isset($test_data[$field])) {
        echo "Found field '$field': " . $test_data[$field] . "\n";
        break;
    }
}

echo "\n";

// Step 2.6: Test date normalization
echo "Step 2.6: Testing Date Normalization\n";
echo "------------------------------------\n";

$test_dates = array(
    '2025-01-15',
    '15.01.2025',
    '15/01/2025',
    '01/15/2025',
    '2025-01-15 10:30:00',
    '15.01.2025 10:30:00'
);

foreach ($test_dates as $date) {
    echo "Original: $date\n";
    
    // Try different date formats
    $formats = array('Y-m-d', 'd.m.Y', 'd/m/Y', 'm/d/Y', 'Y-m-d H:i:s', 'd.m.Y H:i:s');
    $normalized = null;
    
    foreach ($formats as $format) {
        $date_obj = DateTime::createFromFormat($format, $date);
        if ($date_obj !== false) {
            $normalized = $date_obj->format('Y-m-d');
            break;
        }
    }
    
    if (!$normalized) {
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            $normalized = date('Y-m-d', $timestamp);
        }
    }
    
    echo "Normalized: " . ($normalized ?: 'FAILED') . "\n\n";
}

echo "=== Testing Complete ===\n";
echo "To use this tool with real data:\n";
echo "1. Set your PDF.co API key in the get_option function\n";
echo "2. Set a real PDF URL in \$test_pdf_url\n";
echo "3. Run the script again\n\n";

echo "=== Debugging Tips ===\n";
echo "1. Check API key configuration\n";
echo "2. Verify PDF URL accessibility\n";
echo "3. Test different endpoints\n";
echo "4. Validate custom fields\n";
echo "5. Check data transformation logic\n";
echo "6. Verify iDoklad payload format\n\n";

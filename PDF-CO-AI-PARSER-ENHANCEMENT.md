# PDF.co AI Parser Enhancement for iDoklad Integration

This document describes the enhanced PDF.co AI Parser integration that's specifically tuned for extracting data that matches iDoklad API requirements, with comprehensive debugging and step-by-step examination tools.

## Overview

The enhanced PDF.co AI Parser provides:
- **Optimized data extraction** for iDoklad API format
- **Step-by-step debugging** with detailed logging
- **Comprehensive testing tools** for every component
- **Real-time validation** of extracted data
- **Admin interface** for testing and debugging

## Files Created

### Core Enhancement Files

1. **`includes/class-pdf-co-ai-parser-enhanced.php`** - Enhanced parser with debugging
2. **`includes/class-pdf-co-admin-debug.php`** - Admin interface for debugging
3. **`assets/pdf-co-debug.js`** - JavaScript for admin interface
4. **`test-pdf-co-parser.php`** - Standalone testing tool

## Enhanced Features

### 1. Step-by-Step Debugging

The enhanced parser logs every step of the process:

```php
// Step 1: Authentication and job submission
$this->log_step('Submitting parsing job', array('pdf_url' => $pdf_url));

// Step 2: Polling for results
$this->log_step('Polling attempt ' . $attempt, array('job_id' => $job_id));

// Step 3: Data extraction
$this->log_step('Extracting parsed data', array('response_data' => $response_data));

// Step 4: Transformation
$this->log_step('Transforming to iDoklad format', array('extracted_data' => $extracted_data));

// Step 5: Validation
$this->log_step('Validating iDoklad payload', array('validation' => $validation_result));
```

### 2. iDoklad-Optimized Custom Fields

The parser uses custom fields specifically designed for iDoklad:

```php
private function get_idoklad_optimized_fields() {
    return array(
        // Document identification
        'DocumentNumber',
        'DateOfIssue',
        'DateOfTaxing',
        'DateOfMaturity',
        
        // Partner information
        'PartnerName',
        'PartnerAddress',
        'PartnerIdentificationNumber',
        
        // Financial information
        'Currency',
        'ExchangeRate',
        'TotalAmount',
        
        // Payment information
        'VariableSymbol',
        'ConstantSymbol',
        'SpecificSymbol',
        
        // Bank account
        'BankAccountNumber',
        'Iban',
        'Swift',
        
        // Items (will be parsed as array)
        'Items',
        
        // Description
        'Description',
        'Note'
    );
}
```

### 3. Comprehensive Data Transformation

The parser transforms extracted data to match iDoklad API requirements:

```php
private function transform_to_idoklad_format($extracted_data) {
    $idoklad_data = array();
    
    // Document identification
    $idoklad_data['DocumentNumber'] = $this->extract_field($extracted_data, array('DocumentNumber', 'InvoiceNumber', 'InvoiceNo'));
    $idoklad_data['DateOfIssue'] = $this->normalize_date($this->extract_field($extracted_data, array('DateOfIssue', 'IssueDate', 'InvoiceDate')));
    
    // Partner information
    $idoklad_data['PartnerName'] = $this->extract_field($extracted_data, array('PartnerName', 'SupplierName', 'CompanyName'));
    $idoklad_data['PartnerAddress'] = $this->extract_field($extracted_data, array('PartnerAddress', 'SupplierAddress', 'Address'));
    
    // Financial information
    $idoklad_data['CurrencyId'] = $this->get_currency_id($this->extract_field($extracted_data, array('Currency', 'CurrencyCode')));
    $idoklad_data['ExchangeRate'] = floatval($this->extract_field($extracted_data, array('ExchangeRate', 'Rate'))) ?: 1.0;
    
    // Payment information
    $idoklad_data['VariableSymbol'] = $this->extract_field($extracted_data, array('VariableSymbol', 'VS', 'VariableSym'));
    $idoklad_data['ConstantSymbol'] = $this->extract_field($extracted_data, array('ConstantSymbol', 'KS', 'ConstantSym'));
    
    // Items processing
    $idoklad_data['Items'] = $this->process_items($extracted_data);
    
    // Set defaults for required iDoklad fields
    $idoklad_data['PaymentOptionId'] = 1; // Bank transfer
    $idoklad_data['IsEet'] = false;
    $idoklad_data['IsIncomeTax'] = true;
    $idoklad_data['VatRegime'] = 0;
    $idoklad_data['ReportLanguage'] = 1;
    
    return $idoklad_data;
}
```

### 4. Advanced Item Processing

The parser processes invoice items with proper validation:

```php
private function process_items($extracted_data) {
    $items = array();
    
    if (isset($extracted_data['Items']) && is_array($extracted_data['Items'])) {
        foreach ($extracted_data['Items'] as $item) {
            if (!is_array($item)) continue;
            
            $processed_item = array(
                'Name' => $this->extract_field($item, array('Name', 'Description', 'Product', 'Service')),
                'Unit' => $this->extract_field($item, array('Unit', 'UnitOfMeasure', 'UOM')) ?: 'pcs',
                'Amount' => floatval($this->extract_field($item, array('Amount', 'Quantity', 'Qty'))) ?: 1.0,
                'UnitPrice' => floatval($this->extract_field($item, array('UnitPrice', 'Price', 'Rate'))) ?: 0.0,
                'PriceType' => 1, // With VAT
                'VatRateType' => 2, // Standard rate
                'VatRate' => floatval($this->extract_field($item, array('VatRate', 'TaxRate'))) ?: 0.0,
                'IsTaxMovement' => false,
                'DiscountPercentage' => floatval($this->extract_field($item, array('DiscountPercentage', 'Discount'))) ?: 0.0
            );
            
            // Only add item if it has a name
            if (!empty($processed_item['Name'])) {
                $items[] = $processed_item;
            }
        }
    }
    
    return $items;
}
```

### 5. Payload Validation

The parser validates extracted data against iDoklad requirements:

```php
private function validate_idoklad_payload($data) {
    $validation = array(
        'is_valid' => true,
        'errors' => array(),
        'warnings' => array(),
        'required_fields_present' => array(),
        'required_fields_missing' => array()
    );
    
    $required_fields = array('DocumentNumber', 'DateOfIssue', 'PartnerName', 'Items');
    
    foreach ($required_fields as $field) {
        if (isset($data[$field]) && !empty($data[$field])) {
            $validation['required_fields_present'][] = $field;
        } else {
            $validation['required_fields_missing'][] = $field;
            $validation['errors'][] = "Required field missing: $field";
            $validation['is_valid'] = false;
        }
    }
    
    // Validate items
    if (isset($data['Items']) && is_array($data['Items'])) {
        if (empty($data['Items'])) {
            $validation['errors'][] = "Items array is empty";
            $validation['is_valid'] = false;
        } else {
            foreach ($data['Items'] as $index => $item) {
                if (!isset($item['Name']) || empty($item['Name'])) {
                    $validation['errors'][] = "Item $index missing name";
                    $validation['is_valid'] = false;
                }
                if (!isset($item['Amount']) || $item['Amount'] <= 0) {
                    $validation['warnings'][] = "Item $index has invalid amount";
                }
            }
        }
    }
    
    return $validation;
}
```

## Admin Interface

### Features

The admin interface provides:

1. **Configuration Status** - Shows API key and debug mode status
2. **Connection Testing** - Tests API endpoints
3. **Parser Testing** - Tests AI parser with sample PDFs
4. **Step-by-Step Testing** - Tests each step separately
5. **Payload Validation** - Validates extracted data
6. **Custom Fields Display** - Shows all iDoklad custom fields

### Access

Navigate to: **WordPress Admin → iDoklad Processor → PDF.co AI Parser Debug**

## Testing Tools

### 1. Standalone Test Script

Run the comprehensive test script:

```bash
php test-pdf-co-parser.php
```

This script tests:
- API connection
- Custom fields
- Data transformation
- Date normalization
- Field extraction
- Payload validation

### 2. Admin Interface Testing

Use the WordPress admin interface to:
- Test API connections
- Test parser with real PDFs
- Validate extracted data
- Debug step-by-step processes

### 3. Enhanced Parser Testing

```php
$parser = new IDokladProcessor_PDFCoAIParserEnhanced();
$result = $parser->parse_invoice_with_debug($pdf_url);

// Result includes:
// - success: boolean
// - data: extracted and transformed data
// - debug_info: detailed debugging information
```

## Debugging Features

### 1. Detailed Logging

The enhanced parser logs every step:

```
[PDF.co AI Parser] Starting PDF.co AI parsing with debug mode
[PDF.co AI Parser] API key configured: 12345678...
[PDF.co AI Parser] Custom fields prepared - {"fields":["DocumentNumber","DateOfIssue",...]}
[PDF.co AI Parser] Submitting parsing job - {"pdf_url":"https://example.com/invoice.pdf"}
[PDF.co AI Parser] Job submitted successfully - {"job_id":"abc123"}
[PDF.co AI Parser] Polling attempt 1 - {"job_id":"abc123"}
[PDF.co AI Parser] Job completed successfully
[PDF.co AI Parser] Extracting parsed data - {"response_data":{...}}
[PDF.co AI Parser] Transforming to iDoklad format - {"extracted_data":{...}}
[PDF.co AI Parser] Validating iDoklad payload - {"validation":{...}}
```

### 2. Step-by-Step Examination

Each step can be examined separately:

1. **Job Submission** - Test API endpoint and job creation
2. **Result Polling** - Test job status checking
3. **Data Extraction** - Test raw data extraction
4. **Format Transformation** - Test data transformation
5. **Payload Validation** - Test validation logic

### 3. Error Handling

Comprehensive error handling with detailed messages:

```php
try {
    $result = $parser->parse_invoice_with_debug($pdf_url);
} catch (Exception $e) {
    // Detailed error information
    $this->log_step('Error occurred: ' . $e->getMessage(), array('error' => $e->getMessage()));
    throw $e;
}
```

## Usage Examples

### Basic Usage

```php
$parser = new IDokladProcessor_PDFCoAIParserEnhanced();
$result = $parser->parse_invoice_with_debug('https://example.com/invoice.pdf');

if ($result['success']) {
    $idoklad_data = $result['data'];
    // Use $idoklad_data with iDoklad API
}
```

### Advanced Usage with Custom Fields

```php
$custom_fields = array(
    'CustomField1',
    'CustomField2'
);

$result = $parser->parse_invoice_with_debug('https://example.com/invoice.pdf', $custom_fields);
```

### Testing and Debugging

```php
// Test the parser
$test_result = $parser->test_enhanced_parser('https://example.com/test.pdf');

// Check debug information
if (isset($test_result['debug_info'])) {
    $debug_info = $test_result['debug_info'];
    echo "Job ID: " . $debug_info['job_id'];
    echo "Steps completed: " . implode(', ', $debug_info['steps_completed']);
}
```

## Configuration

### Required Settings

1. **PDF.co API Key** - Set in WordPress admin
2. **Debug Mode** - Enable for detailed logging
3. **Custom Fields** - Configure for specific needs

### Optional Settings

1. **Timeout Settings** - Adjust for large PDFs
2. **Retry Logic** - Configure polling attempts
3. **Custom Validation** - Add custom validation rules

## Troubleshooting

### Common Issues

1. **API Key Not Configured**
   - Check API key in WordPress admin
   - Verify API key is valid

2. **PDF URL Not Accessible**
   - Ensure PDF URL is publicly accessible
   - Check URL format and encoding

3. **Parsing Timeout**
   - Increase timeout settings
   - Check PDF file size

4. **Data Validation Errors**
   - Review extracted data
   - Check custom field mappings

### Debug Steps

1. Enable debug mode
2. Check WordPress error logs
3. Use admin interface for testing
4. Test each step separately
5. Validate extracted data

## Conclusion

The enhanced PDF.co AI Parser provides a robust, debuggable solution for extracting invoice data that's perfectly formatted for iDoklad API integration. With comprehensive testing tools and step-by-step debugging, you can easily identify and resolve any issues with data extraction and transformation.

The system is designed to be production-ready while providing extensive debugging capabilities for development and troubleshooting.

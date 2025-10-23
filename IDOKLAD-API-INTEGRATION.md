# iDoklad API v3 Complete Integration

This document describes the complete, production-grade iDoklad API v3 integration that follows the exact specifications provided.

## Overview

The integration implements a complete workflow for creating issued invoices in iDoklad using OAuth2 Client Credentials authentication. It performs all required steps automatically in sequence:

1. **Authentication** - OAuth2 Client Credentials flow
2. **NumericSequence Resolution** - Find correct numbering series
3. **Invoice Creation** - Create issued invoice with exact payload

## Files Created

### Core Integration Files

1. **`includes/class-idoklad-api-v3-integration.php`** - Main integration class
2. **`includes/class-idoklad-admin-integration.php`** - Admin interface
3. **`assets/integration-admin.js`** - JavaScript for admin interface
4. **`test-idoklad-integration.php`** - Standalone test script

### Integration Class Features

The `IDokladProcessor_IDokladAPIV3Integration` class provides:

- **Complete OAuth2 Client Credentials authentication**
- **Automatic NumericSequence resolution**
- **Dynamic DocumentSerialNumber calculation**
- **Exact payload structure as specified**
- **Comprehensive error handling**
- **Production-ready logging**

## Authentication Flow

### Step 1: OAuth2 Client Credentials

```php
POST https://identity.idoklad.cz/server/connect/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
client_id={YOUR_CLIENT_ID}
client_secret={YOUR_CLIENT_SECRET}
scope=idoklad_api
```

**Response:**
```json
{
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIs...",
    "expires_in": 3600,
    "token_type": "Bearer"
}
```

## NumericSequence Resolution

### Step 2: Get NumericSequences

```php
GET https://api.idoklad.cz/v3/NumericSequences
Authorization: Bearer {access_token}
```

**Process (Updated based on working Postman collection):**
1. Handle different response structures: `Data.Items`, `Items`, or direct array
2. Find sequence with priority:
   - First: `DocumentType = 0` AND `IsDefault = true`
   - Second: `DocumentType = 0` (any)
   - Third: First available sequence (fallback)
3. Extract `Id` → `NumericSequenceId`
4. Extract last number from `LastNumber` or `LastDocumentSerialNumber` → Calculate `DocumentSerialNumber = LastNumber + 1`

## Invoice Creation

### Step 3: Create Issued Invoice

```php
POST https://api.idoklad.cz/v3/IssuedInvoices
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Exact Payload Structure (Updated based on working Postman collection):**

```json
{
    "PartnerId": 22429105,
    "Description": "Consulting and license (API)",
    "Note": "Auto test via Postman",
    "OrderNumber": "PO-2025-01",
    "VariableSymbol": "20250001",
    
    "DateOfIssue": "2025-10-22",
    "DateOfTaxing": "2025-10-22",
    "DateOfMaturity": "2025-11-05",
    "DateOfAccountingEvent": "2025-10-22",
    "DateOfVatApplication": "2025-10-22",
    
    "CurrencyId": 1,
    "ExchangeRate": 1.0,
    "ExchangeRateAmount": 1.0,
    
    "PaymentOptionId": 1,
    "ConstantSymbolId": 7,
    
    "NumericSequenceId": {DYNAMICALLY_INJECTED},
    "DocumentSerialNumber": {DYNAMICALLY_INJECTED},
    
    "IsEet": false,
    "EetResponsibility": 0,
    "IsIncomeTax": true,
    "VatOnPayStatus": 0,
    "VatRegime": 0,
    "HasVatRegimeOss": false,
    
    "ItemsTextPrefix": "Invoice items:",
    "ItemsTextSuffix": "Thanks for your business.",
    
    "Items": [
        {
            "Name": "Consulting service",
            "Unit": "hour",
            "Amount": 2.0,
            "UnitPrice": 1500.0,
            "PriceType": 1,
            "VatRateType": 2,
            "VatRate": 0.0,
            "IsTaxMovement": false,
            "DiscountPercentage": 0.0
        }
    ],
    
    "ReportLanguage": 1
}
```

## Usage Examples

### Basic Usage

```php
// Initialize integration
$integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);

// Test complete workflow
$result = $integration->test_integration();

// Output results
echo "Invoice ID: " . $result['invoice_id'];
echo "Document Number: " . $result['document_number'];
echo "Status Code: " . $result['status_code'];
```

### Custom Invoice Data

```php
// Custom invoice data
$custom_data = array(
    'description' => 'Custom invoice description',
    'note' => 'Custom note',
    'order_number' => 'CUSTOM-001',
    'variable_symbol' => 'CUSTOM001',
    'items' => array(
        array(
            'Name' => 'Custom Service',
            'Description' => 'Custom service description',
            'Code' => 'CUSTOM001',
            'ItemType' => 0,
            'Unit' => 'pcs',
            'Amount' => 1.0,
            'UnitPrice' => 1000.0,
            'PriceType' => 1,
            'VatRateType' => 2,
            'VatRate' => 0.0,
            'IsTaxMovement' => false,
            'DiscountPercentage' => 0.0
        )
    )
);

// Create invoice with custom data
$result = $integration->create_invoice_complete_workflow($custom_data);
```

## Admin Interface

The integration includes a complete WordPress admin interface:

### Features

- **Configuration Management** - Store Client ID and Client Secret
- **Test Integration** - Test complete workflow
- **Create Test Invoice** - Create custom test invoices
- **Real-time Results** - Display integration results
- **Error Handling** - Comprehensive error reporting

### Access

Navigate to: **WordPress Admin → iDoklad Processor → iDoklad API Integration**

## Error Handling

The integration handles all common error scenarios:

### Authentication Errors
- Invalid Client ID/Secret
- Network connectivity issues
- API service unavailability

### NumericSequence Errors
- No sequences found for DocumentType = 0
- Invalid sequence configuration
- Missing sequence data

### Invoice Creation Errors
- Invalid payload structure
- Missing required fields
- Partner not found
- Invalid VAT configuration

### Error Response Format

```json
{
    "success": false,
    "message": "Detailed error message",
    "error_code": "SPECIFIC_ERROR_CODE"
}
```

## Response Format

### Success Response

```json
{
    "success": true,
    "status_code": 201,
    "invoice_id": 12345,
    "document_number": "2025-0001",
    "response_data": {
        "Data": {
            "Id": 12345,
            "DocumentNumber": "2025-0001",
            "PartnerId": 22429105,
            "DateOfIssue": "2025-10-23",
            "Total": 3499.0
        }
    }
}
```

## Testing

### Standalone Test Scripts

Run the integration test script:

```bash
php test-idoklad-integration.php
```

Run the Postman collection workflow test:

```bash
php test-postman-workflow.php
```

The Postman workflow test exactly matches the working Postman collection and can be used to verify the integration works correctly.

### WordPress Admin Testing

1. Configure Client ID and Client Secret in admin
2. Click "Test Complete Integration"
3. Review results in the output panel

## Requirements

### PHP Requirements
- PHP 7.1 or higher
- cURL extension enabled
- JSON extension enabled

### WordPress Requirements
- WordPress 5.0 or higher
- Admin access for configuration

### iDoklad Requirements
- Valid iDoklad account
- API access enabled
- Client ID and Client Secret configured

## Security Considerations

- Client Secret is stored securely in WordPress options
- All API requests use HTTPS
- Access tokens are not stored permanently
- Admin interface requires proper permissions

## Production Deployment

### Configuration Steps

1. **Obtain API Credentials** from iDoklad
2. **Configure WordPress Settings** with Client ID and Secret
3. **Test Integration** using admin interface
4. **Monitor Logs** for any issues
5. **Deploy to Production** environment

### Monitoring

- Check WordPress error logs for integration issues
- Monitor API response times
- Track invoice creation success rates
- Review NumericSequence resolution

## Support

For issues or questions:

1. Check WordPress error logs
2. Verify API credentials
3. Test network connectivity
4. Review iDoklad API documentation

## Conclusion

This integration provides a complete, production-ready solution for creating invoices in iDoklad using their API v3. It follows all specified requirements and includes comprehensive error handling, logging, and admin interface for easy management.

The integration is designed to be robust, secure, and easy to use, making it suitable for production environments where reliable invoice creation is critical.

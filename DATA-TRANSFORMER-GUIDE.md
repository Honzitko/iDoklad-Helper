# iDoklad Data Transformer Guide

## 📚 Overview

The **iDoklad Data Transformer** is a crucial component that bridges the gap between PDF.co/Zapier output and the iDoklad API v3 requirements. It transforms raw invoice data into the exact format required by iDoklad's [ReceivedInvoices API endpoint](https://api.idoklad.cz/Help/v3/cs/index.html).

---

## 🎯 Purpose

### Problem:
- PDF.co extracts raw text from PDFs
- Zapier parses this text into basic invoice fields
- iDoklad API requires specific field names, types, and structure
- Direct mapping doesn't work - data needs transformation

### Solution:
The Data Transformer:
1. Takes raw data from Zapier (flexible format)
2. Maps fields to iDoklad API specifications
3. Adds required IDs (Currency, Country, Payment Option, VAT Rate)
4. Formats dates, numbers, and addresses correctly
5. Validates the final payload
6. Returns iDoklad-ready JSON

---

## 🔄 Data Flow

```
PDF.co
  ↓ (raw text)
Zapier AI
  ↓ (parsed fields: invoice_number, total_amount, supplier_name, etc.)
DATA TRANSFORMER ← YOU ARE HERE
  ↓ (iDoklad format: DocumentNumber, CurrencyId, ReceivedInvoiceItems, etc.)
iDoklad API
  ↓ (created invoice)
Success ✅
```

---

## 📋 Field Mapping

### Input (from Zapier):
```json
{
  "invoice_number": "2024001",
  "date": "21.10.2024",
  "total_amount": "1500.50",
  "currency": "CZK",
  "supplier_name": "Company s.r.o.",
  "supplier_vat_number": "CZ12345678",
  "items": [
    {
      "name": "Service",
      "price": "1500.50",
      "quantity": "1"
    }
  ]
}
```

### Output (for iDoklad):
```json
{
  "DocumentNumber": "2024001",
  "DateOfIssue": "2024-10-21",
  "DateOfTaxing": "2024-10-21",
  "DateOfMaturity": "2024-11-21",
  "PartnerName": "Company s.r.o.",
  "SupplierIdentificationNumber": "CZ12345678",
  "CurrencyId": 1,
  "ExchangeRate": 1,
  "PaymentOptionId": 1,
  "ReceivedInvoiceItems": [
    {
      "Name": "Service",
      "UnitPrice": 1500.50,
      "Amount": 1,
      "PriceType": 0,
      "VatRateType": 0
    }
  ]
}
```

---

## 🗺️ Complete Field Mapping Table

| Zapier Field | iDoklad Field | Type | Notes |
|--------------|---------------|------|-------|
| `invoice_number` | `DocumentNumber` | string | Required |
| `date` | `DateOfIssue` | date (Y-m-d) | Required, auto-formats |
| `tax_date` | `DateOfTaxing` | date (Y-m-d) | Falls back to `date` |
| `due_date` | `DateOfMaturity` | date (Y-m-d) | Falls back to +30 days |
| `supplier_name` | `PartnerName` | string | Supplier info |
| `supplier_vat_number` | `SupplierIdentificationNumber` | string | ICO/VAT number |
| `supplier_address` | `PartnerAddress` | object | Street, City, PostalCode, CountryId |
| `currency` | `CurrencyId` | int | 1=CZK, 2=EUR, 3=USD, etc. |
| `payment_method` | `PaymentOptionId` | int | 1=Bank, 2=Cash, 3=Card |
| `variable_symbol` | `VariableSymbol` | string | Payment reference |
| `bank_account` | `BankAccountNumber` | string | Supplier's account |
| `iban` | `Iban` | string | International account |
| `items` | `ReceivedInvoiceItems` | array | Invoice line items |
| `description` | `Description` | string | Invoice note |

---

## 🔢 ID Mappings

### Currency IDs (from iDoklad API):
```php
'CZK' => 1,  // Czech Koruna
'EUR' => 2,  // Euro
'USD' => 3,  // US Dollar
'GBP' => 4,  // British Pound
'PLN' => 5,  // Polish Zloty
'HUF' => 6,  // Hungarian Forint
'CHF' => 7,  // Swiss Franc
```

### Country IDs:
```php
'CZ' => 1,   // Czech Republic
'SK' => 2,   // Slovakia
'PL' => 3,   // Poland
'DE' => 4,   // Germany
'AT' => 5,   // Austria
'HU' => 6,   // Hungary
```

### Payment Option IDs:
```php
'bank' => 1,     // Bank transfer
'cash' => 2,     // Cash
'card' => 3,     // Card payment
```

### VAT Rate Types:
```php
0 => 21%,  // Basic rate (21%)
1 => 15%,  // Reduced rate 1 (15%)
2 => 10%,  // Reduced rate 2 (10%)
3 => 0%,   // Zero rate (0%)
```

### Price Types:
```php
0 => Without VAT
1 => With VAT
```

---

## 🛠️ Transformer Methods

### Main Methods:

#### `transform_to_idoklad($extracted_data, $pdf_text)`
**Purpose:** Main transformation method

**Input:**
- `$extracted_data` - Array from Zapier
- `$pdf_text` - Raw PDF text (for reference/notes)

**Output:** iDoklad-formatted array

**Example:**
```php
$transformer = new IDokladProcessor_DataTransformer();
$idoklad_data = $transformer->transform_to_idoklad($zapier_data, $pdf_text);
```

---

#### `validate_idoklad_payload($payload)`
**Purpose:** Validate transformed data before sending to iDoklad

**Output:**
```php
array(
    'valid' => true/false,
    'errors' => array(),    // Blocking errors
    'warnings' => array()   // Non-blocking warnings
)
```

**Example:**
```php
$validation = $transformer->validate_idoklad_payload($idoklad_data);
if (!$validation['valid']) {
    throw new Exception('Validation failed: ' . implode(', ', $validation['errors']));
}
```

---

### Helper Methods:

| Method | Purpose |
|--------|---------|
| `extract_document_number()` | Get invoice number with fallback |
| `extract_date()` | Parse and format dates to Y-m-d |
| `extract_partner_id()` | Get supplier ID if exists |
| `extract_address()` | Build address object |
| `get_currency_id()` | Map currency code to ID |
| `get_country_id()` | Map country code to ID |
| `get_payment_option_id()` | Map payment method to ID |
| `transform_items()` | Transform invoice line items |
| `determine_vat_rate()` | Calculate VAT rate type from percentage |
| `extract_field()` | Get field with multiple possible keys |

---

## 📊 Processing Flow

### Step-by-Step in Email Processing:

```
1. Email received with PDF
2. PDF.co extracts text
3. Zapier parses invoice data
   ↓
4. Raw data validated (basic checks)
   ↓
5. DATA TRANSFORMER EXECUTES
   ├─ Map fields to iDoklad format
   ├─ Convert currency code to ID
   ├─ Convert country code to ID
   ├─ Format dates (Y-m-d)
   ├─ Transform invoice items
   ├─ Build address objects
   └─ Add metadata/notes
   ↓
6. iDoklad payload validated
   ├─ Check required fields
   ├─ Validate data types
   └─ Log warnings
   ↓
7. Send to iDoklad API
8. Invoice created ✅
```

---

## 🔍 Queue Logging

### You'll see these steps in the queue:

```
✓ Parsing invoice data with Zapier AI
✓ Zapier AI parsing successful
✓ Validating extracted data
✓ Data validated successfully
✓ Transforming data to iDoklad API format
✓ Data transformed successfully
  - document_number: 2024001
  - items_count: 3
  - currency_id: 1
✓ iDoklad payload validated successfully
✓ Creating invoice in iDoklad
✓ Invoice created successfully
```

---

## ⚠️ Error Handling

### Common Validation Errors:

**Missing Required Fields:**
```
ERROR: iDoklad payload validation failed
- DocumentNumber is required
- DateOfIssue is required
- At least one invoice item is required
```

**Solution:** Ensure Zapier returns these minimum fields:
- `invoice_number` or `document_number`
- `date` or `invoice_date`
- `items` array or `total_amount`

---

### Common Warnings (Non-blocking):

```
Validation warnings (non-critical)
- Partner name is missing
- Supplier VAT/ICO number is missing
```

**Impact:** Invoice still created but with incomplete data

---

## 🧪 Testing

### Test the Transformer:

```php
// Create test data
$test_data = array(
    'invoice_number' => 'TEST-001',
    'date' => '2024-10-21',
    'total_amount' => 1500,
    'currency' => 'CZK',
    'supplier_name' => 'Test Company s.r.o.',
    'supplier_vat_number' => 'CZ12345678',
    'items' => array(
        array(
            'name' => 'Test Item',
            'price' => 1500,
            'quantity' => 1
        )
    )
);

// Transform
$transformer = new IDokladProcessor_DataTransformer();
$result = $transformer->transform_to_idoklad($test_data, '');

// Validate
$validation = $transformer->validate_idoklad_payload($result);

// Output
print_r($result);
print_r($validation);
```

---

## 📝 Examples

### Example 1: Simple Invoice

**Input:**
```json
{
  "invoice_number": "2024001",
  "date": "2024-10-21",
  "total_amount": "1000",
  "supplier_name": "ACME Corp"
}
```

**Output:**
```json
{
  "DocumentNumber": "2024001",
  "DateOfIssue": "2024-10-21",
  "PartnerName": "ACME Corp",
  "CurrencyId": 1,
  "ReceivedInvoiceItems": [
    {
      "Name": "Invoice total",
      "UnitPrice": 1000,
      "Amount": 1,
      "PriceType": 0,
      "VatRateType": 3
    }
  ]
}
```

---

### Example 2: Complete Invoice

**Input:**
```json
{
  "invoice_number": "FV2024001",
  "date": "2024-10-21",
  "due_date": "2024-11-21",
  "currency": "EUR",
  "supplier_name": "European Company s.r.o.",
  "supplier_vat_number": "CZ87654321",
  "supplier_address": "Main Street 123",
  "supplier_city": "Prague",
  "supplier_zip": "11000",
  "bank_account": "123456789/0100",
  "variable_symbol": "2024001",
  "items": [
    {
      "name": "Consulting",
      "price": "1000",
      "quantity": "10",
      "vat_rate": "21"
    },
    {
      "name": "Software License",
      "price": "5000",
      "quantity": "1",
      "vat_rate": "21"
    }
  ]
}
```

**Output:** (Full iDoklad-compliant payload with all fields)

---

## ✅ Best Practices

### 1. **Always Validate**
```php
$validation = $transformer->validate_idoklad_payload($data);
if (!$validation['valid']) {
    // Handle errors
}
```

### 2. **Log Transformations**
Enable debug mode to see full transformation logs:
```
iDoklad Transformer: Starting data transformation
iDoklad Transformer: Input data: {...}
iDoklad Transformer: Output payload: {...}
```

### 3. **Handle Warnings**
Non-blocking warnings are logged but don't stop processing:
```php
if (!empty($validation['warnings'])) {
    // Log warnings for review
    error_log('Transformation warnings: ' . implode(', ', $validation['warnings']));
}
```

### 4. **Provide Fallbacks**
Transformer automatically provides fallbacks:
- Missing invoice number → `AUTO-{timestamp}`
- Missing date → Current date
- Missing currency → CZK (ID: 1)
- Missing items → Create from total_amount

---

## 🔗 Related Documentation

- [iDoklad API v3](https://api.idoklad.cz/Help/v3/cs/index.html)
- [ReceivedInvoices Endpoint](https://api.idoklad.cz/Help/v3/cs/Api/ReceivedInvoices)
- WordPress Plugin: `ARCHITECTURE-FINAL.md`

---

## 🎉 Summary

The Data Transformer:
- ✅ Bridges Zapier ↔ iDoklad API
- ✅ Maps flexible input to strict API format
- ✅ Converts codes to IDs (currency, country, VAT)
- ✅ Formats dates correctly
- ✅ Validates payloads
- ✅ Provides intelligent fallbacks
- ✅ Logs every step for debugging

**Result:** Seamless data flow from PDF to iDoklad! 🚀


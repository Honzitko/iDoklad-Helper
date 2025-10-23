# iDoklad API Fix - Received vs Issued Invoices

## ⚠️ Issue Found

The plugin was using the **wrong endpoint** for creating invoices!

- **Before:** `/IssuedInvoices` - For invoices you SEND to customers
- **Now:** `/ReceivedInvoices` - For invoices you RECEIVE from suppliers ✅

## 🔧 What Was Fixed

### 1. Endpoint Change
```php
// OLD (wrong for received invoices)
$response = $this->make_api_request('/IssuedInvoices', 'POST', $invoice_data);

// NEW (correct for received invoices)  
$response = $this->make_api_request('/ReceivedInvoices', 'POST', $invoice_data);
```

### 2. Payload Structure
Updated to match iDoklad's ReceivedInvoices API requirements:

```php
$invoice_data = array(
    'PartnerId' => $supplier_id,          // Who sent you the invoice
    'DocumentNumber' => 'INV-123',        // Invoice number
    'DateOfIssue' => '2024-10-21',        // When issued
    'DateOfTaxing' => '2024-10-21',       // Tax date
    'DateOfMaturity' => '2024-11-21',     // Due date
    'DateOfPayment' => null,              // Not paid yet
    'PaymentStatus' => 0,                 // 0 = Unpaid
    'CurrencyId' => 1,                    // 1 = CZK
    'ExchangeRate' => 1,
    'ExchangeRateAmount' => 1,
    'Items' => array(
        array(
            'Name' => 'Item name',
            'Amount' => 1,                // Quantity
            'UnitPrice' => 1000.00,       // Price per unit
            'VatRateType' => 1,           // Standard VAT (21%)
            'PriceType' => 0              // 0 = Without VAT, 1 = With VAT
        )
    )
);
```

### 3. Better Error Messages

Now shows **detailed validation errors** from iDoklad:

```
Before: "iDoklad API error (400): API request failed"

Now: "iDoklad API error (400): Invalid field value | Validation errors: 
      Items[0].Amount: Must be greater than 0 | 
      DateOfIssue: Invalid date format"
```

### 4. Debug Logging

Diagnostics test now automatically:
- ✅ Enables debug mode during test
- ✅ Logs full request payload to `debug.log`
- ✅ Logs full response from iDoklad
- ✅ Restores debug mode after test

## 📝 When to Use Each Endpoint

### ReceivedInvoices (✅ This Plugin)
**Use for:** Invoices you **receive** from suppliers
- Supplier sends you an invoice
- You need to record it as an expense
- Example: "We received an invoice from ABC Company for €1,000"

### IssuedInvoices (Not used in this plugin)
**Use for:** Invoices you **send** to customers  
- You send an invoice to a customer
- You're billing someone
- Example: "We sent an invoice to XYZ Client for €1,000"

## 🧪 How to Test

1. **Go to:** Diagnostics & Testing page
2. **Test iDoklad API** section
3. **Select a user**
4. **Keep the default JSON** (it's now correct)
5. **Click "Send to iDoklad"**

### Expected Result
```
✓ iDoklad API Successful
Request Time: 456 ms
Endpoint: /ReceivedInvoices (Received invoice - expense)
Invoice ID: 123456

iDoklad Response:
{
  "Id": 123456,
  "DocumentNumber": "TEST-...",
  "PartnerId": 789,
  ...
}
```

### If You Get an Error

1. **Check debug.log**
   - Location: `/wp-content/debug.log` (if `WP_DEBUG_LOG` is enabled)
   - Look for lines with `iDoklad API:`
   - Will show exact request/response

2. **Common Errors:**

   **"Partner not found"**
   - The supplier doesn't exist in iDoklad
   - Plugin will try to create them automatically
   - Make sure `supplier_name` is in your JSON

   **"Invalid date format"**
   - Dates must be in format: `YYYY-MM-DD`
   - Example: `2024-10-21` ✅
   - Not: `21.10.2024` ❌

   **"Items amount must be greater than 0"**
   - `"quantity"` must be > 0
   - Check your invoice data

   **"Currency not found"**
   - `CurrencyId` must be valid
   - 1 = CZK, 2 = EUR, 3 = USD, 4 = GBP

## 💡 Testing Tips

### Minimal Test JSON
```json
{
  "invoice_number": "TEST-001",
  "date": "2024-10-21",
  "currency": "CZK",
  "total_amount": 1000.00,
  "supplier_name": "Test Supplier s.r.o.",
  "supplier_vat_number": "CZ12345678",
  "items": [
    {
      "name": "Test Item",
      "quantity": 1,
      "price": 1000.00
    }
  ]
}
```

### Test Without Items
```json
{
  "invoice_number": "TEST-002",
  "date": "2024-10-21",
  "currency": "CZK",
  "total_amount": 500.00,
  "supplier_name": "Another Supplier",
  "notes": "Testing without items array"
}
```

The plugin will create a single item automatically.

## 🔍 Where to Check in iDoklad

After successful creation:
1. Log into iDoklad web interface
2. Go to **Přijaté faktury** (Received Invoices)
3. Find your test invoice by number
4. **Delete it** if it's just a test!

## 🚨 Important Notes

1. **This creates REAL invoices** in iDoklad
   - Not a sandbox/test mode
   - Delete test invoices manually
   - Use realistic test data

2. **Supplier Auto-Creation**
   - If supplier doesn't exist, plugin creates them
   - Supplier is matched by exact name
   - Check Contacts in iDoklad after test

3. **VAT Rate**
   - Currently hardcoded to `VatRateType = 1` (Standard 21%)
   - You might need to adjust based on item type
   - Consider making this configurable

4. **Price Type**
   - `PriceType = 0` means price is WITHOUT VAT
   - `PriceType = 1` means price is WITH VAT
   - Currently assumes WITHOUT VAT

## 📊 What Changed in Files

1. **includes/class-idoklad-api.php**
   - Changed `create_invoice()` to use `/ReceivedInvoices`
   - Added `create_issued_invoice()` for future use
   - New `build_received_invoice_data()` method
   - Enhanced error handling with validation details
   - Better debug logging

2. **includes/class-admin.php**
   - Test now enables debug mode temporarily
   - Returns detailed error information
   - Shows endpoint being used

3. **assets/admin.js**
   - Shows detailed error messages
   - Displays endpoint in success message
   - Shows Invoice ID when created

## 🎯 Next Steps

1. **Test the fix** - Try the Diagnostics test now
2. **Check the logs** - Look at `debug.log` for details
3. **Verify in iDoklad** - See the invoice was created
4. **Delete test invoice** - Clean up after testing
5. **Report back** - Let me know if it works or if you get new errors!

---

**The error message you'll see should now be much more helpful and tell you exactly what's wrong!**


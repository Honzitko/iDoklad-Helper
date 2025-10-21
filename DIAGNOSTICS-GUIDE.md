# Diagnostics & Testing Guide

## 🎯 Overview

The **Diagnostics & Testing** page allows you to test each component of the invoice processing pipeline individually. This is perfect for troubleshooting and validating your configuration.

## 📍 Access

Go to: **WordPress Admin** → **iDoklad Processor** → **Diagnostics & Testing**

## 🧪 Available Tests

### 1. 📄 Test PDF Parsing

**What it does:**
- Uploads a PDF and extracts text using all available methods
- Shows which parsing method worked
- Displays extraction time and text preview
- Shows PDF metadata and page count

**How to use:**
1. Click "Choose File" and select a PDF invoice
2. Click "Test PDF Parsing"
3. View results:
   - ✓ Characters extracted
   - ⏱️ Parse time (in milliseconds)
   - 📄 Page count
   - 📝 Text preview (first 500 characters)
   - ✅ Which parsing methods are available

**What you'll learn:**
- Can your server extract text from PDFs?
- Which method works (Native PHP, pdftotext, poppler, ghostscript)?
- How fast is the extraction?
- Is the text quality good enough?

---

### 2. 🔍 Test OCR (Scanned PDFs)

**What it does:**
- Tests OCR on scanned/image-based PDFs
- Shows which OCR methods are available
- Displays OCR processing time

**How to use:**
1. Upload a scanned PDF (image-based invoice)
2. Click "Test OCR"
3. View results:
   - Text extracted from images
   - OCR processing time
   - Available OCR methods (Tesseract, OCR.space, Google Vision)

**What you'll learn:**
- Is OCR configured correctly?
- Which OCR service is working?
- How long does OCR take?
- Is OCR.space API key valid?

---

### 3. ⚡ Test Zapier Webhook

**What it does:**
- Sends extracted PDF text to your Zapier webhook
- Shows Zapier's response
- Measures request time

**How to use:**
1. **Option A:** Run PDF test first, then click "Copy from PDF Test Above"
2. **Option B:** Paste any text manually
3. Click "Send to Zapier"
4. View results:
   - Zapier webhook response (usually processed invoice data)
   - Request time
   - Webhook URL used

**What you'll learn:**
- Is Zapier webhook URL correct?
- Is Zapier Zap working?
- What data does Zapier extract?
- How long does Zapier processing take?

**Pro Tip:** Use the "Copy from PDF Test Above" button to automatically copy extracted text!

---

### 4. 🏢 Test iDoklad API

**What it does:**
- Sends invoice data to iDoklad API
- Creates a real invoice (be careful!)
- Shows API response

**How to use:**
1. Select a user (whose iDoklad credentials to use)
2. **Option A:** Click "Copy from Zapier Response" to use Zapier output
3. **Option B:** Edit the pre-filled JSON manually
4. Click "Send to iDoklad"
5. View results:
   - iDoklad API response
   - Request time
   - Invoice created successfully

**⚠️ Warning:** This creates a **REAL invoice** in iDoklad! Use test data or delete it afterward.

**What you'll learn:**
- Are iDoklad credentials valid?
- Can you create invoices via API?
- What does iDoklad expect in the data?
- API response time

---

## 🔄 Data Flow Testing

You can test the entire pipeline step-by-step:

```
1. Upload PDF → Test PDF Parsing
   ↓ (Copy text)
2. Paste → Test Zapier Webhook
   ↓ (Copy response)
3. Paste → Test iDoklad API
   ✓ Invoice created!
```

### Step-by-Step Example:

1. **Test PDF Parsing**
   - Upload `invoice.pdf`
   - Click "Test PDF Parsing"
   - See: "1,234 characters extracted in 45ms"
   
2. **Test Zapier**
   - Click "Copy from PDF Test Above"
   - Click "Send to Zapier"
   - See: Zapier returns structured JSON with invoice data
   
3. **Test iDoklad**
   - Select user: "supplier@example.com"
   - Click "Copy from Zapier Response"
   - Click "Send to iDoklad"
   - See: Invoice created successfully!

## 🔧 Sidebar Tools

### Check Available Methods

Click "Check Available Methods" to see what's available on your server:

- ✅ **Green** = Available and working
- ❌ **Red** = Not available

Example output:
```
✓ Native PHP Parser - Pure PHP implementation
✗ pdftotext - Command-line tool not installed
✓ OCR.space - Cloud OCR service (API configured)
✗ Tesseract - Local OCR not installed
```

### Current Settings

Shows your active configuration:
- Native Parser: ✓ Yes / ✗ No
- OCR Enabled: ✓ Yes / ✗ No
- Cloud OCR: ✓ Yes / ✗ No
- OCR Service: ocr_space / google_vision / none
- Zapier URL: ✓ Set / ✗ Not set

## 💡 Testing Tips

### 1. **Start with PDF Parsing**
Always test PDF parsing first. If it can't extract text, nothing else will work.

### 2. **Use Real Invoices**
Test with actual PDF invoices you'll be processing, not random PDFs.

### 3. **Check Processing Time**
- PDF parsing: Should be < 100ms
- OCR: Can take 2-10 seconds (cloud APIs)
- Zapier: Usually < 2 seconds
- iDoklad: Usually < 1 second

### 4. **Test Scanned PDFs Separately**
If you process scanned invoices, test OCR specifically.

### 5. **Verify Data Structure**
Check that Zapier returns the correct JSON structure that iDoklad expects.

### 6. **Test Each User's Credentials**
Each user has different iDoklad credentials - test them all!

## 🐛 Common Issues & Solutions

### "Failed to parse PDF"
- **Cause:** PDF is encrypted, corrupted, or image-based
- **Solution:** Try OCR test if it's scanned

### "No PDF file uploaded"
- **Cause:** File input empty
- **Solution:** Select a file before clicking submit

### "Zapier request failed"
- **Cause:** Webhook URL wrong or Zapier down
- **Solution:** Check Settings → Zapier Webhook URL

### "User not found"
- **Cause:** No users configured
- **Solution:** Add users in Authorized Users tab

### "Invalid JSON format"
- **Cause:** Syntax error in JSON
- **Solution:** Use a JSON validator online

### "OCR failed"
- **Cause:** OCR.space API key invalid
- **Solution:** Check Settings → OCR Settings

## 📊 Understanding Results

### PDF Parsing Results

```
✓ PDF Parsing Successful
Characters Extracted: 1,234
Parse Time: 45 ms
Pages: 2
File Size: 245 KB

Extracted Text Preview:
Faktura č. 2024001
Datum: 21.10.2024
Dodavatel: ABC s.r.o.
...

Parsing Methods Tested:
✓ Native PHP Parser - Available
✗ pdftotext - Unavailable
```

**What this means:**
- Text extraction successful
- Native PHP parser worked
- Took 45 milliseconds
- 1,234 characters extracted from 2 pages

### Zapier Response

```
✓ Zapier Webhook Successful
Request Time: 1,234 ms
Webhook URL: https://hooks.zapier.com/...

Zapier Response:
{
  "invoice_number": "2024001",
  "date": "2024-10-21",
  "total_amount": 1000.00,
  "supplier_name": "ABC s.r.o."
  ...
}
```

**What this means:**
- Zapier processed successfully
- Took 1.2 seconds
- Extracted structured data

## 🎓 Best Practices

1. **Test before going live** - Test all components before enabling email monitoring
2. **Keep test PDFs** - Save sample invoices for regular testing
3. **Test after changes** - Re-test after updating settings or WordPress
4. **Document issues** - Note which PDFs fail and why
5. **Use copy buttons** - Save time by copying data between tests

## 🚀 Next Steps After Testing

Once all tests pass:

1. ✅ PDF parsing works → Enable email monitoring
2. ✅ OCR works → Enable OCR in settings for scanned PDFs
3. ✅ Zapier works → Set up your Zap to extract invoice data
4. ✅ iDoklad works → Add all users with valid credentials
5. ✅ Everything works → Send a real test email!

---

**Need help?** If a test fails, the error message will tell you exactly what's wrong. Check the TROUBLESHOOTING.md guide for solutions.

**Pro Tip:** Bookmark this page! You'll use it often when adding new suppliers or troubleshooting issues.


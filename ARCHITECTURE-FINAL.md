# iDoklad Invoice Processor - Final Architecture

## 🏗️ System Architecture (Updated)

### Processing Flow

```
Email Received
    ↓
PDF Attachment Extracted
    ↓
┌─────────────────────────────────────┐
│  PDF.co (PRIMARY)                   │
│  - PDF text extraction              │
│  - Automatic OCR for scanned PDFs   │
│  - Cloud-based processing           │
│  - AI-powered                       │
└─────────────────────────────────────┘
    ↓
PDF Text Extracted
    ↓
┌─────────────────────────────────────┐
│  Zapier Webhook (AI Parsing)        │
│  - Parse invoice data               │
│  - Extract structured fields        │
│  - Return JSON with invoice details │
└─────────────────────────────────────┘
    ↓
Structured Invoice Data
    ↓
┌─────────────────────────────────────┐
│  iDoklad API                        │
│  - Create received invoice          │
│  - Store in accounting system       │
└─────────────────────────────────────┘
    ↓
Invoice Created ✅
    ↓
Email Notification Sent
```

---

## 🔧 Components

### 1. **PDF.co** (PDF Processing)
**Purpose:** Extract text from PDFs (regular and scanned)

**Features:**
- Cloud-based PDF processing
- Automatic OCR detection
- No server dependencies
- AI-powered text extraction
- Handles both text PDFs and image-based scans

**Configuration:**
- Settings → PDF.co section
- API key required (free tier: 300 credits/month)
- Enabled by default

**Fallback:**
- Native PHP parser
- pdftotext
- poppler
- ghostscript

---

### 2. **Zapier** (AI Parsing)
**Purpose:** Parse invoice data from extracted text

**Features:**
- AI-powered invoice parsing
- Extracts structured data
- Returns JSON with:
  - Invoice number
  - Date
  - Total amount
  - Supplier info
  - Line items
  - Currency

**Configuration:**
- Settings → Zapier section
- Webhook URL required
- Receives PDF text
- Returns structured data

**Fallback:**
- Basic data structure with raw PDF text
- iDoklad creates invoice with minimal data

---

### 3. **iDoklad API** (Invoice Creation)
**Purpose:** Create received invoices in accounting system

**Features:**
- OAuth 2.0 authentication
- Per-user credentials
- Received invoice creation
- Automatic token refresh

**Configuration:**
- Per-user setup in Authorized Users
- Client ID + Client Secret
- API URL (production/sandbox)

---

## ❌ **Removed Components**

### ChatGPT Integration
**Status:** REMOVED ✅

**Reason:** 
- PDF.co already provides AI-powered processing
- Zapier handles invoice parsing
- No need for additional AI service
- Simplifies architecture
- Reduces dependencies

**Files (kept but not used):**
- `includes/class-chatgpt-integration.php` (legacy, not called)

---

## 📊 **Data Flow**

### Step-by-Step:

1. **Email Arrives** with PDF attachment
2. **Queue Item Created** with status "pending"
3. **PDF.co Processes PDF:**
   - Uploads file to PDF.co cloud
   - Extracts text (or OCR if scanned)
   - Returns plain text
4. **Zapier Parses Invoice:**
   - Sends text to Zapier webhook
   - AI parses invoice fields
   - Returns structured JSON
5. **Data Validated:**
   - Check required fields
   - Validate amounts
   - Verify dates
6. **iDoklad Creates Invoice:**
   - Authenticate with OAuth
   - Build invoice payload
   - POST to /ReceivedInvoices
7. **Notification Sent:**
   - Email confirmation
   - Queue status: "completed"

---

## 🔄 **Fallback Chain**

### PDF Processing:
```
PDF.co (primary)
    ↓ (if fails)
Native PHP Parser
    ↓ (if fails)
pdftotext
    ↓ (if fails)
poppler
    ↓ (if fails)
ghostscript
    ↓ (if all fail)
ERROR
```

### Invoice Parsing:
```
Zapier AI (primary)
    ↓ (if fails)
Basic Data Structure
(invoice created with minimal fields)
```

---

## 🎯 **Technology Stack**

### Cloud Services:
- **PDF.co** - PDF processing & OCR
- **Zapier** - AI invoice parsing
- **iDoklad** - Accounting API

### WordPress:
- Custom plugin
- AJAX endpoints
- Database tables (users, logs, queue)
- Cron jobs for automation

### PHP Libraries:
- WordPress HTTP API (`wp_remote_*`)
- PHP IMAP for email
- JSON for data exchange

---

## 📁 **File Structure**

### Core Processing:
```
includes/
├── class-pdfco-processor.php      ✅ PDF.co integration
├── class-zapier-integration.php   ✅ Zapier webhook
├── class-idoklad-api.php          ✅ iDoklad API
├── class-email-monitor.php        ✅ Email processing
└── class-pdf-processor.php        ✅ PDF orchestrator
```

### Legacy (Not Used):
```
includes/
├── class-chatgpt-integration.php  ❌ Not called
├── class-ocr-processor.php        ❌ Replaced by PDF.co
└── class-pdf-parser-native.php    ⚠️  Fallback only
```

### Admin:
```
includes/
├── class-admin.php                ✅ Admin interface
├── class-database.php             ✅ Database operations
├── class-notification.php         ✅ Email notifications
└── class-user-manager.php         ✅ User management
```

### Templates:
```
templates/
├── admin-dashboard.php            ✅ Dashboard
├── admin-settings.php             ✅ Settings
├── admin-queue.php                ✅ Queue viewer
├── admin-diagnostics.php          ✅ Testing tools
└── partials/                      ✅ Reusable components
```

---

## ⚙️ **Configuration Required**

### Minimum Setup:
1. ✅ **PDF.co API Key** (Settings → PDF.co)
2. ✅ **Zapier Webhook URL** (Settings → Zapier)
3. ✅ **Email Settings** (IMAP host, credentials)
4. ✅ **Authorized User** (with iDoklad credentials)

### Optional:
- Debug Mode (for troubleshooting)
- Notification Email
- Legacy OCR settings (not used if PDF.co enabled)

---

## 🚀 **Performance**

### Typical Processing Time:
- Email fetch: 1-3 seconds
- PDF.co processing: 1-5 seconds (regular PDF)
- PDF.co OCR: 3-10 seconds (scanned PDF)
- Zapier parsing: 1-3 seconds
- iDoklad API: 1-2 seconds
- **Total: 5-20 seconds per invoice**

### Scalability:
- PDF.co: 300 credits/month (free), unlimited (paid)
- Zapier: Depends on plan
- iDoklad: API rate limits apply
- WordPress: Can handle 100+ invoices/day

---

## 🔍 **Monitoring & Debugging**

### Queue Viewer:
- Real-time status
- Processing details
- Timeline view
- Cancel/retry options

### Debug Mode:
- Detailed logging to `debug.log`
- API request/response logging
- Processing step details
- Error stack traces

### Diagnostics Page:
- Test PDF parsing
- Test OCR
- Test Zapier webhook
- Test iDoklad API
- See full API responses

---

## 📈 **Success Criteria**

✅ Email arrives with PDF invoice  
✅ PDF.co extracts text (with OCR if needed)  
✅ Zapier parses invoice data  
✅ iDoklad creates received invoice  
✅ User receives notification  
✅ Queue status: "completed"  

---

## 🎉 **Current Status**

- ✅ PDF.co integration complete
- ✅ ChatGPT removed (not needed)
- ✅ Zapier as AI parser
- ✅ Dashboard with quick actions
- ✅ Force email check button
- ✅ Process queue button
- ✅ Cancel queue items
- ✅ Detailed logging
- ✅ Error handling
- ✅ Automatic fallbacks

---

## 📝 **Notes**

1. **PDF.co handles ALL PDF processing** (text + OCR)
2. **Zapier handles ALL AI parsing** (invoice data extraction)
3. **No ChatGPT** - not needed, removed from flow
4. **Clean architecture** - each service has one job
5. **Fallbacks in place** - system works even if services fail

---

**Simple, clean, and effective!** 🚀


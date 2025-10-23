# Queue Viewer & Processing Details Guide

## 🎯 New Feature: Real-Time PDF Processing Monitor

We've added a comprehensive **Processing Queue** viewer that gives you complete visibility into what's happening with each PDF invoice as it's being processed.

## 📍 How to Access

1. Go to WordPress Admin
2. Click on **iDoklad Processor** in the sidebar
3. Click on **Processing Queue**

## 🔍 What You Can See

### Queue Overview

- **All** - View all items in the queue
- **Pending** - Items waiting to be processed
- **Processing** - Currently being processed
- **Completed** - Successfully processed
- **Failed** - Encountered errors

### For Each PDF, You Can See:

1. **Email From** - Who sent the invoice
2. **Subject** - Email subject line
3. **Attachment** - PDF filename
4. **Status** - Current processing status
5. **Current Step** - What's happening right now
6. **Attempts** - How many times we've tried to process it
7. **Created** - When it was received
8. **Details Button** - Click to see complete processing timeline

## 📊 Detailed Processing Timeline

Click the **"Details"** button on any queue item to see:

### Step-by-Step Processing Log

Each PDF goes through these steps:

1. ✅ **Checking PDF file** - Verify file exists
2. ✅ **Looking up authorized user** - Check sender is authorized
3. ✅ **Initializing processors** - Prepare PDF parser, Zapier, iDoklad API
4. ✅ **Extracting text from PDF** - Multiple parsing attempts:
   - Native PHP parser (tries first)
   - pdftotext (fallback)
   - poppler (fallback)
   - ghostscript (fallback)
   - OCR for scanned PDFs (if enabled)
5. ✅ **Preparing data for Zapier** - Format extracted text
6. ✅ **Sending to Zapier webhook** - Process through your Zap
7. ✅ **Validating extracted data** - Check all required fields
8. ✅ **Creating invoice in iDoklad** - Send to your iDoklad account
9. ✅ **Sending success notification** - Email confirmation
10. ✅ **Cleaning up PDF file** - Remove temporary file
11. ✅ **Processing completed successfully**

### Error Detection

- **ERROR** steps are highlighted in red
- See exactly where processing failed
- View detailed error messages
- Check which parsing methods were tried

## 🎛️ Queue Controls

### Refresh Queue
- Click **"Refresh"** button to update the view
- Enable **"Auto-refresh every 10 seconds"** for real-time monitoring

### Process Queue Now
- Manually trigger processing of pending items
- Useful for testing or when you can't wait for the scheduled cron job

## 📈 Queue Statistics

The sidebar shows:
- **Pending** count (waiting to process)
- **Processing** count (currently being processed)
- **Completed** count (successfully processed)
- **Failed** count (errors encountered)

## 🔧 Troubleshooting with Queue Viewer

### PDF Stuck at "Pending"?

1. Go to **Processing Queue**
2. Find your PDF
3. Click **"Details"**
4. Check the **"Current Step"** field
5. Look for **ERROR** messages in red

### Common Issues You Can Diagnose:

**"PDF file not found"**
- File was deleted or moved
- Check file permissions

**"User not authorized"**
- Sender email not in authorized users list
- Add user in **Authorized Users** tab

**"Could not extract text from PDF"**
- PDF might be scanned (enable OCR)
- PDF might be encrypted
- PDF might be corrupted

**"Zapier processing failed"**
- Check Zapier webhook URL
- Test webhook connection
- Check Zapier task history

**"Failed to create invoice in iDoklad"**
- Check user's iDoklad API credentials
- Test iDoklad connection for that user
- Check extracted data validation

**"Validation failed"**
- Missing required fields (invoice number, date, amount, supplier)
- Invalid data format
- Check extracted data in details

## 🎨 Visual Indicators

- 🟠 **Orange** = Pending
- 🔵 **Blue** = Processing
- 🟢 **Green** = Completed
- 🔴 **Red** = Failed

## ⚡ Performance Tips

1. **Enable Auto-Refresh** when actively monitoring
2. **Filter by status** to focus on specific items
3. **Check Failed items first** to quickly identify problems
4. **Use "Process Queue Now"** to test immediately

## 🆕 Database Changes (v1.1.0)

The plugin automatically adds these columns to existing installations:
- `processing_details` - JSON log of all processing steps
- `current_step` - The current step being executed

No manual database changes needed! The plugin handles the upgrade automatically.

## 📝 Example Processing Timeline

Here's what you'll see in the details view:

```
1. Checking PDF file
   2024-10-21 10:30:01
   path: /wp-content/uploads/idoklad-invoices/2024-10-21_12345_invoice.pdf
   size: 245678 bytes

2. PDF file found
   2024-10-21 10:30:01

3. Looking up authorized user
   2024-10-21 10:30:01
   email: supplier@example.com

4. User authorized
   2024-10-21 10:30:01
   user_id: 5
   user_name: Example Supplier

5. Initializing processors
   2024-10-21 10:30:02

6. Extracting text from PDF
   2024-10-21 10:30:02
   filename: invoice.pdf

7. PDF Parsing: Trying native PHP parser
   2024-10-21 10:30:02

8. PDF Parsing: Native PHP parser succeeded
   2024-10-21 10:30:03
   characters: 1234

9. Text extracted successfully
   2024-10-21 10:30:03
   text_length: 1234 characters
   preview: Faktura č. 2024001 Datum: 21.10.2024...

... (continues through all steps)
```

## 🚀 Benefits

- **Complete Transparency** - See exactly what's happening
- **Fast Debugging** - Identify issues in seconds
- **Peace of Mind** - Know your invoices are being processed
- **Real-Time Monitoring** - Watch processing happen live
- **Detailed Error Messages** - Fix problems quickly

---

**Need Help?** Check the **Processing Logs** tab for historical data, or enable **Debug Mode** in Settings for even more detailed logging to WordPress error logs.


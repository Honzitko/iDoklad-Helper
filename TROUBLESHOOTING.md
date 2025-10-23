# Troubleshooting Guide

## Items Stuck on "Processing"

### Quick Fix
1. Go to **Processing Queue**
2. Click **"Reset Stuck Items"** button
3. This will move items stuck for more than 5 minutes back to "Pending"
4. Click **"Process Queue Now"** to retry

### What Causes This?
- PHP fatal error during processing
- Missing required classes (Zapier, Notification, etc.)
- Server timeout
- Memory limit exceeded
- Database connection lost

### How We Fixed It
✅ Added **Fatal Error Catching** - Now catches both Exceptions and Errors  
✅ Added **Required Class Loading** - Zapier integration now properly loaded  
✅ Added **Detailed Error Logging** - Every error now logged with stack trace  
✅ Added **Auto-Reset Function** - Can manually reset stuck items

## "Details" Button Not Working

### Check This First
1. **Open Browser Console** (F12 → Console tab)
2. **Click Details Button**
3. **Look for error messages**

### What to Look For in Console

**If you see:** `"Details button clicked, queue ID: X"`  
✅ JavaScript is working

**If you see:** `"AJAX response: ..."`  
✅ Server communication working

**If you see:** `"Network error"` or `"AJAX error"`  
❌ Server issue - check PHP error logs

**If you see:** `"Error: No queue ID found"`  
❌ Button HTML issue - refresh the page

### Common Fixes

#### 1. Clear Browser Cache
```
Ctrl+Shift+R (Windows)
Cmd+Shift+R (Mac)
```

#### 2. Check WordPress Debug Mode
In `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Then check `wp-content/debug.log` for errors

#### 3. Verify AJAX Endpoint
The endpoint should be:
```
/wp-admin/admin-ajax.php?action=idoklad_get_queue_details
```

#### 4. Check for JavaScript Conflicts
Disable other plugins temporarily to see if there's a conflict

## No Processing Details Shown

### Possible Causes

**Empty processing_details column:**
- Item hasn't been processed yet
- Database upgrade didn't run
- Processing stopped before first step

### Fix: Force Database Upgrade

Run this in **Tools → Site Health → Info → Database**:
```sql
SHOW COLUMNS FROM wp_idoklad_queue;
```

Should show `processing_details` and `current_step` columns.

If not, deactivate and reactivate the plugin.

## Items Never Leave "Pending"

### Check Cron Jobs
1. Go to **Tools → Site Health**
2. Check if cron jobs are running
3. Install "WP Crontrol" plugin to view scheduled tasks

### Manual Processing
- Click **"Process Queue Now"** button
- This bypasses cron and processes immediately

### Enable Debug Mode
1. Go to **iDoklad Processor → Settings**
2. Check **"Enable Debug Mode"**
3. Save settings
4. Check `wp-content/debug.log` for detailed processing logs

## Debug Mode Output

When debug mode is enabled, you'll see logs like:

```
[21-Oct-2024 10:30:01] iDoklad Email Monitor: Processing 3 pending emails
[21-Oct-2024 10:30:02] iDoklad PDF Processor: Extracting text from /path/to/invoice.pdf
[21-Oct-2024 10:30:02] iDoklad PDF Processor: Trying native PHP parser
[21-Oct-2024 10:30:03] iDoklad PDF Processor: Native PHP parser succeeded
[21-Oct-2024 10:30:03] iDoklad PDF Processor: Extracted 1234 characters of text using: native PHP parser
[21-Oct-2024 10:30:04] iDoklad Email Monitor: Zapier processing successful
[21-Oct-2024 10:30:05] iDoklad Email Monitor: Successfully processed email from supplier@example.com
```

## Error Messages & Solutions

### "PDF file not found"
**Cause:** File was deleted or moved  
**Solution:** Check file permissions on `/wp-content/uploads/idoklad-invoices/`

### "User not found or not authorized"
**Cause:** Sender email not in authorized users  
**Solution:** Add user in **Authorized Users** tab

### "Could not extract text from PDF"
**Cause:** Scanned PDF or unsupported format  
**Solutions:**
- Enable OCR in settings
- Configure OCR.space API
- Test PDF manually

### "Zapier processing failed"
**Cause:** Webhook URL incorrect or Zapier down  
**Solutions:**
- Test webhook in Settings
- Check Zapier task history
- Verify webhook URL

### "Invalid invoice data"
**Cause:** Missing required fields  
**Solution:** Check extracted data in details - must have:
- invoice_number
- date
- total_amount
- supplier_name

### "Failed to create invoice in iDoklad"
**Cause:** iDoklad API credentials invalid  
**Solutions:**
- Test connection for that user
- Verify Client ID and Secret
- Check iDoklad account status

## Still Having Issues?

### 1. Check All Systems
Go to each settings tab and click test buttons:
- ✅ Test Email Connection
- ✅ Test Zapier Webhook  
- ✅ Test OCR.space API (if using scanned PDFs)
- ✅ Test iDoklad Connection (for each user)

### 2. Review Queue Timeline
1. Click **Details** on a failed item
2. Read the timeline step by step
3. Find the first ERROR message
4. That's where it failed!

### 3. Check Server Resources
- PHP memory limit (minimum 256MB recommended)
- PHP max execution time (minimum 60 seconds)
- Available disk space
- Server load

### 4. Fresh Start
1. Click **"Reset Stuck Items"**
2. Click **"Process Queue Now"**
3. Watch the queue with **Auto-refresh enabled**
4. Click **Details** immediately if one fails

## Getting Help

When asking for help, provide:

1. **Error message** from Details timeline
2. **Debug log** excerpt (with sensitive data removed)
3. **Browser console** output
4. **PHP version** and server type
5. **WordPress version**
6. **Plugin version** (currently 1.1.0)

## Quick Diagnostic Checklist

- [ ] Database upgrade ran (check for `processing_details` column)
- [ ] JavaScript loaded (no console errors)
- [ ] AJAX endpoint responding
- [ ] All required classes loaded
- [ ] Debug mode enabled for detailed logs
- [ ] Cron jobs running
- [ ] Server resources adequate
- [ ] All test connections passing

---

**Last Updated:** v1.1.0  
**Need more help?** Enable debug mode and check the processing timeline!


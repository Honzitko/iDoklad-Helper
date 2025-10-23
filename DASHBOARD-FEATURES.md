# Dashboard & New Features Guide

## 🎯 What's New

### 1. **Dashboard (New First Page)**

The Dashboard is now the first page you see when accessing iDoklad Processor!

**Location:** iDoklad Processor → Dashboard

**Features:**
- **Quick Actions**
  - ✅ Check Emails Now (force email monitoring)
  - ✅ Process Pending Queue
  - ✅ Diagnostics & Testing shortcut

- **Statistics Cards**
  - ⏳ Pending items
  - ⚙️ Processing items
  - ✅ Completed items
  - ❌ Failed items
  - 📊 Total processed
  - 📅 Today's items

- **System Status**
  - Email monitoring status
  - OCR status
  - Zapier integration status
  - Authorized users count
  - Last processed time

- **Recent Queue Items** (last 10)
  - Quick view of latest items
  - Details button
  - **Cancel button** for pending/processing/failed items

- **Recent Activity Log** (last 5)
  - Latest processing activity

---

### 2. **Force Email Check Button** ⚡

**Where:** Dashboard → Quick Actions

**What it does:**
- Manually triggers email monitoring
- Fetches new emails from your inbox
- Processes pending queue items
- Shows how many emails found and processed

**When to use:**
- Testing email integration
- Force immediate processing without waiting for cron
- After adding a new authorized user
- Debugging email issues

**Example Result:**
```
✅ Success: Found 3 new email(s), processed 2 item(s)
```

---

### 3. **Cancel Queue Items** ❌

**Where:** 
- Dashboard → Recent Queue Items → Cancel button
- Processing Queue → Actions → Cancel button

**What it does:**
- Stops processing of any item
- Marks item as "failed"
- Adds "Cancelled by user" to processing steps
- Prevents further attempts

**Available for:**
- ✅ Pending items
- ✅ Processing items
- ✅ Failed items (to prevent retry)

**Not available for:**
- ❌ Completed items (already done)

**Confirmation:**
Before cancelling, you'll see:
```
Are you sure you want to cancel this item? 
It will be marked as failed.
```

**What happens:**
1. Item status changes to "failed"
2. Current step set to "Cancelled by user"
3. Processing details log the cancellation
4. Item won't be retried automatically

**Example use cases:**
- Wrong PDF uploaded
- Duplicate email
- Testing gone wrong
- Stuck item you want to clear
- Email from wrong sender

---

## 📊 Dashboard Statistics

### Status Explanations:

**Pending** ⏳
- Waiting to be processed
- Will be picked up by cron or manual trigger

**Processing** ⚙️
- Currently being processed
- Usually takes < 30 seconds
- If stuck > 5 minutes, use "Reset Stuck Items"

**Completed** ✅
- Successfully processed
- Invoice created in iDoklad
- Email notification sent

**Failed** ❌
- Processing failed
- Check Details for error
- Can be cancelled to prevent retry

---

## 🚀 Quick Workflow

### Normal Operation:
1. **Dashboard** - Check statistics
2. **Quick Actions** - Process queue if needed
3. **Recent Items** - Monitor latest processing
4. **View Details** - Check any failed items

### Manual Processing:
1. Go to **Dashboard**
2. Click **"Check Emails Now"**
3. Wait for confirmation
4. Check **Recent Queue Items** for results

### Handling Errors:
1. See failed item on **Dashboard**
2. Click **"Details"** to see error
3. Fix the issue (user config, PDF, etc.)
4. Click **"Cancel"** to prevent retry
5. Or let it retry automatically

---

## 🔄 Menu Structure (New Order)

1. **Dashboard** (NEW! - First page)
2. **Settings**
3. **Authorized Users**
4. **Processing Queue**
5. **Processing Logs**
6. **Diagnostics & Testing**

---

## 💡 Tips

### Email Monitoring
- Use "Check Emails Now" for testing
- Don't spam it - be reasonable
- Wait 10-30 seconds between checks

### Cancelling Items
- Only cancel if you're sure
- Completed items can't be cancelled
- Cancellation is logged in processing details

### Dashboard Refresh
- Statistics update on page load
- Click "Check Emails Now" to force update
- Auto-refresh not enabled (refresh page manually)

### When to Use Dashboard
- ✅ Quick status check
- ✅ Manual email trigger
- ✅ Cancel stuck/wrong items
- ✅ Monitor recent activity

### When to Use Queue Page
- ✅ View all items (not just recent 10)
- ✅ Filter by status
- ✅ Detailed timeline view
- ✅ Reset stuck items in bulk

---

## 🎨 Visual Guide

### Dashboard Layout:

```
┌─────────────────────────────────────────────────────┐
│  iDoklad Invoice Processor - Dashboard             │
├─────────────────────────────────────────────────────┤
│  Quick Actions:                                     │
│  [Check Emails Now] [Process Queue] [Diagnostics]  │
├─────────────────────────────────────────────────────┤
│  Statistics:                                        │
│  [⏳ Pending] [⚙️ Processing] [✅ Completed]        │
│  [❌ Failed]  [📊 Total]     [📅 Today]            │
├─────────────────────────────────────────────────────┤
│  System Status:                                     │
│  Email Monitoring: ✓ Active                        │
│  OCR: ✗ Disabled                                    │
│  Zapier: ✓ Configured                              │
├─────────────────────────────────────────────────────┤
│  Recent Queue Items:                                │
│  ID | From | Subject | Status | [Details] [Cancel] │
│  -------------------------------------------------- │
│  10 | a@b  | Invoice | Pending | [Details] [Cancel]│
│   9 | c@d  | Bill    | Success | [Details]          │
└─────────────────────────────────────────────────────┘
```

---

## 🔒 Security

- All actions require `manage_options` capability
- AJAX requests use WordPress nonces
- Cancellation is logged in processing details
- No data is deleted, only status changed

---

## 📝 Changelog

**Version 1.2.0**
- ✅ Added Dashboard as first page
- ✅ Added "Force Email Check" button
- ✅ Added "Cancel" button for queue items
- ✅ Reorganized menu structure
- ✅ Added dashboard statistics
- ✅ Added recent activity view

---

**Enjoy the new Dashboard! 🎉**


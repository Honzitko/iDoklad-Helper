# iDoklad Invoice Processor - User Manual

This manual provides detailed instructions for using the iDoklad Invoice Processor plugin, designed for both end users and administrators.

## Table of Contents

1. [Getting Started](#getting-started)
2. [For End Users](#for-end-users)
3. [For Administrators](#for-administrators)
4. [Troubleshooting](#troubleshooting)
5. [Best Practices](#best-practices)

## Getting Started

### What is iDoklad Invoice Processor?

The iDoklad Invoice Processor is a WordPress plugin that automates the processing of PDF invoices. It:

- Monitors a dedicated email inbox for new PDF invoices
- Uses AI (ChatGPT) to extract data from PDF invoices
- Creates invoices or expenses in iDoklad automatically
- Sends confirmation emails to users
- Provides detailed logging and monitoring

### How It Works

1. **Email Reception**: Users send PDF invoices to a configured email address
2. **Authorization Check**: System verifies the sender is authorized
3. **PDF Processing**: AI extracts structured data from the PDF
4. **Data Validation**: System validates the extracted information
5. **iDoklad Integration**: Creates invoice/expense in iDoklad
6. **Notification**: Sends confirmation email to user

## For End Users

### Sending Invoices

#### Step 1: Prepare Your Invoice
- Ensure your PDF invoice is clear and readable
- Include all required information:
  - Invoice number
  - Date
  - Supplier name
  - Total amount
  - Items (if applicable)
  - VAT number (if applicable)

#### Step 2: Send Email
1. **Compose a new email** to the configured invoice processing address
2. **Attach your PDF invoice** (only PDF files are supported)
3. **Send the email**

#### Step 3: Wait for Processing
- Processing typically takes 1-5 minutes
- You'll receive a confirmation email when complete
- Check your email for any error notifications

### Understanding Notifications

#### Success Notification
You'll receive an email confirming:
- Invoice was processed successfully
- Invoice details (number, supplier, amount)
- iDoklad ID of the created record

#### Failure Notification
If processing fails, you'll receive an email with:
- Error type and description
- What went wrong
- Steps to resolve the issue

### Common Error Messages

#### "Unauthorized sender"
- Your email address is not in the authorized users list
- Contact your administrator to add your email

#### "No PDF attachments found"
- Your email doesn't contain a PDF attachment
- Resend with a PDF file attached

#### "Poor quality scan - PDF is not readable"
- The PDF is not clear enough for processing
- Try scanning at higher resolution
- Ensure the PDF contains text, not just images

#### "Incomplete invoice - missing required information"
- The invoice is missing required fields
- Ensure all necessary information is present and clear

#### "Corrupted or invalid PDF file"
- The PDF file is damaged
- Try recreating or rescanning the PDF

### Best Practices for End Users

1. **Use Clear PDFs**: Ensure invoices are scanned at high resolution
2. **Include All Information**: Make sure all required fields are visible
3. **One Invoice Per Email**: Send only one invoice per email
4. **Use Standard Formats**: Stick to common invoice formats
5. **Check Email Address**: Ensure you're sending to the correct address

## For Administrators

### Accessing the Admin Interface

1. **Log in** to your WordPress admin panel
2. **Navigate** to "iDoklad Processor" in the admin menu
3. **Access different sections**:
   - Settings: Configure the system
   - Authorized Users: Manage user access
   - Processing Logs: Monitor system activity

### Settings Configuration

#### Email Settings
Configure the email account that receives invoices:

- **Email Host**: IMAP server address
- **Port**: Usually 993 for SSL
- **Username**: Full email address
- **Password**: Email password or app password
- **Encryption**: SSL, TLS, or None

**Test Connection**: Use the test button to verify settings

#### iDoklad API Settings
Configure connection to iDoklad:

- **API URL**: https://api.idoklad.cz/v3
- **Client ID**: From your iDoklad application
- **Client Secret**: From your iDoklad application

**Test Connection**: Verify API access

#### ChatGPT Settings
Configure AI processing:

- **API Key**: Your OpenAI API key
- **Model**: Choose between GPT-4, GPT-3.5 Turbo, or GPT-4 Turbo
- **Extraction Prompt**: Customize how AI extracts data

**Test Connection**: Verify API access

#### General Settings
- **Notification Email**: Where to send system notifications
- **Debug Mode**: Enable for troubleshooting

### User Management

#### Adding Users
1. **Go to** Authorized Users
2. **Click** "Add New User"
3. **Enter**:
   - Email address
   - Display name
   - iDoklad User ID (optional)
4. **Save** the user

#### Managing Users
- **View** all authorized users
- **Edit** user information
- **Delete** users
- **Bulk import** from CSV

#### User Statistics
Monitor user activity:
- Total users
- Active users (last 30 days)
- Top users by email count
- Success/failure rates

### Monitoring and Logs

#### Processing Logs
View detailed logs of all processing activities:

- **Filter by status**: Success, Failed, Pending
- **View details**: Click "View Details" for full information
- **Export logs**: Download as CSV
- **Search logs**: Find specific entries

#### System Status
Monitor system health:

- **Plugin version** and compatibility
- **PHP extensions** status
- **System tools** availability
- **Queue status** (pending, processing, failed)
- **Recent activity** summary

#### Queue Management
- **View pending emails** in the processing queue
- **Process queue manually** if needed
- **Monitor processing times**
- **Handle failed items**

### Troubleshooting for Administrators

#### Email Issues
**Problem**: Cannot connect to email server
**Solutions**:
- Verify email credentials
- Check IMAP is enabled
- Try different port/encryption
- Contact email provider

**Problem**: Emails not being processed
**Solutions**:
- Check cron job is running
- Verify email settings
- Check for firewall issues
- Review error logs

#### API Issues
**Problem**: iDoklad API connection failed
**Solutions**:
- Verify Client ID and Secret
- Check API access in iDoklad
- Ensure correct API URL
- Check for rate limits

**Problem**: ChatGPT API errors
**Solutions**:
- Verify API key
- Check account credits
- Monitor usage limits
- Try different model

#### Processing Issues
**Problem**: PDFs not being processed
**Solutions**:
- Check PDF file integrity
- Verify text extraction tools
- Enable OCR for image PDFs
- Review extraction prompts

**Problem**: Data extraction errors
**Solutions**:
- Improve PDF quality
- Customize extraction prompts
- Check for missing information
- Review validation rules

### Maintenance Tasks

#### Daily Tasks
- **Check processing logs** for errors
- **Monitor queue status**
- **Review failed items**
- **Check system notifications**

#### Weekly Tasks
- **Review user activity**
- **Check API usage** and costs
- **Clean up old logs**
- **Update user permissions**

#### Monthly Tasks
- **Review system performance**
- **Update plugin** if new version available
- **Backup configuration**
- **Review and optimize settings**

## Troubleshooting

### Common Issues and Solutions

#### System Issues

**Problem**: Plugin not activating
**Solution**: Check PHP version and required extensions

**Problem**: Database errors
**Solution**: Verify database permissions and WordPress database integrity

**Problem**: Memory errors
**Solution**: Increase PHP memory limit

#### Email Processing Issues

**Problem**: Emails not being received
**Solution**: 
- Check email server settings
- Verify IMAP is enabled
- Check spam folders
- Test email connection

**Problem**: Attachments not being processed
**Solution**:
- Verify PDF file format
- Check file size limits
- Ensure proper email encoding

#### AI Processing Issues

**Problem**: Poor data extraction
**Solution**:
- Improve PDF quality
- Customize extraction prompts
- Use higher quality AI model
- Enable OCR for image PDFs

**Problem**: API rate limits
**Solution**:
- Monitor API usage
- Implement rate limiting
- Use appropriate model
- Optimize prompts

#### iDoklad Integration Issues

**Problem**: Invoices not being created
**Solution**:
- Verify API credentials
- Check data validation
- Review iDoklad settings
- Check for API errors

**Problem**: Wrong invoice format
**Solution**:
- Review data mapping
- Check currency settings
- Verify VAT rates
- Update field mappings

### Getting Help

#### Self-Help Resources
1. **Check logs** for error messages
2. **Enable debug mode** for detailed information
3. **Review documentation** and FAQs
4. **Test individual components**

#### Contact Support
1. **Gather information**:
   - Error messages
   - Log entries
   - System configuration
   - Steps to reproduce
2. **Contact administrator** or support team
3. **Provide detailed information** about the issue

## Best Practices

### For End Users

1. **Use High-Quality PDFs**
   - Scan at 300 DPI or higher
   - Ensure text is clear and readable
   - Avoid skewed or rotated pages

2. **Include Complete Information**
   - Invoice number
   - Date
   - Supplier details
   - Itemized list
   - Total amounts
   - VAT information

3. **Follow Email Guidelines**
   - One invoice per email
   - Use clear subject lines
   - Attach only PDF files
   - Send from authorized email

4. **Monitor Notifications**
   - Check for confirmation emails
   - Respond to error notifications
   - Keep records of processed invoices

### For Administrators

1. **Regular Monitoring**
   - Check logs daily
   - Monitor system status
   - Review user activity
   - Track API usage

2. **Security Best Practices**
   - Use strong passwords
   - Enable 2FA where possible
   - Regular security updates
   - Monitor access logs

3. **Performance Optimization**
   - Regular database cleanup
   - Monitor server resources
   - Optimize settings
   - Use caching

4. **Backup and Recovery**
   - Regular configuration backups
   - Test recovery procedures
   - Document settings
   - Keep API keys secure

5. **User Management**
   - Regular user reviews
   - Remove inactive users
   - Monitor user activity
   - Provide user training

### System Maintenance

1. **Regular Updates**
   - Keep WordPress updated
   - Update plugins regularly
   - Monitor security patches
   - Test updates in staging

2. **Performance Monitoring**
   - Monitor server resources
   - Check processing times
   - Optimize database
   - Review error rates

3. **Data Management**
   - Regular log cleanup
   - Archive old data
   - Monitor storage usage
   - Implement retention policies

---

This manual should help you effectively use and manage the iDoklad Invoice Processor. For additional support, refer to the installation guide and troubleshooting sections.

# iDoklad Invoice Processor - Installation Guide

This guide will walk you through the complete installation and setup process for the iDoklad Invoice Processor WordPress plugin.

## Prerequisites

### Server Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL**: 5.6 or higher
- **Memory**: Minimum 256MB PHP memory limit
- **Disk Space**: At least 100MB free space

### Required PHP Extensions
- `imap` - For email processing
- `curl` - For API communications
- `json` - For data processing
- `mbstring` - For text processing
- `openssl` - For secure connections

### Optional but Recommended
- `ghostscript` - For better PDF processing
- `poppler-utils` - For text extraction from PDFs
- `tesseract-ocr` - For OCR processing of image-based PDFs

## Step 1: Plugin Installation

### Method 1: Manual Upload
1. Download the plugin files
2. Upload the `idoklad-invoice-processor` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin panel

### Method 2: WordPress Admin Upload
1. Go to **Plugins > Add New** in your WordPress admin
2. Click **Upload Plugin**
3. Choose the plugin zip file
4. Click **Install Now** and then **Activate**

## Step 2: System Requirements Check

After activation, the plugin will automatically check your system requirements:

1. Go to **iDoklad Processor > Settings**
2. Check the **System Status** widget in the sidebar
3. Verify all requirements are met

### Installing Missing PHP Extensions

#### Ubuntu/Debian
```bash
sudo apt-get update
sudo apt-get install php-imap php-curl php-mbstring php-json
sudo systemctl restart apache2  # or nginx
```

#### CentOS/RHEL
```bash
sudo yum install php-imap php-curl php-mbstring php-json
sudo systemctl restart httpd
```

#### cPanel/Shared Hosting
Contact your hosting provider to enable the required PHP extensions.

### Installing Optional Tools

#### Ubuntu/Debian
```bash
sudo apt-get install ghostscript poppler-utils tesseract-ocr
```

#### CentOS/RHEL
```bash
sudo yum install ghostscript poppler-utils tesseract
```

## Step 3: Email Account Setup

### Gmail Setup
1. **Enable 2-Factor Authentication** on your Gmail account
2. **Generate App Password**:
   - Go to Google Account settings
   - Security > 2-Step Verification > App passwords
   - Generate a password for "Mail"
3. **Configure IMAP**:
   - Gmail Settings > Forwarding and POP/IMAP
   - Enable IMAP access

### Outlook/Office 365 Setup
1. **Enable IMAP** in your Outlook settings
2. **Use your regular email password**
3. **Configure server settings**:
   - IMAP server: outlook.office365.com
   - Port: 993
   - Encryption: SSL/TLS

### Other Email Providers
Check your email provider's documentation for IMAP settings.

## Step 4: iDoklad API Setup

### Create iDoklad Application
1. **Log in** to your iDoklad account
2. **Go to Settings** > API
3. **Create New Application**:
   - Application name: "WordPress Invoice Processor"
   - Description: "Automated invoice processing"
   - Redirect URI: Leave empty
4. **Save the credentials**:
   - Client ID
   - Client Secret

### Test API Connection
1. In the plugin settings, enter your iDoklad credentials
2. Click **Test iDoklad Connection**
3. Verify the connection is successful

## Step 5: OpenAI API Setup

### Create OpenAI Account
1. **Sign up** at https://platform.openai.com
2. **Add payment method** (required for API access)
3. **Generate API key**:
   - Go to API Keys section
   - Create new secret key
   - Copy and save the key securely

### Configure ChatGPT Settings
1. **Enter your API key** in the plugin settings
2. **Choose model**:
   - GPT-4: Best accuracy, higher cost
   - GPT-3.5 Turbo: Good balance
   - GPT-4 Turbo: Latest features
3. **Test connection** using the test button

## Step 6: Plugin Configuration

### Email Settings
```
Email Host: imap.gmail.com (or your provider)
Port: 993
Username: your-email@domain.com
Password: your-app-password
Encryption: SSL
```

### iDoklad Settings
```
API URL: https://api.idoklad.cz/v3
Client ID: [from iDoklad application]
Client Secret: [from iDoklad application]
```

### ChatGPT Settings
```
API Key: [from OpenAI]
Model: gpt-4 (recommended)
Extraction Prompt: [default or customized]
```

### General Settings
```
Notification Email: admin@yourdomain.com
Debug Mode: Enable for troubleshooting
```

## Step 7: Add Authorized Users

### Add Users
1. Go to **iDoklad Processor > Authorized Users**
2. Click **Add New User**
3. Enter:
   - Email address
   - Display name
   - iDoklad User ID (optional)

### Bulk Import (Optional)
1. Create a CSV file with columns: Email, Name, iDoklad User ID
2. Use the bulk import feature

## Step 8: Test the System

### Send Test Email
1. **Send a test PDF invoice** to your configured email address
2. **Check the logs** in the admin interface
3. **Verify processing** in iDoklad

### Monitor Processing
1. Go to **Processing Logs** to see the processing status
2. Check for any errors or warnings
3. Verify the invoice was created in iDoklad

## Step 9: Production Setup

### Disable Debug Mode
Once everything is working, disable debug mode to reduce log verbosity.

### Set Up Monitoring
1. **Configure email notifications** for system errors
2. **Set up regular log reviews**
3. **Monitor API usage and costs**

### Backup Configuration
1. **Export settings** (if available)
2. **Document your configuration**
3. **Keep API keys secure**

## Troubleshooting Installation

### Common Issues

#### "IMAP extension not found"
```bash
# Ubuntu/Debian
sudo apt-get install php-imap
sudo systemctl restart apache2

# CentOS/RHEL
sudo yum install php-imap
sudo systemctl restart httpd
```

#### "Cannot connect to email server"
- Verify email credentials
- Check firewall settings
- Ensure IMAP is enabled on email account
- Try different port/encryption settings

#### "iDoklad API connection failed"
- Verify Client ID and Secret
- Check if API access is enabled in iDoklad
- Ensure correct API URL

#### "ChatGPT API error"
- Verify API key is correct
- Check if you have sufficient credits
- Ensure the model is available in your region

### Getting Help

1. **Check the logs** in the admin interface
2. **Enable debug mode** for detailed information
3. **Review the troubleshooting section** in the main README
4. **Contact your system administrator**

## Security Considerations

### API Keys
- Store API keys securely
- Never share them in public
- Rotate keys regularly
- Use environment variables if possible

### Email Security
- Use app-specific passwords
- Enable 2-factor authentication
- Monitor email access logs
- Use secure connections (SSL/TLS)

### WordPress Security
- Keep WordPress and plugins updated
- Use strong passwords
- Limit admin access
- Enable security plugins

## Maintenance

### Regular Tasks
- **Monitor logs** for errors
- **Check API usage** and costs
- **Update plugin** when new versions are available
- **Review authorized users** list
- **Backup configuration** settings

### Performance Optimization
- **Clean up old logs** regularly
- **Monitor server resources**
- **Optimize database** if needed
- **Use caching** for better performance

## Support

For additional support:
1. Check the main README.md file
2. Review the troubleshooting section
3. Contact your system administrator
4. Check WordPress and plugin documentation

---

**Note**: This installation guide assumes you have administrative access to your WordPress installation and server. If you're using shared hosting, some steps may need to be performed by your hosting provider.

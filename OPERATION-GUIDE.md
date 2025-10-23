# Operation Guide - iDoklad Invoice Processor

This comprehensive guide covers all aspects of operating the iDoklad Invoice Processor plugin, from initial setup to daily operations and troubleshooting.

## 📋 Table of Contents

1. [Initial Setup](#initial-setup)
2. [Daily Operations](#daily-operations)
3. [Admin Interface Guide](#admin-interface-guide)
4. [Testing and Debugging](#testing-and-debugging)
5. [Maintenance](#maintenance)
6. [Troubleshooting](#troubleshooting)
7. [Best Practices](#best-practices)

## 🚀 Initial Setup

### 1. Plugin Installation

#### Step 1: Upload Plugin
```bash
# Upload the plugin folder to WordPress plugins directory
/wp-content/plugins/idoklad-invoice-processor/
```

#### Step 2: Activate Plugin
1. Go to **WordPress Admin → Plugins**
2. Find "iDoklad Invoice Processor"
3. Click **Activate**

#### Step 3: Verify Installation
1. Check that plugin appears in admin menu
2. Verify no activation errors
3. Check error logs for any issues

### 2. Basic Configuration

#### PDF.co API Setup
1. **Get API Key**
   - Sign up at [PDF.co](https://pdf.co)
   - Get your API key from dashboard

2. **Configure in WordPress**
   - Go to **iDoklad Processor → Settings**
   - Enter PDF.co API Key
   - Enable AI Parser
   - Save settings

#### iDoklad API Setup
1. **Get Credentials**
   - Sign up at [iDoklad](https://idoklad.cz)
   - Get Client ID and Client Secret

2. **Configure in WordPress**
   - Go to **iDoklad Processor → iDoklad API Integration**
   - Enter Client ID and Client Secret
   - Set Default Partner ID
   - Save configuration

#### Email Configuration
1. **IMAP Settings**
   - Go to **iDoklad Processor → Settings**
   - Configure email settings:
     - Host: `imap.gmail.com`
     - Port: `993`
     - Username: Your email
     - Password: Your password
     - Encryption: `SSL`

### 3. User Management

#### Add Authorized Users
1. **Go to Users Page**
   - Navigate to **iDoklad Processor → Authorized Users**

2. **Add New User**
   - Click "Add New User"
   - Enter email and name
   - Configure iDoklad credentials (optional)
   - Save user

## 📊 Daily Operations

### 1. Dashboard Monitoring

#### Overview Dashboard
**Location**: WordPress Admin → iDoklad Processor → Dashboard

**Key Metrics to Monitor**:
- **Processed Invoices**: Total invoices processed
- **Success Rate**: Percentage of successful processing
- **Queue Status**: Current queue items and status
- **Recent Activity**: Latest processing activities

**Daily Tasks**:
- Check dashboard for system health
- Review recent processing activities
- Monitor success rates
- Check for any error indicators

### 2. Queue Management

#### Processing Queue
**Location**: WordPress Admin → iDoklad Processor → Processing Queue

**Queue Statuses**:
- **Pending**: Items waiting to be processed
- **Processing**: Items currently being processed
- **Completed**: Successfully processed items
- **Failed**: Items that failed processing

**Daily Tasks**:
- Monitor queue for stuck items
- Retry failed items if appropriate
- Check processing times
- Clean up old completed items

#### Queue Actions
- **Retry Failed Items**: Click retry button for failed items
- **Cancel Stuck Items**: Cancel items stuck in processing
- **View Details**: Click on items to see detailed information
- **Export Queue**: Export queue data for analysis

### 3. Log Management

#### Processing Logs
**Location**: WordPress Admin → iDoklad Processor → Processing Logs

**Log Types**:
- **Success**: Successfully processed invoices
- **Failed**: Failed processing attempts
- **Pending**: Items waiting for processing
- **Processing**: Items currently being processed

**Daily Tasks**:
- Review failed processing logs
- Check for recurring errors
- Monitor processing times
- Export logs for analysis

#### Log Actions
- **Filter by Status**: Filter logs by processing status
- **Export Logs**: Export logs in CSV format
- **View Details**: Click on log entries for detailed information
- **Clean Old Logs**: Remove old log entries

## 🖥️ Admin Interface Guide

### 1. Settings Management

#### General Settings
**Location**: WordPress Admin → iDoklad Processor → Settings

**Configuration Sections**:

##### PDF Processing
- **PDF.co API Key**: Your PDF.co API key
- **Use AI Parser**: Enable AI-powered parsing
- **Use Native Parser**: Enable native text extraction
- **Use PDF.co**: Enable PDF.co text extraction

##### Email Settings
- **Email Host**: IMAP server hostname
- **Email Port**: IMAP server port
- **Email Username**: Email account username
- **Email Password**: Email account password
- **Email Encryption**: Encryption type (SSL/TLS)

##### General Options
- **Notification Email**: Email for notifications
- **Debug Mode**: Enable detailed logging
- **Use Native Parser First**: Try native parser before AI

### 2. User Management

#### Authorized Users
**Location**: WordPress Admin → iDoklad Processor → Authorized Users

**User Management**:
- **Add User**: Add new authorized user
- **Edit User**: Modify user information
- **Delete User**: Remove user access
- **Test Connection**: Test user's iDoklad connection

**User Configuration**:
- **Email**: User's email address
- **Name**: User's display name
- **iDoklad Credentials**: User's API credentials
- **Status**: Active/Inactive status

### 3. Testing and Debugging

#### PDF.co AI Parser Debug
**Location**: WordPress Admin → iDoklad Processor → PDF.co AI Parser Debug

**Testing Tools**:
- **Connection Test**: Test API connection
- **Parser Test**: Test with sample PDF
- **Step-by-Step Test**: Test each step separately
- **Payload Validation**: Validate extracted data

#### iDoklad API Integration
**Location**: WordPress Admin → iDoklad Processor → iDoklad API Integration

**Testing Tools**:
- **Test Integration**: Test complete workflow
- **Create Test Invoice**: Create test invoice
- **Connection Test**: Test API connection
- **Payload Validation**: Validate invoice payload

#### Diagnostics
**Location**: WordPress Admin → iDoklad Processor → Diagnostics & Testing

**Diagnostic Tools**:
- **PDF Parsing Test**: Test PDF parsing functionality
- **OCR Test**: Test OCR functionality
- **API Test**: Test API connections
- **System Test**: Test system components

### 4. Database Management

#### Database Management
**Location**: WordPress Admin → iDoklad Processor → Database Management

**Database Operations**:
- **Clean Old Logs**: Remove old log entries
- **Clean Old Queue**: Remove old queue items
- **Database Statistics**: View database statistics
- **Export Data**: Export database data

## 🔧 Testing and Debugging

### 1. Debug Mode

#### Enable Debug Mode
1. **Go to Settings**
   - Navigate to **iDoklad Processor → Settings**
   - Enable "Debug Mode"
   - Save settings

2. **Check Logs**
   - Debug information is written to WordPress error log
   - Log location: `/wp-content/debug.log`

#### Debug Information
Debug mode provides detailed information about:
- API requests and responses
- PDF parsing steps
- Data transformation
- Error handling
- Performance metrics

### 2. Testing Tools

#### Standalone Test Scripts
```bash
# Test iDoklad integration
php test-idoklad-integration.php

# Test PDF parsing
php test-pdf-co-parser.php

# Test complete workflow
php test-postman-workflow.php
```

#### Admin Interface Testing
- **Connection Testing**: Test API connections
- **Parser Testing**: Test PDF parsing
- **Integration Testing**: Test complete workflow
- **Validation Testing**: Test data validation

### 3. Error Handling

#### Common Error Types
- **API Connection Errors**: Network or authentication issues
- **PDF Parsing Errors**: PDF format or parsing issues
- **Data Validation Errors**: Invalid or missing data
- **Processing Errors**: General processing failures

#### Error Resolution
1. **Check Error Logs**: Review detailed error information
2. **Use Debug Tools**: Test components separately
3. **Verify Configuration**: Check API keys and settings
4. **Test with Samples**: Use test data to isolate issues

## 🔧 Maintenance

### 1. Regular Maintenance

#### Daily Tasks
- **Monitor Dashboard**: Check system health
- **Review Logs**: Check for errors or issues
- **Monitor Queue**: Ensure queue is processing
- **Check Notifications**: Review any alerts

#### Weekly Tasks
- **Clean Old Logs**: Remove old log entries
- **Clean Old Queue**: Remove old queue items
- **Review Performance**: Check processing times
- **Update Documentation**: Update any changes

#### Monthly Tasks
- **Database Maintenance**: Clean up database
- **Performance Review**: Analyze performance metrics
- **Security Review**: Check security settings
- **Backup Verification**: Verify backup systems

### 2. Database Maintenance

#### Clean Old Data
1. **Go to Database Management**
   - Navigate to **iDoklad Processor → Database Management**

2. **Clean Operations**
   - Clean old logs (older than 30 days)
   - Clean old queue items (older than 7 days)
   - Clean old attachments (older than 30 days)

#### Database Statistics
- **Total Records**: Number of records in each table
- **Storage Usage**: Database storage usage
- **Performance Metrics**: Query performance data

### 3. Performance Monitoring

#### Key Metrics
- **Processing Time**: Time to process invoices
- **Success Rate**: Percentage of successful processing
- **Queue Length**: Number of items in queue
- **Error Rate**: Percentage of failed processing

#### Performance Optimization
- **Queue Management**: Optimize queue processing
- **Database Optimization**: Optimize database queries
- **API Optimization**: Optimize API requests
- **Caching**: Implement caching where appropriate

## 🚨 Troubleshooting

### 1. Common Issues

#### Plugin Activation Issues
**Problem**: Plugin fails to activate
**Solutions**:
- Check error logs for specific errors
- Verify all files are present
- Check WordPress and PHP versions
- Ensure proper file permissions

#### API Connection Issues
**Problem**: API connections fail
**Solutions**:
- Verify API keys are correct
- Check network connectivity
- Test API endpoints manually
- Review error logs for details

#### PDF Parsing Issues
**Problem**: PDFs not parsing correctly
**Solutions**:
- Check PDF format and quality
- Verify PDF.co API key
- Test with different PDFs
- Use debug tools to isolate issues

#### iDoklad Integration Issues
**Problem**: Invoices not creating in iDoklad
**Solutions**:
- Verify iDoklad credentials
- Check payload format
- Test API connection
- Review error logs

### 2. Debugging Steps

#### Step 1: Enable Debug Mode
1. Go to **Settings → Debug Mode**
2. Enable debug mode
3. Check error logs

#### Step 2: Test Components
1. Use admin interface testing tools
2. Test each component separately
3. Identify which component is failing

#### Step 3: Check Configuration
1. Verify API keys and settings
2. Check email configuration
3. Review user permissions

#### Step 4: Review Logs
1. Check WordPress error logs
2. Review plugin-specific logs
3. Look for error patterns

### 3. Getting Help

#### Documentation
- Review all documentation files
- Check troubleshooting guides
- Use built-in help resources

#### Testing Tools
- Use built-in testing tools
- Test with sample data
- Isolate issues systematically

#### Support Resources
- Check error logs
- Use debug mode
- Test components separately

## 📋 Best Practices

### 1. Configuration

#### API Keys
- **Secure Storage**: Store API keys securely
- **Regular Rotation**: Rotate API keys regularly
- **Access Control**: Limit access to API keys
- **Monitoring**: Monitor API key usage

#### Email Settings
- **Secure Connection**: Use SSL/TLS encryption
- **Strong Passwords**: Use strong email passwords
- **Regular Updates**: Update email settings as needed
- **Backup Access**: Maintain backup email access

### 2. Operations

#### Daily Monitoring
- **Check Dashboard**: Monitor system health daily
- **Review Logs**: Check logs for issues
- **Monitor Queue**: Ensure queue is processing
- **Check Notifications**: Review any alerts

#### Regular Maintenance
- **Clean Old Data**: Regular cleanup of old data
- **Update Documentation**: Keep documentation current
- **Review Performance**: Monitor performance metrics
- **Security Review**: Regular security reviews

### 3. Security

#### Access Control
- **User Permissions**: Limit user access appropriately
- **API Access**: Control API access carefully
- **Admin Access**: Limit admin access
- **Regular Reviews**: Regular access reviews

#### Data Protection
- **Encryption**: Use encryption for sensitive data
- **Backup**: Regular backups of important data
- **Access Logs**: Monitor access logs
- **Security Updates**: Keep system updated

### 4. Performance

#### Optimization
- **Queue Management**: Optimize queue processing
- **Database Performance**: Optimize database queries
- **API Efficiency**: Optimize API requests
- **Caching**: Implement caching where appropriate

#### Monitoring
- **Performance Metrics**: Monitor key performance metrics
- **Error Rates**: Monitor error rates
- **Processing Times**: Monitor processing times
- **Resource Usage**: Monitor resource usage

---

**Version**: 1.1.0  
**Last Updated**: 2025-01-23  
**For Support**: Review documentation and use built-in testing tools

# iDoklad Invoice Processor - Complete WordPress Plugin

A comprehensive WordPress plugin that automates invoice processing through email monitoring, AI-powered PDF parsing, and direct integration with iDoklad API v3.

## 🚀 Quick Start

### Installation
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Configure your API credentials in **iDoklad Processor → Settings**

### Basic Configuration
1. **PDF.co API Key** - For AI-powered invoice parsing
2. **iDoklad API Credentials** - Client ID and Client Secret
3. **Email Settings** - IMAP configuration for receiving invoices
4. **Authorized Users** - Add users who can receive invoices

## 📋 Table of Contents

- [Features](#features)
- [Architecture](#architecture)
- [Configuration Guide](#configuration-guide)
- [Operation Guide](#operation-guide)
- [API Integration](#api-integration)
- [Troubleshooting](#troubleshooting)
- [Change Log](#change-log)
- [Support](#support)

## ✨ Features

### Core Functionality
- **Email Monitoring** - Automatically processes invoices from email attachments
- **AI-Powered PDF Parsing** - Uses PDF.co AI to extract structured data
- **iDoklad API v3 Integration** - Direct integration with iDoklad for invoice creation
- **Per-User Credentials** - Each user can have their own iDoklad credentials
- **Queue Management** - Robust processing queue with retry logic
- **Comprehensive Logging** - Detailed logs for debugging and monitoring

### Advanced Features
- **Multiple Parsing Methods** - AI parsing, native text extraction, OCR support
- **Data Validation** - Validates extracted data against iDoklad requirements
- **Error Handling** - Comprehensive error handling with notifications
- **Admin Dashboard** - Complete admin interface for management
- **Debug Tools** - Step-by-step debugging for troubleshooting
- **Testing Tools** - Built-in testing for all components

## 🏗️ Architecture

### Main Components

```
iDoklad Invoice Processor/
├── Core Plugin File
│   └── idoklad-invoice-processor.php
├── Includes/
│   ├── Database Management
│   ├── Email Monitoring
│   ├── PDF Processing
│   ├── AI Parsing
│   ├── iDoklad API Integration
│   ├── User Management
│   └── Admin Interface
├── Templates/
│   ├── Admin Dashboard
│   ├── Settings Pages
│   └── Queue Management
├── Assets/
│   ├── CSS Styles
│   └── JavaScript
└── Documentation/
    ├── README.md
    ├── CHANGELOG.md
    └── Operation Guide
```

### Data Flow

1. **Email Reception** → Mock Email Monitor receives PDF attachments
2. **PDF Processing** → PDF.co AI Parser extracts structured data
3. **Data Transformation** → Data is transformed to iDoklad format
4. **API Integration** → iDoklad API v3 creates invoices
5. **Logging & Notifications** → Results are logged and notifications sent

## ⚙️ Configuration Guide

### 1. PDF.co AI Parser Setup

```php
// In WordPress Admin → iDoklad Processor → Settings
PDF.co API Key: [Your API Key]
Use AI Parser: ✓ Enabled
```

### 2. iDoklad API v3 Setup

```php
// In WordPress Admin → iDoklad Processor → iDoklad API Integration
Client ID: [Your Client ID]
Client Secret: [Your Client Secret]
Default Partner ID: 22429105
```

### 3. Email Configuration

```php
// In WordPress Admin → iDoklad Processor → Settings
Email Host: imap.gmail.com
Email Port: 993
Email Username: [Your Email]
Email Password: [Your Password]
Email Encryption: SSL
```

### 4. Authorized Users

```php
// In WordPress Admin → iDoklad Processor → Authorized Users
Add users who can receive invoices with their own iDoklad credentials
```

## 📖 Operation Guide

### Daily Operations

#### 1. Monitoring Dashboard
- **Location**: WordPress Admin → iDoklad Processor → Dashboard
- **Purpose**: Overview of system status, recent activity, and statistics
- **Key Metrics**: Processed invoices, success rate, queue status

#### 2. Queue Management
- **Location**: WordPress Admin → iDoklad Processor → Processing Queue
- **Purpose**: Monitor and manage invoice processing queue
- **Actions**: View queue items, retry failed items, cancel stuck items

#### 3. Processing Logs
- **Location**: WordPress Admin → iDoklad Processor → Processing Logs
- **Purpose**: Detailed logs of all processing activities
- **Features**: Filter by status, export logs, view detailed information

### Testing and Debugging

#### 1. PDF.co AI Parser Debug
- **Location**: WordPress Admin → iDoklad Processor → PDF.co AI Parser Debug
- **Purpose**: Test and debug PDF parsing functionality
- **Features**: Connection testing, parser testing, step-by-step debugging

#### 2. iDoklad API Integration
- **Location**: WordPress Admin → iDoklad Processor → iDoklad API Integration
- **Purpose**: Test iDoklad API integration
- **Features**: Connection testing, invoice creation testing

#### 3. Diagnostics
- **Location**: WordPress Admin → iDoklad Processor → Diagnostics & Testing
- **Purpose**: System diagnostics and testing tools
- **Features**: PDF parsing tests, OCR tests, API tests

### Maintenance

#### 1. Database Management
- **Location**: WordPress Admin → iDoklad Processor → Database Management
- **Purpose**: Database maintenance and cleanup
- **Features**: Clean old logs, clean old queue items, database statistics

#### 2. Settings Management
- **Location**: WordPress Admin → iDoklad Processor → Settings
- **Purpose**: Configure plugin settings
- **Features**: API keys, email settings, parsing options

## 🔌 API Integration

### iDoklad API v3 Integration

The plugin includes a complete iDoklad API v3 integration that follows the exact workflow:

1. **Authentication** - OAuth2 Client Credentials flow
2. **NumericSequence Resolution** - Find correct numbering series
3. **Invoice Creation** - Create issued invoices with proper payload

#### Usage Example

```php
// Initialize integration
$integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);

// Create invoice
$result = $integration->create_invoice_complete_workflow($invoice_data);

// Check result
if ($result['success']) {
    echo "Invoice created: " . $result['document_number'];
}
```

### PDF.co AI Parser Integration

The plugin uses PDF.co AI Parser for intelligent invoice data extraction:

```php
// Initialize parser
$parser = new IDokladProcessor_PDFCoAIParserEnhanced();

// Parse invoice with debugging
$result = $parser->parse_invoice_with_debug($pdf_url);

// Check result
if ($result['success']) {
    $idoklad_data = $result['data'];
    // Data is ready for iDoklad API
}
```

## 🔧 Troubleshooting

### Common Issues

#### 1. Plugin Activation Issues
- **Problem**: Plugin fails to activate
- **Solution**: Check error logs, ensure all files are present
- **Reference**: See `TROUBLESHOOTING.md`

#### 2. API Connection Issues
- **Problem**: API connections fail
- **Solution**: Verify API keys, check network connectivity
- **Reference**: Use admin interface testing tools

#### 3. PDF Parsing Issues
- **Problem**: PDFs not parsing correctly
- **Solution**: Check PDF format, verify API key, test with debug tools
- **Reference**: Use PDF.co AI Parser Debug interface

#### 4. iDoklad Integration Issues
- **Problem**: Invoices not creating in iDoklad
- **Solution**: Verify credentials, check payload format
- **Reference**: Use iDoklad API Integration testing tools

### Debug Mode

Enable debug mode for detailed logging:

```php
// In WordPress Admin → iDoklad Processor → Settings
Debug Mode: ✓ Enabled
```

Debug logs are written to WordPress error log.

### Testing Tools

The plugin includes comprehensive testing tools:

1. **Standalone Test Scripts**
   - `test-idoklad-integration.php` - Test iDoklad integration
   - `test-pdf-co-parser.php` - Test PDF parsing
   - `test-postman-workflow.php` - Test complete workflow

2. **Admin Interface Testing**
   - Connection testing
   - Parser testing
   - Step-by-step debugging
   - Payload validation

## 📚 Documentation Files

- **`README.md`** - This file (complete overview)
- **`CHANGELOG.md`** - Complete change log
- **`OPERATION-GUIDE.md`** - Detailed operation guide
- **`IDOKLAD-API-INTEGRATION.md`** - iDoklad API integration details
- **`PDF-CO-AI-PARSER-ENHANCEMENT.md`** - PDF parsing enhancement details
- **`TROUBLESHOOTING.md`** - Troubleshooting guide

## 🔄 Change Log

See `CHANGELOG.md` for complete change history.

### Recent Updates

- **v1.1.0** - Complete iDoklad API v3 integration
- **v1.1.0** - Enhanced PDF.co AI Parser with debugging
- **v1.1.0** - Comprehensive admin interface
- **v1.1.0** - Step-by-step debugging tools
- **v1.1.0** - Complete testing suite

## 🆘 Support

### Getting Help

1. **Check Documentation** - Review all documentation files
2. **Use Debug Tools** - Enable debug mode and check logs
3. **Test Components** - Use built-in testing tools
4. **Check Troubleshooting** - Review troubleshooting guide

### Debug Information

When reporting issues, please include:

1. **Plugin Version** - Current version number
2. **WordPress Version** - WordPress version
3. **PHP Version** - PHP version
4. **Error Logs** - Relevant error log entries
5. **Debug Output** - Output from debug tools
6. **Configuration** - Relevant configuration settings

### Contact

For support and questions:
- Review documentation files
- Check troubleshooting guide
- Use built-in testing tools
- Enable debug mode for detailed logging

## 📄 License

This plugin is licensed under GPL v2 or later.

## 🎯 Goals

This plugin aims to provide:
- **Complete automation** of invoice processing
- **Robust error handling** and recovery
- **Comprehensive debugging** tools
- **Production-ready** reliability
- **Easy maintenance** and troubleshooting

---

**Version**: 1.1.0  
**Last Updated**: 2025-01-23  
**Compatibility**: WordPress 5.0+, PHP 7.1+

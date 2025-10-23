# Documentation Index - iDoklad Invoice Processor

This index provides a comprehensive overview of all documentation files in the iDoklad Invoice Processor plugin.

## 📚 Main Documentation

### Essential Reading
- **[README.md](README.md)** - Complete plugin overview and quick start guide
- **[CHANGELOG.md](CHANGELOG.md)** - Complete change history and version information
- **[OPERATION-GUIDE.md](OPERATION-GUIDE.md)** - Detailed operation guide for daily use

### API Integration Documentation
- **[IDOKLAD-API-INTEGRATION.md](IDOKLAD-API-INTEGRATION.md)** - Complete iDoklad API v3 integration guide
- **[PDF-CO-AI-PARSER-ENHANCEMENT.md](PDF-CO-AI-PARSER-ENHANCEMENT.md)** - Enhanced PDF.co AI Parser documentation

## 📋 Feature Documentation

### Core Features
- **[COMPLETE-FEATURE-SUMMARY.md](COMPLETE-FEATURE-SUMMARY.md)** - Complete feature overview
- **[ENHANCED-SYSTEM-OVERVIEW.md](ENHANCED-SYSTEM-OVERVIEW.md)** - System architecture overview
- **[DASHBOARD-FEATURES.md](DASHBOARD-FEATURES.md)** - Admin dashboard features

### Processing Features
- **[DATA-TRANSFORMER-GUIDE.md](DATA-TRANSFORMER-GUIDE.md)** - Data transformation guide
- **[PDF-PARSER-README.md](PDF-PARSER-README.md)** - PDF parsing documentation
- **[QUEUE-VIEWER-GUIDE.md](QUEUE-VIEWER-GUIDE.md)** - Queue management guide

## 🔧 Technical Documentation

### Architecture
- **[ARCHITECTURE-FINAL.md](ARCHITECTURE-FINAL.md)** - Final system architecture
- **[FINAL-ARCHITECTURE.md](FINAL-ARCHITECTURE.md)** - Architecture documentation
- **[SIMPLIFIED-SYSTEM.md](SIMPLIFIED-SYSTEM.md)** - Simplified system overview

### Integration Guides
- **[PDFCO-INTEGRATION.md](PDFCO-INTEGRATION.md)** - PDF.co integration guide
- **[INTEGRATION-COMPLETE.md](INTEGRATION-COMPLETE.md)** - Complete integration guide

## 🛠️ Setup and Configuration

### Installation Guides
- **[INSTALL-OCR.md](INSTALL-OCR.md)** - OCR installation guide
- **[OCR-SPACE-SETUP.md](OCR-SPACE-SETUP.md)** - OCR Space setup guide
- **[SETTINGS-CLEANUP.md](SETTINGS-CLEANUP.md)** - Settings cleanup guide

### Configuration Guides
- **[OCR-README.md](OCR-README.md)** - OCR configuration guide
- **[PRECISE-DATA-HANDLING.md](PRECISE-DATA-HANDLING.md)** - Data handling guide

## 🚨 Troubleshooting and Fixes

### Error Resolution
- **[TROUBLESHOOTING.md](TROUBLESHOOTING.md)** - Comprehensive troubleshooting guide
- **[FATAL-ERROR-FIX.md](FATAL-ERROR-FIX.md)** - Fatal error resolution
- **[FINAL-FIX.md](FINAL-FIX.md)** - Final fixes documentation

### Fix Documentation
- **[FIXES-2024-10-21.md](FIXES-2024-10-21.md)** - Fixes applied on 2024-10-21
- **[FIXES-APPLIED.md](FIXES-APPLIED.md)** - Applied fixes documentation
- **[IDOKLAD-API-FIX.md](IDOKLAD-API-FIX.md)** - iDoklad API fixes
- **[PDFCO-FIX.md](PDFCO-FIX.md)** - PDF.co fixes

### Change Documentation
- **[CHANGELOG-NATIVE-PARSER.md](CHANGELOG-NATIVE-PARSER.md)** - Native parser changelog
- **[FEATURE-SUMMARY.md](FEATURE-SUMMARY.md)** - Feature summary

## 🔍 Testing and Debugging

### Testing Tools
- **[DIAGNOSTICS-GUIDE.md](DIAGNOSTICS-GUIDE.md)** - Diagnostics and testing guide

### Test Files
- **`test-idoklad-integration.php`** - iDoklad integration testing
- **`test-pdf-co-parser.php`** - PDF.co parser testing
- **`test-postman-workflow.php`** - Postman workflow testing

## 📁 File Structure

### Core Files
- **`idoklad-invoice-processor.php`** - Main plugin file
- **`uninstall.php`** - Plugin uninstall script

### Includes Directory
```
includes/
├── class-admin.php                    # Admin interface
├── class-chatgpt-integration.php      # ChatGPT integration
├── class-database.php                 # Database management
├── class-email-monitor.php            # Email monitoring
├── class-email-monitor-v3.php         # Enhanced email monitoring
├── class-idoklad-admin-integration.php # iDoklad admin integration
├── class-idoklad-api-v3-integration.php # iDoklad API v3 integration
├── class-logger.php                   # Logging system
├── class-notification.php             # Notification system
├── class-notification-v3.php          # Enhanced notifications
├── class-pdf-co-admin-debug.php       # PDF.co debug interface
├── class-pdf-co-ai-parser.php         # PDF.co AI parser
├── class-pdf-co-ai-parser-enhanced.php # Enhanced PDF.co parser
├── class-pdf-parser-native.php        # Native PDF parser
├── class-pdf-processor.php            # PDF processor
├── class-pdfco-processor.php          # PDF.co processor
├── class-user-manager.php             # User management
└── class-user-manager-v3.php          # Enhanced user management
```

### Templates Directory
```
templates/
├── admin-dashboard.php                # Admin dashboard
├── admin-dashboard-v3.php             # Enhanced dashboard
├── admin-database.php                 # Database management
├── admin-diagnostics.php              # Diagnostics page
├── admin-logs.php                     # Logs page
├── admin-queue.php                    # Queue management
├── admin-settings.php                 # Settings page
├── admin-users.php                    # User management
└── partials/
    └── queue-row.php                  # Queue row template
```

### Assets Directory
```
assets/
├── admin.css                          # Admin styles
├── admin.js                           # Admin JavaScript
├── integration-admin.js               # Integration admin JS
└── pdf-co-debug.js                    # PDF.co debug JS
```

## 📖 Reading Order

### For New Users
1. **README.md** - Start here for overview
2. **OPERATION-GUIDE.md** - Learn how to operate the plugin
3. **TROUBLESHOOTING.md** - Common issues and solutions

### For Developers
1. **ARCHITECTURE-FINAL.md** - Understand the architecture
2. **IDOKLAD-API-INTEGRATION.md** - API integration details
3. **PDF-CO-AI-PARSER-ENHANCEMENT.md** - PDF parsing details

### For Administrators
1. **OPERATION-GUIDE.md** - Daily operations
2. **DASHBOARD-FEATURES.md** - Admin interface features
3. **DIAGNOSTICS-GUIDE.md** - Testing and debugging

### For Troubleshooting
1. **TROUBLESHOOTING.md** - Common issues
2. **FATAL-ERROR-FIX.md** - Error resolution
3. **DIAGNOSTICS-GUIDE.md** - Diagnostic tools

## 🔄 Version Information

### Current Version
- **Version**: 1.1.0
- **Last Updated**: 2025-01-23
- **WordPress Compatibility**: 5.0+
- **PHP Compatibility**: 7.1+

### Version History
- **v1.1.0** - Complete iDoklad API v3 integration, Enhanced PDF parsing
- **v1.0.0** - Initial plugin release
- **v0.9.0** - Development version
- **v0.8.0** - Planning phase

## 📞 Support Resources

### Documentation
- Review all documentation files
- Check troubleshooting guides
- Use built-in help resources

### Testing Tools
- Use built-in testing tools
- Test with sample data
- Enable debug mode for detailed logging

### Getting Help
1. Check documentation files
2. Use troubleshooting guides
3. Enable debug mode
4. Use built-in testing tools
5. Check error logs

## 📝 Documentation Maintenance

### Keeping Documentation Current
- Update documentation when making changes
- Review documentation regularly
- Keep version information current
- Update troubleshooting guides as needed

### Documentation Standards
- Use clear, concise language
- Include code examples where helpful
- Provide step-by-step instructions
- Include troubleshooting information

---

**Last Updated**: 2025-01-23  
**Maintainer**: iDoklad Invoice Processor Team  
**For Questions**: Review documentation and use built-in testing tools

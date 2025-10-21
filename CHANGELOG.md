# Changelog

All notable changes to the iDoklad Invoice Processor plugin will be documented in this file.

## [1.0.0] - 2024-01-XX

### Added
- Initial release of iDoklad Invoice Processor
- Email monitoring system with IMAP support
- ChatGPT integration for AI-powered PDF data extraction
- iDoklad API integration for invoice/expense creation
- Comprehensive user management system
- Detailed logging and monitoring capabilities
- WordPress admin interface with settings pages
- Notification system for success/failure messages
- Queue management for email processing
- Debug mode for troubleshooting
- CSV export functionality for logs
- System requirements checking
- Uninstall script for clean removal

### Features
- **Email Processing**: Automatically monitors email inbox for PDF invoices
- **AI Extraction**: Uses ChatGPT to extract structured data from PDFs
- **iDoklad Integration**: Creates invoices and expenses in iDoklad
- **User Management**: Manages authorized email senders
- **Logging**: Comprehensive logging system with debug messages
- **Notifications**: Email notifications for users and administrators
- **Admin Interface**: Easy-to-use WordPress admin interface
- **Queue System**: Processes emails in background queue
- **Error Handling**: Robust error handling with user-friendly messages
- **Security**: Secure API key storage and user authorization

### Technical Details
- WordPress 5.0+ compatibility
- PHP 7.4+ requirement
- MySQL 5.6+ database support
- IMAP PHP extension requirement
- Optional: Ghostscript, Poppler, Tesseract OCR support
- RESTful API integration with iDoklad
- OpenAI ChatGPT API integration
- Comprehensive error logging and debugging

### Documentation
- Complete installation guide
- Detailed user manual
- Troubleshooting documentation
- API reference
- Security considerations
- Best practices guide

---

## Future Versions

### Planned Features
- Webhook support for real-time notifications
- Advanced PDF processing with OCR
- Custom field mapping for iDoklad
- Batch processing capabilities
- Advanced reporting and analytics
- Multi-language support
- Custom notification templates
- API rate limiting and optimization
- Advanced user permissions
- Integration with other accounting systems

### Known Issues
- None at this time

### Security Notes
- All API keys are stored securely in WordPress options
- User authorization is required for all email processing
- Comprehensive logging for audit trails
- Secure email processing with IMAP
- Input validation and sanitization throughout

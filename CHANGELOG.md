# Changelog - iDoklad Invoice Processor

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-01-23

### Added
- **Complete iDoklad API v3 Integration**
  - OAuth2 Client Credentials authentication flow
  - NumericSequence resolution for issued invoices
  - Dynamic DocumentSerialNumber calculation
  - Exact payload structure matching iDoklad requirements
  - Production-ready error handling and logging

- **Enhanced PDF.co AI Parser**
  - iDoklad-optimized custom fields
  - Step-by-step debugging with detailed logging
  - Comprehensive data transformation
  - Advanced item processing with validation
  - Smart field mapping with multiple fallback options
  - Date normalization and currency mapping

- **Comprehensive Admin Interface**
  - iDoklad API Integration management page
  - PDF.co AI Parser debug interface
  - Real-time testing and validation tools
  - Step-by-step process examination
  - Payload validation against iDoklad requirements

- **Testing and Debugging Tools**
  - Standalone test scripts for all components
  - WordPress admin interface testing
  - Connection testing for API endpoints
  - Parser testing with real PDFs
  - Complete workflow testing

- **Documentation**
  - Complete README with operation guide
  - Comprehensive changelog
  - API integration documentation
  - PDF parser enhancement documentation
  - Troubleshooting guides

### Changed
- **Plugin Architecture**
  - Cleaned up all iDoklad integration components
  - Rebuilt with production-grade iDoklad API v3 integration
  - Enhanced error handling and logging throughout
  - Improved class loading with conditional checks

- **Database Schema**
  - Removed iDoklad-specific fields from user tables
  - Updated field names to be more generic
  - Improved data structure for better compatibility

- **Admin Interface**
  - Enhanced admin interface with better organization
  - Added comprehensive testing tools
  - Improved error handling and user feedback
  - Better visual design and user experience

### Fixed
- **Plugin Activation Issues**
  - Fixed syntax errors in class definitions
  - Resolved class loading order issues
  - Fixed constant redefinition warnings
  - Improved error handling during plugin activation

- **API Integration Issues**
  - Fixed OAuth2 authentication flow
  - Resolved NumericSequence resolution logic
  - Fixed payload structure for iDoklad API
  - Improved error handling and response parsing

- **PDF Parsing Issues**
  - Enhanced data extraction accuracy
  - Improved field mapping and validation
  - Fixed date normalization issues
  - Better error handling for parsing failures

### Security
- **API Key Management**
  - Secure storage of API credentials
  - Proper sanitization of user input
  - Enhanced error handling without exposing sensitive data

### Performance
- **Optimized Processing**
  - Improved queue management
  - Better error handling and recovery
  - Enhanced logging without performance impact
  - Optimized API request handling

## [1.0.0] - 2024-10-21

### Added
- **Initial Plugin Release**
  - Basic email monitoring functionality
  - PDF processing capabilities
  - Initial iDoklad API integration
  - Basic admin interface
  - User management system
  - Queue processing system

- **Core Features**
  - Email attachment processing
  - PDF text extraction
  - Basic data transformation
  - Simple iDoklad API integration
  - Basic logging system

### Known Issues
- Limited error handling
- Basic API integration
- Limited debugging capabilities
- Incomplete documentation

## [0.9.0] - 2024-10-20

### Added
- **Development Version**
  - Initial plugin structure
  - Basic functionality testing
  - Core component development

### Changed
- **Architecture**
  - Initial plugin architecture
  - Basic class structure
  - Initial database schema

## [0.8.0] - 2024-10-19

### Added
- **Planning Phase**
  - Project planning and architecture design
  - Requirements analysis
  - Initial development setup

### Changed
- **Project Structure**
  - Initial project structure
  - Development environment setup
  - Planning documentation

---

## Version History Summary

| Version | Date | Major Changes |
|---------|------|---------------|
| 1.1.0 | 2025-01-23 | Complete iDoklad API v3 integration, Enhanced PDF parsing, Comprehensive admin interface |
| 1.0.0 | 2024-10-21 | Initial plugin release with basic functionality |
| 0.9.0 | 2024-10-20 | Development version with core components |
| 0.8.0 | 2024-10-19 | Planning and initial setup |

## Breaking Changes

### Version 1.1.0
- **Database Schema Changes**
  - Removed iDoklad-specific fields from user tables
  - Updated field names for better compatibility
  - Requires database update on upgrade

- **API Integration Changes**
  - Complete rewrite of iDoklad API integration
  - New authentication flow
  - Updated payload structure
  - Requires reconfiguration of API credentials

- **Class Structure Changes**
  - New enhanced classes for better functionality
  - Updated class names and methods
  - Improved error handling throughout

## Migration Guide

### Upgrading to Version 1.1.0

1. **Backup Database**
   - Backup WordPress database before upgrade
   - Export current settings and configurations

2. **Update Plugin**
   - Deactivate current plugin
   - Replace plugin files
   - Reactivate plugin

3. **Reconfigure Settings**
   - Update iDoklad API credentials
   - Reconfigure PDF.co API settings
   - Update email settings if needed

4. **Test Functionality**
   - Use built-in testing tools
   - Verify all components work correctly
   - Check admin interface functionality

## Future Roadmap

### Version 1.2.0 (Planned)
- **Enhanced Features**
  - Additional PDF parsing methods
  - Improved error recovery
  - Enhanced reporting capabilities
  - Better user management

### Version 1.3.0 (Planned)
- **Advanced Features**
  - Multi-language support
  - Advanced analytics
  - Custom field mapping
  - API webhook support

## Support

For questions about changes or upgrades:
1. Review this changelog
2. Check the README.md for operation guide
3. Use built-in testing tools
4. Enable debug mode for detailed logging

---

**Last Updated**: 2025-01-23  
**Maintainer**: iDoklad Invoice Processor Team

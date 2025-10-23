# Native PHP PDF Parser

## Overview

The iDoklad Helper plugin now includes a **native PHP PDF parser** that extracts text from PDF invoices **without requiring any external APIs or server-side dependencies**. This makes the plugin fully portable and compatible with any WordPress hosting environment.

## Features

### ✅ Pure PHP Implementation
- **No External APIs**: All PDF parsing happens internally on your WordPress server
- **No System Dependencies**: Doesn't require pdftotext, Ghostscript, or other command-line tools
- **Universal Compatibility**: Works on any hosting environment (shared hosting, VPS, cloud)
- **Zero Configuration**: Works out of the box with no additional setup

### ✅ Intelligent Fallback System
The plugin uses multiple parsing methods in order of priority:

1. **Native PHP Parser** (Primary - Always Available)
   - Pure PHP implementation
   - Works on any server
   - No external dependencies
   - Handles most standard PDFs

2. **pdftotext** (Fallback - If Available)
   - Command-line tool from Poppler utils
   - High accuracy for text-based PDFs
   - Requires server installation

3. **Ghostscript** (Fallback - If Available)
   - PostScript/PDF interpreter
   - Good for complex PDFs
   - Requires server installation

### ✅ What It Can Extract

The native parser can extract:
- Invoice text content
- Invoice numbers
- Dates and amounts
- Supplier and customer information
- Line items and descriptions
- PDF metadata (title, author, creation date, etc.)
- Page count

## How It Works

### PDF Structure Parsing

The native PHP parser works by:

1. **Reading PDF Binary Structure**
   - Parses PDF objects and references
   - Identifies page objects and content streams
   - Extracts text operators (Tj, TJ, ', ")

2. **Text Extraction**
   - Decodes text strings from PDF format
   - Handles multiple encoding formats (UTF-8, ISO-8859-1, Windows-1252)
   - Processes both regular and hexadecimal strings
   - Decompresses FlateDecode streams using PHP's native gzip functions

3. **Text Cleaning & Normalization**
   - Removes control characters
   - Normalizes whitespace and line breaks
   - Converts to UTF-8 encoding
   - Ensures consistent formatting

## Configuration

### Admin Settings

Navigate to **iDoklad Processor → Settings → PDF Processing Settings**

- **Use native PHP parser first (recommended)**: ✅ Enabled by default
  - When enabled, the native parser is tried first
  - Falls back to command-line tools if extraction fails
  - Provides best compatibility across all hosting environments

### Diagnostic Information

The settings page displays:
- Available parsing methods
- Status of each method (Available / Not Available)
- Description of each parsing method
- Total count of available methods

## Supported PDF Types

### ✅ Supported
- Text-based PDFs (most invoices)
- PDFs with embedded fonts
- Compressed PDFs (FlateDecode)
- Multi-page documents
- PDFs with standard encodings

### ⚠️ Limited Support
- Image-based PDFs (scanned documents) - OCR not included
- Encrypted/password-protected PDFs
- PDFs with complex custom encodings
- PDFs with heavily customized fonts

## API Reference

### PDF Processor Class

```php
$pdf_processor = new IDokladProcessor_PDFProcessor();
```

#### Extract Text
```php
$text = $pdf_processor->extract_text($pdf_path);
// Returns: Extracted text as string
```

#### Get Metadata
```php
$metadata = $pdf_processor->get_metadata($pdf_path);
// Returns: Array with title, author, subject, creation_date, pdf_version
```

#### Get Page Count
```php
$page_count = $pdf_processor->get_page_count($pdf_path);
// Returns: Number of pages (int)
```

#### Get PDF Info (Diagnostics)
```php
$info = $pdf_processor->get_pdf_info($pdf_path);
// Returns: Array with file_size, metadata, page_count, parsing_methods
```

#### Test Parsing Methods
```php
$methods = $pdf_processor->test_parsing_methods();
// Returns: Array of available parsing methods with status
```

### Native Parser Class

```php
$native_parser = new IDokladProcessor_NativePDFParser();
```

#### Extract Text
```php
$text = $native_parser->extract_text($pdf_path);
```

#### Get Metadata
```php
$metadata = $native_parser->get_metadata($pdf_path);
```

#### Get Page Count
```php
$pages = $native_parser->get_page_count($pdf_path);
```

## Troubleshooting

### Issue: No text extracted from PDF

**Possible Causes:**
1. PDF is image-based (scanned document)
2. PDF is encrypted or password-protected
3. PDF uses unsupported encoding

**Solutions:**
- Enable Debug Mode in settings to see detailed logs
- Try with a different PDF
- Ensure PDF is text-based (not scanned)
- Check debug.log for specific error messages

### Issue: Garbled or incorrect text

**Possible Causes:**
1. PDF uses non-standard encoding
2. Custom font mapping issues

**Solutions:**
- The parser will attempt multiple encodings
- Enable Debug Mode to see what's happening
- Command-line tools (pdftotext) may work better for this specific PDF

### Issue: Partial text extraction

**Possible Causes:**
1. Complex PDF structure
2. Multiple content streams

**Solutions:**
- The parser handles multiple content streams
- Some PDFs may need manual processing
- Check if alternative parsing methods work better

## Debug Mode

Enable Debug Mode to see detailed logging:

1. Go to **iDoklad Processor → Settings**
2. Check **Enable debug logging** under General Settings
3. Save settings

Debug logs will show:
- Which parsing method was used
- Number of characters extracted
- Any errors encountered
- PDF structure information

View logs in WordPress debug.log file.

## Performance

### Speed
- Native parser: ~0.1-0.5 seconds per PDF (typical invoice)
- Memory: ~2-5 MB per PDF
- No network requests (everything is local)

### Optimization
- PDFs are processed sequentially
- No external API calls
- Minimal memory footprint
- Efficient regular expression matching

## Security

### Data Privacy
- All PDF processing happens locally on your WordPress server
- No data is sent to external services
- No third-party API calls
- Complete data privacy and GDPR compliance

### File Safety
- PDFs are validated before processing
- File type checking (must start with %PDF)
- No code execution from PDFs
- Safe handling of malformed PDFs

## Comparison with External APIs

| Feature | Native Parser | External APIs |
|---------|---------------|---------------|
| **Cost** | Free | Paid per request |
| **Privacy** | 100% Private | Data sent externally |
| **Speed** | Fast (local) | Network dependent |
| **Reliability** | Always available | API downtime possible |
| **Setup** | No setup needed | API keys required |
| **Dependencies** | None | Internet required |
| **Hosting** | Works everywhere | May be blocked |

## Technical Details

### PDF Format Support
- PDF Version: 1.0 - 1.7 (most common versions)
- Compression: FlateDecode (gzip)
- Encodings: UTF-8, ISO-8859-1, ISO-8859-2, Windows-1252, ASCII

### Text Operators Supported
- `Tj` - Show text
- `TJ` - Show text with positioning
- `'` - Move to next line and show text  
- `"` - Set spacing, move to next line and show text

### Stream Filters
- `/FlateDecode` - gzip/zlib compression ✅
- Other filters: Not currently supported

## Future Enhancements

Potential improvements for future versions:

- [ ] Support for additional compression methods
- [ ] OCR integration for scanned PDFs
- [ ] Table extraction and structure recognition
- [ ] Image extraction from PDFs
- [ ] Support for encrypted PDFs
- [ ] Advanced font mapping
- [ ] Form field extraction

## Credits

Native PHP PDF Parser developed for iDoklad Helper plugin.

## License

This component is part of the iDoklad Helper plugin and follows the same GPL v2 or later license.

## Support

For issues or questions:
1. Enable Debug Mode
2. Check debug.log for errors
3. Test with different PDFs
4. Verify PDF is text-based (not scanned)

---

**Version**: 1.0  
**Last Updated**: October 2025  
**Compatibility**: WordPress 5.0+, PHP 7.0+


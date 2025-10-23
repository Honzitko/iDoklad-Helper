# OCR Support for Scanned PDFs

## Overview

The iDoklad Helper plugin now includes **full OCR (Optical Character Recognition) support** to extract text from **scanned PDF invoices**. This means you can now process both text-based and image-based (scanned) PDF documents.

## Features

### ✅ Automatic Detection
- **Smart Detection**: Automatically identifies scanned PDFs
- **Seamless Processing**: No manual intervention required
- **Fallback System**: Tries text extraction first, then OCR if needed

### ✅ Multiple OCR Methods

#### 1. **Tesseract OCR** (Local, Free, Open Source)
- Industry-standard OCR engine
- Works locally on your server
- No external API calls
- Complete privacy
- Supports 100+ languages
- **Recommended for most users**

#### 2. **OCR.space API** (Cloud, Free Tier Available)
- Cloud-based OCR service
- Free tier: 25,000 requests/month
- Fast and accurate
- Good fallback option

#### 3. **Google Cloud Vision** (Cloud, Premium)
- Google's advanced OCR
- Highly accurate
- Requires Google Cloud account
- Pay-per-use pricing

### ✅ Multiple PDF-to-Image Converters

The plugin supports various methods to convert PDFs to images:
- **ImageMagick** (command-line)
- **Ghostscript** (command-line)
- **PHP Imagick Extension** (PHP-based)

## How It Works

### Automatic Workflow

1. **PDF Received** → Email attachment arrives
2. **Text Extraction Attempted** → Native parser tries to extract text
3. **Scanned PDF Detection** → If little/no text found, checks if it's scanned
4. **PDF to Images** → Converts PDF pages to PNG images
5. **OCR Processing** → Runs OCR on each page
6. **Text Extraction** → Combines text from all pages
7. **Data Parsing** → Sends to ChatGPT/Zapier for invoice data extraction

### Detection Logic

The plugin identifies scanned PDFs by:
- Very little text extracted (< 50 characters)
- Presence of image objects in PDF
- Ratio of images to text content

## Installation & Setup

### Option 1: Tesseract OCR (Recommended)

#### On Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install tesseract-ocr
sudo apt-get install tesseract-ocr-ces  # Czech language
sudo apt-get install tesseract-ocr-eng  # English language
sudo apt-get install imagemagick         # For PDF conversion
```

#### On macOS:
```bash
brew install tesseract
brew install tesseract-lang  # All languages
brew install imagemagick
```

#### On Windows:
1. Download Tesseract installer from: https://github.com/UB-Mannheim/tesseract/wiki
2. Install with language packs (Czech, English)
3. Download ImageMagick from: https://imagemagick.org/script/download.php
4. Update Tesseract path in plugin settings if not in PATH

#### Verify Installation:
```bash
tesseract --version
convert --version  # ImageMagick
```

### Option 2: Cloud OCR Services

#### OCR.space (Free Tier):
1. Go to https://ocr.space/ocrapi
2. Sign up for free API key (25,000 requests/month)
3. Enter API key in plugin settings
4. Select "OCR.space" as cloud service

#### Google Cloud Vision:
1. Create Google Cloud account
2. Enable Cloud Vision API
3. Create API credentials
4. Enter API key in plugin settings
5. Select "Google Cloud Vision" as cloud service

### Option 3: PHP Imagick Extension

If you can't install command-line tools, use PHP extension:

#### Install PHP Imagick:
```bash
# Ubuntu/Debian
sudo apt-get install php-imagick

# macOS
brew install imagemagick
pecl install imagick

# Verify
php -m | grep imagick
```

## Configuration

### Admin Settings

Navigate to: **iDoklad Processor → Settings → OCR Settings**

#### Basic Settings:
- ✅ **Enable OCR**: Turn OCR on/off
- Default: Enabled

#### Local OCR (Tesseract):
- **Use Tesseract OCR**: Enable/disable Tesseract
- **Tesseract Path**: Path to tesseract command (default: `tesseract`)
- **OCR Languages**: Language codes (default: `ces+eng` for Czech + English)

#### Cloud OCR (Optional):
- **Use Cloud OCR**: Enable as fallback
- **Cloud Service**: Choose OCR.space or Google Vision
- **API Keys**: Enter your cloud service API key

### Language Codes

Common language codes for Tesseract:
- `eng` - English
- `ces` - Czech (Česky)
- `deu` - German
- `fra` - French
- `spa` - Spanish
- `pol` - Polish
- `slk` - Slovak

Combine multiple: `ces+eng+deu`

## Supported Languages

### Tesseract Supports 100+ Languages:
- Czech (Česky)
- English
- German (Deutsch)
- French (Français)
- Spanish (Español)
- Polish (Polski)
- Slovak (Slovenčina)
- And many more...

Install additional languages:
```bash
sudo apt-get install tesseract-ocr-[lang-code]
```

## System Requirements

### Minimum Requirements:
- **PHP**: 7.0+
- **WordPress**: 5.0+
- **Memory**: 256MB+ (512MB recommended for OCR)
- **Disk Space**: 50MB+ for temp files

### Recommended for Tesseract:
- **ImageMagick** or **Ghostscript** for PDF-to-image conversion
- **Tesseract 4.0+** for best accuracy
- **512MB+ PHP memory limit**

### For PHP Imagick:
- PHP Imagick extension
- ImageMagick library

## Performance

### Speed:
- **Text-based PDFs**: ~0.1-0.5 seconds
- **Scanned PDFs (Tesseract)**: ~2-5 seconds per page
- **Cloud OCR**: ~1-3 seconds per page (network dependent)

### Accuracy:
- **Tesseract**: 85-95% (depends on image quality)
- **OCR.space**: 90-95%
- **Google Vision**: 95-99%

### Memory Usage:
- **Text extraction**: ~2-5 MB
- **OCR processing**: ~50-100 MB per page
- **Temp files**: ~1-5 MB per page (auto-deleted)

## Diagnostic Tools

### OCR Capabilities Status

The settings page shows real-time status:

✅ **Ready to process scanned PDFs** - All components available  
⚠ **Cannot process scanned PDFs** - Missing components

#### Component Status Table:
- Tesseract OCR - Available / Not Available
- ImageMagick - Available / Not Available  
- Ghostscript - Available / Not Available
- PHP Imagick Extension - Available / Not Available
- OCR.space API - Configured / Not Configured
- Google Cloud Vision - Configured / Not Configured

## Troubleshooting

### Issue: OCR not working

**Check:**
1. Is OCR enabled in settings?
2. Is Tesseract installed? Run: `tesseract --version`
3. Is ImageMagick installed? Run: `convert --version`
4. Check debug.log for error messages

**Solution:**
- Install missing components
- Configure cloud OCR as fallback
- Enable Debug Mode to see detailed logs

### Issue: "Cannot process scanned PDFs" warning

**Cause:** Missing PDF-to-image converter or OCR engine

**Solution:**
- Install ImageMagick: `sudo apt-get install imagemagick`
- Install Tesseract: `sudo apt-get install tesseract-ocr`
- Or configure cloud OCR service

### Issue: Poor OCR accuracy

**Possible Causes:**
1. Low-quality scan
2. Wrong language setting
3. Complex document layout
4. Handwritten text

**Solutions:**
- Ensure correct language is set (e.g., `ces` for Czech)
- Use higher-quality scans (300+ DPI)
- Try cloud OCR service (usually more accurate)
- Clean/deskew images before scanning

### Issue: Tesseract not found

**Solution:**
```bash
# Find tesseract path
which tesseract

# Update path in settings
# Example: /usr/bin/tesseract or /usr/local/bin/tesseract
```

### Issue: Out of memory errors

**Solution:**
1. Increase PHP memory limit in `php.ini`:
   ```
   memory_limit = 512M
   ```
2. Process smaller PDFs
3. Reduce image resolution in conversion

### Issue: Temporary files not deleted

**Check:**
- Ensure temp directory is writable
- Check PHP permissions
- Files are in: `sys_get_temp_dir()`

**Manual cleanup:**
```bash
# Find temp files
ls /tmp/pdf_page_* /tmp/tesseract_output_*

# Remove old files
find /tmp -name "pdf_page_*" -mtime +1 -delete
find /tmp -name "tesseract_output_*" -mtime +1 -delete
```

## API Reference

### OCR Processor Class

```php
$ocr_processor = new IDokladProcessor_OCRProcessor();
```

#### Extract Text from Scanned PDF
```php
$text = $ocr_processor->extract_text_from_scanned_pdf($pdf_path);
// Returns: Extracted text from all pages
```

#### Extract Text from Image
```php
$text = $ocr_processor->extract_text_from_image($image_path);
// Returns: Extracted text from single image
```

#### Check if PDF is Scanned
```php
$is_scanned = $ocr_processor->is_scanned_pdf($pdf_path, $native_parser_text);
// Returns: true if PDF is image-based
```

#### Test OCR Methods
```php
$methods = $ocr_processor->test_ocr_methods();
// Returns: Array of available OCR methods and their status
```

#### Get Tesseract Languages
```php
$languages = $ocr_processor->get_tesseract_languages();
// Returns: Array of installed Tesseract language packs
```

### PDF Processor Integration

```php
$pdf_processor = new IDokladProcessor_PDFProcessor();

// Automatically uses OCR if needed
$text = $pdf_processor->extract_text($pdf_path);

// Test OCR capabilities
$ocr_status = $pdf_processor->test_ocr_capabilities();
```

## Privacy & Security

### Data Privacy:

#### Tesseract (Local):
✅ 100% private - all processing on your server  
✅ No data sent externally  
✅ GDPR compliant  
✅ No third-party access  

#### Cloud OCR Services:
⚠️ PDFs are sent to external service  
⚠️ Check service privacy policy  
⚠️ Consider data sensitivity  
✅ Optional - not required  

### Security:
- Temporary files auto-deleted after processing
- Secure file handling
- No code execution from PDFs
- API keys stored securely in WordPress options

## Cost Comparison

| Method | Setup Cost | Per-Page Cost | Privacy | Accuracy |
|--------|-----------|---------------|---------|----------|
| **Tesseract** | Free | $0 | 100% Private | 85-95% |
| **OCR.space Free** | Free | $0 (25K/mo) | Cloud Service | 90-95% |
| **OCR.space Paid** | $60/yr | ~$0.002 | Cloud Service | 90-95% |
| **Google Vision** | Free tier | ~$0.0015 | Cloud Service | 95-99% |

**Recommendation:** Use Tesseract for privacy and cost. Use cloud services as fallback.

## Best Practices

### 1. Scan Quality
- Use 300+ DPI for scanning
- Ensure good contrast
- Avoid skewed pages
- Use clean, flat documents

### 2. Language Configuration
- Set correct language codes
- Include all languages in invoices
- Test with sample documents

### 3. Performance Optimization
- Enable only needed OCR methods
- Use Tesseract for bulk processing
- Use cloud OCR for occasional needs
- Monitor memory usage

### 4. Error Handling
- Enable Debug Mode during setup
- Monitor logs for issues
- Have fallback to cloud OCR
- Test with various PDF types

## Common Use Cases

### 1. Mixed Documents
**Scenario:** Some invoices are text-based, some are scanned

**Solution:**
- Plugin automatically detects type
- Text extraction for normal PDFs
- OCR for scanned PDFs
- No manual intervention needed

### 2. Multi-Language Invoices
**Scenario:** Invoices in Czech and English

**Solution:**
- Set: `tesseract_lang = ces+eng`
- OCR will recognize both languages
- ChatGPT handles data extraction

### 3. Low-Quality Scans
**Scenario:** Poor quality scanned invoices

**Solution:**
- Enable cloud OCR as fallback
- Use Google Vision for better accuracy
- Consider re-scanning at higher quality

### 4. High Volume Processing
**Scenario:** 100+ invoices per day

**Solution:**
- Use Tesseract (unlimited, free)
- Increase PHP memory limit
- Monitor server resources
- Consider dedicated OCR server

## Installation Verification

### Check Tesseract:
```bash
tesseract --version
tesseract --list-langs
```

### Check ImageMagick:
```bash
convert --version
convert -list format | grep PDF
```

### Check PHP Extensions:
```bash
php -m | grep imagick
php -m | grep gd
```

### Check from WordPress:
Go to **iDoklad Processor → Settings → OCR Settings**
- View "OCR Capabilities Status"
- All required components should show ✓ Available

## Examples

### Example 1: Process Scanned Invoice

```php
// Get PDF processor
$processor = new IDokladProcessor_PDFProcessor();

// Extract text (automatically uses OCR if scanned)
$text = $processor->extract_text('/path/to/scanned-invoice.pdf');

// Text is now ready for ChatGPT processing
echo $text;
```

### Example 2: Check if Ready for OCR

```php
$processor = new IDokladProcessor_PDFProcessor();
$status = $processor->test_ocr_capabilities();

if ($status['can_process_scanned_pdfs']) {
    echo "✓ Ready to process scanned PDFs";
} else {
    echo "⚠ Missing: ";
    if (!$status['has_pdf_converter']) echo "PDF converter ";
    if (!$status['has_ocr_engine']) echo "OCR engine";
}
```

### Example 3: Get OCR Method Details

```php
$ocr = new IDokladProcessor_OCRProcessor();
$methods = $ocr->test_ocr_methods();

foreach ($methods as $name => $info) {
    echo $info['name'] . ": ";
    echo $info['available'] ? "Available" : "Not Available";
    echo "\n";
}
```

## Support & Resources

### Official Resources:
- **Tesseract:** https://github.com/tesseract-ocr/tesseract
- **ImageMagick:** https://imagemagick.org/
- **OCR.space:** https://ocr.space/ocrapi
- **Google Vision:** https://cloud.google.com/vision/docs/ocr

### Language Data:
- Tesseract languages: https://github.com/tesseract-ocr/tessdata
- Training data: https://github.com/tesseract-ocr/tessdata_best

### Debugging:
- Enable **Debug Mode** in plugin settings
- Check WordPress **debug.log**
- Use **test_ocr_methods()** for diagnostics

## Summary

The OCR feature enables your iDoklad Helper plugin to:

✅ **Process Scanned PDFs** - Extract text from image-based documents  
✅ **Automatic Detection** - No manual PDF type selection needed  
✅ **Multiple OCR Engines** - Local (Tesseract) + Cloud options  
✅ **Multi-Language Support** - Czech, English, and 100+ languages  
✅ **Privacy Options** - Local processing or cloud services  
✅ **Cost-Effective** - Free option (Tesseract) available  
✅ **High Accuracy** - 85-99% depending on method  
✅ **Easy Setup** - Clear installation instructions  
✅ **Diagnostic Tools** - Built-in testing and status monitoring  

Your plugin now handles **ALL types of PDF invoices** - both text-based and scanned! 🎉

---

**Version**: 1.0  
**Last Updated**: October 2025  
**Compatibility**: WordPress 5.0+, PHP 7.0+


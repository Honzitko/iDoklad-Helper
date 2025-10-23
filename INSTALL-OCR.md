# Quick OCR Installation Guide

This guide will help you set up OCR support for processing scanned PDF invoices.

## Quick Start (Ubuntu/Debian)

### 1. Install Tesseract OCR

```bash
# Update package list
sudo apt-get update

# Install Tesseract
sudo apt-get install -y tesseract-ocr

# Install Czech language pack
sudo apt-get install -y tesseract-ocr-ces

# Install English language pack
sudo apt-get install -y tesseract-ocr-eng

# Verify installation
tesseract --version
tesseract --list-langs
```

### 2. Install ImageMagick (for PDF to image conversion)

```bash
# Install ImageMagick
sudo apt-get install -y imagemagick

# Verify installation
convert --version
```

### 3. Configure in WordPress

1. Go to **WordPress Admin → iDoklad Processor → Settings**
2. Scroll to **OCR Settings (Scanned PDFs)**
3. Verify that all components show ✓ Available
4. Save settings

That's it! You're ready to process scanned PDFs! 🎉

---

## Quick Start (macOS)

### 1. Install Homebrew (if not installed)

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

### 2. Install Tesseract and ImageMagick

```bash
# Install Tesseract with all languages
brew install tesseract
brew install tesseract-lang

# Install ImageMagick
brew install imagemagick

# Verify installations
tesseract --version
tesseract --list-langs
convert --version
```

### 3. Configure in WordPress

Same as Ubuntu steps above.

---

## Quick Start (Windows)

### 1. Install Tesseract

1. Download installer from: https://github.com/UB-Mannheim/tesseract/wiki
2. Run the installer
3. **Important:** Check "Additional language data" during installation
4. Select Czech and English languages
5. Note the installation path (e.g., `C:\Program Files\Tesseract-OCR\tesseract.exe`)

### 2. Install ImageMagick

1. Download from: https://imagemagick.org/script/download.php#windows
2. Run the installer
3. Check "Install legacy utilities (e.g., convert)"

### 3. Configure in WordPress

1. Go to **WordPress Admin → iDoklad Processor → Settings**
2. Scroll to **OCR Settings**
3. Update **Tesseract Path** to full path (e.g., `C:\Program Files\Tesseract-OCR\tesseract.exe`)
4. Verify all components show ✓ Available
5. Save settings

---

## Alternative: Cloud OCR (No Installation Required)

If you can't install software on your server, use cloud OCR:

### Option 1: OCR.space (Free Tier)

1. Go to https://ocr.space/ocrapi
2. Sign up for free API key (25,000 requests/month)
3. Copy your API key
4. In WordPress settings:
   - Check **Use Cloud OCR**
   - Select **OCR.space** from dropdown
   - Paste your API key
   - Save settings

### Option 2: Google Cloud Vision

1. Create Google Cloud account
2. Enable Cloud Vision API
3. Create API key
4. In WordPress settings:
   - Check **Use Cloud OCR**
   - Select **Google Cloud Vision**
   - Paste your API key
   - Save settings

---

## Verification

### Check Installation Status

1. Go to **WordPress Admin → iDoklad Processor → Settings**
2. Scroll to **OCR Settings (Scanned PDFs)**
3. Check the **OCR Capabilities Status** section

You should see:

✅ **Ready to process scanned PDFs**

If you see ⚠ warnings, check which components are missing and install them.

### Test with Sample PDF

1. Create a test scanned PDF invoice
2. Send it to your configured email
3. Check processing logs
4. Verify text was extracted

---

## Troubleshooting

### "Missing PDF-to-image converter"

**Install one of:**
- ImageMagick: `sudo apt-get install imagemagick`
- Ghostscript: `sudo apt-get install ghostscript`
- PHP Imagick: `sudo apt-get install php-imagick`

### "Missing OCR engine"

**Install one of:**
- Tesseract: `sudo apt-get install tesseract-ocr tesseract-ocr-ces tesseract-ocr-eng`
- Or configure cloud OCR service (OCR.space or Google Vision)

### "Tesseract not found"

**Find tesseract path:**
```bash
which tesseract
# or on Windows:
where tesseract
```

**Update in settings:**
- Go to OCR Settings
- Update "Tesseract Path" field
- Example: `/usr/bin/tesseract` or `/usr/local/bin/tesseract`

### Poor OCR accuracy

**Solutions:**
1. Ensure correct language is set (e.g., `ces` for Czech invoices)
2. Use higher-quality scans (300+ DPI)
3. Try cloud OCR services (usually more accurate)
4. Check if language pack is installed:
   ```bash
   tesseract --list-langs
   ```

---

## Recommended Setup

### For Best Results:

1. **Primary:** Tesseract OCR (local, free, private)
2. **Fallback:** OCR.space (cloud, free tier, accurate)
3. **Languages:** Czech + English (`ces+eng`)
4. **Image Quality:** 300+ DPI scans

### Configuration:
```
✓ Enable OCR
✓ Use Tesseract OCR
  Tesseract Path: tesseract
  OCR Languages: ces+eng
✓ Use Cloud OCR (as fallback)
  Cloud Service: OCR.space
  API Key: [your-key]
```

---

## Language Codes Reference

Common languages for Czech invoices:

| Language | Code |
|----------|------|
| Czech | `ces` |
| English | `eng` |
| German | `deu` |
| Slovak | `slk` |
| Polish | `pol` |

### Install Additional Languages:

```bash
# Ubuntu/Debian
sudo apt-get install tesseract-ocr-[code]

# Examples:
sudo apt-get install tesseract-ocr-deu  # German
sudo apt-get install tesseract-ocr-slk  # Slovak
```

---

## Memory Requirements

### Recommended PHP Settings:

```ini
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 20M
post_max_size = 20M
```

Update in `php.ini` or `.htaccess`:

```apache
php_value memory_limit 512M
php_value max_execution_time 300
```

---

## Next Steps

After installation:

1. ✅ Verify all components are available
2. ✅ Test with a sample scanned invoice
3. ✅ Enable Debug Mode to monitor processing
4. ✅ Check logs for any errors
5. ✅ Process real invoices

---

## Support

For detailed documentation, see:
- **OCR-README.md** - Complete OCR documentation
- **PDF-PARSER-README.md** - PDF parsing documentation

For help:
1. Enable Debug Mode
2. Check WordPress debug.log
3. Review OCR Capabilities Status in settings

---

**Installation Complete!** 🎉

Your iDoklad Helper plugin can now process both:
- ✅ Text-based PDF invoices
- ✅ Scanned (image-based) PDF invoices

All automatically detected and processed! 🚀


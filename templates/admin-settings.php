<?php
/**
 * Admin settings template - Updated for per-user iDoklad credentials
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('iDoklad Invoice Processor Settings', 'idoklad-invoice-processor'); ?></h1>
    
    <div class="idoklad-admin-container">
        <div class="idoklad-admin-main">
            <form method="post" action="">
                <?php wp_nonce_field('idoklad_settings_nonce'); ?>
                
                <!-- PDF.co Settings (PRIMARY) -->
                <div class="idoklad-settings-section" style="border: 3px solid #0073aa; background: #f0f8ff;">
                    <h2 style="color: #0073aa;">📄 PDF.co Cloud Processing (Recommended)</h2>
                    <p class="description" style="font-size: 14px; background: #e5f5fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                        <strong>PDF.co is the recommended method for PDF processing.</strong><br>
                        It handles both regular PDFs and scanned documents (OCR) automatically in the cloud.<br>
                        <strong>Replaces all other PDF processing methods when enabled.</strong><br>
                        Get free API key (300 credits/month) at <a href="https://pdf.co/" target="_blank">pdf.co →</a>
                    </p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="use_pdfco"><?php _e('Enable PDF.co', 'idoklad-invoice-processor'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="use_pdfco" 
                                           name="use_pdfco" 
                                           value="1" 
                                           <?php checked(get_option('idoklad_use_pdfco'), true); ?>>
                                    <?php _e('Use PDF.co for all PDF processing (recommended)', 'idoklad-invoice-processor'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, PDF.co will handle all PDF processing (regular text + OCR). Other methods will be used as fallback only.', 'idoklad-invoice-processor'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="pdfco_api_key"><?php _e('PDF.co API Key', 'idoklad-invoice-processor'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="pdfco_api_key" 
                                       name="pdfco_api_key" 
                                       value="<?php echo esc_attr(get_option('idoklad_pdfco_api_key')); ?>" 
                                       class="regular-text"
                                       style="font-family: monospace;"
                                       placeholder="your-pdf-co-api-key">
                                <p class="description">
                                    <?php _e('Get your free API key from', 'idoklad-invoice-processor'); ?> 
                                    <a href="https://pdf.co/" target="_blank">pdf.co</a>
                                    <br>Free tier: 300 credits/month (enough for ~300 PDFs)
                                </p>
                                <p>
                                    <button type="button" id="test-pdfco" class="button button-secondary">
                                        <?php _e('Test PDF.co Connection', 'idoklad-invoice-processor'); ?>
                                    </button>
                                    <span id="pdfco-test-result" style="margin-left: 10px;"></span>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Email Settings -->
                <div class="idoklad-settings-section">
                    <h2><?php _e('Email Settings', 'idoklad-invoice-processor'); ?></h2>
                    <p class="description"><?php _e('Configure the email account that will receive invoice PDFs.', 'idoklad-invoice-processor'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Email Host', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="email_host" value="<?php echo esc_attr(get_option('idoklad_email_host')); ?>" class="regular-text" />
                                <p class="description"><?php _e('e.g., imap.gmail.com, outlook.office365.com', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Port', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="number" name="email_port" value="<?php echo esc_attr(get_option('idoklad_email_port', 993)); ?>" class="small-text" />
                                <p class="description"><?php _e('Usually 993 for SSL, 143 for TLS', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Username', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="email_username" value="<?php echo esc_attr(get_option('idoklad_email_username')); ?>" class="regular-text" />
                                <p class="description"><?php _e('Full email address', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Password', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="password" name="email_password" value="<?php echo esc_attr(get_option('idoklad_email_password')); ?>" class="regular-text" />
                                <p class="description"><?php _e('Email password or app-specific password', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Encryption', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <select name="email_encryption">
                                    <option value="ssl" <?php selected(get_option('idoklad_email_encryption', 'ssl'), 'ssl'); ?>><?php _e('SSL', 'idoklad-invoice-processor'); ?></option>
                                    <option value="tls" <?php selected(get_option('idoklad_email_encryption'), 'tls'); ?>><?php _e('TLS', 'idoklad-invoice-processor'); ?></option>
                                    <option value="novalidate-cert" <?php selected(get_option('idoklad_email_encryption'), 'novalidate-cert'); ?>><?php _e('No SSL/TLS', 'idoklad-invoice-processor'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="button" id="test-email-connection" class="button button-secondary">
                            <?php _e('Test Email Connection', 'idoklad-invoice-processor'); ?>
                        </button>
                        <span id="email-test-result"></span>
                    </p>
                </div>
                
                <!-- Zapier Integration Settings -->
                <div class="idoklad-settings-section">
                    <h2><?php _e('Zapier Integration Settings', 'idoklad-invoice-processor'); ?></h2>
                    <p class="description"><?php _e('Configure Zapier webhook for invoice processing and automation.', 'idoklad-invoice-processor'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Webhook URL', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="url" name="zapier_webhook_url" value="<?php echo esc_attr(get_option('idoklad_zapier_webhook_url')); ?>" class="large-text" placeholder="https://hooks.zapier.com/hooks/catch/123456/abcdef/" />
                                <p class="description"><?php _e('Your Zapier webhook URL for processing invoices. Get this from your Zapier Zap.', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="button" id="test-zapier-webhook" class="button button-primary">
                            <?php _e('Test Zapier Webhook', 'idoklad-invoice-processor'); ?>
                        </button>
                        <span id="zapier-test-result"></span>
                    </p>
                </div>
                
                <!-- PDF Processing Settings -->
                <div class="idoklad-settings-section">
                    <h2><?php _e('PDF Processing Settings', 'idoklad-invoice-processor'); ?></h2>
                    <p class="description"><?php _e('Configure how PDF invoices are parsed and processed.', 'idoklad-invoice-processor'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('PDF Parser', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="use_native_parser_first" value="1" <?php checked(get_option('idoklad_use_native_parser_first', true), 1); ?> />
                                    <?php _e('Use native PHP parser first (recommended)', 'idoklad-invoice-processor'); ?>
                                </label>
                                <p class="description"><?php _e('Native PHP parser works on any server without external dependencies. Falls back to system tools (pdftotext, Ghostscript) if needed.', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Available Parsing Methods', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <?php
                                require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-processor.php';
                                $pdf_processor = new IDokladProcessor_PDFProcessor();
                                $parsing_methods = $pdf_processor->test_parsing_methods();
                                ?>
                                <table class="widefat" style="max-width: 600px;">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Method', 'idoklad-invoice-processor'); ?></th>
                                            <th><?php _e('Status', 'idoklad-invoice-processor'); ?></th>
                                            <th><?php _e('Description', 'idoklad-invoice-processor'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($parsing_methods as $key => $method): ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($method['name']); ?></strong></td>
                                                <td>
                                                    <?php if ($method['available']): ?>
                                                        <span style="color: green;">✓ Available</span>
                                                    <?php else: ?>
                                                        <span style="color: orange;">✗ Not Available</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo esc_html($method['description']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p class="description" style="margin-top: 10px;">
                                    <?php 
                                    $available_count = count(array_filter($parsing_methods, function($m) { return $m['available']; }));
                                    echo sprintf(
                                        _n('%d parsing method available', '%d parsing methods available', $available_count, 'idoklad-invoice-processor'),
                                        $available_count
                                    ); 
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- OCR Settings for Scanned PDFs -->
                <div class="idoklad-settings-section">
                    <h2><?php _e('OCR Settings (Scanned PDFs)', 'idoklad-invoice-processor'); ?></h2>
                    <p class="description"><?php _e('Configure OCR (Optical Character Recognition) to extract text from scanned PDF invoices.', 'idoklad-invoice-processor'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable OCR', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_ocr" value="1" <?php checked(get_option('idoklad_enable_ocr', true), 1); ?> />
                                    <?php _e('Enable OCR for scanned PDFs (recommended)', 'idoklad-invoice-processor'); ?>
                                </label>
                                <p class="description"><?php _e('Automatically detect and process scanned (image-based) PDFs using OCR.', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('Local OCR (Tesseract)', 'idoklad-invoice-processor'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Use Tesseract OCR', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="use_tesseract" value="1" <?php checked(get_option('idoklad_use_tesseract', true), 1); ?> />
                                    <?php _e('Use Tesseract if available', 'idoklad-invoice-processor'); ?>
                                </label>
                                <p class="description"><?php _e('Tesseract is a free, open-source OCR engine. Requires installation on your server.', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Tesseract Path', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="tesseract_path" value="<?php echo esc_attr(get_option('idoklad_tesseract_path', 'tesseract')); ?>" class="regular-text" />
                                <p class="description"><?php _e('Path to tesseract command (usually just "tesseract" if installed globally)', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('OCR Languages', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="tesseract_lang" value="<?php echo esc_attr(get_option('idoklad_tesseract_lang', 'ces+eng')); ?>" class="regular-text" />
                                <p class="description"><?php _e('Language codes separated by + (e.g., "ces+eng" for Czech and English)', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('Cloud OCR Services (Optional)', 'idoklad-invoice-processor'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Use Cloud OCR', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="use_cloud_ocr" value="1" <?php checked(get_option('idoklad_use_cloud_ocr', false), 1); ?> />
                                    <?php _e('Use cloud OCR services as fallback', 'idoklad-invoice-processor'); ?>
                                </label>
                                <p class="description"><?php _e('Use cloud OCR services if Tesseract is not available or fails.', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Cloud OCR Service', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <select name="cloud_ocr_service">
                                    <option value="none" <?php selected(get_option('idoklad_cloud_ocr_service', 'none'), 'none'); ?>><?php _e('None', 'idoklad-invoice-processor'); ?></option>
                                    <option value="ocr_space" <?php selected(get_option('idoklad_cloud_ocr_service'), 'ocr_space'); ?>><?php _e('OCR.space (Free tier available)', 'idoklad-invoice-processor'); ?></option>
                                    <option value="google_vision" <?php selected(get_option('idoklad_cloud_ocr_service'), 'google_vision'); ?>><?php _e('Google Cloud Vision', 'idoklad-invoice-processor'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('OCR.space API Key', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="ocr_space_api_key" value="<?php echo esc_attr(get_option('idoklad_ocr_space_api_key')); ?>" class="regular-text" />
                                <p class="description">
                                    <?php _e('Get a free API key at', 'idoklad-invoice-processor'); ?> 
                                    <a href="https://ocr.space/ocrapi" target="_blank">ocr.space/ocrapi</a>
                                    (25,000 requests/month free)
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('OCR Language Detection', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <p><strong style="color: green;">✓ <?php _e('Automatic Language Detection Enabled', 'idoklad-invoice-processor'); ?></strong></p>
                                <p class="description">
                                    <?php _e('OCR.space Engine 2 automatically detects all languages including Czech, English, German, French, Spanish, and 50+ more languages.', 'idoklad-invoice-processor'); ?>
                                    <br><?php _e('No language configuration needed - works automatically for all invoice types!', 'idoklad-invoice-processor'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Test Connection', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <button type="button" id="test-ocr-space" class="button button-secondary">
                                    <?php _e('Test OCR.space API', 'idoklad-invoice-processor'); ?>
                                </button>
                                <span id="ocr-space-test-result" style="margin-left: 10px;"></span>
                                <p class="description"><?php _e('Test your OCR.space API key and check if OCR is working correctly', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Google Vision API Key', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="google_vision_api_key" value="<?php echo esc_attr(get_option('idoklad_google_vision_api_key')); ?>" class="regular-text" />
                                <p class="description">
                                    <?php _e('Get an API key from', 'idoklad-invoice-processor'); ?> 
                                    <a href="https://cloud.google.com/vision" target="_blank">Google Cloud Console</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('OCR Capabilities Status', 'idoklad-invoice-processor'); ?></h3>
                    <?php
                    require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-processor.php';
                    $pdf_processor_ocr = new IDokladProcessor_PDFProcessor();
                    $ocr_status = $pdf_processor_ocr->test_ocr_capabilities();
                    ?>
                    
                    <?php if ($ocr_status['enabled']): ?>
                        <table class="widefat" style="max-width: 800px;">
                            <thead>
                                <tr>
                                    <th><?php _e('Component', 'idoklad-invoice-processor'); ?></th>
                                    <th><?php _e('Status', 'idoklad-invoice-processor'); ?></th>
                                    <th><?php _e('Description', 'idoklad-invoice-processor'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ocr_status['methods'] as $key => $method): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($method['name']); ?></strong></td>
                                        <td>
                                            <?php if ($method['available']): ?>
                                                <span style="color: green;">✓ Available</span>
                                                <?php if (isset($method['enabled']) && !$method['enabled']): ?>
                                                    <span style="color: orange;"> (Disabled)</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: red;">✗ Not Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($method['description']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 15px; padding: 10px; background: <?php echo $ocr_status['can_process_scanned_pdfs'] ? '#d4edda' : '#fff3cd'; ?>; border: 1px solid <?php echo $ocr_status['can_process_scanned_pdfs'] ? '#c3e6cb' : '#ffeeba'; ?>; border-radius: 4px;">
                            <?php if ($ocr_status['can_process_scanned_pdfs']): ?>
                                <strong style="color: #155724;">✓ <?php _e('Ready to process scanned PDFs', 'idoklad-invoice-processor'); ?></strong>
                                <p style="margin: 5px 0 0 0; color: #155724;">
                                    <?php _e('Your system has both PDF-to-image conversion and OCR engine available.', 'idoklad-invoice-processor'); ?>
                                </p>
                            <?php else: ?>
                                <strong style="color: #856404;">⚠ <?php _e('Cannot process scanned PDFs', 'idoklad-invoice-processor'); ?></strong>
                                <p style="margin: 5px 0 0 0; color: #856404;">
                                    <?php if (!$ocr_status['has_pdf_converter']): ?>
                                        <?php _e('Missing PDF-to-image converter. Please install ImageMagick, Ghostscript, or PHP Imagick extension.', 'idoklad-invoice-processor'); ?><br>
                                    <?php endif; ?>
                                    <?php if (!$ocr_status['has_ocr_engine']): ?>
                                        <?php _e('Missing OCR engine. Please install Tesseract or configure a cloud OCR service.', 'idoklad-invoice-processor'); ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: orange;"><?php _e('OCR is currently disabled. Enable it above to process scanned PDFs.', 'idoklad-invoice-processor'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- General Settings -->
                <div class="idoklad-settings-section">
                    <h2><?php _e('General Settings', 'idoklad-invoice-processor'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Notification Email', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="email" name="notification_email" value="<?php echo esc_attr(get_option('idoklad_notification_email', get_option('admin_email'))); ?>" class="regular-text" />
                                <p class="description"><?php _e('Email address for system notifications and error reports', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Debug Mode', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="debug_mode" value="1" <?php checked(get_option('idoklad_debug_mode'), 1); ?> />
                                    <?php _e('Enable debug logging', 'idoklad-invoice-processor'); ?>
                                </label>
                                <p class="description"><?php _e('Enable detailed logging for troubleshooting', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('Save Settings', 'idoklad-invoice-processor')); ?>
            </form>
        </div>
        
        <div class="idoklad-admin-sidebar">
            <div class="idoklad-widget">
                <h3><?php _e('Quick Actions', 'idoklad-invoice-processor'); ?></h3>
                <p>
                    <button type="button" id="process-queue-manually" class="button button-primary">
                        <?php _e('Process Queue Now', 'idoklad-invoice-processor'); ?>
                    </button>
                </p>
                <p class="description"><?php _e('Manually trigger processing of pending emails', 'idoklad-invoice-processor'); ?></p>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('System Status', 'idoklad-invoice-processor'); ?></h3>
                <p><strong><?php _e('Plugin Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo IDOKLAD_PROCESSOR_VERSION; ?></p>
                <p><strong><?php _e('WordPress Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
                <p><strong><?php _e('PHP Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo PHP_VERSION; ?></p>
                <p><strong><?php _e('IMAP Extension:', 'idoklad-invoice-processor'); ?></strong> 
                    <?php echo extension_loaded('imap') ? '<span style="color: green;">✓ Enabled</span>' : '<span style="color: red;">✗ Not Available</span>'; ?>
                </p>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('Queue Status', 'idoklad-invoice-processor'); ?></h3>
                <?php
                global $wpdb;
                $queue_table = $wpdb->prefix . 'idoklad_queue';
                $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'pending'");
                $processing_count = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'processing'");
                $failed_count = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'failed'");
                ?>
                <p><strong><?php _e('Pending:', 'idoklad-invoice-processor'); ?></strong> <span class="queue-pending"><?php echo $pending_count; ?></span></p>
                <p><strong><?php _e('Processing:', 'idoklad-invoice-processor'); ?></strong> <span class="queue-processing"><?php echo $processing_count; ?></span></p>
                <p><strong><?php _e('Failed:', 'idoklad-invoice-processor'); ?></strong> <span class="queue-failed"><?php echo $failed_count; ?></span></p>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('Important Note', 'idoklad-invoice-processor'); ?></h3>
                <p><?php _e('iDoklad API credentials are now configured per user in the Authorized Users section. Each user must have their own Client ID and Client Secret.', 'idoklad-invoice-processor'); ?></p>
                <p><a href="<?php echo admin_url('admin.php?page=idoklad-processor-users'); ?>" class="button button-secondary">
                    <?php _e('Manage Users', 'idoklad-invoice-processor'); ?>
                </a></p>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('Zapier Setup Instructions', 'idoklad-invoice-processor'); ?></h3>
                <p><strong><?php _e('Step-by-Step Setup:', 'idoklad-invoice-processor'); ?></strong></p>
                <ol>
                    <li><?php _e('Go to <a href="https://zapier.com" target="_blank">Zapier.com</a> and create a new Zap', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Choose "Webhooks by Zapier" as the trigger', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Select "Catch Hook" as the trigger event', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Copy the webhook URL provided by Zapier', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Paste the webhook URL in the settings above', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Test the connection using the "Test Webhook" button', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Configure your Zap actions (e.g., create iDoklad invoice)', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Activate your Zap', 'idoklad-invoice-processor'); ?></li>
                </ol>
                <p><strong><?php _e('Webhook URL Format:', 'idoklad-invoice-processor'); ?></strong></p>
                <code>https://hooks.zapier.com/hooks/catch/123456/abcdef/</code>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('Zapier Integration Benefits', 'idoklad-invoice-processor'); ?></h3>
                <p><strong><?php _e('Why Use Zapier:', 'idoklad-invoice-processor'); ?></strong></p>
                <ul>
                    <li><?php _e('✓ Powerful automation - connect to 5000+ apps', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('✓ Flexible processing - use any AI service or custom logic', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('✓ Easy setup - visual workflow builder', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('✓ Reliable - enterprise-grade infrastructure', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('✓ Scalable - handle any volume of invoices', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('✓ Customizable - create complex workflows', 'idoklad-invoice-processor'); ?></li>
                </ul>
                <p><strong><?php _e('Common Zapier Workflows:', 'idoklad-invoice-processor'); ?></strong></p>
                <ul>
                    <li><?php _e('• Extract data with ChatGPT/Claude/GPT-4', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('• Create iDoklad invoice automatically', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('• Send notifications to Slack/Teams', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('• Store data in Google Sheets/Airtable', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('• Archive PDFs to Google Drive/Dropbox', 'idoklad-invoice-processor'); ?></li>
                </ul>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('Debug Information', 'idoklad-invoice-processor'); ?></h3>
                <p><strong><?php _e('WordPress Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
                <p><strong><?php _e('PHP Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo PHP_VERSION; ?></p>
                <p><strong><?php _e('cURL Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo curl_version()['version'] ?? 'Not available'; ?></p>
                <p><strong><?php _e('OpenSSL Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo OPENSSL_VERSION_TEXT ?? 'Not available'; ?></p>
                <p><strong><?php _e('SSL Verify:', 'idoklad-invoice-processor'); ?></strong> 
                    <?php 
                    $ssl_test = wp_remote_get('https://api.idoklad.cz/v3', array('sslverify' => true, 'timeout' => 10));
                    if (is_wp_error($ssl_test)) {
                        echo '<span style="color: red;">✗ Failed: ' . $ssl_test->get_error_message() . '</span>';
                    } else {
                        echo '<span style="color: green;">✓ Working</span>';
                    }
                    ?>
                </p>
                <p><strong><?php _e('OAuth Endpoint:', 'idoklad-invoice-processor'); ?></strong> 
                    <?php 
                    $oauth_test = wp_remote_get('https://app.idoklad.cz/identity/server/connect/token', array('sslverify' => true, 'timeout' => 10));
                    if (is_wp_error($oauth_test)) {
                        echo '<span style="color: red;">✗ Failed: ' . $oauth_test->get_error_message() . '</span>';
                    } else {
                        $oauth_code = wp_remote_retrieve_response_code($oauth_test);
                        if ($oauth_code == 400) {
                            echo '<span style="color: green;">✓ Reachable (400 expected without credentials)</span>';
                        } else {
                            echo '<span style="color: orange;">✓ Reachable (HTTP ' . $oauth_code . ')</span>';
                        }
                    }
                    ?>
                </p>
                <p><strong><?php _e('ChatGPT API:', 'idoklad-invoice-processor'); ?></strong> 
                    <?php 
                    $chatgpt_key = get_option('idoklad_chatgpt_api_key');
                    if (empty($chatgpt_key)) {
                        echo '<span style="color: red;">✗ API Key not set</span>';
                    } else {
                        $chatgpt_test = wp_remote_get('https://api.openai.com/v1/models', array(
                            'headers' => array('Authorization' => 'Bearer ' . $chatgpt_key),
                            'sslverify' => true, 
                            'timeout' => 10
                        ));
                        if (is_wp_error($chatgpt_test)) {
                            echo '<span style="color: red;">✗ Failed: ' . $chatgpt_test->get_error_message() . '</span>';
                        } else {
                            $chatgpt_code = wp_remote_retrieve_response_code($chatgpt_test);
                            if ($chatgpt_code == 200) {
                                echo '<span style="color: green;">✓ Reachable</span>';
                            } else {
                                echo '<span style="color: orange;">✓ Reachable (HTTP ' . $chatgpt_code . ')</span>';
                            }
                        }
                    }
                    ?>
                </p>
                <p><strong><?php _e('Debug Mode:', 'idoklad-invoice-processor'); ?></strong> 
                    <?php echo get_option('idoklad_debug_mode') ? '<span style="color: green;">✓ Enabled</span>' : '<span style="color: orange;">Disabled</span>'; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-detect best ChatGPT model
    $('#auto-detect-model').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('<?php _e('Detecting...', 'idoklad-invoice-processor'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_get_chatgpt_models',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    // Priority order for models (best to fallback)
                    var preferredModels = ['gpt-4o', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo'];
                    var bestModel = null;
                    
                    // Find the best available model
                    for (var i = 0; i < preferredModels.length; i++) {
                        if (response.data.indexOf(preferredModels[i]) !== -1) {
                            bestModel = preferredModels[i];
                            break;
                        }
                    }
                    
                    // If no preferred model found, use the first available
                    if (!bestModel && response.data.length > 0) {
                        bestModel = response.data[0];
                    }
                    
                    if (bestModel) {
                        $('#chatgpt_model').val(bestModel);
                        alert('<?php _e('Auto-detected best model:', 'idoklad-invoice-processor'); ?> ' + bestModel);
                    } else {
                        alert('<?php _e('No suitable models found', 'idoklad-invoice-processor'); ?>');
                    }
                } else {
                    alert('<?php _e('Could not fetch available models from OpenAI API', 'idoklad-invoice-processor'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Error fetching available models', 'idoklad-invoice-processor'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Test ChatGPT connection
    $('#test-chatgpt-connection').on('click', function() {
        var button = $(this);
        var resultSpan = $('#chatgpt-test-result');
        
        button.prop('disabled', true);
        resultSpan.html('<?php _e('Testing...', 'idoklad-invoice-processor'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_test_chatgpt_connection',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">✗ <?php _e('Connection test failed', 'idoklad-invoice-processor'); ?></span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Test Zapier webhook
    $('#test-zapier-webhook').on('click', function() {
        var button = $(this);
        var resultSpan = $('#zapier-test-result');
        
        button.prop('disabled', true);
        resultSpan.html('<?php _e('Testing...', 'idoklad-invoice-processor'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_test_zapier_webhook',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">✗ <?php _e('Webhook test failed', 'idoklad-invoice-processor'); ?></span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Test OCR.space API
    $('#test-ocr-space').on('click', function() {
        var button = $(this);
        var resultSpan = $('#ocr-space-test-result');
        
        button.prop('disabled', true);
        resultSpan.html('<span style="color: blue;">⏳ <?php _e('Testing OCR.space API... This may take a few seconds...', 'idoklad-invoice-processor'); ?></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_test_ocr_space',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">✗ <?php _e('OCR.space test failed', 'idoklad-invoice-processor'); ?></span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});
</script>

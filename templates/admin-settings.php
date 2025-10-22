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
                                    <button type="button" id="test-ai-parser" class="button button-secondary">
                                        <?php _e('Test AI Parser', 'idoklad-invoice-processor'); ?>
                                    </button>
                                    <span id="pdfco-test-result" style="margin-left: 10px;"></span>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="use_ai_parser"><?php _e('Enable AI Invoice Parser', 'idoklad-invoice-processor'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="use_ai_parser" 
                                           name="use_ai_parser" 
                                           value="1" 
                                           <?php checked(get_option('idoklad_use_ai_parser'), true); ?>>
                                    <?php _e('Use PDF.co AI Invoice Parser for structured data extraction', 'idoklad-invoice-processor'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, uses PDF.co\'s AI Invoice Parser to extract structured invoice data (invoice number, amounts, dates, etc.) instead of just text. More accurate than text parsing.', 'idoklad-invoice-processor'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ChatGPT AI Extraction Settings -->
                <div class="idoklad-settings-section">
                    <h2>🤖 <?php _e('AI Invoice Extraction (ChatGPT)', 'idoklad-invoice-processor'); ?></h2>
                    <p class="description"><?php _e('Configure the OpenAI ChatGPT API that is used to extract structured data from PDF text when PDF.co AI parsing is unavailable.', 'idoklad-invoice-processor'); ?></p>

                    <?php
                    $chatgpt_models = array(
                        'gpt-4o' => __('GPT-4o (Recommended)', 'idoklad-invoice-processor'),
                        'gpt-4o-mini' => __('GPT-4o Mini', 'idoklad-invoice-processor'),
                        'gpt-4-turbo' => __('GPT-4 Turbo', 'idoklad-invoice-processor'),
                        'gpt-4' => __('GPT-4', 'idoklad-invoice-processor'),
                        'gpt-3.5-turbo' => __('GPT-3.5 Turbo', 'idoklad-invoice-processor'),
                    );
                    $selected_model = get_option('idoklad_chatgpt_model', 'gpt-4o');
                    ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="chatgpt_api_key"><?php _e('OpenAI API Key', 'idoklad-invoice-processor'); ?> *</label>
                            </th>
                            <td>
                                <input type="password"
                                       id="chatgpt_api_key"
                                       name="chatgpt_api_key"
                                       value="<?php echo esc_attr(get_option('idoklad_chatgpt_api_key')); ?>"
                                       class="regular-text"
                                       autocomplete="off"
                                       placeholder="sk-..." />
                                <p class="description"><?php _e('Store securely. The key is used to generate invoice data when PDF.co parsing is unavailable.', 'idoklad-invoice-processor'); ?></p>
                                <p>
                                    <button type="button" id="test-chatgpt-connection" class="button button-secondary">
                                        <?php _e('Test ChatGPT Connection', 'idoklad-invoice-processor'); ?>
                                    </button>
                                    <span id="chatgpt-test-result"></span>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="chatgpt_model"><?php _e('Preferred Model', 'idoklad-invoice-processor'); ?></label>
                            </th>
                            <td>
                                <select id="chatgpt_model" name="chatgpt_model" style="max-width: 320px;">
                                    <?php foreach ($chatgpt_models as $model_key => $model_label): ?>
                                        <option value="<?php echo esc_attr($model_key); ?>" <?php selected($selected_model, $model_key); ?>>
                                            <?php echo esc_html($model_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (!isset($chatgpt_models[$selected_model])): ?>
                                        <option value="<?php echo esc_attr($selected_model); ?>" selected>
                                            <?php printf(esc_html__('%s (custom)', 'idoklad-invoice-processor'), esc_html($selected_model)); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <p class="description"><?php _e('Click refresh to load models that are currently available for your API key.', 'idoklad-invoice-processor'); ?></p>
                                <p>
                                    <button type="button" id="refresh-chatgpt-models" class="button button-secondary" data-loading-text="<?php esc_attr_e('Refreshing...', 'idoklad-invoice-processor'); ?>">
                                        <?php _e('Refresh Available Models', 'idoklad-invoice-processor'); ?>
                                    </button>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="chatgpt_prompt"><?php _e('Extraction Prompt', 'idoklad-invoice-processor'); ?></label>
                            </th>
                            <td>
                                <textarea id="chatgpt_prompt"
                                          name="chatgpt_prompt"
                                          rows="4"
                                          style="width:100%; max-width:600px; font-family: monospace;"><?php echo esc_textarea(get_option('idoklad_chatgpt_prompt')); ?></textarea>
                                <p class="description"><?php _e('Customise how invoice data is extracted from PDF text. Keep JSON keys consistent with the rest of the system.', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Cloud OCR Settings -->
                <div class="idoklad-settings-section">
                    <h2>🔍 <?php _e('OCR Services (Fallback)', 'idoklad-invoice-processor'); ?></h2>
                    <p class="description"><?php _e('Configure OCR providers that can be used when PDF.co is disabled or runs out of credits.', 'idoklad-invoice-processor'); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Cloud OCR', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="use_cloud_ocr" value="1" <?php checked(get_option('idoklad_use_cloud_ocr'), 1); ?> />
                                    <?php _e('Allow the plugin to call external OCR APIs when needed', 'idoklad-invoice-processor'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('OCR Provider', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <select name="cloud_ocr_service" id="cloud_ocr_service" style="max-width: 320px;">
                                    <option value="none" <?php selected(get_option('idoklad_cloud_ocr_service', 'none'), 'none'); ?>><?php _e('Disabled', 'idoklad-invoice-processor'); ?></option>
                                    <option value="ocr_space" <?php selected(get_option('idoklad_cloud_ocr_service'), 'ocr_space'); ?>><?php _e('OCR.space', 'idoklad-invoice-processor'); ?></option>
                                    <option value="google_vision" <?php selected(get_option('idoklad_cloud_ocr_service'), 'google_vision'); ?>><?php _e('Google Vision', 'idoklad-invoice-processor'); ?></option>
                                </select>
                                <p class="description"><?php _e('OCR.space is the quickest to configure and supports Czech language.', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('OCR.space API Key', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="ocr_space_api_key" value="<?php echo esc_attr(get_option('idoklad_ocr_space_api_key')); ?>" class="regular-text" placeholder="helloworld" />
                                <p class="description"><?php _e('Required when OCR.space is selected. Free tier available at ocr.space.', 'idoklad-invoice-processor'); ?></p>
                                <p>
                                    <button type="button" id="test-ocr-space" class="button button-secondary">
                                        <?php _e('Test OCR.space Connection', 'idoklad-invoice-processor'); ?>
                                    </button>
                                    <span id="ocr-space-test-result"></span>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('OCR.space Language', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="ocr_space_language" value="<?php echo esc_attr(get_option('idoklad_ocr_space_language', 'eng')); ?>" class="regular-text" style="width: 120px;" />
                                <p class="description"><?php _e('Use ISO language codes (e.g. eng, cze, ger). Multiple values can be combined with +, such as ces+eng.', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Google Vision API Key', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="google_vision_api_key" value="<?php echo esc_attr(get_option('idoklad_google_vision_api_key')); ?>" class="regular-text" />
                                <p class="description"><?php _e('Optional: used when Google Vision is selected as the OCR provider.', 'idoklad-invoice-processor'); ?></p>
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
                <h3><?php _e('Debug Information', 'idoklad-invoice-processor'); ?></h3>
                <p><strong><?php _e('WordPress Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
                <p><strong><?php _e('PHP Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo PHP_VERSION; ?></p>
                <p><strong><?php _e('cURL Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo curl_version()['version'] ?? 'Not available'; ?></p>
                <p><strong><?php _e('OpenSSL Version:', 'idoklad-invoice-processor'); ?></strong> <?php echo OPENSSL_VERSION_TEXT ?? 'Not available'; ?></p>
                <p><strong><?php _e('SSL Verify:', 'idoklad-invoice-processor'); ?></strong> 
                    <?php 
                    $ssl_test = wp_remote_get('https://api.idoklad.cz/api/v3', array('sslverify' => true, 'timeout' => 10));
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
                <p><strong><?php _e('PDF.co API:', 'idoklad-invoice-processor'); ?></strong> 
                    <?php 
                    $pdfco_key = get_option('idoklad_pdfco_api_key');
                    if (empty($pdfco_key)) {
                        echo '<span style="color: orange;">⚠ API Key not set</span>';
                    } else {
                        echo '<span style="color: green;">✓ API Key configured</span>';
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
    // Settings page is now minimal - no deprecated tests
});
</script>

<?php
/**
 * Plugin Name: iDoklad Invoice Processor
 * Plugin URI: https://your-website.com
 * Description: Automated invoice processing system that receives PDF invoices via email, extracts data using AI, and creates records in iDoklad with per-user credentials.
 * Version: 1.1.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: idoklad-invoice-processor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IDOKLAD_PROCESSOR_VERSION', '1.1.0');
define('IDOKLAD_PROCESSOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IDOKLAD_PROCESSOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IDOKLAD_PROCESSOR_PLUGIN_FILE', __FILE__);

// Include required files
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-database.php';
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-admin.php';
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-email-monitor.php';
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-processor.php';
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-chatgpt-integration.php';
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-idoklad-api.php';
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-notification.php';
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-logger.php';
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-user-manager.php';

/**
 * Main plugin class
 */
class IDokladInvoiceProcessor {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Check for database upgrades
        $this->check_database_upgrades();
        
        // Initialize components
        new IDokladProcessor_Database();
        new IDokladProcessor_Admin();
        new IDokladProcessor_EmailMonitor();
        new IDokladProcessor_UserManager();
        
        // Load text domain for translations
        load_plugin_textdomain('idoklad-invoice-processor', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
        // Create database tables
        IDokladProcessor_Database::create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Set plugin version for future upgrades
        update_option('idoklad_processor_db_version', '1.1.0');
        
        // Schedule email monitoring cron job
        if (!wp_next_scheduled('idoklad_check_emails')) {
            wp_schedule_event(time(), 'every_5_minutes', 'idoklad_check_emails');
        }
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('idoklad_check_emails');
    }
    
    private function set_default_options() {
        $default_options = array(
            'email_host' => '',
            'email_port' => 993,
            'email_username' => '',
            'email_password' => '',
            'email_encryption' => 'ssl',
            'chatgpt_api_key' => '',
            'chatgpt_model' => 'gpt-4o', // Updated to latest model
            'chatgpt_prompt' => 'Extract invoice data from this PDF. Return JSON with: invoice_number, date, total_amount, supplier_name, supplier_vat_number, items (array with name, quantity, price), currency. Validate data completeness.',
            'notification_email' => get_option('admin_email'),
            'debug_mode' => false,
            'use_native_parser_first' => true, // Use native PHP parser first (no external dependencies)
            
            // PDF.co settings (PRIMARY - replaces all other PDF processing)
            'use_pdfco' => true,  // Enable PDF.co by default
            'pdfco_api_key' => '', // User must configure
            
            // OCR settings (LEGACY - only used if PDF.co is disabled)
            'enable_ocr' => false, // Disabled when using PDF.co
            'use_tesseract' => false, // Use Tesseract OCR if available
            'tesseract_path' => 'tesseract', // Path to tesseract command
            'tesseract_lang' => 'ces+eng', // OCR languages (Czech + English)
            'use_cloud_ocr' => false, // Use cloud OCR services
            'cloud_ocr_service' => 'none', // Cloud OCR service (ocr_space, google_vision)
            'ocr_space_api_key' => '', // OCR.space API key
            'ocr_space_language' => 'eng', // OCR.space language (eng=English/Auto-detect, cze=Czech, etc.)
            'google_vision_api_key' => '' // Google Cloud Vision API key
        );
        
        foreach ($default_options as $key => $value) {
            if (!get_option('idoklad_' . $key)) {
                add_option('idoklad_' . $key, $value);
            }
        }
    }
    
    /**
     * Check for database upgrades
     */
    private function check_database_upgrades() {
        $current_version = get_option('idoklad_processor_db_version', '1.0.0');
        
        // Upgrade to 1.1.0 - Add queue processing details columns
        if (version_compare($current_version, '1.1.0', '<')) {
            global $wpdb;
            $table = $wpdb->prefix . 'idoklad_queue';
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                // Check if columns already exist
                $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
                $column_names = array_column($columns, 'Field');
                
                if (!in_array('processing_details', $column_names)) {
                    $wpdb->query("ALTER TABLE $table ADD COLUMN processing_details longtext DEFAULT NULL AFTER status");
                }
                
                if (!in_array('current_step', $column_names)) {
                    $wpdb->query("ALTER TABLE $table ADD COLUMN current_step varchar(255) DEFAULT NULL AFTER processing_details");
                }
                
                update_option('idoklad_processor_db_version', '1.1.0');
                
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad Invoice Processor: Database upgraded to version 1.1.0');
                }
            }
        }
    }
}

// Initialize the plugin
IDokladInvoiceProcessor::get_instance();

// Add custom cron interval
add_filter('cron_schedules', function($schedules) {
    $schedules['every_5_minutes'] = array(
        'interval' => 300,
        'display' => __('Every 5 Minutes', 'idoklad-invoice-processor')
    );
    return $schedules;
});

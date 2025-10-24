<?php
/**
 * Plugin Name: iDoklad Invoice Processor
 * Plugin URI: https://your-website.com
 * Description: Automated invoice processing system that receives PDF invoices via email, extracts data using AI, and creates records in iDoklad with per-user credentials.
 * Version: 1.1.1
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: idoklad-invoice-processor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants (only if not already defined)
if (!defined('IDOKLAD_PROCESSOR_VERSION')) {
    define('IDOKLAD_PROCESSOR_VERSION', '1.1.1');
}
if (!defined('IDOKLAD_PROCESSOR_PLUGIN_DIR')) {
    define('IDOKLAD_PROCESSOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('IDOKLAD_PROCESSOR_PLUGIN_URL')) {
    define('IDOKLAD_PROCESSOR_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('IDOKLAD_PROCESSOR_PLUGIN_FILE')) {
    define('IDOKLAD_PROCESSOR_PLUGIN_FILE', __FILE__);
}

// Include required files with error handling
$required_files = array(
    'includes/class-database.php',
    'includes/class-admin.php',
    'includes/class-email-monitor.php',
    'includes/class-pdf-processor.php',
    'includes/class-chatgpt-integration.php',
    'includes/class-notification.php',
    'includes/class-logger.php',
    'includes/class-user-manager.php'
);

foreach ($required_files as $file) {
    $file_path = IDOKLAD_PROCESSOR_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        error_log('iDoklad Plugin: Required file not found: ' . $file_path);
    }
}

// Include enhanced v3 components with error handling (excluding iDoklad integration)
$v3_files = array(
    'includes/class-email-monitor-v3.php',
    'includes/class-notification-v3.php',
    'includes/class-user-manager-v3.php'
);

// Include new iDoklad API v3 integration
$integration_files = array(
    'includes/class-idoklad-api-v3-integration.php',
    'includes/class-idoklad-admin-integration.php'
);

// Include PDF.co AI Parser enhanced components
$pdf_co_files = array(
    'includes/class-pdf-co-ai-parser-enhanced.php',
    'includes/class-pdf-co-admin-debug.php'
);

foreach ($v3_files as $file) {
    $file_path = IDOKLAD_PROCESSOR_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        try {
            require_once $file_path;
        } catch (Exception $e) {
            error_log('iDoklad Plugin: Error loading v3 file ' . $file . ': ' . $e->getMessage());
        } catch (ParseError $e) {
            error_log('iDoklad Plugin: Parse error in v3 file ' . $file . ': ' . $e->getMessage());
        } catch (Error $e) {
            error_log('iDoklad Plugin: Fatal error in v3 file ' . $file . ': ' . $e->getMessage());
        }
    }
}

foreach ($integration_files as $file) {
    $file_path = IDOKLAD_PROCESSOR_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        try {
            require_once $file_path;
        } catch (Exception $e) {
            error_log('iDoklad Plugin: Error loading integration file ' . $file . ': ' . $e->getMessage());
        } catch (ParseError $e) {
            error_log('iDoklad Plugin: Parse error in integration file ' . $file . ': ' . $e->getMessage());
        } catch (Error $e) {
            error_log('iDoklad Plugin: Fatal error in integration file ' . $file . ': ' . $e->getMessage());
        }
    }
}

foreach ($pdf_co_files as $file) {
    $file_path = IDOKLAD_PROCESSOR_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        try {
            require_once $file_path;
        } catch (Exception $e) {
            error_log('iDoklad Plugin: Error loading PDF.co file ' . $file . ': ' . $e->getMessage());
        } catch (ParseError $e) {
            error_log('iDoklad Plugin: Parse error in PDF.co file ' . $file . ': ' . $e->getMessage());
        } catch (Error $e) {
            error_log('iDoklad Plugin: Fatal error in PDF.co file ' . $file . ': ' . $e->getMessage());
        }
    }
}

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
        
        // Initialize core components only if classes exist
        if (class_exists('IDokladProcessor_Database')) {
            new IDokladProcessor_Database();
        }
        
        if (class_exists('IDokladProcessor_Admin')) {
            new IDokladProcessor_Admin();
        }
        
        if (class_exists('IDokladProcessor_EmailMonitor')) {
            new IDokladProcessor_EmailMonitor();
        }
        
        if (class_exists('IDokladProcessor_UserManager')) {
            new IDokladProcessor_UserManager();
        }
        
        // Initialize enhanced v3 components (only if classes exist and are safe to load)
        if (class_exists('IDokladProcessor_EmailMonitorV3') && class_exists('IDokladProcessor_Database')) {
            try {
                new IDokladProcessor_EmailMonitorV3();
            } catch (Exception $e) {
                error_log('iDoklad Plugin: Failed to initialize EmailMonitorV3: ' . $e->getMessage());
            } catch (ParseError $e) {
                error_log('iDoklad Plugin: Parse error in EmailMonitorV3: ' . $e->getMessage());
            } catch (Error $e) {
                error_log('iDoklad Plugin: Fatal error in EmailMonitorV3: ' . $e->getMessage());
            }
        }
        
        if (class_exists('IDokladProcessor_UserManagerV3') && class_exists('IDokladProcessor_Database')) {
            try {
                new IDokladProcessor_UserManagerV3();
            } catch (Exception $e) {
                error_log('iDoklad Plugin: Failed to initialize UserManagerV3: ' . $e->getMessage());
            } catch (ParseError $e) {
                error_log('iDoklad Plugin: Parse error in UserManagerV3: ' . $e->getMessage());
            } catch (Error $e) {
                error_log('iDoklad Plugin: Fatal error in UserManagerV3: ' . $e->getMessage());
            }
        }
        
        // Initialize new iDoklad API v3 integration
        if (class_exists('IDokladProcessor_AdminIntegration')) {
            try {
                new IDokladProcessor_AdminIntegration();
            } catch (Exception $e) {
                error_log('iDoklad Plugin: Failed to initialize AdminIntegration: ' . $e->getMessage());
            } catch (ParseError $e) {
                error_log('iDoklad Plugin: Parse error in AdminIntegration: ' . $e->getMessage());
            } catch (Error $e) {
                error_log('iDoklad Plugin: Fatal error in AdminIntegration: ' . $e->getMessage());
            }
        }
        
        // Initialize PDF.co AI Parser debug interface
        if (class_exists('IDokladProcessor_PDFCoAdminDebug')) {
            try {
                new IDokladProcessor_PDFCoAdminDebug();
            } catch (Exception $e) {
                error_log('iDoklad Plugin: Failed to initialize PDFCoAdminDebug: ' . $e->getMessage());
            } catch (ParseError $e) {
                error_log('iDoklad Plugin: Parse error in PDFCoAdminDebug: ' . $e->getMessage());
            } catch (Error $e) {
                error_log('iDoklad Plugin: Fatal error in PDFCoAdminDebug: ' . $e->getMessage());
            }
        }
        
        // Load text domain for translations
        load_plugin_textdomain('idoklad-invoice-processor', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
        // Create database tables only if database class exists
        if (class_exists('IDokladProcessor_Database')) {
            IDokladProcessor_Database::create_tables();
        }
        
        // Set default options
        $this->set_default_options();
        
        // Set plugin version for future upgrades
        update_option('idoklad_processor_db_version', '1.1.0');
        
        // Schedule email monitoring cron job (legacy)
        if (!wp_next_scheduled('idoklad_check_emails')) {
            wp_schedule_event(time(), 'every_5_minutes', 'idoklad_check_emails');
        }

        // Schedule enhanced email monitoring cron job (v3)
        if (!wp_next_scheduled('idoklad_check_emails_v3')) {
            wp_schedule_event(time(), 'every_5_minutes', 'idoklad_check_emails_v3');
        }
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('idoklad_check_emails');
        wp_clear_scheduled_hook('idoklad_check_emails_v3');
    }
    
    private function set_default_options() {
        $default_options = array(
            'email_host' => '',
            'email_port' => 993,
            'email_username' => '',
            'email_password' => '',
            'email_encryption' => 'ssl',
            'chatgpt_api_key' => '',
            'chatgpt_model' => 'gpt-4o',
            'chatgpt_prompt' => 'Extract invoice data from this PDF. Return JSON with: invoice_number, date, total_amount, supplier_name, supplier_vat_number, items (array with name, quantity, price), currency. Validate data completeness.',
            'notification_email' => get_option('admin_email'),
            'debug_mode' => false,
            'use_native_parser_first' => true,
            'use_pdfco' => true,
            'pdfco_api_key' => '',
            'enable_ocr' => false,
            'use_tesseract' => false,
            'tesseract_path' => 'tesseract',
            'tesseract_lang' => 'ces+eng',
            'use_cloud_ocr' => false,
            'cloud_ocr_service' => 'none',
            'ocr_space_api_key' => '',
            'ocr_space_language' => 'eng',
            'google_vision_api_key' => ''
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
    $interval = 300; // 5 minutes

    $schedules['every_5_minutes'] = array(
        'interval' => $interval,
        'display' => __('Every 5 Minutes', 'idoklad-invoice-processor')
    );

    // Custom identifiers used throughout the admin UX
    $schedules['idoklad_email_interval'] = array(
        'interval' => $interval,
        'display' => __('iDoklad Email Interval', 'idoklad-invoice-processor')
    );

    $schedules['idoklad_queue_interval'] = array(
        'interval' => $interval,
        'display' => __('iDoklad Queue Interval', 'idoklad-invoice-processor')
    );

    return $schedules;
});

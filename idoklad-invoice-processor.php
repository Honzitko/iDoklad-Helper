<?php
/**
 * Plugin Name: iDoklad Invoice Processor
 * Plugin URI: https://your-website.com
 * Description: Automated invoice processing system that receives PDF invoices via email, extracts data using ChatGPT, and creates records in iDoklad using the REST API.
 * Version: 2.0.0
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
    define('IDOKLAD_PROCESSOR_VERSION', '2.0.0');
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
    'includes/class-chatgpt-integration.php',
    'includes/class-invoice-ai-rest.php',
    'includes/class-idoklad-api-v3-integration.php',
    'includes/class-idoklad-admin-integration.php',
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

        if (class_exists('IDokladProcessor_InvoiceAIRest')) {
            try {
                new IDokladProcessor_InvoiceAIRest();
            } catch (Exception $e) {
                error_log('iDoklad Plugin: Failed to initialize InvoiceAIRest: ' . $e->getMessage());
            } catch (Error $e) {
                error_log('iDoklad Plugin: Fatal error in InvoiceAIRest: ' . $e->getMessage());
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
        update_option('idoklad_processor_db_version', IDOKLAD_PROCESSOR_VERSION);

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
            'chatgpt_model' => 'gpt-4o-mini',
            'chatgpt_model_manual' => 0,
            'chatgpt_prompt' => 'Extract invoice data from this PDF. Return JSON with: invoice_number, date, total_amount, supplier_name, supplier_vat_number, items (array with name, quantity, price), currency. Validate data completeness.',
            'client_id' => '',
            'client_secret' => '',
            'notification_email' => get_option('admin_email'),
            'debug_mode' => false
        );

        foreach ($default_options as $key => $value) {
            $option_key = 'idoklad_' . $key;
            if (get_option($option_key, null) === null) {
                add_option($option_key, $value);
            }
        }
    }

    /**
     * Check for database upgrades
     */
    private function check_database_upgrades() {
        $current_version = get_option('idoklad_processor_db_version', '1.0.0');

        if (version_compare($current_version, IDOKLAD_PROCESSOR_VERSION, '<')) {
            update_option('idoklad_processor_db_version', IDOKLAD_PROCESSOR_VERSION);
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

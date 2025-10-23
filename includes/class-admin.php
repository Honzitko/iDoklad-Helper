<?php
/**
 * Admin interface class - Updated for per-user iDoklad credentials
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_idoklad_test_email_connection', array($this, 'test_email_connection'));
        add_action('wp_ajax_idoklad_test_chatgpt_connection', array($this, 'test_chatgpt_connection'));
        // TODO: iDoklad connection test removed - to be rebuilt
        // add_action('wp_ajax_idoklad_test_user_idoklad_connection', array($this, 'test_user_idoklad_connection'));
        add_action('wp_ajax_idoklad_process_queue_manually', array($this, 'process_queue_manually'));
        add_action('wp_ajax_idoklad_get_log_details', array($this, 'get_log_details'));
        add_action('wp_ajax_idoklad_export_logs', array($this, 'export_logs'));
        add_action('wp_ajax_idoklad_get_queue_status', array($this, 'get_queue_status'));
        add_action('wp_ajax_idoklad_get_user_data', array($this, 'get_user_data'));
        add_action('wp_ajax_idoklad_update_user', array($this, 'update_user'));
        add_action('wp_ajax_idoklad_get_chatgpt_models', array($this, 'get_chatgpt_models'));
        add_action('wp_ajax_idoklad_test_zapier_webhook', array($this, 'test_zapier_webhook'));
        add_action('wp_ajax_idoklad_test_ocr_space', array($this, 'test_ocr_space_connection'));
        add_action('wp_ajax_idoklad_get_queue_details', array($this, 'get_queue_details'));
        add_action('wp_ajax_idoklad_refresh_queue', array($this, 'refresh_queue'));
        add_action('wp_ajax_idoklad_reset_stuck_items', array($this, 'reset_stuck_items'));
        
        // Diagnostics AJAX handlers
        add_action('wp_ajax_idoklad_test_pdf_parsing', array($this, 'test_pdf_parsing'));
        add_action('wp_ajax_idoklad_test_ocr_on_pdf', array($this, 'test_ocr_on_pdf'));
        add_action('wp_ajax_idoklad_test_zapier_payload', array($this, 'test_zapier_payload'));
        // TODO: iDoklad payload test removed - to be rebuilt
        // add_action('wp_ajax_idoklad_test_idoklad_payload', array($this, 'test_idoklad_payload'));
        add_action('wp_ajax_idoklad_get_parsing_methods', array($this, 'get_parsing_methods'));
        add_action('wp_ajax_idoklad_test_pdfco', array($this, 'test_pdfco_connection'));
        add_action('wp_ajax_idoklad_test_ai_parser', array($this, 'test_ai_parser'));
        
        // Dashboard AJAX handlers
        add_action('wp_ajax_idoklad_force_email_check', array($this, 'force_email_check'));
        add_action('wp_ajax_idoklad_cancel_queue_item', array($this, 'cancel_queue_item'));
        
        // Email processing AJAX handlers
        add_action('wp_ajax_idoklad_grab_emails_manually', array($this, 'grab_emails_manually'));
        add_action('wp_ajax_idoklad_process_emails_manually', array($this, 'process_emails_manually'));
        add_action('wp_ajax_idoklad_start_automatic_processing', array($this, 'start_automatic_processing'));
        add_action('wp_ajax_idoklad_stop_automatic_processing', array($this, 'stop_automatic_processing'));
        add_action('wp_ajax_idoklad_get_processing_status', array($this, 'get_processing_status'));
        
        // Queue reprocessing AJAX handlers
        add_action('wp_ajax_idoklad_reprocess_selected', array($this, 'reprocess_selected_items'));
        add_action('wp_ajax_idoklad_reprocess_single', array($this, 'reprocess_single_item'));
        add_action('wp_ajax_idoklad_reset_selected', array($this, 'reset_selected_items'));
        
        // Log management AJAX handlers
        add_action('wp_ajax_idoklad_export_log', array($this, 'export_single_log'));
        add_action('wp_ajax_idoklad_export_selected_logs', array($this, 'export_selected_logs'));
        add_action('wp_ajax_idoklad_delete_log', array($this, 'delete_single_log'));
        add_action('wp_ajax_idoklad_delete_selected_logs', array($this, 'delete_selected_logs'));
        
        // Database Management AJAX handlers
        add_action('wp_ajax_idoklad_get_db_stats', array($this, 'get_database_stats'));
        add_action('wp_ajax_idoklad_cleanup_old_logs', array($this, 'cleanup_old_logs'));
        add_action('wp_ajax_idoklad_cleanup_old_queue', array($this, 'cleanup_old_queue'));
        add_action('wp_ajax_idoklad_cleanup_attachments', array($this, 'cleanup_old_attachments'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('iDoklad Invoice Processor', 'idoklad-invoice-processor'),
            __('iDoklad Processor', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor',
            array($this, 'dashboard_page'),
            'dashicons-media-spreadsheet',
            30
        );
        
        add_submenu_page(
            'idoklad-processor',
            __('Dashboard', 'idoklad-invoice-processor'),
            __('Dashboard', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'idoklad-processor',
            __('Settings', 'idoklad-invoice-processor'),
            __('Settings', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor-settings',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'idoklad-processor',
            __('Authorized Users', 'idoklad-invoice-processor'),
            __('Authorized Users', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor-users',
            array($this, 'users_page')
        );
        
        add_submenu_page(
            'idoklad-processor',
            __('Processing Queue', 'idoklad-invoice-processor'),
            __('Processing Queue', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor-queue',
            array($this, 'queue_page')
        );
        
        add_submenu_page(
            'idoklad-processor',
            __('Processing Logs', 'idoklad-invoice-processor'),
            __('Processing Logs', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor-logs',
            array($this, 'logs_page')
        );
        
        add_submenu_page(
            'idoklad-processor',
            __('Diagnostics & Testing', 'idoklad-invoice-processor'),
            __('Diagnostics & Testing', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor-diagnostics',
            array($this, 'diagnostics_page')
        );
        
        add_submenu_page(
            'idoklad-processor',
            __('Database Management', 'idoklad-invoice-processor'),
            __('Database Management', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor-database',
            array($this, 'database_management_page')
        );
        
        add_submenu_page(
            'idoklad-processor',
            __('Email Processing', 'idoklad-invoice-processor'),
            __('Email Processing', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor-email-processing',
            array($this, 'email_processing_page')
        );
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        include IDOKLAD_PROCESSOR_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    /**
     * Email processing page
     */
    public function email_processing_page() {
        include IDOKLAD_PROCESSOR_PLUGIN_DIR . 'templates/admin-email-processing.php';
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Email settings
        register_setting('idoklad_email_settings', 'idoklad_email_host');
        register_setting('idoklad_email_settings', 'idoklad_email_port');
        register_setting('idoklad_email_settings', 'idoklad_email_username');
        register_setting('idoklad_email_settings', 'idoklad_email_password');
        register_setting('idoklad_email_settings', 'idoklad_email_encryption');
        
        // Zapier settings
        register_setting('idoklad_zapier_settings', 'idoklad_zapier_webhook_url');
        
        // PDF.co settings (PRIMARY)
        register_setting('idoklad_pdfco_settings', 'idoklad_use_pdfco');
        register_setting('idoklad_pdfco_settings', 'idoklad_pdfco_api_key');
        register_setting('idoklad_pdfco_settings', 'idoklad_use_ai_parser');
        
        // PDF processing settings (FALLBACK)
        register_setting('idoklad_pdf_settings', 'idoklad_use_native_parser_first');
        
        // OCR settings (LEGACY - only used if PDF.co disabled)
        register_setting('idoklad_ocr_settings', 'idoklad_enable_ocr');
        register_setting('idoklad_ocr_settings', 'idoklad_use_tesseract');
        register_setting('idoklad_ocr_settings', 'idoklad_tesseract_path');
        register_setting('idoklad_ocr_settings', 'idoklad_tesseract_lang');
        register_setting('idoklad_ocr_settings', 'idoklad_use_cloud_ocr');
        register_setting('idoklad_ocr_settings', 'idoklad_cloud_ocr_service');
        register_setting('idoklad_ocr_settings', 'idoklad_ocr_space_api_key');
        register_setting('idoklad_ocr_settings', 'idoklad_ocr_space_language');
        register_setting('idoklad_ocr_settings', 'idoklad_google_vision_api_key');
        
        // General settings
        register_setting('idoklad_general_settings', 'idoklad_notification_email');
        register_setting('idoklad_general_settings', 'idoklad_debug_mode');
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'idoklad-processor') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'idoklad-admin-js',
            IDOKLAD_PROCESSOR_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            IDOKLAD_PROCESSOR_VERSION,
            true
        );
        
        // Enqueue email processing script for email processing page
        if (strpos($hook, 'email-processing') !== false) {
            wp_enqueue_script(
                'idoklad-email-processing-js',
                IDOKLAD_PROCESSOR_PLUGIN_URL . 'assets/email-processing.js',
                array('jquery'),
                IDOKLAD_PROCESSOR_VERSION,
                true
            );
        }
        
        // Enqueue queue reprocessing script for queue page
        if (strpos($hook, 'queue') !== false) {
            wp_enqueue_script(
                'idoklad-queue-reprocessing-js',
                IDOKLAD_PROCESSOR_PLUGIN_URL . 'assets/queue-reprocessing.js',
                array('jquery'),
                IDOKLAD_PROCESSOR_VERSION,
                true
            );
        }
        
        // Enqueue log management script for logs page
        if (strpos($hook, 'logs') !== false) {
            wp_enqueue_script(
                'idoklad-log-management-js',
                IDOKLAD_PROCESSOR_PLUGIN_URL . 'assets/log-management.js',
                array('jquery'),
                IDOKLAD_PROCESSOR_VERSION,
                true
            );
        }
        
        wp_localize_script('idoklad-admin-js', 'idoklad_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('idoklad_admin_nonce')
        ));
        
        // Localize script for queue reprocessing
        if (strpos($hook, 'queue') !== false) {
            wp_localize_script('idoklad-queue-reprocessing-js', 'idoklad_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('idoklad_admin_nonce')
            ));
        }
        
        // Localize script for log management
        if (strpos($hook, 'logs') !== false) {
            wp_localize_script('idoklad-log-management-js', 'idoklad_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('idoklad_admin_nonce')
            ));
        }
        
        // Localize email processing script
        if (strpos($hook, 'email-processing') !== false) {
            wp_localize_script('idoklad-email-processing-js', 'idoklad_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('idoklad_admin_nonce')
            ));
        }
        
        wp_enqueue_style(
            'idoklad-admin-css',
            IDOKLAD_PROCESSOR_PLUGIN_URL . 'assets/admin.css',
            array(),
            IDOKLAD_PROCESSOR_VERSION
        );
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        include IDOKLAD_PROCESSOR_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Users management page
     */
    public function users_page() {
        if (isset($_POST['add_user'])) {
            $this->add_authorized_user();
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['user_id'])) {
            $this->delete_authorized_user($_GET['user_id']);
        }
        
        include IDOKLAD_PROCESSOR_PLUGIN_DIR . 'templates/admin-users.php';
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $logs = IDokladProcessor_Database::get_logs($limit, $offset);
        $total_logs = $this->get_total_logs();
        $total_pages = ceil($total_logs / $limit);
        
        include IDOKLAD_PROCESSOR_PLUGIN_DIR . 'templates/admin-logs.php';
    }
    
    /**
     * Queue viewer page
     */
    public function queue_page() {
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
        $queue_items = IDokladProcessor_Database::get_queue_items($status_filter, 50);
        
        include IDOKLAD_PROCESSOR_PLUGIN_DIR . 'templates/admin-queue.php';
    }
    
    /**
     * Diagnostics & Testing page
     */
    public function diagnostics_page() {
        include IDOKLAD_PROCESSOR_PLUGIN_DIR . 'templates/admin-diagnostics.php';
    }
    
    /**
     * Database Management page
     */
    public function database_management_page() {
        include IDOKLAD_PROCESSOR_PLUGIN_DIR . 'templates/admin-database.php';
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'idoklad_settings_nonce')) {
            wp_die(__('Security check failed', 'idoklad-invoice-processor'));
        }
        
        // Save PDF.co settings (PRIMARY)
        if (isset($_POST['use_pdfco'])) {
            update_option('idoklad_use_pdfco', 1);
        } else {
            update_option('idoklad_use_pdfco', 0);
        }
        
        if (isset($_POST['pdfco_api_key'])) {
            update_option('idoklad_pdfco_api_key', sanitize_text_field($_POST['pdfco_api_key']));
        }
        
        if (isset($_POST['use_ai_parser'])) {
            update_option('idoklad_use_ai_parser', 1);
        } else {
            update_option('idoklad_use_ai_parser', 0);
        }
        
        // Save email settings
        if (isset($_POST['email_host'])) {
        update_option('idoklad_email_host', sanitize_text_field($_POST['email_host']));
        }
        if (isset($_POST['email_port'])) {
        update_option('idoklad_email_port', intval($_POST['email_port']));
        }
        if (isset($_POST['email_username'])) {
        update_option('idoklad_email_username', sanitize_text_field($_POST['email_username']));
        }
        if (isset($_POST['email_password'])) {
        update_option('idoklad_email_password', sanitize_text_field($_POST['email_password']));
        }
        if (isset($_POST['email_encryption'])) {
        update_option('idoklad_email_encryption', sanitize_text_field($_POST['email_encryption']));
        }
        
        // Save Zapier settings
        if (isset($_POST['zapier_webhook_url'])) {
            update_option('idoklad_zapier_webhook_url', esc_url_raw($_POST['zapier_webhook_url']));
        }
        
        // Save PDF processing settings
        if (isset($_POST['use_native_parser_first'])) {
            update_option('idoklad_use_native_parser_first', 1);
        } else {
            update_option('idoklad_use_native_parser_first', 0);
        }
        
        // Save OCR settings
        if (isset($_POST['enable_ocr'])) {
            update_option('idoklad_enable_ocr', 1);
        } else {
            update_option('idoklad_enable_ocr', 0);
        }
        
        if (isset($_POST['use_tesseract'])) {
            update_option('idoklad_use_tesseract', 1);
        } else {
            update_option('idoklad_use_tesseract', 0);
        }
        
        if (isset($_POST['tesseract_path'])) {
            update_option('idoklad_tesseract_path', sanitize_text_field($_POST['tesseract_path']));
        }
        
        if (isset($_POST['tesseract_lang'])) {
            update_option('idoklad_tesseract_lang', sanitize_text_field($_POST['tesseract_lang']));
        }
        
        if (isset($_POST['use_cloud_ocr'])) {
            update_option('idoklad_use_cloud_ocr', 1);
        } else {
            update_option('idoklad_use_cloud_ocr', 0);
        }
        
        if (isset($_POST['cloud_ocr_service'])) {
            update_option('idoklad_cloud_ocr_service', sanitize_text_field($_POST['cloud_ocr_service']));
        }
        
        if (isset($_POST['ocr_space_api_key'])) {
            update_option('idoklad_ocr_space_api_key', sanitize_text_field($_POST['ocr_space_api_key']));
        }
        
        if (isset($_POST['ocr_space_language'])) {
            update_option('idoklad_ocr_space_language', sanitize_text_field($_POST['ocr_space_language']));
        }
        
        if (isset($_POST['google_vision_api_key'])) {
            update_option('idoklad_google_vision_api_key', sanitize_text_field($_POST['google_vision_api_key']));
        }
        
        // Save general settings
        if (isset($_POST['notification_email'])) {
        update_option('idoklad_notification_email', sanitize_email($_POST['notification_email']));
        }
        
        if (isset($_POST['debug_mode'])) {
            update_option('idoklad_debug_mode', 1);
        } else {
            update_option('idoklad_debug_mode', 0);
        }
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'idoklad-invoice-processor') . '</p></div>';
    }
    
    /**
     * Add authorized user with iDoklad credentials
     */
    private function add_authorized_user() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'idoklad_users_nonce')) {
            wp_die(__('Security check failed', 'idoklad-invoice-processor'));
        }
        
        $email = sanitize_email($_POST['user_email']);
        $name = sanitize_text_field($_POST['user_name']);
        $idoklad_client_id = sanitize_text_field($_POST['idoklad_client_id']);
        $idoklad_client_secret = sanitize_text_field($_POST['idoklad_client_secret']);
        $idoklad_api_url = esc_url_raw($_POST['idoklad_api_url']);
        $idoklad_user_id = sanitize_text_field($_POST['idoklad_user_id']);
        
        if (!is_email($email)) {
            echo '<div class="notice notice-error"><p>' . __('Invalid email address', 'idoklad-invoice-processor') . '</p></div>';
            return;
        }
        
        $result = IDokladProcessor_Database::add_authorized_user(
            $email, 
            $name, 
            $idoklad_client_id, 
            $idoklad_client_secret, 
            $idoklad_api_url, 
            $idoklad_user_id
        );
        
        if ($result) {
            echo '<div class="notice notice-success"><p>' . __('User added successfully!', 'idoklad-invoice-processor') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Failed to add user. Email might already exist.', 'idoklad-invoice-processor') . '</p></div>';
        }
    }
    
    /**
     * Delete authorized user
     */
    private function delete_authorized_user($user_id) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'idoklad_delete_user_nonce')) {
            wp_die(__('Security check failed', 'idoklad-invoice-processor'));
        }
        
        $result = IDokladProcessor_Database::delete_authorized_user(intval($user_id));
        
        if ($result) {
            echo '<div class="notice notice-success"><p>' . __('User deleted successfully!', 'idoklad-invoice-processor') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Failed to delete user.', 'idoklad-invoice-processor') . '</p></div>';
        }
    }
    
    /**
     * Test email connection
     */
    public function test_email_connection() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $host = get_option('idoklad_email_host');
        $port = get_option('idoklad_email_port', 993);
        $username = get_option('idoklad_email_username');
        $password = get_option('idoklad_email_password');
        $encryption = get_option('idoklad_email_encryption', 'ssl');
        
        if (empty($host) || empty($username) || empty($password)) {
            wp_send_json_error(__('Email settings are incomplete', 'idoklad-invoice-processor'));
        }
        
        try {
            $connection_string = '{' . $host . ':' . $port . '/imap/' . $encryption . '}INBOX';
            $connection = imap_open($connection_string, $username, $password);
            
            if ($connection) {
                imap_close($connection);
                wp_send_json_success(__('Email connection successful!', 'idoklad-invoice-processor'));
            } else {
                wp_send_json_error(__('Email connection failed: ' . imap_last_error(), 'idoklad-invoice-processor'));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Email connection failed: ' . $e->getMessage(), 'idoklad-invoice-processor'));
        }
    }
    
    /**
     * Test user-specific iDoklad connection
     */
    public function test_user_idoklad_connection() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error(__('User ID is required', 'idoklad-invoice-processor'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_users';
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $user_id));
        
        if (!$user) {
            wp_send_json_error(__('User not found', 'idoklad-invoice-processor'));
        }
        
        if (empty($user->idoklad_client_id) || empty($user->idoklad_client_secret)) {
            wp_send_json_error(__('iDoklad credentials are not configured for this user', 'idoklad-invoice-processor'));
        }
        
        // Test connection using OAuth
        try {
            // TODO: iDoklad API integration removed - to be rebuilt
            // $idoklad_api = new IDokladProcessor_IDokladAPI($user);
            $result = $idoklad_api->test_connection();
            
            if (is_array($result) && isset($result['success'])) {
                if ($result['success']) {
                    wp_send_json_success($result['message']);
                } else {
                    wp_send_json_error($result['message']);
                }
            } else {
                // Backward compatibility for boolean return
                if ($result) {
                    wp_send_json_success(__('OAuth connection successful', 'idoklad-invoice-processor'));
                } else {
                    wp_send_json_error(__('OAuth connection failed', 'idoklad-invoice-processor'));
                }
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Test ChatGPT connection
     */
    public function test_chatgpt_connection() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $api_key = get_option('idoklad_chatgpt_api_key');
        
        if (empty($api_key)) {
            wp_send_json_error(__('ChatGPT API key is not set', 'idoklad-invoice-processor'));
        }
        
        $chatgpt = new IDokladProcessor_ChatGPTIntegration();
        $result = $chatgpt->test_connection();
        
        if (is_array($result) && isset($result['success'])) {
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } else {
            // Backward compatibility for boolean return
            if ($result) {
                wp_send_json_success(__('ChatGPT connection successful', 'idoklad-invoice-processor'));
            } else {
                wp_send_json_error(__('ChatGPT connection failed', 'idoklad-invoice-processor'));
            }
        }
    }
    
    /**
     * Process queue manually
     */
    public function process_queue_manually() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $email_monitor = new IDokladProcessor_EmailMonitor();
        $result = $email_monitor->process_pending_emails();
        
        wp_send_json_success(sprintf(__('Processed %d emails', 'idoklad-invoice-processor'), $result));
    }
    
    /**
     * Get log details for modal
     */
    public function get_log_details() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;

        if (!$log_id) {
            wp_send_json_error(__('Log ID is required', 'idoklad-invoice-processor'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_logs';
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $log_id));
        
        if (!$log) {
            wp_send_json_error(__('Log entry not found', 'idoklad-invoice-processor'));
        }
        
        $html = '<div class="log-detail-section">';
        $html .= '<h4>Basic Information</h4>';
        $html .= '<p><strong>Email From:</strong> ' . esc_html($log->email_from) . '</p>';
        $html .= '<p><strong>Subject:</strong> ' . esc_html($log->email_subject ?: 'N/A') . '</p>';
        $html .= '<p><strong>Attachment:</strong> ' . esc_html($log->attachment_name ?: 'N/A') . '</p>';
        $html .= '<p><strong>Status:</strong> <span class="status-' . $log->processing_status . '">' . ucfirst($log->processing_status) . '</span></p>';
        $html .= '<p><strong>Created:</strong> ' . esc_html($log->created_at) . '</p>';
        if ($log->processed_at) {
            $html .= '<p><strong>Processed:</strong> ' . esc_html($log->processed_at) . '</p>';
        }
        $html .= '</div>';
        
        if ($log->extracted_data) {
            $html .= '<div class="log-detail-section">';
            $html .= '<h4>Extracted Data</h4>';
            $html .= '<div class="json-data">' . esc_html($log->extracted_data) . '</div>';
            $html .= '</div>';
        }
        
        if ($log->idoklad_response) {
            $html .= '<div class="log-detail-section">';
            $html .= '<h4>iDoklad Response</h4>';
            $html .= '<div class="json-data">' . esc_html($log->idoklad_response) . '</div>';
            $html .= '</div>';
        }
        
        if ($log->error_message) {
            $html .= '<div class="log-detail-section">';
            $html .= '<h4>Error Message</h4>';
            $html .= '<p style="color: red;">' . esc_html($log->error_message) . '</p>';
            $html .= '</div>';
        }
        
        wp_send_json_success($html);
    }
    
    /**
     * Export logs to CSV
     */
    public function export_logs() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $logger = IDokladProcessor_Logger::get_instance();
        $csv_data = $logger->export_logs_to_csv();
        
        $filename = 'idoklad-logs-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get queue status
     */
    public function get_queue_status() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        global $wpdb;
        $queue_table = $wpdb->prefix . 'idoklad_queue';
        
        $status = array(
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'pending'"),
            'processing' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'processing'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'failed'")
        );
        
        wp_send_json_success($status);
    }
    
    /**
     * Get total logs count
     */
    private function get_total_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_logs';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
    
    /**
     * Get user data for editing
     */
    public function get_user_data() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error(__('User ID is required', 'idoklad-invoice-processor'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_users';
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $user_id));
        
        if (!$user) {
            wp_send_json_error(__('User not found', 'idoklad-invoice-processor'));
        }
        
        // Remove sensitive data
        unset($user->idoklad_client_secret);
        
        wp_send_json_success($user);
    }
    
    /**
     * Update user data
     */
    public function update_user() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $idoklad_client_id = isset($_POST['idoklad_client_id']) ? sanitize_text_field($_POST['idoklad_client_id']) : '';
        $idoklad_client_secret = isset($_POST['idoklad_client_secret']) ? sanitize_text_field($_POST['idoklad_client_secret']) : '';
        $idoklad_api_url = isset($_POST['idoklad_api_url']) ? esc_url_raw($_POST['idoklad_api_url']) : '';
        $idoklad_user_id = isset($_POST['idoklad_user_id']) ? sanitize_text_field($_POST['idoklad_user_id']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!$user_id) {
            wp_send_json_error(__('User ID is required', 'idoklad-invoice-processor'));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(__('Invalid email address', 'idoklad-invoice-processor'));
        }

        if ($name === '') {
            wp_send_json_error(__('User name is required', 'idoklad-invoice-processor'));
        }

        // Check if email is being changed and if new email already exists
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_users';
        $current_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $user_id));
        
        if (!$current_user) {
            wp_send_json_error(__('User not found', 'idoklad-invoice-processor'));
        }
        
        if ($email !== $current_user->email) {
            $existing_user = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE email = %s AND id != %d", $email, $user_id));
            if ($existing_user) {
                wp_send_json_error(__('Email already exists', 'idoklad-invoice-processor'));
            }
        }
        
        $update_data = array(
            'email' => $email,
            'name' => $name,
            'idoklad_client_id' => $idoklad_client_id,
            'idoklad_api_url' => $idoklad_api_url,
            'idoklad_user_id' => $idoklad_user_id,
            'is_active' => $is_active
        );
        
        // Only update client secret if provided
        if (!empty($idoklad_client_secret)) {
            $update_data['idoklad_client_secret'] = $idoklad_client_secret;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $user_id),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(__('User updated successfully', 'idoklad-invoice-processor'));
        } else {
            wp_send_json_error(__('Failed to update user', 'idoklad-invoice-processor'));
        }
    }
    
    /**
     * Get available ChatGPT models from API
     */
    public function get_chatgpt_models() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $chatgpt = new IDokladProcessor_ChatGPTIntegration();
        $api_models = $chatgpt->get_api_available_models();
        
        if (!empty($api_models)) {
            wp_send_json_success($api_models);
        } else {
            wp_send_json_error(__('Could not fetch available models from OpenAI API', 'idoklad-invoice-processor'));
        }
    }
    
    /**
     * Test Zapier webhook connection
     */
    public function test_zapier_webhook() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        // Zapier integration is no longer used
        wp_send_json_error('Zapier testing is deprecated. The system now uses direct PDF text parsing with PDF.co.');
    }
    
    /**
     * Test OCR.space API connection
     */
    public function test_ocr_space_connection() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        // Check API key
        $api_key = get_option('idoklad_ocr_space_api_key');
        if (empty($api_key)) {
            wp_send_json_error(__('OCR.space API key is not configured', 'idoklad-invoice-processor'));
            return;
        }
        
        // Check cloud OCR is enabled
        $use_cloud_ocr = get_option('idoklad_use_cloud_ocr');
        if (!$use_cloud_ocr) {
            wp_send_json_error(__('Cloud OCR is not enabled. Please check "Use Cloud OCR" checkbox and save settings.', 'idoklad-invoice-processor'));
            return;
        }
        
        // Check OCR.space is selected
        $cloud_service = get_option('idoklad_cloud_ocr_service');
        if ($cloud_service !== 'ocr_space') {
            wp_send_json_error(__('OCR.space is not selected as cloud service. Please select "OCR.space" from dropdown and save settings.', 'idoklad-invoice-processor'));
            return;
        }
        
        // Create a simple test image with text
        $test_image = $this->create_test_image();
        
        if (!$test_image) {
            wp_send_json_error(__('Could not create test image. GD library may not be available.', 'idoklad-invoice-processor'));
            return;
        }
        
        // OCR.space is no longer used - PDF.co handles OCR
        wp_send_json_error('OCR.space testing is deprecated. PDF.co handles all OCR automatically.');
        return;
        
        try {
            // Test OCR.space API directly
            $url = 'https://api.ocr.space/parse/image';
            
            $image_data = file_get_contents($test_image);
            
            // Use simple POST method for testing
            // No language parameter = auto-detect (works best with Engine 2)
            $post_data = array(
                'base64Image' => 'data:image/png;base64,' . base64_encode($image_data),
                'OCREngine' => '2'
            );
            
            $args = array(
                'headers' => array(
                    'apikey' => $api_key,
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ),
                'body' => $post_data,
                'timeout' => 30,
                'method' => 'POST'
            );
            
            $response = wp_remote_request($url, $args);
            
            // Clean up test image
            @unlink($test_image);
            
            // Check for errors
            if (is_wp_error($response)) {
                wp_send_json_error(__('Network error: ', 'idoklad-invoice-processor') . $response->get_error_message());
                return;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                wp_send_json_error(__('HTTP error ', 'idoklad-invoice-processor') . $response_code . ': ' . substr($response_body, 0, 200));
                return;
            }
            
            $data = json_decode($response_body, true);
            
            if (!$data) {
                wp_send_json_error(__('Invalid JSON response from OCR.space', 'idoklad-invoice-processor'));
                return;
            }
            
            // Check for API errors
            if (isset($data['IsErroredOnProcessing']) && $data['IsErroredOnProcessing'] === true) {
                $error_msg = isset($data['ErrorMessage'][0]) ? $data['ErrorMessage'][0] : 'Unknown error';
                wp_send_json_error(__('OCR.space API error: ', 'idoklad-invoice-processor') . $error_msg);
                return;
            }
            
            // Check if text was extracted
            if (isset($data['ParsedResults'][0]['ParsedText'])) {
                $text = $data['ParsedResults'][0]['ParsedText'];
                
                if (!empty($text)) {
                    wp_send_json_success(__('✓ OCR.space connection successful! API key is valid. Extracted text: ', 'idoklad-invoice-processor') . '"' . substr($text, 0, 50) . '"');
                } else {
                    wp_send_json_success(__('✓ OCR.space API key is valid, but no text was extracted from test image. This is okay - it will work with real invoices.', 'idoklad-invoice-processor'));
                }
            } else {
                // API worked but no parsed text - still a success
                wp_send_json_success(__('✓ OCR.space API key is valid and connection successful!', 'idoklad-invoice-processor'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(__('Test failed: ', 'idoklad-invoice-processor') . $e->getMessage());
        }
    }
    
    /**
     * Create a test image for OCR testing
     */
    private function create_test_image() {
        // Check if GD library is available
        if (!function_exists('imagecreate')) {
            return false;
        }
        
        // Create a simple image with text
        $width = 400;
        $height = 100;
        $image = imagecreate($width, $height);
        
        if (!$image) {
            return false;
        }
        
        // Colors
        $background = imagecolorallocate($image, 255, 255, 255); // White
        $text_color = imagecolorallocate($image, 0, 0, 0); // Black
        
        // Add text
        $text = 'OCR TEST INVOICE 12345';
        imagestring($image, 5, 50, 40, $text, $text_color);
        
        // Save to temp file
        $temp_file = sys_get_temp_dir() . '/ocr_test_' . uniqid() . '.png';
        imagepng($image, $temp_file);
        imagedestroy($image);
        
        return $temp_file;
    }
    
    /**
     * Get queue item details (AJAX)
     */
    public function get_queue_details() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $queue_id = isset($_POST['queue_id']) ? intval($_POST['queue_id']) : 0;

        if (!$queue_id) {
            wp_send_json_error(__('Queue ID is required', 'idoklad-invoice-processor'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_queue';
        $queue_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $queue_id));
        
        if (!$queue_item) {
            wp_send_json_error(__('Queue item not found', 'idoklad-invoice-processor'));
        }
        
        // Parse processing details
        $processing_details = !empty($queue_item->processing_details) 
            ? json_decode($queue_item->processing_details, true) 
            : array();
        
        // Build HTML response
        $html = '<div class="queue-detail-section">';
        $html .= '<h4>' . __('Basic Information', 'idoklad-invoice-processor') . '</h4>';
        $html .= '<table class="widefat">';
        $html .= '<tr><th>' . __('Email From:', 'idoklad-invoice-processor') . '</th><td>' . esc_html($queue_item->email_from) . '</td></tr>';
        $html .= '<tr><th>' . __('Subject:', 'idoklad-invoice-processor') . '</th><td>' . esc_html($queue_item->email_subject ?: 'N/A') . '</td></tr>';
        $html .= '<tr><th>' . __('Attachment:', 'idoklad-invoice-processor') . '</th><td>' . esc_html(basename($queue_item->attachment_path)) . '</td></tr>';
        $html .= '<tr><th>' . __('Status:', 'idoklad-invoice-processor') . '</th><td><span class="status-badge status-' . esc_attr($queue_item->status) . '">' . ucfirst($queue_item->status) . '</span></td></tr>';
        $html .= '<tr><th>' . __('Current Step:', 'idoklad-invoice-processor') . '</th><td>' . esc_html($queue_item->current_step ?: 'N/A') . '</td></tr>';
        $html .= '<tr><th>' . __('Attempts:', 'idoklad-invoice-processor') . '</th><td>' . $queue_item->attempts . ' / ' . $queue_item->max_attempts . '</td></tr>';
        $html .= '<tr><th>' . __('Created:', 'idoklad-invoice-processor') . '</th><td>' . esc_html($queue_item->created_at) . '</td></tr>';
        if ($queue_item->processed_at) {
            $html .= '<tr><th>' . __('Processed:', 'idoklad-invoice-processor') . '</th><td>' . esc_html($queue_item->processed_at) . '</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
        
        if (!empty($processing_details)) {
            $html .= '<div class="queue-detail-section">';
            $html .= '<h4>' . __('Processing Steps', 'idoklad-invoice-processor') . '</h4>';
            $html .= '<div class="processing-timeline">';
            
            foreach ($processing_details as $index => $step) {
                $is_error = strpos($step['step'], 'ERROR') === 0;
                $step_class = $is_error ? 'timeline-step-error' : 'timeline-step-success';
                
                $html .= '<div class="timeline-step ' . $step_class . '">';
                $html .= '<div class="timeline-marker">' . ($index + 1) . '</div>';
                $html .= '<div class="timeline-content">';
                $html .= '<strong>' . esc_html($step['step']) . '</strong>';
                $html .= '<div class="timeline-timestamp">' . esc_html($step['timestamp']) . '</div>';
                
                if (!empty($step['data'])) {
                    $html .= '<div class="timeline-data"><pre>' . esc_html(print_r($step['data'], true)) . '</pre></div>';
                }
                
                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
        
        wp_send_json_success($html);
    }
    
    /**
     * Refresh queue status (AJAX)
     */
    public function refresh_queue() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
        $queue_items = IDokladProcessor_Database::get_queue_items($status_filter, 50);
        
        ob_start();
        foreach ($queue_items as $item) {
            include IDOKLAD_PROCESSOR_PLUGIN_DIR . 'templates/partials/queue-row.php';
        }
        $html = ob_get_clean();
        
        wp_send_json_success($html);
    }
    
    /**
     * Reset items stuck in processing (AJAX)
     */
    public function reset_stuck_items() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $count = IDokladProcessor_Database::reset_stuck_items();
        
        if ($count > 0) {
            wp_send_json_success(sprintf(__('Reset %d stuck items back to pending', 'idoklad-invoice-processor'), $count));
        } else {
            wp_send_json_success(__('No stuck items found', 'idoklad-invoice-processor'));
        }
    }
    
    /**
     * Test PDF parsing (AJAX)
     */
    public function test_pdf_parsing() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        if (empty($_FILES['pdf_file'])) {
            wp_send_json_error(__('No PDF file uploaded', 'idoklad-invoice-processor'));
        }
        
        $file = $_FILES['pdf_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload error', 'idoklad-invoice-processor'));
        }
        
        if ($file['type'] !== 'application/pdf') {
            wp_send_json_error(__('File must be a PDF', 'idoklad-invoice-processor'));
        }
        
        $temp_path = $file['tmp_name'];
        
        try {
            $pdf_processor = new IDokladProcessor_PDFProcessor();
            
            // Get parsing methods info
            $methods = $pdf_processor->test_parsing_methods();
            
            // Try to extract text
            $start_time = microtime(true);
            $text = $pdf_processor->extract_text($temp_path);
            $end_time = microtime(true);
            $parse_time = round(($end_time - $start_time) * 1000, 2);
            
            // Get metadata
            $metadata = $pdf_processor->get_metadata($temp_path);
            $page_count = $pdf_processor->get_page_count($temp_path);
            
            wp_send_json_success(array(
                'text' => $text,
                'text_length' => strlen($text),
                'preview' => substr($text, 0, 500),
                'parse_time_ms' => $parse_time,
                'metadata' => $metadata,
                'page_count' => $page_count,
                'file_size' => $file['size'],
                'methods' => $methods
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(__('PDF parsing failed: ', 'idoklad-invoice-processor') . $e->getMessage());
        }
    }
    
    /**
     * Test OCR on PDF (AJAX)
     */
    public function test_ocr_on_pdf() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        if (empty($_FILES['pdf_file'])) {
            wp_send_json_error(__('No PDF file uploaded', 'idoklad-invoice-processor'));
        }
        
        $file = $_FILES['pdf_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload error', 'idoklad-invoice-processor'));
        }
        
        $temp_path = $file['tmp_name'];
        
        // OCR testing is deprecated - PDF.co handles OCR automatically
        wp_send_json_error('OCR testing is no longer available. PDF.co handles all OCR automatically.');
        return;
        
        try {
            // OCR testing deprecated
            
            // Enable debug for this test
            $original_debug = get_option('idoklad_debug_mode');
            update_option('idoklad_debug_mode', true);
            
            // Clear previous response
            delete_option('idoklad_last_ocr_response');
            
            // Get method availability first
            $methods = $ocr_processor->test_ocr_methods();
            
            $start_time = microtime(true);
            
            // Use special test method that captures API response
            try {
                $text = $ocr_processor->test_ocr_space_with_response($temp_path);
            } catch (Exception $inner_e) {
                // Fall back to regular method
                $text = $ocr_processor->extract_text_from_scanned_pdf($temp_path);
            }
            
            $end_time = microtime(true);
            $ocr_time = round(($end_time - $start_time) * 1000, 2);
            
            // Get captured API response
            $api_response = get_option('idoklad_last_ocr_response');
            
            // Restore debug
            update_option('idoklad_debug_mode', $original_debug);
            
            wp_send_json_success(array(
                'text' => $text,
                'text_length' => strlen($text),
                'preview' => substr($text, 0, 500),
                'ocr_time_ms' => $ocr_time,
                'methods' => $methods,
                'api_response' => $api_response
            ));
            
        } catch (Exception $e) {
            // Restore debug
            if (isset($original_debug)) {
                update_option('idoklad_debug_mode', $original_debug);
            }
            
            // Get diagnostics (OCR is now handled by PDF.co)
            $methods = array();
            
            // Build detailed error message
            $error_details = array();
            $error_details[] = 'Error: ' . $e->getMessage();
            $error_details[] = '';
            $error_details[] = 'Diagnostic Information:';
            
            // Check PDF to Image conversion
            $has_converter = false;
            if ($methods['imagemagick']['available']) {
                $error_details[] = '✓ ImageMagick (convert) is available';
                $has_converter = true;
            } else {
                $error_details[] = '✗ ImageMagick (convert) not available';
            }
            
            if ($methods['ghostscript']['available']) {
                $error_details[] = '✓ Ghostscript (gs) is available';
                $has_converter = true;
            } else {
                $error_details[] = '✗ Ghostscript (gs) not available';
            }
            
            if ($methods['imagick_extension']['available']) {
                $error_details[] = '✓ PHP Imagick extension is loaded';
                $has_converter = true;
            } else {
                $error_details[] = '✗ PHP Imagick extension not loaded';
            }
            
            if (!$has_converter) {
                $error_details[] = '';
                $error_details[] = '⚠️  No PDF to image converter available!';
                $error_details[] = 'Install ImageMagick, Ghostscript, or PHP Imagick extension';
            }
            
            $error_details[] = '';
            
            // Check OCR methods
            $has_ocr = false;
            
            if ($methods['tesseract']['enabled']) {
                if ($methods['tesseract']['available']) {
                    $error_details[] = '✓ Tesseract is available and enabled';
                    $has_ocr = true;
                } else {
                    $error_details[] = '✗ Tesseract is enabled but not installed';
                }
            } else {
                $error_details[] = '⚠️  Tesseract is disabled in settings';
            }
            
            if ($methods['ocr_space']['enabled']) {
                if ($methods['ocr_space']['available']) {
                    $error_details[] = '✓ OCR.space API is configured and enabled';
                    $has_ocr = true;
                } else {
                    $error_details[] = '✗ OCR.space is enabled but API key is missing';
                }
            } else {
                $error_details[] = '⚠️  OCR.space is not enabled';
            }
            
            if ($methods['google_vision']['enabled']) {
                if ($methods['google_vision']['available']) {
                    $error_details[] = '✓ Google Vision API is configured and enabled';
                    $has_ocr = true;
                } else {
                    $error_details[] = '✗ Google Vision is enabled but API key is missing';
                }
            } else {
                $error_details[] = '⚠️  Google Vision is not enabled';
            }
            
            if (!$has_ocr) {
                $error_details[] = '';
                $error_details[] = '⚠️  No OCR method is available!';
                $error_details[] = 'Enable and configure at least one: Tesseract, OCR.space, or Google Vision';
            }
            
            $error_details[] = '';
            $error_details[] = 'Check WordPress debug.log for detailed error messages.';
            
            // Get captured API response (if any)
            $api_response = get_option('idoklad_last_ocr_response');
            
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'details' => implode("\n", $error_details),
                'methods' => $methods,
                'api_response' => $api_response
            ));
        }
    }
    
    /**
     * Test Zapier with payload (AJAX)
     */
    public function test_zapier_payload() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $pdf_text = isset($_POST['pdf_text']) ? stripslashes($_POST['pdf_text']) : '';
        
        if (empty($pdf_text)) {
            wp_send_json_error(__('No text provided', 'idoklad-invoice-processor'));
        }
        
        // Zapier is no longer used - we parse directly
        wp_send_json_error('Zapier testing is deprecated. The system now uses direct PDF text parsing.');
        return;
        
        try {
            // Zapier testing deprecated
            
            $email_data = array(
                'email_from' => 'test@example.com',
                'email_subject' => 'Test Invoice',
                'attachment_name' => 'test.pdf'
            );
            
            $start_time = microtime(true);
            $response = array();
            $end_time = microtime(true);
            $request_time = round(($end_time - $start_time) * 1000, 2);
            
            wp_send_json_success(array(
                'response' => $response,
                'request_time_ms' => $request_time,
                'webhook_url' => get_option('idoklad_zapier_webhook_url')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(__('Zapier request failed: ', 'idoklad-invoice-processor') . $e->getMessage());
        }
    }
    
    /**
     * Test iDoklad with payload (AJAX)
     */
    public function test_idoklad_payload() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $user_email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        $invoice_data = isset($_POST['invoice_data']) ? $_POST['invoice_data'] : '';
        
        if (empty($user_email)) {
            wp_send_json_error(__('No user email provided', 'idoklad-invoice-processor'));
        }
        
        if (empty($invoice_data)) {
            wp_send_json_error(__('No invoice data provided', 'idoklad-invoice-processor'));
        }
        
        // Parse JSON
        $data = json_decode(stripslashes($invoice_data), true);
        if (!$data) {
            wp_send_json_error(__('Invalid JSON format', 'idoklad-invoice-processor'));
        }
        
        // Get user
        $user = IDokladProcessor_Database::get_authorized_user($user_email);
        if (!$user) {
            wp_send_json_error(__('User not found', 'idoklad-invoice-processor'));
        }
        
        try {
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-idoklad-api.php';
            // TODO: iDoklad API integration removed - to be rebuilt
            // $idoklad_api = new IDokladProcessor_IDokladAPI($user);
            
            // Enable debug mode for this test
            $original_debug = get_option('idoklad_debug_mode');
            update_option('idoklad_debug_mode', true);
            
            // Clear previous response
            delete_option('idoklad_last_api_response');
            
            $start_time = microtime(true);
            $response = $idoklad_api->create_invoice_with_response($data);
            $end_time = microtime(true);
            $request_time = round(($end_time - $start_time) * 1000, 2);
            
            // Get captured API response
            $api_response = get_option('idoklad_last_api_response');
            
            // Restore debug mode
            update_option('idoklad_debug_mode', $original_debug);
            
            wp_send_json_success(array(
                'response' => $response,
                'request_time_ms' => $request_time,
                'user_name' => $user->name,
                'api_url' => $user->idoklad_api_url,
                'endpoint' => '/ReceivedInvoices (Received invoice - expense)',
                'api_response' => $api_response
            ));
            
        } catch (Exception $e) {
            // Restore debug mode
            if (isset($original_debug)) {
                update_option('idoklad_debug_mode', $original_debug);
            }
            
            // Get captured API response (if any)
            $api_response = get_option('idoklad_last_api_response');
            
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'help' => 'Check WordPress debug.log for detailed API request/response. Enable Debug Mode in Settings for permanent logging.',
                'endpoint_used' => '/ReceivedInvoices',
                'note' => 'This creates a RECEIVED invoice (expense). For invoices you send out, the endpoint would be /IssuedInvoices',
                'api_response' => $api_response
            ));
        }
    }
    
    /**
     * Get available parsing methods (AJAX)
     */
    public function get_parsing_methods() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        try {
            $pdf_processor = new IDokladProcessor_PDFProcessor();
            $methods = $pdf_processor->test_parsing_methods();
            
            wp_send_json_success($methods);
            
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to get parsing methods: ', 'idoklad-invoice-processor') . $e->getMessage());
        }
    }
    
    /**
     * Test PDF.co connection (AJAX)
     */
    public function test_pdfco_connection() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        try {
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdfco-processor.php';
            $pdfco = new IDokladProcessor_PDFCoProcessor();
            $result = $pdfco->test_connection();
        
        if ($result['success']) {
                wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
            
        } catch (Exception $e) {
            wp_send_json_error(__('Test failed: ', 'idoklad-invoice-processor') . $e->getMessage());
        }
    }
    
    /**
     * Test AI Parser (AJAX)
     */
    public function test_ai_parser() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        try {
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-co-ai-parser.php';
            $ai_parser = new IDokladProcessor_PDFCoAIParser();
            $result = $ai_parser->test_connection();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(__('AI Parser test failed: ', 'idoklad-invoice-processor') . $e->getMessage());
        }
    }
    
    /**
     * Force email check (AJAX)
     */
    public function force_email_check() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        try {
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-email-monitor.php';
            $email_monitor = new IDokladProcessor_EmailMonitor();
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad: Force email check initiated');
            }
            
            // Check for new emails (this adds them to the queue)
            $new_emails = $email_monitor->check_for_new_emails();
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad: Found ' . $new_emails . ' new emails');
            }
            
            // Process pending emails from queue
            $processed = $email_monitor->process_pending_emails();
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad: Processed ' . $processed . ' items');
            }
            
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Found %d new email(s), processed %d item(s)', 'idoklad-invoice-processor'),
                    $new_emails,
                    $processed
                ),
                'new_emails' => $new_emails,
                'processed' => $processed
            ));
            
        } catch (Exception $e) {
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad: Email check error: ' . $e->getMessage());
                error_log('iDoklad: Stack trace: ' . $e->getTraceAsString());
            }
            wp_send_json_error('Email check failed: ' . $e->getMessage());
        } catch (Error $e) {
            // Catch fatal errors
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad: Fatal email check error: ' . $e->getMessage());
                error_log('iDoklad: Stack trace: ' . $e->getTraceAsString());
            }
            wp_send_json_error('Email check failed (fatal error): ' . $e->getMessage());
        }
    }
    
    /**
     * Cancel/delete queue item (AJAX)
     */
    public function cancel_queue_item() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }
        
        $queue_id = isset($_POST['queue_id']) ? intval($_POST['queue_id']) : 0;

        if (!$queue_id) {
            wp_send_json_error(__('Queue ID is required', 'idoklad-invoice-processor'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_queue';
        
        // Get the item first
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $queue_id));
        
        if (!$item) {
            wp_send_json_error(__('Queue item not found', 'idoklad-invoice-processor'));
        }
        
        // Add cancellation step
        IDokladProcessor_Database::add_queue_step($queue_id, 'Cancelled by user', array(
            'cancelled_at' => current_time('mysql'),
            'previous_status' => $item->status
        ));
        
        // Update status to failed with cancellation note
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'failed',
                'current_step' => 'Cancelled by user'
            ),
            array('id' => $queue_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(__('Queue item cancelled successfully', 'idoklad-invoice-processor'));
        } else {
            wp_send_json_error(__('Failed to cancel queue item', 'idoklad-invoice-processor'));
        }
    }
    
    /**
     * Get database statistics (AJAX)
     */
    public function get_database_stats() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        // Get counts
        $logs_table = $wpdb->prefix . 'idoklad_logs';
        $queue_table = $wpdb->prefix . 'idoklad_queue';
        
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
        $logs_30_days = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $logs_90_days = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        $total_queue = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table");
        $queue_30_days = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $queue_completed = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'completed'");
        $queue_failed = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'failed'");
        
        // Get attachment stats
        $upload_dir = wp_upload_dir();
        $attachments_dir = $upload_dir['basedir'] . '/idoklad-attachments';
        
        $attachment_count = 0;
        $attachment_size = 0;
        
        if (file_exists($attachments_dir)) {
            $files = glob($attachments_dir . '/*');
            $attachment_count = count($files);
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $attachment_size += filesize($file);
                }
            }
        }
        
        wp_send_json_success(array(
            'logs' => array(
                'total' => $total_logs,
                'older_than_30_days' => $logs_30_days,
                'older_than_90_days' => $logs_90_days
            ),
            'queue' => array(
                'total' => $total_queue,
                'older_than_30_days' => $queue_30_days,
                'completed' => $queue_completed,
                'failed' => $queue_failed
            ),
            'attachments' => array(
                'count' => $attachment_count,
                'size_mb' => round($attachment_size / 1024 / 1024, 2)
            )
        ));
    }
    
    /**
     * Cleanup old logs (AJAX)
     */
    public function cleanup_old_logs() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $days = intval($_POST['days']);
        if ($days < 7) {
            wp_send_json_error('Minimum 7 days required');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_logs';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        wp_send_json_success(array(
            'deleted' => $deleted,
            'message' => sprintf(__('Deleted %d log entries older than %d days', 'idoklad-invoice-processor'), $deleted, $days)
        ));
    }
    
    /**
     * Cleanup old queue items (AJAX)
     */
    public function cleanup_old_queue() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $days = intval($_POST['days']);
        $status = sanitize_text_field($_POST['status'] ?? 'completed');
        
        if ($days < 7) {
            wp_send_json_error('Minimum 7 days required');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_queue';
        
        $where = $wpdb->prepare("created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days);
        
        if ($status !== 'all') {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }
        
        $deleted = $wpdb->query("DELETE FROM $table WHERE $where");
        
        wp_send_json_success(array(
            'deleted' => $deleted,
            'message' => sprintf(__('Deleted %d queue items', 'idoklad-invoice-processor'), $deleted)
        ));
    }
    
    /**
     * Cleanup old attachments (AJAX)
     */
    public function cleanup_old_attachments() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $days = intval($_POST['days']);
        if ($days < 7) {
            wp_send_json_error('Minimum 7 days required');
        }
        
        $upload_dir = wp_upload_dir();
        $attachments_dir = $upload_dir['basedir'] . '/idoklad-attachments';
        
        if (!file_exists($attachments_dir)) {
            wp_send_json_success(array(
                'deleted' => 0,
                'message' => __('No attachments directory found', 'idoklad-invoice-processor')
            ));
        }
        
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        $files = glob($attachments_dir . '/*');
        $deleted_count = 0;
        $freed_space = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                $freed_space += filesize($file);
                unlink($file);
                $deleted_count++;
            }
        }
        
        wp_send_json_success(array(
            'deleted' => $deleted_count,
            'freed_mb' => round($freed_space / 1024 / 1024, 2),
            'message' => sprintf(__('Deleted %d attachments, freed %.2f MB', 'idoklad-invoice-processor'), $deleted_count, round($freed_space / 1024 / 1024, 2))
        ));
    }
    
    /**
     * Grab emails manually (AJAX)
     */
    public function grab_emails_manually() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            // Get email monitor instance
            $email_monitor = new IDokladProcessor_EmailMonitor();
            
            // Force email check
            $result = $email_monitor->check_emails();
            
            if ($result['success']) {
                $message = sprintf(
                    __('Email check completed. Found %d new emails, %d PDFs processed.', 'idoklad-invoice-processor'),
                    $result['emails_found'] ?? 0,
                    $result['pdfs_processed'] ?? 0
                );
                
                wp_send_json_success(array(
                    'message' => $message,
                    'emails_found' => $result['emails_found'] ?? 0,
                    'pdfs_processed' => $result['pdfs_processed'] ?? 0,
                    'queue_items_added' => $result['queue_items_added'] ?? 0
                ));
            } else {
                wp_send_json_error($result['message'] ?? 'Email check failed');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Process emails manually (AJAX)
     */
    public function process_emails_manually() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            // Get database instance
            $database = new IDokladProcessor_Database();
            
            // Get pending queue items
            $pending_items = $database->get_queue_items('pending', 10);
            
            if (empty($pending_items)) {
                wp_send_json_success(array(
                    'message' => __('No pending items to process', 'idoklad-invoice-processor'),
                    'processed' => 0
                ));
            }
            
            $processed_count = 0;
            $errors = array();
            
            foreach ($pending_items as $item) {
                try {
                    // Update status to processing
                    $database->update_queue_item($item['id'], array('status' => 'processing'));
                    
                    // Process the item (this would call the actual processing logic)
                    $result = $this->process_single_queue_item($item);
                    
                    if ($result['success']) {
                        $database->update_queue_item($item['id'], array(
                            'status' => 'completed',
                            'processed_at' => current_time('mysql'),
                            'api_response' => json_encode($result['data'])
                        ));
                        $processed_count++;
                    } else {
                        $database->update_queue_item($item['id'], array(
                            'status' => 'failed',
                            'error_message' => $result['message']
                        ));
                        $errors[] = "Item {$item['id']}: " . $result['message'];
                    }
                    
                } catch (Exception $e) {
                    $database->update_queue_item($item['id'], array(
                        'status' => 'failed',
                        'error_message' => $e->getMessage()
                    ));
                    $errors[] = "Item {$item['id']}: " . $e->getMessage();
                }
            }
            
            $message = sprintf(
                __('Processed %d items successfully', 'idoklad-invoice-processor'),
                $processed_count
            );
            
            if (!empty($errors)) {
                $message .= '. Errors: ' . implode(', ', $errors);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'processed' => $processed_count,
                'errors' => $errors
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Start automatic processing (AJAX)
     */
    public function start_automatic_processing() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            // Enable automatic processing
            update_option('idoklad_automatic_processing', true);
            
            // Schedule cron jobs if not already scheduled
            if (!wp_next_scheduled('idoklad_check_emails')) {
                wp_schedule_event(time(), 'idoklad_email_interval', 'idoklad_check_emails');
            }
            
            if (!wp_next_scheduled('idoklad_process_queue')) {
                wp_schedule_event(time(), 'idoklad_queue_interval', 'idoklad_process_queue');
            }
            
            wp_send_json_success(array(
                'message' => __('Automatic processing started successfully', 'idoklad-invoice-processor'),
                'status' => 'running'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Stop automatic processing (AJAX)
     */
    public function stop_automatic_processing() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            // Disable automatic processing
            update_option('idoklad_automatic_processing', false);
            
            // Clear scheduled cron jobs
            wp_clear_scheduled_hook('idoklad_check_emails');
            wp_clear_scheduled_hook('idoklad_process_queue');
            
            wp_send_json_success(array(
                'message' => __('Automatic processing stopped successfully', 'idoklad-invoice-processor'),
                'status' => 'stopped'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get processing status (AJAX)
     */
    public function get_processing_status() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $automatic_processing = get_option('idoklad_automatic_processing', false);
            $next_email_check = wp_next_scheduled('idoklad_check_emails');
            $next_queue_process = wp_next_scheduled('idoklad_process_queue');
            
            // Get queue statistics
            $database = new IDokladProcessor_Database();
            $queue_stats = $database->get_queue_statistics();
            
            wp_send_json_success(array(
                'automatic_processing' => $automatic_processing,
                'next_email_check' => $next_email_check ? date('Y-m-d H:i:s', $next_email_check) : null,
                'next_queue_process' => $next_queue_process ? date('Y-m-d H:i:s', $next_queue_process) : null,
                'queue_stats' => $queue_stats,
                'status' => $automatic_processing ? 'running' : 'stopped'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Process a single queue item - Connect PDF parser to iDoklad integration
     */
    private function process_single_queue_item($item) {
        try {
            // Step 1: Parse the PDF using the enhanced PDF.co AI parser
            if (empty($item['attachment_path']) || !file_exists($item['attachment_path'])) {
                throw new Exception('PDF attachment not found: ' . ($item['attachment_path'] ?? 'No path'));
            }
            
            // Convert local file path to accessible URL
            $pdf_url = $this->get_accessible_pdf_url($item['attachment_path']);
            
            // Initialize PDF parser
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-co-ai-parser-enhanced.php';
            $parser = new IDokladProcessor_PDFCoAIParserEnhanced();
            
            // Parse the PDF
            $parse_result = $parser->parse_invoice_with_debug($pdf_url);
            
            if (!$parse_result['success']) {
                throw new Exception('PDF parsing failed: ' . ($parse_result['message'] ?? 'Unknown error'));
            }
            
            $idoklad_data = $parse_result['data'];
            
            // Step 2: Send to iDoklad API using the integration
            $client_id = get_option('idoklad_client_id');
            $client_secret = get_option('idoklad_client_secret');
            
            if (empty($client_id) || empty($client_secret)) {
                throw new Exception('iDoklad API credentials not configured');
            }
            
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-idoklad-api-v3-integration.php';
            $integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
            
            // Create invoice using the parsed data
            $invoice_result = $integration->create_invoice_complete_workflow($idoklad_data);
            
            if (!$invoice_result['success']) {
                throw new Exception('Invoice creation failed: ' . ($invoice_result['message'] ?? 'Unknown error'));
            }
            
            return array(
                'success' => true,
                'data' => array(
                    'processed_at' => current_time('mysql'),
                    'item_id' => $item['id'],
                    'invoice_id' => $invoice_result['invoice_id'] ?? null,
                    'document_number' => $invoice_result['document_number'] ?? null,
                    'parse_result' => $parse_result,
                    'invoice_result' => $invoice_result
                )
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Convert local file path to accessible URL for PDF.co API
     */
    private function get_accessible_pdf_url($file_path) {
        // If it's already a URL, return as is
        if (filter_var($file_path, FILTER_VALIDATE_URL)) {
            return $file_path;
        }
        
        // Convert local path to WordPress uploads URL
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];
        $upload_url = $upload_dir['baseurl'];
        
        // Check if file is in uploads directory
        if (strpos($file_path, $upload_path) === 0) {
            $relative_path = str_replace($upload_path, '', $file_path);
            return $upload_url . $relative_path;
        }
        
        // For files outside uploads directory, try to create a temporary accessible URL
        // This is a fallback - in production, files should be in the uploads directory
        $file_name = basename($file_path);
        $temp_url = $upload_url . '/idoklad-temp/' . $file_name;
        
        // Copy file to temp location if needed
        $temp_dir = $upload_path . '/idoklad-temp/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $temp_path = $temp_dir . $file_name;
        if (!file_exists($temp_path)) {
            copy($file_path, $temp_path);
        }
        
        return $temp_url;
    }
    
    /**
     * Reprocess selected queue items (AJAX)
     */
    public function reprocess_selected_items() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : array();
            
            if (empty($selected_ids)) {
                wp_send_json_error('No items selected');
            }
            
            $database = new IDokladProcessor_Database();
            $processed_count = 0;
            $errors = array();
            
            foreach ($selected_ids as $item_id) {
                try {
                    // Get the queue item
                    $item = $database->get_queue_item($item_id);
                    if (!$item) {
                        $errors[] = "Item $item_id not found";
                        continue;
                    }
                    
                    // Only reprocess completed or failed items
                    if (!in_array($item['status'], array('completed', 'failed'))) {
                        $errors[] = "Item $item_id is not in a reprocessable state (current status: {$item['status']})";
                        continue;
                    }
                    
                    // Reset the item to pending status
                    $database->update_queue_item($item_id, array(
                        'status' => 'pending',
                        'current_step' => 'Ready for reprocessing',
                        'attempts' => 0
                    ));
                    
                    $processed_count++;
                    
                } catch (Exception $e) {
                    $errors[] = "Error processing item $item_id: " . $e->getMessage();
                }
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('%d items queued for reprocessing', 'idoklad-invoice-processor'), $processed_count),
                'processed_count' => $processed_count,
                'errors' => $errors
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Reprocess single queue item (AJAX)
     */
    public function reprocess_single_item() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
            
            if (!$item_id) {
                wp_send_json_error('Invalid item ID');
            }
            
            $database = new IDokladProcessor_Database();
            
            // Get the queue item
            $item = $database->get_queue_item($item_id);
            if (!$item) {
                wp_send_json_error('Item not found');
            }
            
            // Only reprocess completed or failed items
            if (!in_array($item['status'], array('completed', 'failed'))) {
                wp_send_json_error('Item is not in a reprocessable state (current status: ' . $item['status'] . ')');
            }
            
            // Check if iDoklad credentials are configured
            $client_id = get_option('idoklad_client_id');
            $client_secret = get_option('idoklad_client_secret');
            
            if (empty($client_id) || empty($client_secret)) {
                wp_send_json_error('iDoklad API credentials not configured. Please configure them in the iDoklad Integration settings.');
            }
            
            // Check if PDF.co API key is configured
            $pdf_co_api_key = get_option('idoklad_pdfco_api_key');
            if (empty($pdf_co_api_key)) {
                wp_send_json_error('PDF.co API key not configured. Please configure it in the PDF.co settings.');
            }
            
            // Reset the item to pending status
            $result = $database->update_queue_item($item_id, array(
                'status' => 'pending',
                'current_step' => 'Ready for reprocessing',
                'attempts' => 0
            ));
            
            if ($result === false) {
                wp_send_json_error('Failed to update queue item status');
            }
            
            wp_send_json_success(array(
                'message' => __('Item queued for reprocessing', 'idoklad-invoice-processor'),
                'item_id' => $item_id
            ));
            
        } catch (Exception $e) {
            error_log('iDoklad Reprocessing Error: ' . $e->getMessage());
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Reset selected queue items (AJAX)
     */
    public function reset_selected_items() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : array();
            
            if (empty($selected_ids)) {
                wp_send_json_error('No items selected');
            }
            
            $database = new IDokladProcessor_Database();
            $processed_count = 0;
            $errors = array();
            
            foreach ($selected_ids as $item_id) {
                try {
                    // Get the queue item
                    $item = $database->get_queue_item($item_id);
                    if (!$item) {
                        $errors[] = "Item $item_id not found";
                        continue;
                    }
                    
                    // Reset the item to pending status
                    $database->update_queue_item($item_id, array(
                        'status' => 'pending',
                        'current_step' => 'Reset to pending',
                        'attempts' => 0
                    ));
                    
                    $processed_count++;
                    
                } catch (Exception $e) {
                    $errors[] = "Error resetting item $item_id: " . $e->getMessage();
                }
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('%d items reset to pending', 'idoklad-invoice-processor'), $processed_count),
                'processed_count' => $processed_count,
                'errors' => $errors
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Export single log (AJAX)
     */
    public function export_single_log() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
            $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'json';
            
            if (!$log_id) {
                wp_send_json_error('Invalid log ID');
            }
            
            $database = new IDokladProcessor_Database();
            $log = $database->get_log($log_id);
            
            if (!$log) {
                wp_send_json_error('Log not found');
            }
            
            $this->export_log_data($log, $format);
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Export selected logs (AJAX)
     */
    public function export_selected_logs() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : array();
            $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'json';
            
            if (empty($selected_ids)) {
                wp_send_json_error('No logs selected');
            }
            
            $database = new IDokladProcessor_Database();
            $logs = array();
            
            foreach ($selected_ids as $log_id) {
                $log = $database->get_log($log_id);
                if ($log) {
                    $logs[] = $log;
                }
            }
            
            if (empty($logs)) {
                wp_send_json_error('No valid logs found');
            }
            
            $this->export_logs_data($logs, $format);
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete single log (AJAX)
     */
    public function delete_single_log() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
            
            if (!$log_id) {
                wp_send_json_error('Invalid log ID');
            }
            
            $database = new IDokladProcessor_Database();
            $result = $database->delete_log($log_id);
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Log deleted successfully', 'idoklad-invoice-processor'),
                    'log_id' => $log_id
                ));
            } else {
                wp_send_json_error('Failed to delete log');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete selected logs (AJAX)
     */
    public function delete_selected_logs() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : array();
            
            if (empty($selected_ids)) {
                wp_send_json_error('No logs selected');
            }
            
            $database = new IDokladProcessor_Database();
            $deleted_count = 0;
            $errors = array();
            
            foreach ($selected_ids as $log_id) {
                try {
                    if ($database->delete_log($log_id)) {
                        $deleted_count++;
                    } else {
                        $errors[] = "Failed to delete log $log_id";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error deleting log $log_id: " . $e->getMessage();
                }
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('%d logs deleted successfully', 'idoklad-invoice-processor'), $deleted_count),
                'deleted_count' => $deleted_count,
                'errors' => $errors
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Export single log data
     */
    private function export_log_data($log, $format = 'json') {
        $filename = 'idoklad-log-' . $log->id . '-' . date('Y-m-d-H-i-s') . '.' . $format;
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        if ($format === 'json') {
            header('Content-Type: application/json');
            echo json_encode($log, JSON_PRETTY_PRINT);
        } elseif ($format === 'csv') {
            header('Content-Type: text/csv');
            $this->export_log_as_csv(array($log));
        } else {
            echo json_encode($log, JSON_PRETTY_PRINT);
        }
        
        exit;
    }
    
    /**
     * Export multiple logs data
     */
    private function export_logs_data($logs, $format = 'json') {
        $filename = 'idoklad-logs-' . date('Y-m-d-H-i-s') . '.' . $format;
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        if ($format === 'json') {
            header('Content-Type: application/json');
            echo json_encode($logs, JSON_PRETTY_PRINT);
        } elseif ($format === 'csv') {
            header('Content-Type: text/csv');
            $this->export_log_as_csv($logs);
        } else {
            echo json_encode($logs, JSON_PRETTY_PRINT);
        }
        
        exit;
    }
    
    /**
     * Export logs as CSV
     */
    private function export_log_as_csv($logs) {
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'ID',
            'Date',
            'Email From',
            'Email Subject',
            'Attachment',
            'Status',
            'Error Message',
            'Extracted Data',
            'API Response'
        ));
        
        // CSV data
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->id,
                $log->created_at,
                $log->email_from,
                $log->email_subject,
                $log->attachment_name,
                $log->processing_status,
                $log->error_message,
                $log->extracted_data,
                $log->api_response
            ));
        }
        
        fclose($output);
    }
}

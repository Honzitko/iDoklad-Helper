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
        add_action('wp_ajax_idoklad_test_chatgpt_invoice', array($this, 'test_chatgpt_invoice'));
        
        // Dashboard AJAX handlers
        add_action('wp_ajax_idoklad_force_email_check', array($this, 'force_email_check'));
        add_action('wp_ajax_idoklad_cancel_queue_item', array($this, 'cancel_queue_item'));
        
        // Email processing AJAX handlers
        add_action('wp_ajax_idoklad_grab_emails_manually', array($this, 'grab_emails_manually'));
        add_action('wp_ajax_idoklad_process_emails_manually', array($this, 'process_emails_manually'));
        add_action('wp_ajax_idoklad_start_automatic_processing', array($this, 'start_automatic_processing'));
        add_action('wp_ajax_idoklad_stop_automatic_processing', array($this, 'stop_automatic_processing'));
        add_action('wp_ajax_idoklad_toggle_automatic_processing', array($this, 'toggle_automatic_processing'));
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
        
        // ChatGPT settings
        register_setting('idoklad_chatgpt_settings', 'idoklad_chatgpt_api_key');
        register_setting('idoklad_chatgpt_settings', 'idoklad_chatgpt_model');
        register_setting('idoklad_chatgpt_settings', 'idoklad_chatgpt_prompt');
        register_setting('idoklad_chatgpt_settings', 'idoklad_openai_prompt_id');
        register_setting('idoklad_chatgpt_settings', 'idoklad_openai_prompt_version');

        // General settings
        register_setting('idoklad_general_settings', 'idoklad_notification_email');
        register_setting('idoklad_general_settings', 'idoklad_debug_mode');
        register_setting('idoklad_general_settings', 'idoklad_client_id');
        register_setting('idoklad_general_settings', 'idoklad_client_secret');
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

        $fields = array(
            'chatgpt_api_key' => 'sanitize_text_field',
            'chatgpt_model' => 'sanitize_text_field',
            'chatgpt_prompt' => 'sanitize_textarea_field',
            'chatgpt_prompt_id' => 'sanitize_text_field',
            'chatgpt_prompt_version' => 'sanitize_text_field',
            'notification_email' => 'sanitize_email',
            'client_id' => 'sanitize_text_field',
            'client_secret' => 'sanitize_text_field'
        );

        foreach ($fields as $field => $callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($callback, $_POST[$field]);
                update_option('idoklad_' . $field, $value);
            }
        }

        $email_fields = array(
            'email_host' => 'sanitize_text_field',
            'email_port' => 'intval',
            'email_username' => 'sanitize_text_field',
            'email_password' => 'sanitize_text_field',
            'email_encryption' => 'sanitize_text_field'
        );

        foreach ($email_fields as $field => $callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($callback, $_POST[$field]);
                update_option('idoklad_' . $field, $value);
            }
        }

        update_option('idoklad_processing_engine', 'chatgpt');

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

        $decoded_extracted = $log->extracted_data ? json_decode($log->extracted_data, true) : null;
        $decoded_response = $log->idoklad_response ? json_decode($log->idoklad_response, true) : null;
        if (!$decoded_response && !empty($log->api_response)) {
            $decoded_response = json_decode($log->api_response, true);
        }

        $warnings = array();
        $checklist = array();

        if (is_array($decoded_extracted)) {
            if (!empty($decoded_extracted['summary']['warnings']) && is_array($decoded_extracted['summary']['warnings'])) {
                $warnings = array_merge($warnings, $decoded_extracted['summary']['warnings']);
            }
            if (!empty($decoded_extracted['parsed']['warnings']) && is_array($decoded_extracted['parsed']['warnings'])) {
                $warnings = array_merge($warnings, $decoded_extracted['parsed']['warnings']);
            }
            if (!empty($decoded_extracted['warnings']) && is_array($decoded_extracted['warnings'])) {
                $warnings = array_merge($warnings, $decoded_extracted['warnings']);
            }

            if (!empty($decoded_extracted['summary']['checklist']) && is_array($decoded_extracted['summary']['checklist'])) {
                $checklist = array_merge($checklist, $decoded_extracted['summary']['checklist']);
            }
            if (!empty($decoded_extracted['parsed']['checklist']) && is_array($decoded_extracted['parsed']['checklist'])) {
                $checklist = array_merge($checklist, $decoded_extracted['parsed']['checklist']);
            }
            if (!empty($decoded_extracted['checklist']) && is_array($decoded_extracted['checklist'])) {
                $checklist = array_merge($checklist, $decoded_extracted['checklist']);
            }
        }

        $warnings = array_values(array_unique(array_filter(array_map('trim', $warnings))));
        $checklist = array_values(array_unique(array_filter(array_map('trim', $checklist))));

        if (!empty($warnings)) {
            $html .= '<div class="log-detail-section">';
            $html .= '<h4>' . esc_html__('Warnings', 'idoklad-invoice-processor') . '</h4>';
            $html .= '<ul class="log-list">';
            foreach ($warnings as $warning) {
                $html .= '<li>' . esc_html($warning) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        if (!empty($checklist)) {
            $html .= '<div class="log-detail-section">';
            $html .= '<h4>' . esc_html__('Checklist', 'idoklad-invoice-processor') . '</h4>';
            $html .= '<ul class="log-list">';
            foreach ($checklist as $item) {
                $html .= '<li>' . esc_html($item) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        if ($log->extracted_data) {
            $html .= '<div class="log-detail-section">';
            $html .= '<h4>Extracted Data</h4>';
            $pretty_extracted = $decoded_extracted ? wp_json_encode($decoded_extracted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $log->extracted_data;
            $html .= '<div class="json-data">' . esc_html($pretty_extracted) . '</div>';
            $html .= '</div>';
        }

        $raw_response = $log->idoklad_response ?: ($log->api_response ?? '');

        if ($raw_response) {
            $html .= '<div class="log-detail-section">';
            $html .= '<h4>iDoklad Response</h4>';
            $pretty_response = $decoded_response ? wp_json_encode($decoded_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $raw_response;
            $html .= '<div class="json-data">' . esc_html($pretty_response) . '</div>';
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

        wp_send_json_success(array(
            'deprecated' => true,
            'message' => __('Direct PDF parsing is no longer available. Use the ChatGPT extraction test to validate invoices.', 'idoklad-invoice-processor'),
        ));
    }

    /**
     * Test OCR on PDF (AJAX)
     */
    public function test_ocr_on_pdf() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }

        wp_send_json_error(__('OCR testing has been removed. ChatGPT now handles extraction directly from uploaded PDFs.', 'idoklad-invoice-processor'));
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
            $has_api_key = !empty(get_option('idoklad_chatgpt_api_key'));
            $methods = array(
                'chatgpt' => array(
                    'available' => $has_api_key,
                    'name' => __('ChatGPT Invoice Extraction', 'idoklad-invoice-processor'),
                    'description' => __('Send invoices directly to ChatGPT for structured JSON extraction.', 'idoklad-invoice-processor'),
                    'category' => __('AI Extraction', 'idoklad-invoice-processor'),
                ),
            );

            wp_send_json_success($methods);

        } catch (Exception $e) {
            wp_send_json_error(__('Failed to get parsing methods: ', 'idoklad-invoice-processor') . $e->getMessage());
        }
    }
    
    /**
     * Test ChatGPT invoice extraction (AJAX)
     */
    public function test_chatgpt_invoice() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'idoklad-invoice-processor'));
        }

        $api_key = get_option('idoklad_chatgpt_api_key');
        if (empty($api_key)) {
            wp_send_json_error(__('ChatGPT API key is not configured', 'idoklad-invoice-processor'));
        }

        $pdf_text = '';
        $temp_file = null;
        $file_name = '';

        try {
            if (!empty($_FILES['pdf_file']['tmp_name'])) {
                if (!function_exists('wp_tempnam')) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                }
                $temp_file = wp_tempnam($_FILES['pdf_file']['name']);
                if (!$temp_file) {
                    throw new Exception(__('Unable to create temporary file for upload', 'idoklad-invoice-processor'));
                }

                if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $temp_file)) {
                    throw new Exception(__('Failed to move uploaded PDF for processing', 'idoklad-invoice-processor'));
                }
                $file_name = sanitize_file_name($_FILES['pdf_file']['name']);
            }

            if (empty($pdf_text) && isset($_POST['pdf_text'])) {
                $pdf_text = sanitize_textarea_field(wp_unslash($_POST['pdf_text']));
            }

            $pdf_text = trim($pdf_text);

            $chatgpt = new IDokladProcessor_ChatGPTIntegration();
            $context = array(
                'file_name' => $file_name,
            );

            if (!empty($pdf_text)) {
                $extracted_data = $chatgpt->extract_invoice_data_from_text($pdf_text, $context);
            } elseif (!empty($temp_file)) {
                $extracted_data = $chatgpt->extract_invoice_data_from_pdf($temp_file, $context);
            } else {
                throw new Exception(__('Provide a PDF file or extracted text to run the ChatGPT test.', 'idoklad-invoice-processor'));
            }

            // Attach diagnostics metadata
            $extracted_data['source'] = 'chatgpt';

            if (!empty($pdf_text)) {
                $extracted_data['pdf_text_preview'] = function_exists('mb_substr') ? mb_substr($pdf_text, 0, 500) : substr($pdf_text, 0, 500);
            }

            $idoklad_payload = $chatgpt->build_idoklad_payload($extracted_data, $context);

            $text_preview = '';
            if (!empty($pdf_text)) {
                $text_preview = function_exists('mb_substr') ? mb_substr($pdf_text, 0, 500) : substr($pdf_text, 0, 500);
            } elseif (!empty($file_name)) {
                $text_preview = sprintf(__('PDF "%s" processed via base64 (preview unavailable).', 'idoklad-invoice-processor'), $file_name);
            }

            wp_send_json_success(array(
                'model' => get_option('idoklad_chatgpt_model', 'gpt-4o'),
                'text_length' => strlen($pdf_text),
                'text_preview' => $text_preview,
                'extracted_data' => $extracted_data,
                'idoklad_data' => $idoklad_payload,
            ));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        } finally {
            if ($temp_file && file_exists($temp_file)) {
                unlink($temp_file);
            }
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
            $check_result = $email_monitor->check_for_new_emails();

            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad: Email check result: ' . wp_json_encode($check_result));
            }

            $new_emails = 0;
            $queued_attachments = 0;

            if (is_array($check_result)) {
                $new_emails = isset($check_result['emails_found']) ? (int) $check_result['emails_found'] : 0;
                $queued_attachments = isset($check_result['queue_items_added']) ? (int) $check_result['queue_items_added'] : 0;
            } else {
                $new_emails = (int) $check_result;
                $queued_attachments = $new_emails;
                $check_result = array(
                    'success' => true,
                    'emails_found' => $new_emails,
                    'queue_items_added' => $queued_attachments,
                    'message' => ''
                );
            }

            // Process pending emails from queue
            $processed = $email_monitor->process_pending_emails();

            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad: Processed ' . $processed . ' items');
            }

            $message_parts = array();

            if (!empty($check_result['message'])) {
                $message_parts[] = $check_result['message'];
            }

            $message_parts[] = sprintf(
                __('Found %1$d new email(s), queued %2$d attachment(s), processed %3$d item(s)', 'idoklad-invoice-processor'),
                $new_emails,
                $queued_attachments,
                $processed
            );

            wp_send_json_success(array(
                'message' => implode(' ', $message_parts),
                'new_emails' => $new_emails,
                'queued' => $queued_attachments,
                'processed' => $processed,
                'details' => $check_result
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
            
            if (!empty($result['success'])) {
                $message = $result['message'] ?? __('Email check completed.', 'idoklad-invoice-processor');

                wp_send_json_success(array(
                    'message' => $message,
                    'emails_found' => $result['emails_found'] ?? 0,
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

            if (!wp_next_scheduled('idoklad_check_emails_v3')) {
                wp_schedule_event(time(), 'idoklad_email_interval', 'idoklad_check_emails_v3');
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
            wp_clear_scheduled_hook('idoklad_check_emails_v3');
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
     * Toggle automatic processing (AJAX)
     */
    public function toggle_automatic_processing() {
        check_ajax_referer('idoklad_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            $automatic_processing = get_option('idoklad_automatic_processing', false);

            if ($automatic_processing) {
                update_option('idoklad_automatic_processing', false);
                wp_clear_scheduled_hook('idoklad_check_emails');
                wp_clear_scheduled_hook('idoklad_check_emails_v3');
                wp_clear_scheduled_hook('idoklad_process_queue');

                wp_send_json_success(array(
                    'message' => __('Automatic email grabbing and processing disabled', 'idoklad-invoice-processor'),
                    'status' => 'stopped'
                ));
            } else {
                update_option('idoklad_automatic_processing', true);

                if (!wp_next_scheduled('idoklad_check_emails')) {
                    wp_schedule_event(time(), 'idoklad_email_interval', 'idoklad_check_emails');
                }

                if (!wp_next_scheduled('idoklad_check_emails_v3')) {
                    wp_schedule_event(time(), 'idoklad_email_interval', 'idoklad_check_emails_v3');
                }

                if (!wp_next_scheduled('idoklad_process_queue')) {
                    wp_schedule_event(time(), 'idoklad_queue_interval', 'idoklad_process_queue');
                }

                wp_send_json_success(array(
                    'message' => __('Automatic email grabbing and processing enabled', 'idoklad-invoice-processor'),
                    'status' => 'running'
                ));
            }

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
            $next_email_check = wp_next_scheduled('idoklad_check_emails_v3');

            if (!$next_email_check) {
                $next_email_check = wp_next_scheduled('idoklad_check_emails');
            }
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
            if (empty($item['attachment_path']) || !file_exists($item['attachment_path'])) {
                throw new Exception('PDF attachment not found: ' . ($item['attachment_path'] ?? 'No path'));
            }

            $chatgpt = new IDokladProcessor_ChatGPTIntegration();
            $context = array(
                'file_name' => basename($item['attachment_path']),
                'email_from' => isset($item['email_from']) ? $item['email_from'] : '',
                'email_subject' => isset($item['email_subject']) ? $item['email_subject'] : '',
                'queue_id' => isset($item['id']) ? $item['id'] : null,
            );

            $parsed_data = $chatgpt->extract_invoice_data_from_pdf($item['attachment_path'], $context);
            $payload = $chatgpt->build_idoklad_payload($parsed_data, $context);

            $client_id = get_option('idoklad_client_id');
            $client_secret = get_option('idoklad_client_secret');

            if (empty($client_id) || empty($client_secret)) {
                throw new Exception('iDoklad API credentials not configured');
            }

            $integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
            $invoice_result = $integration->create_invoice_complete_workflow($payload);

            if (empty($invoice_result['success'])) {
                $message = isset($invoice_result['message']) ? $invoice_result['message'] : __('Unknown error creating invoice', 'idoklad-invoice-processor');
                throw new Exception($message);
            }

            return array(
                'success' => true,
                'data' => array(
                    'processed_at' => current_time('mysql'),
                    'item_id' => $item['id'] ?? null,
                    'invoice_id' => $invoice_result['invoice_id'] ?? null,
                    'document_number' => $invoice_result['document_number'] ?? null,
                    'parsed_data' => $parsed_data,
                    'idoklad_payload' => $payload,
                    'invoice_result' => $invoice_result,
                ),
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
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

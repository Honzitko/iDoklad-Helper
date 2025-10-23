<?php
/**
 * Enhanced User Manager with comprehensive functionality and user-friendly interface
 * Provides robust user management with deep email integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_UserManagerV3 {
    
    private $logger;
    private $database;
    private $notification;
    
    public function __construct() {
        $this->logger = IDokladProcessor_Logger::get_instance();
        $this->database = new IDokladProcessor_Database();
        
        // Initialize notification only if the class exists
        if (class_exists('IDokladProcessor_NotificationV3')) {
            $this->notification = new IDokladProcessor_NotificationV3();
        } else {
            $this->notification = new IDokladProcessor_Notification();
        }
        
        // Hook into user actions
        add_action('wp_ajax_idoklad_create_user', array($this, 'ajax_create_user'));
        add_action('wp_ajax_idoklad_update_user', array($this, 'ajax_update_user'));
        add_action('wp_ajax_idoklad_delete_user', array($this, 'ajax_delete_user'));
        add_action('wp_ajax_idoklad_test_user_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_idoklad_get_user_stats', array($this, 'ajax_get_user_stats'));
    }
    
    /**
     * Create new user with comprehensive setup
     */
    public function create_user($user_data) {
        try {
            $this->logger->info('Creating new user: ' . $user_data['email']);
            
            // Validate user data
            $validation_result = $this->validate_user_data($user_data);
            if (!$validation_result['valid']) {
                throw new Exception('Invalid user data: ' . implode(', ', $validation_result['errors']));
            }
            
            // Check if user already exists
            if ($this->user_exists($user_data['email'])) {
                throw new Exception('User with this email already exists');
            }
            
            // Create user record
            $user_id = $this->database->create_user($user_data);
            
            if (!$user_id) {
                throw new Exception('Failed to create user in database');
            }
            
            // Test iDoklad connection if credentials provided
            if (!empty($user_data['idoklad_client_id']) && !empty($user_data['idoklad_client_secret'])) {
                $connection_result = $this->test_user_idoklad_connection($user_id);
                if (!$connection_result['success']) {
                    $this->logger->warning('iDoklad connection test failed for new user: ' . $connection_result['message']);
                }
            }
            
            // Send welcome email
            $this->send_welcome_email($user_data);
            
            // Send admin notification
            $this->notification->send_admin_notification(
                'New User Created',
                "New user created:\n" .
                "Name: " . ($user_data['name'] ?? 'N/A') . "\n" .
                "Email: " . $user_data['email'] . "\n" .
                "Created at: " . date('Y-m-d H:i:s')
            );
            
            $this->logger->info('User created successfully: ' . $user_id);
            
            return array(
                'success' => true,
                'user_id' => $user_id,
                'message' => 'User created successfully'
            );
            
        } catch (Exception $e) {
            $this->logger->info('Error creating user: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Update user with comprehensive validation
     */
    public function update_user($user_id, $user_data) {
        try {
            $this->logger->info('Updating user: ' . $user_id);
            
            // Validate user data
            $validation_result = $this->validate_user_data($user_data, $user_id);
            if (!$validation_result['valid']) {
                throw new Exception('Invalid user data: ' . implode(', ', $validation_result['errors']));
            }
            
            // Update user record
            $result = $this->database->update_user($user_id, $user_data);
            
            if (!$result) {
                throw new Exception('Failed to update user in database');
            }
            
            // Test iDoklad connection if credentials were updated
            if (!empty($user_data['idoklad_client_id']) || !empty($user_data['idoklad_client_secret'])) {
                $connection_result = $this->test_user_idoklad_connection($user_id);
                if (!$connection_result['success']) {
                    $this->logger->warning('iDoklad connection test failed for updated user: ' . $connection_result['message']);
                }
            }
            
            // Send update notification
            $this->send_user_update_notification($user_id, $user_data);
            
            $this->logger->info('User updated successfully: ' . $user_id);
            
            return array(
                'success' => true,
                'message' => 'User updated successfully'
            );
            
        } catch (Exception $e) {
            $this->logger->info('Error updating user: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Delete user with cleanup
     */
    public function delete_user($user_id) {
        try {
            $this->logger->info('Deleting user: ' . $user_id);
            
            // Get user data before deletion
            $user = $this->database->get_user($user_id);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Delete user record
            $result = $this->database->delete_user($user_id);
            
            if (!$result) {
                throw new Exception('Failed to delete user from database');
            }
            
            // Send deletion notification
            $this->notification->send_admin_notification(
                'User Deleted',
                "User deleted:\n" .
                "Name: " . ($user->name ?? 'N/A') . "\n" .
                "Email: " . $user->email . "\n" .
                "Deleted at: " . date('Y-m-d H:i:s')
            );
            
            $this->logger->info('User deleted successfully: ' . $user_id);
            
            return array(
                'success' => true,
                'message' => 'User deleted successfully'
            );
            
        } catch (Exception $e) {
            $this->logger->info('Error deleting user: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Test user's iDoklad connection
     */
    public function test_user_idoklad_connection($user_id) {
        try {
            $user = $this->database->get_user($user_id);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // TODO: iDoklad credentials check removed - to be rebuilt
            // if (empty($user->idoklad_client_id) || empty($user->idoklad_client_secret)) {
            //     throw new Exception('iDoklad credentials not configured');
            // }
            
            // TODO: iDoklad API integration removed - to be rebuilt
            // $idoklad_api = new IDokladProcessor_IDokladAPIV3($user);
            // $connection_result = $idoklad_api->test_connection();
            
            // Temporary placeholder response
            $connection_result = array('success' => false, 'message' => 'iDoklad integration removed - to be rebuilt');
            
            // Update connection status
            $this->database->update_user($user_id, array(
                'connection_status' => $connection_result['success'] ? 'connected' : 'failed',
                'last_connection_test' => current_time('mysql'),
                'connection_error' => $connection_result['success'] ? null : $connection_result['message']
            ));
            
            return $connection_result;
            
        } catch (Exception $e) {
            $this->logger->info('Error testing user connection: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get user statistics
     */
    public function get_user_statistics($user_id) {
        try {
            $user = $this->database->get_user($user_id);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            $stats = array(
                'user_id' => $user_id,
                'total_emails_processed' => $user->total_emails_processed ?? 0,
                'total_documents_processed' => $user->total_documents_processed ?? 0,
                'total_invoices_created' => $user->total_invoices_created ?? 0,
                'total_expenses_created' => $user->total_expenses_created ?? 0,
                'total_contacts_created' => $user->total_contacts_created ?? 0,
                'last_email_processed' => $user->last_email_processed ?? null,
                'connection_status' => $user->connection_status ?? 'unknown',
                'last_connection_test' => $user->last_connection_test ?? null,
                'created_at' => $user->created_at ?? null,
                'updated_at' => $user->updated_at ?? null
            );
            
            // Get additional statistics from logs
            $recent_activity = $this->get_user_recent_activity($user_id);
            $stats['recent_activity'] = $recent_activity;
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logger->info('Error getting user statistics: ' . $e->getMessage(), 'error');
            return array(
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get user recent activity
     */
    private function get_user_recent_activity($user_id) {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'idoklad_logs';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $logs_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 10",
            $user_id
        ));
        
        return $results ?: array();
    }
    
    /**
     * Validate user data
     */
    private function validate_user_data($user_data, $user_id = null) {
        $errors = array();
        $warnings = array();
        
        // Required fields
        if (empty($user_data['email'])) {
            $errors[] = 'Email is required';
        } elseif (!is_email($user_data['email'])) {
            $errors[] = 'Invalid email format';
        }
        
        if (empty($user_data['name'])) {
            $errors[] = 'Name is required';
        }
        
        // iDoklad credentials validation
        if (!empty($user_data['idoklad_client_id']) && empty($user_data['idoklad_client_secret'])) {
            $errors[] = 'iDoklad client secret is required when client ID is provided';
        }
        
        if (!empty($user_data['idoklad_client_secret']) && empty($user_data['idoklad_client_id'])) {
            $errors[] = 'iDoklad client ID is required when client secret is provided';
        }
        
        // Email settings validation
        if (!empty($user_data['email_host']) && empty($user_data['email_username'])) {
            $errors[] = 'Email username is required when email host is provided';
        }
        
        if (!empty($user_data['email_username']) && empty($user_data['email_password'])) {
            $errors[] = 'Email password is required when email username is provided';
        }
        
        // Check for duplicate email (only when creating new user or changing email)
        if (empty($user_id) || (isset($user_data['email']) && $user_data['email'] !== $this->get_user_email($user_id))) {
            if ($this->user_exists($user_data['email'])) {
                $errors[] = 'User with this email already exists';
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        );
    }
    
    /**
     * Check if user exists
     */
    private function user_exists($email) {
        return $this->database->get_user_by_email($email) !== null;
    }
    
    /**
     * Get user email
     */
    private function get_user_email($user_id) {
        $user = $this->database->get_user($user_id);
        return $user ? $user->email : null;
    }
    
    /**
     * Send welcome email to new user
     */
    private function send_welcome_email($user_data) {
        try {
            $subject = 'Welcome to iDoklad Invoice Processor';
            
            $message = $this->build_welcome_email_template($user_data);
            
            $this->notification->send_email($user_data['email'], $subject, $message);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending welcome email: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send user update notification
     */
    private function send_user_update_notification($user_id, $user_data) {
        try {
            $user = $this->database->get_user($user_id);
            if (!$user) {
                return;
            }
            
            $subject = 'Your iDoklad Account Has Been Updated';
            
            $message = $this->build_user_update_template($user, $user_data);
            
            $this->notification->send_email($user->email, $subject, $message);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending user update notification: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Build welcome email template
     */
    private function build_welcome_email_template($user_data) {
        $template = '<html><body>';
        $template .= '<h2>Welcome to iDoklad Invoice Processor</h2>';
        
        $template .= '<p>Hello ' . ($user_data['name'] ?? 'there') . ',</p>';
        
        $template .= '<p>Your account has been successfully created. You can now start using our automated invoice processing system.</p>';
        
        $template .= '<h3>Getting Started:</h3>';
        $template .= '<ol>';
        $template .= '<li>Configure your iDoklad API credentials</li>';
        $template .= '<li>Set up your email monitoring settings</li>';
        $template .= '<li>Start sending invoices via email for automatic processing</li>';
        $template .= '</ol>';
        
        $template .= '<h3>Features Available:</h3>';
        $template .= '<ul>';
        $template .= '<li>Automatic PDF invoice processing</li>';
        $template .= '<li>Email-based invoice submission</li>';
        $template .= '<li>Real-time processing notifications</li>';
        $template .= '<li>Comprehensive reporting</li>';
        $template .= '</ul>';
        
        $template .= '<p>If you have any questions, please don\'t hesitate to contact our support team.</p>';
        
        $template .= '<p>Best regards,<br>The iDoklad Team</p>';
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Build user update template
     */
    private function build_user_update_template($user, $updated_data) {
        $template = '<html><body>';
        $template .= '<h2>Your Account Has Been Updated</h2>';
        
        $template .= '<p>Hello ' . ($user->name ?? 'there') . ',</p>';
        
        $template .= '<p>Your iDoklad Invoice Processor account has been updated with the following changes:</p>';
        
        $template .= '<h3>Updated Information:</h3>';
        $template .= '<ul>';
        
        if (isset($updated_data['name'])) {
            $template .= '<li><strong>Name:</strong> ' . $updated_data['name'] . '</li>';
        }
        
        if (isset($updated_data['email'])) {
            $template .= '<li><strong>Email:</strong> ' . $updated_data['email'] . '</li>';
        }
        
        if (isset($updated_data['idoklad_client_id'])) {
            $template .= '<li><strong>iDoklad Client ID:</strong> Updated</li>';
        }
        
        if (isset($updated_data['email_host'])) {
            $template .= '<li><strong>Email Host:</strong> Updated</li>';
        }
        
        $template .= '</ul>';
        
        $template .= '<p>If you did not request these changes, please contact our support team immediately.</p>';
        
        $template .= '<p>Best regards,<br>The iDoklad Team</p>';
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * AJAX handler for creating user
     */
    public function ajax_create_user() {
        check_ajax_referer('idoklad_user_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $user_data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'idoklad_client_id' => sanitize_text_field($_POST['idoklad_client_id'] ?? ''),
            'idoklad_client_secret' => sanitize_text_field($_POST['idoklad_client_secret'] ?? ''),
            'idoklad_redirect_uri' => esc_url_raw($_POST['idoklad_redirect_uri'] ?? ''),
            'email_host' => sanitize_text_field($_POST['email_host'] ?? ''),
            'email_port' => intval($_POST['email_port'] ?? 993),
            'email_username' => sanitize_text_field($_POST['email_username'] ?? ''),
            'email_password' => sanitize_text_field($_POST['email_password'] ?? ''),
            'email_encryption' => sanitize_text_field($_POST['email_encryption'] ?? 'ssl'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        $result = $this->create_user($user_data);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for updating user
     */
    public function ajax_update_user() {
        check_ajax_referer('idoklad_user_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        $user_data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'idoklad_client_id' => sanitize_text_field($_POST['idoklad_client_id'] ?? ''),
            'idoklad_client_secret' => sanitize_text_field($_POST['idoklad_client_secret'] ?? ''),
            'idoklad_redirect_uri' => esc_url_raw($_POST['idoklad_redirect_uri'] ?? ''),
            'email_host' => sanitize_text_field($_POST['email_host'] ?? ''),
            'email_port' => intval($_POST['email_port'] ?? 993),
            'email_username' => sanitize_text_field($_POST['email_username'] ?? ''),
            'email_password' => sanitize_text_field($_POST['email_password'] ?? ''),
            'email_encryption' => sanitize_text_field($_POST['email_encryption'] ?? 'ssl'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        $result = $this->update_user($user_id, $user_data);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for deleting user
     */
    public function ajax_delete_user() {
        check_ajax_referer('idoklad_user_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        $result = $this->delete_user($user_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for testing connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('idoklad_user_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        $result = $this->test_user_idoklad_connection($user_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for getting user stats
     */
    public function ajax_get_user_stats() {
        check_ajax_referer('idoklad_user_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        $result = $this->get_user_statistics($user_id);
        
        wp_send_json($result);
    }
    
    /**
     * Get all users with enhanced information
     */
    public function get_all_users_with_stats() {
        try {
            $users = $this->database->get_all_users();
            $users_with_stats = array();
            
            foreach ($users as $user) {
                $stats = $this->get_user_statistics($user->id);
                $users_with_stats[] = array(
                    'user' => $user,
                    'stats' => $stats
                );
            }
            
            return $users_with_stats;
            
        } catch (Exception $e) {
            $this->logger->info('Error getting users with stats: ' . $e->getMessage(), 'error');
            return array();
        }
    }
    
    /**
     * Bulk operations for users
     */
    public function bulk_user_operation($operation, $user_ids) {
        try {
            $results = array();
            
            foreach ($user_ids as $user_id) {
                switch ($operation) {
                    case 'activate':
                        $result = $this->update_user($user_id, array('is_active' => 1));
                        break;
                        
                    case 'deactivate':
                        $result = $this->update_user($user_id, array('is_active' => 0));
                        break;
                        
                    case 'test_connections':
                        $result = $this->test_user_idoklad_connection($user_id);
                        break;
                        
                    default:
                        $result = array('success' => false, 'message' => 'Unknown operation');
                }
                
                $results[$user_id] = $result;
            }
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->info('Error in bulk user operation: ' . $e->getMessage(), 'error');
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Export user data
     */
    public function export_user_data($user_id) {
        try {
            $user = $this->database->get_user($user_id);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            $stats = $this->get_user_statistics($user_id);
            
            $export_data = array(
                'user_info' => $user,
                'statistics' => $stats,
                'export_date' => date('Y-m-d H:i:s'),
                'exported_by' => get_current_user_id()
            );
            
            return $export_data;
            
        } catch (Exception $e) {
            $this->logger->info('Error exporting user data: ' . $e->getMessage(), 'error');
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Import user data
     */
    public function import_user_data($import_data) {
        try {
            if (empty($import_data['user_info'])) {
                throw new Exception('Invalid import data');
            }
            
            $user_data = $import_data['user_info'];
            
            // Remove ID to create new user
            unset($user_data['id']);
            unset($user_data['created_at']);
            unset($user_data['updated_at']);
            
            $result = $this->create_user($user_data);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->info('Error importing user data: ' . $e->getMessage(), 'error');
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
}

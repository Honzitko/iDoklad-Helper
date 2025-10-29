<?php
/**
 * Database management class - Updated for per-user iDoklad credentials
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_Database {
    
    public function __construct() {
        // Constructor can be used for additional initialization if needed
    }
    
    /**
     * Create database tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for authorized users (iDoklad integration removed - to be rebuilt)
        $table_users = $wpdb->prefix . 'idoklad_users';
        $sql_users = "CREATE TABLE $table_users (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            name varchar(100) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) $charset_collate;";
        
        // Table for processing logs
        $table_logs = $wpdb->prefix . 'idoklad_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email_from varchar(100) NOT NULL,
            email_subject varchar(255) DEFAULT NULL,
            attachment_name varchar(255) DEFAULT NULL,
            processing_status enum('pending','processing','success','failed') DEFAULT 'pending',
            extracted_data longtext DEFAULT NULL,
            api_response longtext DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY email_from (email_from),
            KEY processing_status (processing_status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table for email processing queue
        $table_queue = $wpdb->prefix . 'idoklad_queue';
        $sql_queue = "CREATE TABLE $table_queue (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email_id varchar(100) NOT NULL,
            email_from varchar(100) NOT NULL,
            email_subject varchar(255) DEFAULT NULL,
            attachment_path varchar(500) DEFAULT NULL,
            status enum('pending','processing','completed','failed') DEFAULT 'pending',
            processing_details longtext DEFAULT NULL,
            current_step varchar(255) DEFAULT NULL,
            attempts int(3) DEFAULT 0,
            max_attempts int(3) DEFAULT 3,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email_id (email_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_users);
        dbDelta($sql_logs);
        dbDelta($sql_queue);
        
        // Log the table creation
        error_log('iDoklad Invoice Processor: Database tables created successfully');
    }
    
    /**
     * Get authorized user by email
     */
    public static function get_authorized_user($email) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND is_active = 1",
            $email
        ));
    }
    
    /**
     * Add new authorized user (iDoklad integration removed - to be rebuilt)
     */
    public static function add_authorized_user($email, $name) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_users';
        
        return $wpdb->insert(
            $table,
            array(
                'email' => $email,
                'name' => $name
            ),
            array('%s', '%s')
        );
    }
    
    /**
     * Update authorized user
     */
    public static function update_authorized_user($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_users';
        
        return $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d'),
            array('%d')
        );
    }
    
    /**
     * Delete authorized user
     */
    public static function delete_authorized_user($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_users';
        
        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }
    
    /**
     * Get all authorized users
     */
    public static function get_all_authorized_users() {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_users';

        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    /**
     * Backwards compatible wrapper used by legacy classes expecting get_all_users()
     */
    public function get_all_users() {
        return self::get_all_authorized_users();
    }
    
    /**
     * Add processing log entry
     */
    public static function add_log($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_logs';
        
        $inserted = $wpdb->insert(
            $table,
            array(
                'email_from' => $data['email_from'],
                'email_subject' => $data['email_subject'] ?? null,
                'attachment_name' => $data['attachment_name'] ?? null,
                'processing_status' => $data['processing_status'] ?? 'pending',
                'extracted_data' => isset($data['extracted_data']) ? json_encode($data['extracted_data']) : null,
                'api_response' => isset($data['api_response']) ? json_encode($data['api_response']) : null,
                'error_message' => $data['error_message'] ?? null
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }
    
    /**
     * Update processing log
     */
    public static function update_log($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_logs';
        
        $update_data = array();
        $format = array();
        
        if (isset($data['processing_status'])) {
            $update_data['processing_status'] = $data['processing_status'];
            $format[] = '%s';
        }
        
        if (isset($data['extracted_data'])) {
            $update_data['extracted_data'] = json_encode($data['extracted_data']);
            $format[] = '%s';
        }
        
        if (isset($data['idoklad_response'])) {
            $update_data['idoklad_response'] = json_encode($data['idoklad_response']);
            $format[] = '%s';
        }
        
        if (isset($data['error_message'])) {
            $update_data['error_message'] = $data['error_message'];
            $format[] = '%s';
        }
        
        if (isset($data['processed_at'])) {
            $update_data['processed_at'] = $data['processed_at'];
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Get processing logs
     */
    public static function get_logs($limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }
    
    /**
     * Add email to processing queue
     */
    public static function add_to_queue($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_queue';

        $inserted = $wpdb->insert(
            $table,
            array(
                'email_id' => $data['email_id'],
                'email_from' => $data['email_from'],
                'email_subject' => $data['email_subject'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }
    
    /**
     * Get pending emails from queue
     */
    public static function get_pending_queue($limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_queue';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'pending' AND attempts < max_attempts ORDER BY created_at ASC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Update queue item status
     */
    public static function update_queue_status($id, $status, $increment_attempts = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_queue';
        
        $update_data = array('status' => $status);
        $format = array('%s');
        
        if ($increment_attempts) {
            $update_data['attempts'] = $wpdb->get_var($wpdb->prepare(
                "SELECT attempts FROM $table WHERE id = %d",
                $id
            )) + 1;
            $format[] = '%d';
        }
        
        if ($status === 'completed' || $status === 'failed') {
            $update_data['processed_at'] = current_time('mysql');
            $format[] = '%s';
        }
        
        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Add processing step details to queue item
     */
    public static function add_queue_step($id, $step_name, $step_data = null, $update_current_step = true) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_queue';
        
        // Get current processing details
        $current_details = $wpdb->get_var($wpdb->prepare(
            "SELECT processing_details FROM $table WHERE id = %d",
            $id
        ));
        
        $details = $current_details ? json_decode($current_details, true) : array();
        if (!is_array($details)) {
            $details = array();
        }
        
        // Add new step with timestamp
        $details[] = array(
            'step' => $step_name,
            'timestamp' => current_time('mysql'),
            'data' => $step_data
        );
        
        $update_data = array(
            'processing_details' => json_encode($details)
        );
        $format = array('%s');
        
        if ($update_current_step) {
            $update_data['current_step'] = $step_name;
            $format[] = '%s';
        }
        
        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Get all queue items with optional status filter
     */
    public static function get_queue_items($status = null, $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_queue';
        
        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE status = %s ORDER BY created_at DESC LIMIT %d",
                $status,
                $limit
            ));
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
                $limit
            ));
        }
    }
    
    /**
     * Reset items stuck in processing status (older than 5 minutes)
     */
    public static function reset_stuck_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_queue';
        
        // Find items stuck in "processing" for more than 5 minutes
        $stuck_items = $wpdb->get_results(
            "SELECT * FROM $table 
             WHERE status = 'processing' 
             AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
        
        $count = 0;
        foreach ($stuck_items as $item) {
            // Add error step
            self::add_queue_step($item->id, 'ERROR: Processing timeout - reset to pending', array(
                'stuck_for' => 'over 5 minutes',
                'last_step' => $item->current_step
            ));
            
            // Reset to pending with incremented attempts
            $wpdb->update(
                $table,
                array(
                    'status' => 'pending',
                    'current_step' => 'Reset due to timeout'
                ),
                array('id' => $item->id),
                array('%s', '%s'),
                array('%d')
            );
            
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Get queue statistics
     */
    public function get_queue_statistics() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'idoklad_queue';
        
        $stats = $wpdb->get_results("
            SELECT 
                status,
                COUNT(*) as count
            FROM $table 
            GROUP BY status
        ", ARRAY_A);
        
        $result = array(
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'total' => 0
        );
        
        foreach ($stats as $stat) {
            $result[$stat['status']] = intval($stat['count']);
            $result['total'] += intval($stat['count']);
        }
        
        return $result;
    }
    
    /**
     * Get single log by ID
     */
    public function get_log($log_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'idoklad_logs';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $log_id
        ));
    }
    
    /**
     * Delete log by ID
     */
    public function delete_log($log_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'idoklad_logs';
        
        return $wpdb->delete(
            $table,
            array('id' => $log_id),
            array('%d')
        );
    }
    
    /**
     * Get single queue item by ID
     */
    public function get_queue_item($item_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'idoklad_queue';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $item_id
        ));
        
        // Convert to array format for consistency
        if ($result) {
            return (array) $result;
        }
        
        return null;
    }
    
    /**
     * Update queue item
     */
    public function update_queue_item($item_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'idoklad_queue';
        
        // Build format array based on field types
        $format = array();
        foreach ($data as $key => $value) {
            if ($key === 'attempts' || $key === 'max_attempts') {
                $format[] = '%d'; // Integer
            } else {
                $format[] = '%s'; // String
            }
        }
        
        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $item_id),
            $format,
            array('%d')
        );
        
        // Log the update attempt for debugging
        if ($result === false) {
            error_log('Queue item update failed. SQL Error: ' . $wpdb->last_error);
            error_log('Update data: ' . print_r($data, true));
            error_log('Item ID: ' . $item_id);
        }
        
        return $result;
    }
}

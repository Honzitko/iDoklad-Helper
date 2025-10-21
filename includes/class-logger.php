<?php
/**
 * Logging system class
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_Logger {
    
    private static $instance = null;
    private $log_file;
    private $max_log_size = 10485760; // 10MB
    private $max_log_files = 5;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/idoklad-logs';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $this->log_file = $log_dir . '/idoklad-processor.log';
    }
    
    /**
     * Log debug message
     */
    public function debug($message, $context = array()) {
        if (get_option('idoklad_debug_mode')) {
            $this->log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Log info message
     */
    public function info($message, $context = array()) {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning($message, $context = array()) {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error($message, $context = array()) {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Main logging method
     */
    private function log($level, $message, $context = array()) {
        $timestamp = current_time('Y-m-d H:i:s');
        $context_string = !empty($context) ? ' ' . json_encode($context) : '';
        $log_entry = "[$timestamp] [$level] $message$context_string" . PHP_EOL;
        
        // Write to file
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Also write to WordPress error log if debug mode is enabled
        if (get_option('idoklad_debug_mode')) {
            error_log("iDoklad Processor [$level]: $message" . $context_string);
        }
    }
    
    /**
     * Export logs to CSV
     */
    public function export_logs_to_csv($start_date = null, $end_date = null) {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'idoklad_logs';
        
        $where_clause = '';
        $params = array();
        
        if ($start_date) {
            $where_clause .= ' AND DATE(created_at) >= %s';
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where_clause .= ' AND DATE(created_at) <= %s';
            $params[] = $end_date;
        }
        
        $query = "SELECT * FROM $logs_table WHERE 1=1 $where_clause ORDER BY created_at DESC";
        
        if (!empty($params)) {
            $logs = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $logs = $wpdb->get_results($query);
        }
        
        $csv_data = array();
        $csv_data[] = array(
            'ID', 'Email From', 'Email Subject', 'Attachment Name', 
            'Processing Status', 'Created At', 'Processed At', 'Error Message'
        );
        
        foreach ($logs as $log) {
            $csv_data[] = array(
                $log->id,
                $log->email_from,
                $log->email_subject,
                $log->attachment_name,
                $log->processing_status,
                $log->created_at,
                $log->processed_at,
                $log->error_message
            );
        }
        
        return $csv_data;
    }
}

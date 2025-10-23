<?php
/**
 * Logging system class
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_Logger {

    private static $instance = null;
    private $log_dir;
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

        $default_dir = trailingslashit($upload_dir['basedir']) . 'idoklad-logs';
        /**
         * Filters the log directory used by the iDoklad processor logger.
         *
         * @since 1.1.1
         *
         * @param string $default_dir The default log directory inside the uploads folder.
         */
        $this->log_dir = apply_filters('idoklad_processor_log_directory', $default_dir);

        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }

        if (!$this->is_writable_directory($this->log_dir)) {
            $fallback_directories = array(
                trailingslashit($upload_dir['basedir']) . 'idoklad-logs',
                trailingslashit(WP_CONTENT_DIR) . 'idoklad-logs',
            );

            foreach ($fallback_directories as $fallback_directory) {
                if (!file_exists($fallback_directory)) {
                    wp_mkdir_p($fallback_directory);
                }

                if ($this->is_writable_directory($fallback_directory)) {
                    $this->log_dir = $fallback_directory;
                    break;
                }
            }

            if (!$this->is_writable_directory($this->log_dir)) {
                error_log('iDoklad Processor: Log directory is not writable. Check permissions for: ' . $this->log_dir);
            }
        }

        $this->log_file = trailingslashit($this->log_dir) . 'idoklad-processor.log';

        /**
         * Filters the maximum size a log file can reach before rotation starts.
         *
         * @since 1.1.1
         *
         * @param int $max_log_size Maximum size in bytes. Defaults to 10MB.
         */
        $this->max_log_size = (int) apply_filters('idoklad_processor_max_log_size', $this->max_log_size);

        /**
         * Filters the number of rotated log files that should be kept.
         *
         * @since 1.1.1
         *
         * @param int $max_log_files Number of rotated log files. Defaults to 5.
         */
        $this->max_log_files = max(1, (int) apply_filters('idoklad_processor_max_log_files', $this->max_log_files));

        if (!file_exists($this->log_file)) {
            @touch($this->log_file);
        }
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

        $this->maybe_rotate_logs();

        // Write to file
        $bytes_written = @file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);

        if (false === $bytes_written) {
            error_log('iDoklad Processor: Failed to write to log file: ' . $this->log_file);
        }

        // Also write to WordPress error log if debug mode is enabled
        if (get_option('idoklad_debug_mode')) {
            error_log("iDoklad Processor [$level]: $message" . $context_string);
        }
    }

    /**
     * Rotate logs if necessary to keep file size manageable.
     */
    private function maybe_rotate_logs() {
        if (!file_exists($this->log_file)) {
            return;
        }

        clearstatcache(true, $this->log_file);
        $file_size = filesize($this->log_file);
        if (false === $file_size || $file_size < $this->max_log_size) {
            return;
        }

        // Remove the oldest file if it already exists beyond the retention limit.
        $oldest_file = $this->log_file . '.' . $this->max_log_files;
        if (file_exists($oldest_file)) {
            @unlink($oldest_file);
        }

        // Shift existing rotated files.
        for ($index = $this->max_log_files - 1; $index >= 1; $index--) {
            $source = $this->log_file . '.' . $index;
            if (file_exists($source)) {
                $destination = $this->log_file . '.' . ($index + 1);
                @rename($source, $destination);
            }
        }

        // Rotate the current log file.
        @rename($this->log_file, $this->log_file . '.1');
        @touch($this->log_file);
    }

    /**
     * Checks if a directory is writable in the current environment.
     *
     * @param string $directory Directory path.
     * @return bool
     */
    private function is_writable_directory($directory) {
        if (!is_dir($directory)) {
            return false;
        }

        if (function_exists('wp_is_writable')) {
            return wp_is_writable($directory);
        }

        return is_writable($directory);
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

<?php
/**
 * Uninstall script for iDoklad Invoice Processor
 * 
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin data from the database.
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
$options_to_remove = array(
    'idoklad_email_host',
    'idoklad_email_port',
    'idoklad_email_username',
    'idoklad_email_password',
    'idoklad_email_encryption',
    'idoklad_chatgpt_api_key',
    'idoklad_chatgpt_model',
    'idoklad_chatgpt_prompt',
    'idoklad_notification_email',
    'idoklad_debug_mode'
);

foreach ($options_to_remove as $option) {
    delete_option($option);
}

// Remove database tables
global $wpdb;

$tables_to_remove = array(
    $wpdb->prefix . 'idoklad_users',
    $wpdb->prefix . 'idoklad_logs',
    $wpdb->prefix . 'idoklad_queue'
);

foreach ($tables_to_remove as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Clear scheduled events
wp_clear_scheduled_hook('idoklad_check_emails');

// Remove log files
$upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/idoklad-logs';

if (file_exists($log_dir)) {
    $files = glob($log_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($log_dir);
}

// Remove invoice files
$invoice_dir = $upload_dir['basedir'] . '/idoklad-invoices';

if (file_exists($invoice_dir)) {
    $files = glob($invoice_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($invoice_dir);
}

// Log uninstall action
error_log('iDoklad Invoice Processor: Plugin uninstalled and all data removed');

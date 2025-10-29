<?php
/**
 * Admin dashboard template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get statistics
global $wpdb;
$queue_table = $wpdb->prefix . 'idoklad_queue';
$logs_table = $wpdb->prefix . 'idoklad_logs';

$stats = array(
    'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'pending'"),
    'processing' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'processing'"),
    'completed' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'completed'"),
    'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'failed'"),
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table"),
    'today' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE DATE(created_at) = CURDATE()"),
    'last_processed' => $wpdb->get_var("SELECT MAX(processed_at) FROM $queue_table WHERE status = 'completed'")
);

$numeric_stat_keys = array('pending', 'processing', 'completed', 'failed', 'total', 'today');
foreach ($numeric_stat_keys as $key) {
    $stats[$key] = isset($stats[$key]) ? (int) $stats[$key] : 0;
}

$last_processed_timestamp = false;
if (!empty($stats['last_processed'])) {
    $parsed_time = strtotime($stats['last_processed']);
    if ($parsed_time !== false) {
        $last_processed_timestamp = $parsed_time;
    }
}

// Get recent queue items
$recent_items = $wpdb->get_results("SELECT * FROM $queue_table ORDER BY created_at DESC LIMIT 10");

// Get recent logs
$recent_logs = $wpdb->get_results("SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT 5");

// Get settings status
$email_monitor_enabled = get_option('idoklad_enable_email_monitor', false);
$authorized_users = IDokladProcessor_Database::get_all_authorized_users();

$recent_items = is_array($recent_items) ? $recent_items : array();
$recent_logs = is_array($recent_logs) ? $recent_logs : array();
$authorized_users = is_array($authorized_users) ? $authorized_users : array();
?>

<div class="wrap">
    <h1><?php _e('iDoklad Invoice Processor - Dashboard', 'idoklad-invoice-processor'); ?></h1>
    
    <div class="idoklad-dashboard">
        
        <!-- Quick Actions -->
        <div class="idoklad-quick-actions">
            <h2><?php _e('Quick Actions', 'idoklad-invoice-processor'); ?></h2>
            <div class="action-buttons">
                <button id="force-email-check" class="button button-primary button-hero">
                    <span class="dashicons dashicons-email"></span>
                    <?php _e('Check Emails Now', 'idoklad-invoice-processor'); ?>
                </button>
                <button id="process-queue-now" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Process Pending Queue', 'idoklad-invoice-processor'); ?>
                </button>
                <a href="<?php echo admin_url('admin.php?page=idoklad-processor-diagnostics'); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e('Diagnostics & Testing', 'idoklad-invoice-processor'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=idoklad-processor-email-processing'); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php _e('Email Processing Control', 'idoklad-invoice-processor'); ?>
                </a>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="idoklad-stats-grid">
            <div class="stat-card stat-pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                    <div class="stat-label"><?php _e('Pending', 'idoklad-invoice-processor'); ?></div>
                </div>
            </div>
            
            <div class="stat-card stat-processing">
                <div class="stat-icon">⚙️</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['processing']); ?></div>
                    <div class="stat-label"><?php _e('Processing', 'idoklad-invoice-processor'); ?></div>
                </div>
            </div>
            
            <div class="stat-card stat-completed">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['completed']); ?></div>
                    <div class="stat-label"><?php _e('Completed', 'idoklad-invoice-processor'); ?></div>
                </div>
            </div>
            
            <div class="stat-card stat-failed">
                <div class="stat-icon">❌</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['failed']); ?></div>
                    <div class="stat-label"><?php _e('Failed', 'idoklad-invoice-processor'); ?></div>
                </div>
            </div>
            
            <div class="stat-card stat-total">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label"><?php _e('Total Processed', 'idoklad-invoice-processor'); ?></div>
                </div>
            </div>
            
            <div class="stat-card stat-today">
                <div class="stat-icon">📅</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['today']); ?></div>
                    <div class="stat-label"><?php _e('Today', 'idoklad-invoice-processor'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="idoklad-system-status">
            <h2><?php _e('System Status', 'idoklad-invoice-processor'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Email Monitoring', 'idoklad-invoice-processor'); ?></strong></td>
                        <td>
                            <?php if ($email_monitor_enabled): ?>
                                <span class="status-badge status-active">✓ Active</span>
                            <?php else: ?>
                                <span class="status-badge status-inactive">✗ Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Authorized Users', 'idoklad-invoice-processor'); ?></strong></td>
                        <td>
                            <?php if (count($authorized_users) > 0): ?>
                                <span class="status-badge status-active"><?php echo count($authorized_users); ?> user(s)</span>
                            <?php else: ?>
                                <span class="status-badge status-warning">⚠ No users</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Last Processed', 'idoklad-invoice-processor'); ?></strong></td>
                        <td>
                            <?php if ($last_processed_timestamp): ?>
                                <?php echo esc_html(human_time_diff($last_processed_timestamp, current_time('timestamp')) . ' ' . __('ago', 'idoklad-invoice-processor')); ?>
                            <?php else: ?>
                                <?php _e('Never', 'idoklad-invoice-processor'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Recent Queue Items -->
        <div class="idoklad-recent-items">
            <h2><?php _e('Recent Queue Items', 'idoklad-invoice-processor'); ?></h2>
            <?php if (count($recent_items) > 0): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'idoklad-invoice-processor'); ?></th>
                            <th><?php _e('From', 'idoklad-invoice-processor'); ?></th>
                            <th><?php _e('Subject', 'idoklad-invoice-processor'); ?></th>
                            <th><?php _e('Status', 'idoklad-invoice-processor'); ?></th>
                            <th><?php _e('Actions', 'idoklad-invoice-processor'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_items as $item): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($item->created_at)); ?></td>
                                <td><?php echo esc_html($item->email_from); ?></td>
                                <td><?php echo esc_html($item->email_subject); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $item->status; ?>">
                                        <?php echo ucfirst($item->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="button button-small view-queue-details" data-queue-id="<?php echo $item->id; ?>">
                                        <?php _e('Details', 'idoklad-invoice-processor'); ?>
                                    </button>
                                    <?php if (in_array($item->status, array('pending', 'processing', 'failed'))): ?>
                                        <button class="button button-small cancel-queue-item" data-queue-id="<?php echo $item->id; ?>">
                                            <?php _e('Cancel', 'idoklad-invoice-processor'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=idoklad-processor-queue'); ?>" class="button">
                        <?php _e('View All Queue Items', 'idoklad-invoice-processor'); ?>
                    </a>
                </p>
            <?php else: ?>
                <p><?php _e('No queue items yet.', 'idoklad-invoice-processor'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Activity Log -->
        <div class="idoklad-recent-logs">
            <h2><?php _e('Recent Activity', 'idoklad-invoice-processor'); ?></h2>
            <?php if (count($recent_logs) > 0): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'idoklad-invoice-processor'); ?></th>
                            <th><?php _e('Type', 'idoklad-invoice-processor'); ?></th>
                            <th><?php _e('Status', 'idoklad-invoice-processor'); ?></th>
                            <th><?php _e('Email', 'idoklad-invoice-processor'); ?></th>
                            <th><?php _e('Details', 'idoklad-invoice-processor'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                            <?php
                                $action_type   = isset($log->action_type) ? (string) $log->action_type : '';
                                $status        = isset($log->status) ? (string) $log->status : '';
                                $email_from    = isset($log->email_from) ? (string) $log->email_from : '';
                                $details       = isset($log->details) ? (string) $log->details : '';
                                $status_slug   = $status !== '' ? sanitize_html_class(strtolower($status)) : 'unknown';
                                $status_label  = $status !== '' ? ucfirst($status) : __('Unknown', 'idoklad-invoice-processor');
                            ?>
                            <tr>
                                <td><?php echo human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' ago'; ?></td>
                                <td><?php echo esc_html($action_type); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($status_slug); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($email_from); ?></td>
                                <?php
                                    $details_preview = function_exists('mb_substr')
                                        ? mb_substr($details, 0, 100)
                                        : substr($details, 0, 100);
                                ?>
                                <td><?php echo esc_html($details_preview); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=idoklad-processor-logs'); ?>" class="button">
                        <?php _e('View All Logs', 'idoklad-invoice-processor'); ?>
                    </a>
                </p>
            <?php else: ?>
                <p><?php _e('No recent activity.', 'idoklad-invoice-processor'); ?></p>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- Queue Details Modal (reused from queue page) -->
<div id="queue-details-modal" class="idoklad-modal" style="display: none;">
    <div class="idoklad-modal-content">
        <span class="idoklad-modal-close">&times;</span>
        <div id="queue-details-content"></div>
    </div>
</div>

<style>
.idoklad-dashboard {
    max-width: 1400px;
}

.idoklad-quick-actions {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.action-buttons .button-hero {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.idoklad-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
    border-left: 4px solid #ddd;
}

.stat-card.stat-pending { border-left-color: #f0ad4e; }
.stat-card.stat-processing { border-left-color: #5bc0de; }
.stat-card.stat-completed { border-left-color: #5cb85c; }
.stat-card.stat-failed { border-left-color: #d9534f; }
.stat-card.stat-total { border-left-color: #0073aa; }
.stat-card.stat-today { border-left-color: #9b59b6; }

.stat-icon {
    font-size: 36px;
    line-height: 1;
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    line-height: 1;
    color: #333;
}

.stat-label {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

.idoklad-system-status,
.idoklad-recent-items,
.idoklad-recent-logs {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge.status-active {
    background: #d4edda;
    color: #155724;
}

.status-badge.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.status-warning {
    background: #fff3cd;
    color: #856404;
}

.status-badge.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.status-processing {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-badge.status-failed {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.status-success {
    background: #d4edda;
    color: #155724;
}
</style>


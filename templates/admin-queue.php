<?php
/**
 * Admin queue viewer template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get queue statistics
global $wpdb;
$queue_table = $wpdb->prefix . 'idoklad_queue';
$stats = array(
    'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'pending'"),
    'processing' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'processing'"),
    'completed' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'completed'"),
    'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'failed'")
);

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
?>

<div class="wrap">
    <h1><?php _e('Processing Queue', 'idoklad-invoice-processor'); ?></h1>
    
    <div class="idoklad-admin-container">
        <div class="idoklad-admin-main">
            <div class="idoklad-settings-section">
                <!-- Filter buttons -->
                <div class="queue-filters" style="margin-bottom: 20px;">
                    <a href="<?php echo admin_url('admin.php?page=idoklad-processor-queue'); ?>" 
                       class="button <?php echo empty($status_filter) ? 'button-primary' : ''; ?>">
                        <?php _e('All', 'idoklad-invoice-processor'); ?> 
                        (<?php echo array_sum($stats); ?>)
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=idoklad-processor-queue&status=pending'); ?>" 
                       class="button <?php echo $status_filter === 'pending' ? 'button-primary' : ''; ?>">
                        <?php _e('Pending', 'idoklad-invoice-processor'); ?> 
                        (<?php echo $stats['pending']; ?>)
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=idoklad-processor-queue&status=processing'); ?>" 
                       class="button <?php echo $status_filter === 'processing' ? 'button-primary' : ''; ?>">
                        <?php _e('Processing', 'idoklad-invoice-processor'); ?> 
                        (<?php echo $stats['processing']; ?>)
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=idoklad-processor-queue&status=completed'); ?>" 
                       class="button <?php echo $status_filter === 'completed' ? 'button-primary' : ''; ?>">
                        <?php _e('Completed', 'idoklad-invoice-processor'); ?> 
                        (<?php echo $stats['completed']; ?>)
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=idoklad-processor-queue&status=failed'); ?>" 
                       class="button <?php echo $status_filter === 'failed' ? 'button-primary' : ''; ?>">
                        <?php _e('Failed', 'idoklad-invoice-processor'); ?> 
                        (<?php echo $stats['failed']; ?>)
                    </a>
                    
                    <button type="button" id="refresh-queue" class="button button-secondary" style="margin-left: 20px;">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'idoklad-invoice-processor'); ?>
                    </button>
                </div>
                
                <?php if (empty($queue_items)): ?>
                    <p><?php _e('No queue items found.', 'idoklad-invoice-processor'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" id="queue-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php _e('ID', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('From', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Subject', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Attachment', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Status', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Current Step', 'idoklad-invoice-processor'); ?></th>
                                <th style="width: 100px;"><?php _e('Attempts', 'idoklad-invoice-processor'); ?></th>
                                <th style="width: 150px;"><?php _e('Created', 'idoklad-invoice-processor'); ?></th>
                                <th style="width: 100px;"><?php _e('Actions', 'idoklad-invoice-processor'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="queue-tbody">
                            <?php foreach ($queue_items as $item): ?>
                                <?php include IDOKLAD_PROCESSOR_PLUGIN_DIR . 'templates/partials/queue-row.php'; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="idoklad-admin-sidebar">
            <div class="idoklad-widget">
                <h3><?php _e('Queue Statistics', 'idoklad-invoice-processor'); ?></h3>
                <div class="queue-stats">
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Pending:', 'idoklad-invoice-processor'); ?></span>
                        <span class="stat-value" style="color: orange;"><?php echo $stats['pending']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Processing:', 'idoklad-invoice-processor'); ?></span>
                        <span class="stat-value" style="color: blue;"><?php echo $stats['processing']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Completed:', 'idoklad-invoice-processor'); ?></span>
                        <span class="stat-value" style="color: green;"><?php echo $stats['completed']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Failed:', 'idoklad-invoice-processor'); ?></span>
                        <span class="stat-value" style="color: red;"><?php echo $stats['failed']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('Queue Actions', 'idoklad-invoice-processor'); ?></h3>
                <p>
                    <button type="button" id="process-queue-button" class="button button-primary" style="width: 100%;">
                        <?php _e('Process Queue Now', 'idoklad-invoice-processor'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php _e('Manually trigger processing of pending items in the queue.', 'idoklad-invoice-processor'); ?>
                </p>
                
                <hr style="margin: 15px 0;">
                
                <p>
                    <button type="button" id="reset-stuck-items" class="button button-secondary" style="width: 100%;">
                        <?php _e('Reset Stuck Items', 'idoklad-invoice-processor'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php _e('Reset items stuck in "processing" status for more than 5 minutes back to pending.', 'idoklad-invoice-processor'); ?>
                </p>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('Auto-Refresh', 'idoklad-invoice-processor'); ?></h3>
                <p>
                    <label>
                        <input type="checkbox" id="auto-refresh-queue" />
                        <?php _e('Auto-refresh every 10 seconds', 'idoklad-invoice-processor'); ?>
                    </label>
                </p>
                <p class="description">
                    <?php _e('Automatically refresh the queue to see real-time updates.', 'idoklad-invoice-processor'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Queue Details Modal -->
<div id="queue-details-modal" class="idoklad-modal" style="display: none;">
    <div class="idoklad-modal-content" style="max-width: 800px;">
        <div class="idoklad-modal-header">
            <h2><?php _e('Queue Item Details', 'idoklad-invoice-processor'); ?></h2>
            <span class="idoklad-modal-close">&times;</span>
        </div>
        <div class="idoklad-modal-body">
            <div id="queue-details-content"></div>
        </div>
    </div>
</div>

<style>
.queue-stats {
    padding: 10px 0;
}
.stat-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.stat-label {
    font-weight: 600;
}
.stat-value {
    font-size: 18px;
    font-weight: bold;
}
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}
.status-pending {
    background-color: #fff3cd;
    color: #856404;
}
.status-processing {
    background-color: #cce5ff;
    color: #004085;
}
.status-completed {
    background-color: #d4edda;
    color: #155724;
}
.status-failed {
    background-color: #f8d7da;
    color: #721c24;
}
.current-step-text {
    font-size: 11px;
    color: #666;
    font-style: italic;
}
.processing-timeline {
    margin-top: 15px;
}
.timeline-step {
    display: flex;
    margin-bottom: 15px;
    padding: 10px;
    background: #f9f9f9;
    border-left: 3px solid #4CAF50;
    border-radius: 3px;
}
.timeline-step-error {
    border-left-color: #f44336;
    background: #ffebee;
}
.timeline-marker {
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    background: #4CAF50;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 15px;
}
.timeline-step-error .timeline-marker {
    background: #f44336;
}
.timeline-content {
    flex-grow: 1;
}
.timeline-timestamp {
    font-size: 11px;
    color: #999;
    margin-top: 3px;
}
.timeline-data {
    margin-top: 8px;
    padding: 8px;
    background: white;
    border-radius: 3px;
    font-size: 11px;
}
.timeline-data pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.queue-detail-section {
    margin-bottom: 25px;
}
.queue-detail-section h4 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #ddd;
}
</style>


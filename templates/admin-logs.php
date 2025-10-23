<?php
/**
 * Admin logs template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Processing Logs', 'idoklad-invoice-processor'); ?></h1>
    
    <div class="idoklad-admin-container">
        <div class="idoklad-admin-main">
            <div class="idoklad-settings-section">
                <!-- Bulk Actions -->
                <div class="bulk-actions" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <div>
                            <input type="checkbox" id="select-all-logs" />
                            <label for="select-all-logs" style="margin-left: 5px;">
                                <?php _e('Select All', 'idoklad-invoice-processor'); ?>
                            </label>
                        </div>
                        <div>
                            <button type="button" id="export-selected-logs" class="button button-secondary" disabled>
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Export Selected', 'idoklad-invoice-processor'); ?>
                            </button>
                            <button type="button" id="delete-selected-logs" class="button button-secondary" disabled>
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Delete Selected', 'idoklad-invoice-processor'); ?>
                            </button>
                        </div>
                        <div id="selected-logs-count" style="color: #666; font-size: 14px;">
                            <?php _e('0 logs selected', 'idoklad-invoice-processor'); ?>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($logs)): ?>
                    <p><?php _e('No processing logs found.', 'idoklad-invoice-processor'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" id="logs-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;">
                                    <input type="checkbox" id="select-all-logs-header" />
                                </th>
                                <th><?php _e('Date', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('From', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Subject', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Attachment', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Status', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Actions', 'idoklad-invoice-processor'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr data-log-id="<?php echo $log->id; ?>">
                                    <td>
                                        <input type="checkbox" class="log-checkbox" value="<?php echo $log->id; ?>" />
                                    </td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                                    <td><?php echo esc_html($log->email_from); ?></td>
                                    <td><?php echo esc_html($log->email_subject ?: '-'); ?></td>
                                    <td><?php echo esc_html($log->attachment_name ?: '-'); ?></td>
                                    <td>
                                        <?php
                                        $status_colors = array(
                                            'pending' => 'orange',
                                            'processing' => 'blue',
                                            'success' => 'green',
                                            'failed' => 'red'
                                        );
                                        $color = $status_colors[$log->processing_status] ?? 'gray';
                                        ?>
                                        <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                            <?php echo ucfirst($log->processing_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small view-log-details" data-log-id="<?php echo $log->id; ?>">
                                            <?php _e('View', 'idoklad-invoice-processor'); ?>
                                        </button>
                                        <button type="button" class="button button-small export-single-log" data-log-id="<?php echo $log->id; ?>" style="margin-left: 5px;">
                                            <?php _e('Export', 'idoklad-invoice-processor'); ?>
                                        </button>
                                        <button type="button" class="button button-small delete-single-log" data-log-id="<?php echo $log->id; ?>" style="margin-left: 5px; color: #d63638;">
                                            <?php _e('Delete', 'idoklad-invoice-processor'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav">
                            <div class="tablenav-pages">
                                <?php
                                $current_url = remove_query_arg('paged');
                                $pagination_args = array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'current' => $page,
                                    'total' => $total_pages,
                                    'prev_text' => __('&laquo; Previous', 'idoklad-invoice-processor'),
                                    'next_text' => __('Next &raquo;', 'idoklad-invoice-processor')
                                );
                                echo paginate_links($pagination_args);
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="idoklad-admin-sidebar">
            <div class="idoklad-widget">
                <h3><?php _e('Log Statistics', 'idoklad-invoice-processor'); ?></h3>
                <?php
                global $wpdb;
                $logs_table = $wpdb->prefix . 'idoklad_logs';
                $stats = $wpdb->get_results("
                    SELECT 
                        processing_status,
                        COUNT(*) as count
                    FROM $logs_table 
                    GROUP BY processing_status
                ");
                ?>
                <?php foreach ($stats as $stat): ?>
                    <p>
                        <strong><?php echo ucfirst($stat->processing_status); ?>:</strong> 
                        <?php echo $stat->count; ?>
                    </p>
                <?php endforeach; ?>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('Export Logs', 'idoklad-invoice-processor'); ?></h3>
                <p>
                    <button type="button" id="export-logs" class="button button-secondary">
                        <?php _e('Export CSV', 'idoklad-invoice-processor'); ?>
                    </button>
                </p>
                <p class="description"><?php _e('Download logs as CSV file', 'idoklad-invoice-processor'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div id="log-details-modal" class="idoklad-modal" style="display: none;">
    <div class="idoklad-modal-content">
        <div class="idoklad-modal-header">
            <h2><?php _e('Log Details', 'idoklad-invoice-processor'); ?></h2>
            <span class="idoklad-modal-close">&times;</span>
        </div>
        <div class="idoklad-modal-body">
            <div id="log-details-content"></div>
        </div>
    </div>
</div>

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
                <?php if (empty($logs)): ?>
                    <p><?php _e('No processing logs found.', 'idoklad-invoice-processor'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
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
                                <tr>
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
                                            <?php _e('View Details', 'idoklad-invoice-processor'); ?>
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

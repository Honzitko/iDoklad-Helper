<?php
/**
 * Database Management Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h2>Database Statistics</h2>
        <p>View and manage stored data. Clean up old logs, queue items, and attachments to free up space.</p>
        
        <button type="button" id="refresh-stats" class="button" style="margin-bottom: 20px;">
            🔄 Refresh Statistics
        </button>
        
        <div id="db-stats-container">
            <p><em>Loading statistics...</em></p>
        </div>
    </div>
    
    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h2>🗑️ Cleanup Tools</h2>
        <p class="description">
            <strong>Warning:</strong> These actions are permanent and cannot be undone. Please be careful when cleaning up data.
        </p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            
            <!-- Cleanup Logs -->
            <div class="cleanup-section" style="border: 1px solid #ccc; padding: 15px; border-radius: 5px;">
                <h3>📋 Processing Logs</h3>
                <p>Delete old processing logs to reduce database size.</p>
                
                <label for="logs-days">Delete logs older than:</label>
                <select id="logs-days" style="margin: 10px 0; width: 100%;">
                    <option value="30">30 days</option>
                    <option value="60">60 days</option>
                    <option value="90" selected>90 days</option>
                    <option value="180">180 days</option>
                    <option value="365">1 year</option>
                </select>
                
                <button type="button" id="cleanup-logs" class="button button-secondary" style="width: 100%;">
                    Delete Old Logs
                </button>
                <div id="cleanup-logs-result" style="margin-top: 10px;"></div>
            </div>
            
            <!-- Cleanup Queue -->
            <div class="cleanup-section" style="border: 1px solid #ccc; padding: 15px; border-radius: 5px;">
                <h3>📬 Processing Queue</h3>
                <p>Delete old queue items to free up space.</p>
                
                <label for="queue-days">Delete queue items older than:</label>
                <select id="queue-days" style="margin: 10px 0; width: 100%;">
                    <option value="7">7 days</option>
                    <option value="14">14 days</option>
                    <option value="30" selected>30 days</option>
                    <option value="60">60 days</option>
                    <option value="90">90 days</option>
                </select>
                
                <label for="queue-status">Status:</label>
                <select id="queue-status" style="margin: 10px 0; width: 100%;">
                    <option value="completed">Completed only</option>
                    <option value="failed">Failed only</option>
                    <option value="all">All statuses</option>
                </select>
                
                <button type="button" id="cleanup-queue" class="button button-secondary" style="width: 100%;">
                    Delete Old Queue Items
                </button>
                <div id="cleanup-queue-result" style="margin-top: 10px;"></div>
            </div>
            
            <!-- Cleanup Attachments -->
            <div class="cleanup-section" style="border: 1px solid #ccc; padding: 15px; border-radius: 5px;">
                <h3>📎 PDF Attachments</h3>
                <p>Delete old PDF files to free up disk space.</p>
                
                <label for="attachments-days">Delete attachments older than:</label>
                <select id="attachments-days" style="margin: 10px 0; width: 100%;">
                    <option value="7">7 days</option>
                    <option value="14">14 days</option>
                    <option value="30" selected>30 days</option>
                    <option value="60">60 days</option>
                    <option value="90">90 days</option>
                </select>
                
                <button type="button" id="cleanup-attachments" class="button button-secondary" style="width: 100%;">
                    Delete Old Attachments
                </button>
                <div id="cleanup-attachments-result" style="margin-top: 10px;"></div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Load statistics on page load
    loadStats();
    
    // Refresh button
    $('#refresh-stats').on('click', function() {
        loadStats();
    });
    
    // Load database statistics
    function loadStats() {
        $('#db-stats-container').html('<p><em>Loading statistics...</em></p>');
        
        $.post(ajaxurl, {
            action: 'idoklad_get_db_stats',
            nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                const data = response.data;
                
                let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">';
                
                // Logs stats
                html += '<div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #f9f9f9;">';
                html += '<h3 style="margin-top: 0;">📋 Processing Logs</h3>';
                html += '<p><strong>Total:</strong> ' + data.logs.total + ' entries</p>';
                html += '<p><strong>Older than 30 days:</strong> ' + data.logs.older_than_30_days + '</p>';
                html += '<p><strong>Older than 90 days:</strong> ' + data.logs.older_than_90_days + '</p>';
                html += '</div>';
                
                // Queue stats
                html += '<div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #f9f9f9;">';
                html += '<h3 style="margin-top: 0;">📬 Processing Queue</h3>';
                html += '<p><strong>Total:</strong> ' + data.queue.total + ' items</p>';
                html += '<p><strong>Older than 30 days:</strong> ' + data.queue.older_than_30_days + '</p>';
                html += '<p><strong>Completed:</strong> ' + data.queue.completed + '</p>';
                html += '<p><strong>Failed:</strong> ' + data.queue.failed + '</p>';
                html += '</div>';
                
                // Attachments stats
                html += '<div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #f9f9f9;">';
                html += '<h3 style="margin-top: 0;">📎 PDF Attachments</h3>';
                html += '<p><strong>Total files:</strong> ' + data.attachments.count + '</p>';
                html += '<p><strong>Total size:</strong> ' + data.attachments.size_mb + ' MB</p>';
                html += '</div>';
                
                html += '</div>';
                
                $('#db-stats-container').html(html);
            } else {
                $('#db-stats-container').html('<p style="color: red;">Error loading statistics</p>');
            }
        });
    }
    
    // Cleanup logs
    $('#cleanup-logs').on('click', function() {
        if (!confirm('Are you sure you want to delete old log entries? This cannot be undone.')) {
            return;
        }
        
        const $btn = $(this);
        const days = $('#logs-days').val();
        $btn.prop('disabled', true).text('Deleting...');
        
        $.post(ajaxurl, {
            action: 'idoklad_cleanup_old_logs',
            nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>',
            days: days
        }, function(response) {
            $btn.prop('disabled', false).text('Delete Old Logs');
            
            if (response.success) {
                $('#cleanup-logs-result').html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                loadStats();
            } else {
                $('#cleanup-logs-result').html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
            }
        });
    });
    
    // Cleanup queue
    $('#cleanup-queue').on('click', function() {
        if (!confirm('Are you sure you want to delete old queue items? This cannot be undone.')) {
            return;
        }
        
        const $btn = $(this);
        const days = $('#queue-days').val();
        const status = $('#queue-status').val();
        $btn.prop('disabled', true).text('Deleting...');
        
        $.post(ajaxurl, {
            action: 'idoklad_cleanup_old_queue',
            nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>',
            days: days,
            status: status
        }, function(response) {
            $btn.prop('disabled', false).text('Delete Old Queue Items');
            
            if (response.success) {
                $('#cleanup-queue-result').html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                loadStats();
            } else {
                $('#cleanup-queue-result').html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
            }
        });
    });
    
    // Cleanup attachments
    $('#cleanup-attachments').on('click', function() {
        if (!confirm('Are you sure you want to delete old PDF attachments? This cannot be undone.')) {
            return;
        }
        
        const $btn = $(this);
        const days = $('#attachments-days').val();
        $btn.prop('disabled', true).text('Deleting...');
        
        $.post(ajaxurl, {
            action: 'idoklad_cleanup_attachments',
            nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>',
            days: days
        }, function(response) {
            $btn.prop('disabled', false).text('Delete Old Attachments');
            
            if (response.success) {
                $('#cleanup-attachments-result').html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                loadStats();
            } else {
                $('#cleanup-attachments-result').html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
            }
        });
    });
});
</script>

<style>
.cleanup-section h3 {
    margin-top: 0;
    color: #2271b1;
}

.cleanup-section label {
    font-weight: 600;
    display: block;
    margin-top: 10px;
}

.cleanup-section select {
    padding: 5px;
}

.notice.inline {
    margin: 10px 0 0 0;
    padding: 8px 12px;
}
</style>


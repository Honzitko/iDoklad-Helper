<?php
/**
 * Email Processing Control Panel
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current processing status
$automatic_processing = get_option('idoklad_automatic_processing', false);
$next_email_check = wp_next_scheduled('idoklad_check_emails_v3');

if (!$next_email_check) {
    $next_email_check = wp_next_scheduled('idoklad_check_emails');
}
$next_queue_process = wp_next_scheduled('idoklad_process_queue');

// Get queue statistics
$database = new IDokladProcessor_Database();
$queue_stats = $database->get_queue_statistics();
?>

<div class="wrap" id="idoklad-email-processing-page">
    <h1><?php _e('Email Processing Control', 'idoklad-invoice-processor'); ?></h1>
    
    <div class="idoklad-admin-container">
        
        <!-- Processing Status Card -->
        <div class="idoklad-card">
            <h2><?php _e('Processing Status', 'idoklad-invoice-processor'); ?></h2>
            
            <div class="processing-status">
                <div class="status-indicator">
                    <span class="status-dot <?php echo $automatic_processing ? 'status-running' : 'status-stopped'; ?>"></span>
                    <span class="status-text">
                        <?php echo $automatic_processing ? __('Automatic Processing: RUNNING', 'idoklad-invoice-processor') : __('Automatic Processing: STOPPED', 'idoklad-invoice-processor'); ?>
                    </span>
                </div>
                
                <div class="status-details">
                    <?php if ($next_email_check): ?>
                        <p><strong><?php _e('Next Email Check:', 'idoklad-invoice-processor'); ?></strong> 
                           <?php echo date('Y-m-d H:i:s', $next_email_check); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($next_queue_process): ?>
                        <p><strong><?php _e('Next Queue Process:', 'idoklad-invoice-processor'); ?></strong> 
                           <?php echo date('Y-m-d H:i:s', $next_queue_process); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Queue Statistics Card -->
        <div class="idoklad-card">
            <h2><?php _e('Queue Statistics', 'idoklad-invoice-processor'); ?></h2>
            
            <div class="queue-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $queue_stats['pending'] ?? 0; ?></span>
                    <span class="stat-label"><?php _e('Pending', 'idoklad-invoice-processor'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $queue_stats['processing'] ?? 0; ?></span>
                    <span class="stat-label"><?php _e('Processing', 'idoklad-invoice-processor'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $queue_stats['completed'] ?? 0; ?></span>
                    <span class="stat-label"><?php _e('Completed', 'idoklad-invoice-processor'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $queue_stats['failed'] ?? 0; ?></span>
                    <span class="stat-label"><?php _e('Failed', 'idoklad-invoice-processor'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Automatic Processing Controls -->
        <div class="idoklad-card">
            <h2><?php _e('Automatic Processing Controls', 'idoklad-invoice-processor'); ?></h2>
            
            <div class="automatic-controls">
                <div class="control-buttons">
                    <button type="button" id="toggle-automatic-processing" class="button <?php echo $automatic_processing ? 'button-secondary' : 'button-primary'; ?>" data-running="<?php echo $automatic_processing ? '1' : '0'; ?>">
                        <span class="dashicons <?php echo $automatic_processing ? 'dashicons-controls-pause' : 'dashicons-controls-play'; ?>"></span>
                        <?php echo $automatic_processing ? __('Disable Auto Email Processing', 'idoklad-invoice-processor') : __('Enable Auto Email Processing', 'idoklad-invoice-processor'); ?>
                    </button>

                    <button type="button" id="start-automatic-processing" class="button button-primary">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php _e('Start Automatic Processing', 'idoklad-invoice-processor'); ?>
                    </button>

                    <button type="button" id="stop-automatic-processing" class="button button-secondary">
                        <span class="dashicons dashicons-controls-pause"></span>
                        <?php _e('Stop Automatic Processing', 'idoklad-invoice-processor'); ?>
                    </button>
                    
                    <button type="button" id="refresh-status" class="button">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh Status', 'idoklad-invoice-processor'); ?>
                    </button>
                </div>
                
                <div class="control-info">
                    <p><?php _e('Automatic processing will check for new emails and process the queue at regular intervals.', 'idoklad-invoice-processor'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Manual Processing Controls -->
        <div class="idoklad-card">
            <h2><?php _e('Manual Processing Controls', 'idoklad-invoice-processor'); ?></h2>
            
            <div class="manual-controls">
                <div class="control-section">
                    <h3><?php _e('Email Operations', 'idoklad-invoice-processor'); ?></h3>
                    <div class="control-buttons">
                        <button type="button" id="grab-emails-manually" class="button button-primary">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php _e('Grab Emails Now', 'idoklad-invoice-processor'); ?>
                        </button>
                        
                        <button type="button" id="test-email-connection" class="button">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php _e('Test Email Connection', 'idoklad-invoice-processor'); ?>
                        </button>
                    </div>
                    <p class="description"><?php _e('Manually check for new emails and extract PDF attachments.', 'idoklad-invoice-processor'); ?></p>
                </div>
                
                <div class="control-section">
                    <h3><?php _e('Queue Processing', 'idoklad-invoice-processor'); ?></h3>
                    <div class="control-buttons">
                        <button type="button" id="process-emails-manually" class="button button-primary">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Process Queue Now', 'idoklad-invoice-processor'); ?>
                        </button>
                        
                        <button type="button" id="process-queue-manually" class="button">
                            <span class="dashicons dashicons-list-view"></span>
                            <?php _e('Process All Pending', 'idoklad-invoice-processor'); ?>
                        </button>
                    </div>
                    <p class="description"><?php _e('Manually process pending items in the queue.', 'idoklad-invoice-processor'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Processing Log -->
        <div class="idoklad-card">
            <h2><?php _e('Processing Log', 'idoklad-invoice-processor'); ?></h2>
            
            <div class="processing-log">
                <div id="processing-log-content" class="log-content">
                    <p><?php _e('Processing log will appear here...', 'idoklad-invoice-processor'); ?></p>
                </div>
                
                <div class="log-controls">
                    <button type="button" id="clear-log" class="button">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Clear Log', 'idoklad-invoice-processor'); ?>
                    </button>
                    
                    <button type="button" id="export-log" class="button">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export Log', 'idoklad-invoice-processor'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="idoklad-card">
            <h2><?php _e('Quick Actions', 'idoklad-invoice-processor'); ?></h2>
            
            <div class="quick-actions">
                <div class="action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=idoklad-queue'); ?>" class="button">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e('View Queue', 'idoklad-invoice-processor'); ?>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=idoklad-logs'); ?>" class="button">
                        <span class="dashicons dashicons-text-page"></span>
                        <?php _e('View Logs', 'idoklad-invoice-processor'); ?>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=idoklad-settings'); ?>" class="button">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Settings', 'idoklad-invoice-processor'); ?>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=idoklad-diagnostics'); ?>" class="button">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Diagnostics', 'idoklad-invoice-processor'); ?>
                    </a>
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
.idoklad-admin-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.idoklad-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.idoklad-card h2 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #23282d;
    font-size: 18px;
}

.processing-status {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

.status-dot.status-running {
    background-color: #46b450;
    animation: pulse 2s infinite;
}

.status-dot.status-stopped {
    background-color: #dc3232;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.status-details p {
    margin: 5px 0;
    color: #666;
}

.queue-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 15px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #23282d;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-top: 5px;
}

.control-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.control-buttons .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

.control-info p {
    color: #666;
    font-style: italic;
    margin: 0;
}

.control-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.control-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.control-section h3 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #23282d;
    font-size: 16px;
}

.control-section .description {
    color: #666;
    font-size: 13px;
    margin: 5px 0 0 0;
}

.processing-log {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.log-content {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    min-height: 200px;
    max-height: 400px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.4;
}

.log-controls {
    display: flex;
    gap: 10px;
}

.quick-actions .action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

.quick-actions .button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    text-decoration: none;
}

@media (max-width: 768px) {
    .idoklad-admin-container {
        grid-template-columns: 1fr;
    }
    
    .control-buttons {
        flex-direction: column;
    }
    
    .queue-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .quick-actions .action-buttons {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Processing status
    let processingStatus = {
        automatic: <?php echo $automatic_processing ? 'true' : 'false'; ?>,
        nextEmailCheck: <?php echo $next_email_check ? $next_email_check : 'null'; ?>,
        nextQueueProcess: <?php echo $next_queue_process ? $next_queue_process : 'null'; ?>
    };
    
    // Initialize processing log
    let processingLog = [];
    
    // Add log entry
    function addLogEntry(message, type = 'info') {
        const timestamp = new Date().toLocaleString();
        const entry = `[${timestamp}] ${message}`;
        processingLog.push({ timestamp, message, type });
        
        // Update log display
        updateLogDisplay();
    }
    
    // Update log display
    function updateLogDisplay() {
        const logContent = $('#processing-log-content');
        let html = '';
        
        processingLog.slice(-50).forEach(entry => {
            const className = entry.type === 'error' ? 'error' : entry.type === 'success' ? 'success' : 'info';
            html += `<div class="log-entry ${className}">${entry.message}</div>`;
        });
        
        if (html === '') {
            html = '<p>Processing log will appear here...</p>';
        }
        
        logContent.html(html);
        logContent.scrollTop(logContent[0].scrollHeight);
    }
    
    // Update processing status
    function updateProcessingStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_get_processing_status',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    processingStatus = data;
                    
                    // Update status indicator
                    const statusDot = $('.status-dot');
                    const statusText = $('.status-text');
                    
                    if (data.automatic_processing) {
                        statusDot.removeClass('status-stopped').addClass('status-running');
                        statusText.text('Automatic Processing: RUNNING');
                    } else {
                        statusDot.removeClass('status-running').addClass('status-stopped');
                        statusText.text('Automatic Processing: STOPPED');
                    }
                    
                    // Update next run times
                    if (data.next_email_check) {
                        $('.status-details p:first').html(`<strong>Next Email Check:</strong> ${data.next_email_check}`);
                    }
                    if (data.next_queue_process) {
                        $('.status-details p:last').html(`<strong>Next Queue Process:</strong> ${data.next_queue_process}`);
                    }
                    
                    // Update queue stats
                    if (data.queue_stats) {
                        $('.stat-item:nth-child(1) .stat-number').text(data.queue_stats.pending || 0);
                        $('.stat-item:nth-child(2) .stat-number').text(data.queue_stats.processing || 0);
                        $('.stat-item:nth-child(3) .stat-number').text(data.queue_stats.completed || 0);
                        $('.stat-item:nth-child(4) .stat-number').text(data.queue_stats.failed || 0);
                    }
                }
            },
            error: function() {
                addLogEntry('Failed to update processing status', 'error');
            }
        });
    }
    
    // Start automatic processing
    $('#start-automatic-processing').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Starting...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_start_automatic_processing',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    addLogEntry(response.data.message, 'success');
                    updateProcessingStatus();
                } else {
                    addLogEntry('Failed to start automatic processing: ' + response.data, 'error');
                }
            },
            error: function() {
                addLogEntry('Error starting automatic processing', 'error');
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Start Automatic Processing');
            }
        });
    });
    
    // Stop automatic processing
    $('#stop-automatic-processing').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Stopping...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_stop_automatic_processing',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    addLogEntry(response.data.message, 'success');
                    updateProcessingStatus();
                } else {
                    addLogEntry('Failed to stop automatic processing: ' + response.data, 'error');
                }
            },
            error: function() {
                addLogEntry('Error stopping automatic processing', 'error');
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-controls-pause"></span> Stop Automatic Processing');
            }
        });
    });
    
    // Grab emails manually
    $('#grab-emails-manually').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Grabbing emails...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_grab_emails_manually',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    addLogEntry(response.data.message, 'success');
                    updateProcessingStatus();
                } else {
                    addLogEntry('Failed to grab emails: ' + response.data, 'error');
                }
            },
            error: function() {
                addLogEntry('Error grabbing emails', 'error');
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> Grab Emails Now');
            }
        });
    });
    
    // Process emails manually
    $('#process-emails-manually').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_process_emails_manually',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    addLogEntry(response.data.message, 'success');
                    updateProcessingStatus();
                } else {
                    addLogEntry('Failed to process emails: ' + response.data, 'error');
                }
            },
            error: function() {
                addLogEntry('Error processing emails', 'error');
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Process Queue Now');
            }
        });
    });
    
    // Test email connection
    $('#test-email-connection').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_test_email_connection',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    addLogEntry('Email connection test successful', 'success');
                } else {
                    addLogEntry('Email connection test failed: ' + response.data, 'error');
                }
            },
            error: function() {
                addLogEntry('Error testing email connection', 'error');
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools"></span> Test Email Connection');
            }
        });
    });
    
    // Process queue manually
    $('#process-queue-manually').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_process_queue_manually',
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    addLogEntry(response.data.message, 'success');
                    updateProcessingStatus();
                } else {
                    addLogEntry('Failed to process queue: ' + response.data, 'error');
                }
            },
            error: function() {
                addLogEntry('Error processing queue', 'error');
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-list-view"></span> Process All Pending');
            }
        });
    });
    
    // Refresh status
    $('#refresh-status').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Refreshing...');
        
        updateProcessingStatus();
        
        setTimeout(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Refresh Status');
        }, 1000);
    });
    
    // Clear log
    $('#clear-log').on('click', function() {
        processingLog = [];
        updateLogDisplay();
        addLogEntry('Log cleared', 'info');
    });
    
    // Export log
    $('#export-log').on('click', function() {
        if (processingLog.length === 0) {
            addLogEntry('No log entries to export', 'info');
            return;
        }
        
        let logText = 'iDoklad Invoice Processor - Processing Log\n';
        logText += 'Generated: ' + new Date().toLocaleString() + '\n\n';
        
        processingLog.forEach(entry => {
            logText += `[${entry.timestamp}] ${entry.message}\n`;
        });
        
        const blob = new Blob([logText], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'idoklad-processing-log-' + new Date().toISOString().split('T')[0] + '.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        addLogEntry('Log exported successfully', 'success');
    });
    
    // Auto-refresh status every 30 seconds
    setInterval(updateProcessingStatus, 30000);
    
    // Initial status update
    updateProcessingStatus();
    addLogEntry('Email processing control panel loaded', 'info');
});
</script>

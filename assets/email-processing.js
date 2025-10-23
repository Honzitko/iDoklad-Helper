/**
 * Email Processing Control JavaScript
 */

jQuery(document).ready(function($) {
    
    // Email Processing Control Panel
    if ($('#idoklad-email-processing-page').length > 0) {
        initEmailProcessingControls();
    }
    
    function initEmailProcessingControls() {
        // Processing status
        let processingStatus = {
            automatic: false,
            nextEmailCheck: null,
            nextQueueProcess: null
        };
        
        // Initialize processing log
        let processingLog = [];
        
        // Add log entry
        function addLogEntry(message, type = 'info') {
            const timestamp = new Date().toLocaleString();
            const entry = { timestamp, message, type };
            processingLog.push(entry);
            
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
                    nonce: idoklad_admin.nonce
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
                    nonce: idoklad_admin.nonce
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
                    nonce: idoklad_admin.nonce
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
                    nonce: idoklad_admin.nonce
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
                    nonce: idoklad_admin.nonce
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
                    nonce: idoklad_admin.nonce
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
                    nonce: idoklad_admin.nonce
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
    }
    
    // Dashboard quick actions
    if ($('#force-email-check').length > 0) {
        $('#force-email-check').on('click', function() {
            const button = $(this);
            button.prop('disabled', true).text('Checking emails...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'idoklad_force_email_check',
                    nonce: idoklad_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Email check completed: ' + response.data.message);
                    } else {
                        alert('Email check failed: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error checking emails');
                },
                complete: function() {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-email"></span> Check Emails Now');
                }
            });
        });
    }
    
    if ($('#process-queue-now').length > 0) {
        $('#process-queue-now').on('click', function() {
            const button = $(this);
            button.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'idoklad_process_queue_manually',
                    nonce: idoklad_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Queue processing completed: ' + response.data.message);
                    } else {
                        alert('Queue processing failed: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error processing queue');
                },
                complete: function() {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Process Pending Queue');
                }
            });
        });
    }
});

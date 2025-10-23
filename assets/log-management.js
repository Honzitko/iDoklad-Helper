/**
 * Log Management JavaScript
 * Handles log selection, export, and deletion
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize log management functionality
    initLogManagement();
    
    function initLogManagement() {
        // Handle select all checkbox
        $('#select-all-logs, #select-all-logs-header').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('.log-checkbox').prop('checked', isChecked);
            updateSelectedCount();
            updateBulkActionButtons();
        });
        
        // Handle individual checkbox changes
        $(document).on('change', '.log-checkbox', function() {
            updateSelectedCount();
            updateBulkActionButtons();
            updateSelectAllState();
        });
        
        // Handle export selected button
        $('#export-selected-logs').on('click', function() {
            exportSelectedLogs();
        });
        
        // Handle delete selected button
        $('#delete-selected-logs').on('click', function() {
            deleteSelectedLogs();
        });
        
        // Handle single log export
        $(document).on('click', '.export-single-log', function() {
            var logId = $(this).data('log-id');
            exportSingleLog(logId);
        });
        
        // Handle single log deletion
        $(document).on('click', '.delete-single-log', function() {
            var logId = $(this).data('log-id');
            deleteSingleLog(logId);
        });
    }
    
    function updateSelectedCount() {
        var selectedCount = $('.log-checkbox:checked').length;
        var totalLogs = $('.log-checkbox').length;
        
        $('#selected-logs-count').text(selectedCount + ' of ' + totalLogs + ' logs selected');
    }
    
    function updateBulkActionButtons() {
        var selectedCount = $('.log-checkbox:checked').length;
        var hasSelection = selectedCount > 0;
        
        $('#export-selected-logs, #delete-selected-logs').prop('disabled', !hasSelection);
    }
    
    function updateSelectAllState() {
        var totalLogs = $('.log-checkbox').length;
        var selectedCount = $('.log-checkbox:checked').length;
        
        if (selectedCount === 0) {
            $('#select-all-logs, #select-all-logs-header').prop('indeterminate', false).prop('checked', false);
        } else if (selectedCount === totalLogs) {
            $('#select-all-logs, #select-all-logs-header').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#select-all-logs, #select-all-logs-header').prop('indeterminate', true);
        }
    }
    
    function getSelectedLogIds() {
        var selectedIds = [];
        $('.log-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        return selectedIds;
    }
    
    function exportSingleLog(logId) {
        // Show format selection dialog
        var format = prompt('Select export format:\n1. JSON\n2. CSV\n\nEnter 1 or 2:', '1');
        
        if (format === null) return; // User cancelled
        
        var exportFormat = (format === '2') ? 'csv' : 'json';
        
        // Create form and submit
        var form = $('<form>', {
            method: 'POST',
            action: idoklad_admin.ajax_url,
            target: '_blank'
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'idoklad_export_log'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'log_id',
            value: logId
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'format',
            value: exportFormat
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: idoklad_admin.nonce
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        showNotice('success', 'Export started. Download should begin shortly.');
    }
    
    function exportSelectedLogs() {
        var selectedIds = getSelectedLogIds();
        
        if (selectedIds.length === 0) {
            alert('Please select logs to export.');
            return;
        }
        
        // Show format selection dialog
        var format = prompt('Select export format:\n1. JSON\n2. CSV\n\nEnter 1 or 2:', '1');
        
        if (format === null) return; // User cancelled
        
        var exportFormat = (format === '2') ? 'csv' : 'json';
        
        // Create form and submit
        var form = $('<form>', {
            method: 'POST',
            action: idoklad_admin.ajax_url,
            target: '_blank'
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'idoklad_export_selected_logs'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'selected_ids',
            value: JSON.stringify(selectedIds)
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'format',
            value: exportFormat
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: idoklad_admin.nonce
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        showNotice('success', 'Export started. Download should begin shortly.');
    }
    
    function deleteSingleLog(logId) {
        if (!confirm('Are you sure you want to delete this log? This action cannot be undone.')) {
            return;
        }
        
        var $button = $('.delete-single-log[data-log-id="' + logId + '"]');
        var originalText = $button.html();
        $button.prop('disabled', true).text('Deleting...');
        
        $.ajax({
            url: idoklad_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_delete_log',
                log_id: logId,
                nonce: idoklad_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    // Remove the row from the table
                    $('tr[data-log-id="' + logId + '"]').fadeOut(function() {
                        $(this).remove();
                        updateSelectedCount();
                        updateBulkActionButtons();
                        updateSelectAllState();
                    });
                } else {
                    showNotice('error', response.data || 'Failed to delete log');
                }
            },
            error: function() {
                showNotice('error', 'An error occurred while deleting the log');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    function deleteSelectedLogs() {
        var selectedIds = getSelectedLogIds();
        
        if (selectedIds.length === 0) {
            alert('Please select logs to delete.');
            return;
        }
        
        if (!confirm('Are you sure you want to delete ' + selectedIds.length + ' selected log(s)? This action cannot be undone.')) {
            return;
        }
        
        var $button = $('#delete-selected-logs');
        var originalText = $button.html();
        $button.prop('disabled', true).html('<span class="dashicons dashicons-trash"></span> Deleting...');
        
        $.ajax({
            url: idoklad_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_delete_selected_logs',
                selected_ids: selectedIds,
                nonce: idoklad_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    // Show errors if any
                    if (response.data.errors && response.data.errors.length > 0) {
                        showNotice('error', 'Some logs could not be deleted: ' + response.data.errors.join(', '));
                    }
                    
                    // Remove deleted rows from the table
                    selectedIds.forEach(function(logId) {
                        $('tr[data-log-id="' + logId + '"]').fadeOut(function() {
                            $(this).remove();
                        });
                    });
                    
                    // Update UI after animation
                    setTimeout(function() {
                        updateSelectedCount();
                        updateBulkActionButtons();
                        updateSelectAllState();
                    }, 500);
                    
                } else {
                    showNotice('error', response.data || 'Failed to delete logs');
                }
            },
            error: function() {
                showNotice('error', 'An error occurred while deleting logs');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Remove existing notices
        $('.notice').remove();
        
        // Add new notice
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    }
    
    // Initialize on page load
    updateSelectedCount();
    updateBulkActionButtons();
    updateSelectAllState();
});

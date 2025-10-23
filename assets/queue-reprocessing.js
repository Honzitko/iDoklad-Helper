/**
 * Queue Reprocessing JavaScript
 * Handles selection and reprocessing of queue items
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize queue reprocessing functionality
    initQueueReprocessing();
    
    function initQueueReprocessing() {
        // Handle select all checkbox
        $('#select-all-queue-items, #select-all-header').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('.queue-item-checkbox:not(:disabled)').prop('checked', isChecked);
            updateSelectedCount();
            updateBulkActionButtons();
        });
        
        // Handle individual checkbox changes
        $(document).on('change', '.queue-item-checkbox', function() {
            updateSelectedCount();
            updateBulkActionButtons();
            updateSelectAllState();
        });
        
        // Handle reprocess selected button
        $('#reprocess-selected').on('click', function() {
            reprocessSelectedItems();
        });
        
        // Handle reset selected button
        $('#reset-selected').on('click', function() {
            resetSelectedItems();
        });
        
        // Handle single item reprocess
        $(document).on('click', '.reprocess-single', function() {
            var itemId = $(this).data('queue-id');
            reprocessSingleItem(itemId);
        });
    }
    
    function updateSelectedCount() {
        var selectedCount = $('.queue-item-checkbox:checked').length;
        var totalSelectable = $('.queue-item-checkbox:not(:disabled)').length;
        
        $('#selected-count').text(selectedCount + ' of ' + totalSelectable + ' items selected');
    }
    
    function updateBulkActionButtons() {
        var selectedCount = $('.queue-item-checkbox:checked').length;
        var hasSelection = selectedCount > 0;
        
        $('#reprocess-selected, #reset-selected').prop('disabled', !hasSelection);
    }
    
    function updateSelectAllState() {
        var totalSelectable = $('.queue-item-checkbox:not(:disabled)').length;
        var selectedCount = $('.queue-item-checkbox:checked').length;
        
        if (selectedCount === 0) {
            $('#select-all-queue-items, #select-all-header').prop('indeterminate', false).prop('checked', false);
        } else if (selectedCount === totalSelectable) {
            $('#select-all-queue-items, #select-all-header').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#select-all-queue-items, #select-all-header').prop('indeterminate', true);
        }
    }
    
    function getSelectedIds() {
        var selectedIds = [];
        $('.queue-item-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        return selectedIds;
    }
    
    function reprocessSelectedItems() {
        var selectedIds = getSelectedIds();
        
        if (selectedIds.length === 0) {
            alert('Please select items to reprocess.');
            return;
        }
        
        if (!confirm('Are you sure you want to reprocess ' + selectedIds.length + ' selected item(s)? This will reset them to pending status.')) {
            return;
        }
        
        var $button = $('#reprocess-selected');
        var originalText = $button.html();
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Reprocessing...');
        
        $.ajax({
            url: idoklad_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_reprocess_selected',
                selected_ids: selectedIds,
                nonce: idoklad_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    // Show errors if any
                    if (response.data.errors && response.data.errors.length > 0) {
                        showNotice('error', 'Some items could not be reprocessed: ' + response.data.errors.join(', '));
                    }
                    
                    // Refresh the queue
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data || 'Failed to reprocess items');
                }
            },
            error: function() {
                showNotice('error', 'An error occurred while reprocessing items');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    function resetSelectedItems() {
        var selectedIds = getSelectedIds();
        
        if (selectedIds.length === 0) {
            alert('Please select items to reset.');
            return;
        }
        
        if (!confirm('Are you sure you want to reset ' + selectedIds.length + ' selected item(s) to pending status?')) {
            return;
        }
        
        var $button = $('#reset-selected');
        var originalText = $button.html();
        $button.prop('disabled', true).html('<span class="dashicons dashicons-undo"></span> Resetting...');
        
        $.ajax({
            url: idoklad_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_reset_selected',
                selected_ids: selectedIds,
                nonce: idoklad_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    // Show errors if any
                    if (response.data.errors && response.data.errors.length > 0) {
                        showNotice('error', 'Some items could not be reset: ' + response.data.errors.join(', '));
                    }
                    
                    // Refresh the queue
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data || 'Failed to reset items');
                }
            },
            error: function() {
                showNotice('error', 'An error occurred while resetting items');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    function reprocessSingleItem(itemId) {
        if (!confirm('Are you sure you want to reprocess this item? This will reset it to pending status.')) {
            return;
        }
        
        var $button = $('.reprocess-single[data-queue-id="' + itemId + '"]');
        var originalText = $button.html();
        $button.prop('disabled', true).text('Reprocessing...');
        
        $.ajax({
            url: idoklad_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_reprocess_single',
                item_id: itemId,
                nonce: idoklad_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    // Refresh the queue
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data || 'Failed to reprocess item');
                }
            },
            error: function() {
                showNotice('error', 'An error occurred while reprocessing the item');
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

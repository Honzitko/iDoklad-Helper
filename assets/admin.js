/**
 * iDoklad Invoice Processor Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Test Email Connection
    $('#test-email-connection').on('click', function() {
        var button = $(this);
        var resultSpan = $('#email-test-result');
        
        button.prop('disabled', true);
        resultSpan.html('<span class="idoklad-loading"></span> Testing...');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_email_connection',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span class="success">✓ ' + response.data + '</span>');
                } else {
                    resultSpan.html('<span class="error">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span class="error">✗ Connection test failed</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Test ChatGPT Connection
    $('#test-chatgpt-connection').on('click', function() {
        var button = $(this);
        var resultSpan = $('#chatgpt-test-result');

        button.prop('disabled', true);
        resultSpan.html('<span class="idoklad-loading"></span> Testing...');

        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_chatgpt_connection',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span class="success">✓ ' + response.data + '</span>');
                } else {
                    resultSpan.html('<span class="error">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span class="error">✗ Connection test failed</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    // Refresh ChatGPT models
    $('#refresh-chatgpt-models').on('click', function() {
        var button = $(this);
        var select = $('#chatgpt_model');
        var originalText = button.text();
        var loadingText = button.data('loading-text') || 'Refreshing...';
        var currentSelection = select.val();

        button.prop('disabled', true).text(loadingText);

        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_get_chatgpt_models',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var models = response.data;
                    var hasCurrent = false;
                    var optionsHtml = '';

                    $.each(models, function(key, label) {
                        var value = escapeHtml(key);
                        var text = escapeHtml(label || key);
                        if (key === currentSelection) {
                            hasCurrent = true;
                        }
                        optionsHtml += '<option value="' + value + '">' + text + '</option>';
                    });

                    select.empty().append(optionsHtml);

                    if (!hasCurrent && currentSelection) {
                        select.append('<option value="' + escapeHtml(currentSelection) + '">' + escapeHtml(currentSelection) + ' (' + escapeHtml('custom') + ')</option>');
                    }

                    if (currentSelection) {
                        select.val(currentSelection);
                    }

                    alert('Available models refreshed.');
                } else {
                    var errorMsg = 'Unable to fetch models.';
                    if (typeof response.data === 'string') {
                        errorMsg = response.data;
                    }
                    alert('Error: ' + errorMsg);
                }
            },
            error: function() {
                alert('Error: Failed to fetch models from OpenAI.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Process Queue Manually
    $('#process-queue-manually').on('click', function() {
        var button = $(this);
        
        if (!confirm('Are you sure you want to process the queue manually? This may take some time.')) {
            return;
        }
        
        button.prop('disabled', true);
        button.text('Processing...');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_process_queue_manually',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Success: ' + response.data);
                    location.reload(); // Refresh to show updated queue status
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error: Failed to process queue');
            },
            complete: function() {
                button.prop('disabled', false);
                button.text('Process Queue Now');
            }
        });
    });
    
    // Log details modal
    $('.view-log-details').on('click', function() {
        var logId = $(this).data('log-id');

        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_get_log_details',
                log_id: logId,
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#log-details-content').html(response.data);
                    $('#log-details-modal').show();
                } else {
                    alert('Error loading log details: ' + response.data);
                }
            }
        });
    });

    // Reprocess email from logs
    $(document).on('click', '.reprocess-email', function() {
        var button = $(this);
        var logId = button.data('log-id');

        if (!logId) {
            alert('Error: Missing log identifier.');
            return;
        }

        if (!confirm('Reprocess this email? The attachment will be queued again for processing.')) {
            return;
        }

        var originalText = button.text();
        button.prop('disabled', true).text('Reprocessing...');

        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_reprocess_email',
                log_id: logId,
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data || 'Email queued for reprocessing.');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unable to reprocess email.'));
                }
            },
            error: function() {
                alert('Error: Failed to reprocess email.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Close modal
    $('.idoklad-modal-close, .idoklad-modal').on('click', function(e) {
        if (e.target === this) {
            $('.idoklad-modal').hide();
        }
    });
    
    // Export logs
    $('#export-logs').on('click', function() {
        window.location.href = idoklad_ajax.ajax_url + '?action=idoklad_export_logs&nonce=' + idoklad_ajax.nonce;
    });
    
    // Real-time status updates
    if (typeof idoklad_ajax !== 'undefined' && idoklad_ajax.ajax_url) {
        setInterval(function() {
            updateQueueStatus();
        }, 30000); // Update every 30 seconds
    }
    
    function updateQueueStatus() {
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_get_queue_status',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update queue status display
                    $('.queue-pending').text(response.data.pending);
                    $('.queue-processing').text(response.data.processing);
                    $('.queue-failed').text(response.data.failed);
                }
            }
        });
    }
    
    // Queue viewer - View details
    $(document).on('click', '.view-queue-details', function(e) {
        e.preventDefault();
        var queueId = $(this).data('queue-id');
        
        console.log('Details button clicked, queue ID:', queueId);
        
        if (!queueId) {
            alert('Error: No queue ID found');
            return;
        }
        
        // Show loading in modal
        $('#queue-details-content').html('<p style="text-align:center; padding:20px;">Loading...</p>');
        $('#queue-details-modal').show();
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_get_queue_details',
                queue_id: queueId,
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    $('#queue-details-content').html(response.data);
                } else {
                    $('#queue-details-content').html('<p style="color:red;">Error: ' + (response.data || 'Unknown error') + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                $('#queue-details-content').html('<p style="color:red;">Network error: ' + error + '</p>');
            }
        });
    });
    
    // Queue viewer - Refresh queue
    $('#refresh-queue').on('click', function() {
        var button = $(this);
        var originalHtml = button.html();
        
        button.prop('disabled', true);
        button.html('<span class="dashicons dashicons-update" style="animation: rotate 1s linear infinite;"></span> Refreshing...');
        
        // Get current status filter from URL
        var urlParams = new URLSearchParams(window.location.search);
        var status = urlParams.get('status') || '';
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_refresh_queue',
                status: status,
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#queue-tbody').html(response.data);
                    
                    // Optionally reload page to update stats
                    location.reload();
                }
            },
            complete: function() {
                button.prop('disabled', false);
                button.html(originalHtml);
            }
        });
    });
    
    // Queue viewer - Process queue button
    $('#process-queue-button').on('click', function() {
        var button = $(this);
        
        if (!confirm('Process pending items in the queue? This may take some time.')) {
            return;
        }
        
        button.prop('disabled', true);
        button.text('Processing...');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_process_queue_manually',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Success: ' + response.data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error: Failed to process queue');
            },
            complete: function() {
                button.prop('disabled', false);
                button.text('Process Queue Now');
            }
        });
    });
    
    // Queue viewer - Auto-refresh
    var autoRefreshInterval = null;
    $('#auto-refresh-queue').on('change', function() {
        if ($(this).is(':checked')) {
            // Start auto-refresh every 10 seconds
            autoRefreshInterval = setInterval(function() {
                $('#refresh-queue').trigger('click');
            }, 10000);
        } else {
            // Stop auto-refresh
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }
    });
    
    // Test OCR.space API
    $('#test-ocr-space').on('click', function() {
        var button = $(this);
        var resultSpan = $('#ocr-space-test-result');
        
        button.prop('disabled', true);
        resultSpan.html('<span class="idoklad-loading"></span> Testing OCR.space API...');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_ocr_space',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span class="success">✓ ' + response.data + '</span>');
                } else {
                    resultSpan.html('<span class="error">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span class="error">✗ Connection test failed</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Test Zapier webhook
    $('#test-zapier-webhook').on('click', function() {
        var button = $(this);
        var resultSpan = $('#zapier-test-result');
        
        button.prop('disabled', true);
        resultSpan.html('<span class="idoklad-loading"></span> Testing Zapier webhook...');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_zapier_webhook',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span class="success">✓ ' + response.data + '</span>');
                } else {
                    resultSpan.html('<span class="error">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span class="error">✗ Connection test failed</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Reset stuck items
    $('#reset-stuck-items').on('click', function() {
        var button = $(this);
        
        if (!confirm('Reset items stuck in processing status? They will be moved back to pending.')) {
            return;
        }
        
        button.prop('disabled', true);
        button.text('Resetting...');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_reset_stuck_items',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Success: ' + response.data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error: Failed to reset stuck items');
            },
            complete: function() {
                button.prop('disabled', false);
                button.text('Reset Stuck Items');
            }
        });
    });
    
    // ===== DIAGNOSTICS PAGE =====
    
    var lastPdfText = '';
    var lastZapierResponse = '';
    
    // Test PDF Parsing
    $('#test-pdf-parsing-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = this;
        var formData = new FormData(form);
        formData.append('action', 'idoklad_test_pdf_parsing');
        formData.append('nonce', idoklad_ajax.nonce);
        
        var submitBtn = $(form).find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Parsing PDF...');
        
        $('#pdf-parsing-result').hide();
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    lastPdfText = response.data.text;
                    displayPdfParsingResults(response.data);
                    $('#pdf-parsing-result').show();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error: Failed to parse PDF');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Test PDF Parsing');
            }
        });
    });
    
    function displayPdfParsingResults(data) {
        var html = '';
        
        // Success box
        html += '<div class="result-box result-success">';
        html += '<h4>✓ PDF Parsing Successful</h4>';
        html += '<div class="stat-item"><span>Characters Extracted:</span><strong>' + data.text_length.toLocaleString() + '</strong></div>';
        html += '<div class="stat-item"><span>Parse Time:</span><strong>' + data.parse_time_ms + ' ms</strong></div>';
        html += '<div class="stat-item"><span>Pages:</span><strong>' + data.page_count + '</strong></div>';
        html += '<div class="stat-item"><span>File Size:</span><strong>' + formatBytes(data.file_size) + '</strong></div>';
        html += '</div>';
        
        // Text preview
        html += '<div class="result-box result-info">';
        html += '<h4>Extracted Text Preview (first 500 chars)</h4>';
        html += '<div class="code-block">' + escapeHtml(data.preview) + '</div>';
        html += '</div>';
        
        // Metadata
        if (data.metadata && Object.keys(data.metadata).length > 0) {
            html += '<div class="result-box result-info">';
            html += '<h4>PDF Metadata</h4>';
            for (var key in data.metadata) {
                html += '<div class="stat-item"><span>' + key + ':</span><span>' + escapeHtml(data.metadata[key]) + '</span></div>';
            }
            html += '</div>';
        }
        
        // Methods used
        html += '<div class="result-box result-info">';
        html += '<h4>Parsing Methods Tested</h4>';
        for (var methodKey in data.methods) {
            var method = data.methods[methodKey];
            var available = method.available ? 'method-available' : 'method-unavailable';
            var status = method.available ? '✓ Available' : '✗ Unavailable';
            html += '<div class="method-item ' + available + '">';
            html += '<strong>' + method.name + '</strong> - ' + status + '<br>';
            html += '<small>' + method.description + '</small>';
            html += '</div>';
        }
        html += '</div>';
        
        $('#pdf-parsing-content').html(html);
    }
    
    // Test OCR
    $('#test-ocr-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = this;
        var formData = new FormData(form);
        formData.append('action', 'idoklad_test_ocr_on_pdf');
        formData.append('nonce', idoklad_ajax.nonce);
        
        var submitBtn = $(form).find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Running OCR...');
        
        $('#ocr-result').hide();
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    lastPdfText = response.data.text;
                    displayOcrResults(response.data);
                    $('#ocr-result').show();
                } else {
                    // Show detailed error
                    var errorMsg = 'OCR Test Failed\n\n';
                    if (typeof response.data === 'object') {
                        if (response.data.details) {
                            errorMsg += response.data.details;
                        } else {
                            errorMsg += response.data.message || 'Unknown error';
                        }
                    } else {
                        errorMsg += response.data;
                    }
                    alert(errorMsg);
                    
                    // Also show in results area
                    $('#ocr-result').show();
                    var html = '<div class="result-box result-error">';
                    html += '<h4>✗ OCR Test Failed</h4>';
                    if (typeof response.data === 'object' && response.data.details) {
                        html += '<pre style="white-space: pre-wrap; font-family: monospace; font-size: 12px; line-height: 1.5;">';
                        html += escapeHtml(response.data.details);
                        html += '</pre>';
                        
                        // Show API response if available
                        if (response.data.api_response) {
                            html += '<h4 style="margin-top: 20px;">OCR.space API Response:</h4>';
                            html += '<div class="code-block">';
                            html += escapeHtml(JSON.stringify(response.data.api_response, null, 2));
                            html += '</div>';
                        }
                        
                        // Show methods status
                        if (response.data.methods) {
                            html += '<h4 style="margin-top: 20px;">Available Methods:</h4>';
                            for (var key in response.data.methods) {
                                var method = response.data.methods[key];
                                var available = method.available && method.enabled ? 'method-available' : 'method-unavailable';
                                var status = method.available && method.enabled ? '✓ Available & Enabled' : 
                                            method.available ? '⚠️ Available but not enabled' : '✗ Not available';
                                html += '<div class="method-item ' + available + '" style="margin:5px 0;">';
                                html += '<strong>' + method.name + '</strong> - ' + status + '<br>';
                                html += '<small>' + method.description + '</small>';
                                html += '</div>';
                            }
                        }
                    } else {
                        html += '<p>' + escapeHtml(response.data.message || response.data) + '</p>';
                    }
                    html += '</div>';
                    $('#ocr-content').html(html);
                }
            },
            error: function() {
                alert('Error: Failed to run OCR');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Test OCR');
            }
        });
    });
    
    function displayOcrResults(data) {
        var html = '';
        
        html += '<div class="result-box result-success">';
        html += '<h4>✓ OCR Successful</h4>';
        html += '<div class="stat-item"><span>Characters Extracted:</span><strong>' + data.text_length.toLocaleString() + '</strong></div>';
        html += '<div class="stat-item"><span>OCR Time:</span><strong>' + data.ocr_time_ms + ' ms</strong></div>';
        html += '</div>';
        
        html += '<div class="result-box result-info">';
        html += '<h4>Extracted Text Preview</h4>';
        html += '<div class="code-block">' + escapeHtml(data.preview) + '</div>';
        html += '</div>';
        
        // Show API response if available
        if (data.api_response) {
            html += '<div class="result-box result-info">';
            html += '<h4>OCR.space API Response</h4>';
            
            if (data.api_response.error) {
                html += '<p style="color: red;"><strong>Error:</strong> ' + escapeHtml(data.api_response.message) + '</p>';
            } else {
                html += '<div class="stat-item"><span>HTTP Status:</span><strong>' + data.api_response.response_code + '</strong></div>';
                
                if (data.api_response.response_data) {
                    var apiData = data.api_response.response_data;
                    
                    if (apiData.ProcessingTimeInMilliseconds) {
                        html += '<div class="stat-item"><span>Processing Time:</span><strong>' + apiData.ProcessingTimeInMilliseconds + ' ms</strong></div>';
                    }
                    
                    if (apiData.IsErroredOnProcessing) {
                        html += '<div class="stat-item"><span>Has Errors:</span><strong style="color:red;">Yes</strong></div>';
                        if (apiData.ErrorMessage && apiData.ErrorMessage.length > 0) {
                            html += '<div class="stat-item"><span>Error:</span><span style="color:red;">' + escapeHtml(apiData.ErrorMessage[0]) + '</span></div>';
                        }
                    } else {
                        html += '<div class="stat-item"><span>Has Errors:</span><strong style="color:green;">No</strong></div>';
                    }
                    
                    if (apiData.ParsedResults && apiData.ParsedResults[0]) {
                        var result = apiData.ParsedResults[0];
                        html += '<div class="stat-item"><span>Exit Code:</span><strong>' + (result.FileParseExitCode || 'N/A') + '</strong></div>';
                        if (result.ErrorDetails) {
                            html += '<div class="stat-item"><span>Details:</span><span>' + escapeHtml(result.ErrorDetails) + '</span></div>';
                        }
                    }
                }
                
                html += '<h4 style="margin-top: 15px;">Full API Response</h4>';
                html += '<div class="code-block">' + escapeHtml(JSON.stringify(data.api_response.response_data, null, 2)) + '</div>';
            }
            html += '</div>';
        }
        
        html += '<div class="result-box result-info">';
        html += '<h4>OCR Methods Available</h4>';
        for (var methodKey in data.methods) {
            var method = data.methods[methodKey];
            var available = method.available ? 'method-available' : 'method-unavailable';
            var status = method.available ? '✓ Available' : '✗ Unavailable';
            var enabled = method.enabled ? ' (Enabled in settings)' : '';
            html += '<div class="method-item ' + available + '">';
            html += '<strong>' + method.name + '</strong> - ' + status + enabled + '<br>';
            html += '<small>' + method.description + '</small>';
            html += '</div>';
        }
        html += '</div>';
        
        $('#ocr-content').html(html);
    }
    
    // Test Zapier
    $('#test-zapier-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = this;
        var pdfText = $('#zapier-text').val();
        
        if (!pdfText.trim()) {
            alert('Please enter some text to send to Zapier');
            return;
        }
        
        var submitBtn = $(form).find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Sending to Zapier...');
        
        $('#zapier-result').hide();
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_zapier_payload',
                pdf_text: pdfText,
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    lastZapierResponse = JSON.stringify(response.data.response, null, 2);
                    displayZapierResults(response.data);
                    $('#zapier-result').show();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error: Failed to send to Zapier');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Send to Zapier');
            }
        });
    });
    
    function displayZapierResults(data) {
        var html = '';
        
        html += '<div class="result-box result-success">';
        html += '<h4>✓ Zapier Webhook Successful</h4>';
        html += '<div class="stat-item"><span>Request Time:</span><strong>' + data.request_time_ms + ' ms</strong></div>';
        html += '<div class="stat-item"><span>Webhook URL:</span><span>' + escapeHtml(data.webhook_url) + '</span></div>';
        html += '</div>';
        
        html += '<div class="result-box result-info">';
        html += '<h4>Zapier Response</h4>';
        html += '<div class="code-block">' + escapeHtml(JSON.stringify(data.response, null, 2)) + '</div>';
        html += '</div>';
        
        $('#zapier-content').html(html);
    }
    
    // Test iDoklad
    $('#test-idoklad-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = this;
        var userEmail = $('#idoklad-user').val();
        var invoiceData = $('#idoklad-data').val();
        
        if (!userEmail) {
            alert('Please select a user');
            return;
        }
        
        if (!invoiceData.trim()) {
            alert('Please enter invoice data');
            return;
        }
        
        var submitBtn = $(form).find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Sending to iDoklad...');
        
        $('#idoklad-result').hide();
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_idoklad_payload',
                user_email: userEmail,
                invoice_data: invoiceData,
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                console.log('iDoklad API Test Response:', response);
                
                if (response.success) {
                    console.log('iDoklad Success - Full Data:', response.data);
                    console.log('iDoklad API Response Object:', response.data.api_response);
                    displayIdokladResults(response.data);
                    $('#idoklad-result').show();
                } else {
                    // Show detailed error
                    var errorMsg = 'iDoklad API Error:\n\n';
                    if (typeof response.data === 'object') {
                        errorMsg += 'Message: ' + (response.data.message || 'Unknown error') + '\n\n';
                        if (response.data.help) {
                            errorMsg += response.data.help + '\n\n';
                        }
                        if (response.data.endpoint_used) {
                            errorMsg += 'Endpoint: ' + response.data.endpoint_used + '\n';
                        }
                        if (response.data.note) {
                            errorMsg += '\nNote: ' + response.data.note;
                        }
                    } else {
                        errorMsg += response.data;
                    }
                    alert(errorMsg);
                    
                    // Also display in results area
                    $('#idoklad-result').show();
                    var html = '<div class="result-box result-error">';
                    html += '<h4>✗ iDoklad API Error</h4>';
                    if (typeof response.data === 'object') {
                        html += '<p><strong>Error:</strong> ' + escapeHtml(response.data.message || 'Unknown error') + '</p>';
                        if (response.data.help) {
                            html += '<p><em>' + escapeHtml(response.data.help) + '</em></p>';
                        }
                        if (response.data.endpoint_used) {
                            html += '<p><strong>Endpoint:</strong> ' + escapeHtml(response.data.endpoint_used) + '</p>';
                        }
                        if (response.data.note) {
                            html += '<p><small>' + escapeHtml(response.data.note) + '</small></p>';
                        }
                        
                        // Show full API response if available
                        if (response.data.api_response) {
                            html += '<h4 style="margin-top: 20px;">iDoklad API Response:</h4>';
                            
                            if (response.data.api_response.request_data) {
                                html += '<h5>Request Sent:</h5>';
                                html += '<div class="code-block" style="max-height: 300px; overflow-y: auto;">';
                                html += escapeHtml(JSON.stringify(response.data.api_response.request_data, null, 2));
                                html += '</div>';
                            }
                            
                            if (response.data.api_response.response_data) {
                                html += '<h5 style="margin-top: 10px;">Response Received:</h5>';
                                html += '<div class="code-block" style="max-height: 300px; overflow-y: auto;">';
                                html += escapeHtml(JSON.stringify(response.data.api_response.response_data, null, 2));
                                html += '</div>';
                            } else if (response.data.api_response.response_body) {
                                html += '<h5 style="margin-top: 10px;">Raw Response:</h5>';
                                html += '<div class="code-block" style="max-height: 300px; overflow-y: auto;">';
                                html += escapeHtml(response.data.api_response.response_body);
                                html += '</div>';
                            }
                        }
                    } else {
                        html += '<p>' + escapeHtml(response.data) + '</p>';
                    }
                    html += '</div>';
                    $('#idoklad-content').html(html);
                }
            },
            error: function() {
                alert('Error: Failed to send to iDoklad');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Send to iDoklad');
            }
        });
    });
    
    function displayIdokladResults(data) {
        console.log('Displaying iDoklad results:', data);
        
        var html = '';
        
        html += '<div class="result-box result-success">';
        html += '<h4>✓ iDoklad API Successful</h4>';
        html += '<div class="stat-item"><span>Request Time:</span><strong>' + data.request_time_ms + ' ms</strong></div>';
        html += '<div class="stat-item"><span>User:</span><span>' + escapeHtml(data.user_name) + '</span></div>';
        html += '<div class="stat-item"><span>API URL:</span><span>' + escapeHtml(data.api_url) + '</span></div>';
        if (data.endpoint) {
            html += '<div class="stat-item"><span>Endpoint:</span><span>' + escapeHtml(data.endpoint) + '</span></div>';
        }
        if (data.response && data.response.Id) {
            html += '<div class="stat-item"><span>Invoice ID:</span><strong>' + data.response.Id + '</strong></div>';
        }
        html += '</div>';
        
        // ALWAYS show the basic response first
        html += '<div class="result-box result-info">';
        html += '<h4>📄 Basic Response</h4>';
        html += '<div class="code-block" style="max-height: 400px; overflow-y: auto;">';
        html += escapeHtml(JSON.stringify(data.response, null, 2));
        html += '</div>';
        html += '</div>';
        
        // Show full API response details if available
        if (data.api_response) {
            console.log('API Response exists:', data.api_response);
            
            html += '<div class="result-box result-info">';
            html += '<h4>🔍 Complete API Transaction Details</h4>';
            
            if (data.api_response.error) {
                html += '<p style="color: red;"><strong>Error:</strong> ' + escapeHtml(data.api_response.message) + '</p>';
            } else {
                html += '<div class="stat-item"><span>HTTP Status:</span><strong>' + data.api_response.response_code + '</strong></div>';
                html += '<div class="stat-item"><span>Endpoint:</span><span>' + escapeHtml(data.api_response.endpoint) + '</span></div>';
                html += '<div class="stat-item"><span>Method:</span><strong>' + data.api_response.method + '</strong></div>';
                
                if (data.api_response.request_data) {
                    html += '<h4 style="margin-top: 15px;">📤 Request Sent to iDoklad</h4>';
                    html += '<div class="code-block" style="max-height: 400px; overflow-y: auto;">';
                    html += escapeHtml(JSON.stringify(data.api_response.request_data, null, 2));
                    html += '</div>';
                }
                
                if (data.api_response.response_data) {
                    html += '<h4 style="margin-top: 15px;">📥 Full Response from iDoklad</h4>';
                    html += '<div class="code-block" style="max-height: 400px; overflow-y: auto;">';
                    html += escapeHtml(JSON.stringify(data.api_response.response_data, null, 2));
                    html += '</div>';
                } else if (data.api_response.response_body) {
                    html += '<h4 style="margin-top: 15px;">📥 Raw Response Body</h4>';
                    html += '<div class="code-block" style="max-height: 400px; overflow-y: auto;">';
                    html += escapeHtml(data.api_response.response_body);
                    html += '</div>';
                }
                
                if (data.api_response.headers) {
                    html += '<h4 style="margin-top: 15px;">📋 Response Headers</h4>';
                    html += '<div class="code-block" style="max-height: 200px; overflow-y: auto;">';
                    html += escapeHtml(JSON.stringify(data.api_response.headers, null, 2));
                    html += '</div>';
                }
            }
            html += '</div>';
        } else {
            console.warn('No api_response in data!');
            html += '<div class="result-box" style="background: #fff3cd; border-left: 4px solid #ffc107;">';
            html += '<h4>⚠️ Debug Info Missing</h4>';
            html += '<p>api_response data was not captured. Check browser console for raw response.</p>';
            html += '</div>';
        }
        
        $('#idoklad-content').html(html);
        
        console.log('HTML rendered to #idoklad-content');
    }
    
    // Copy from PDF test
    $('#copy-from-pdf').on('click', function() {
        if (lastPdfText) {
            $('#zapier-text').val(lastPdfText);
            alert('Text copied from PDF test!');
        } else {
            alert('Please run "Test PDF Parsing" first');
        }
    });
    
    // Copy from Zapier
    $('#copy-from-zapier').on('click', function() {
        if (lastZapierResponse) {
            $('#idoklad-data').val(lastZapierResponse);
            alert('Data copied from Zapier response!');
        } else {
            alert('Please run "Test Zapier" first');
        }
    });
    
    // Check parsing methods
    $('#check-methods').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('Checking...');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_get_parsing_methods',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayMethodsList(response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error: Failed to check methods');
            },
            complete: function() {
                button.prop('disabled', false).text('Check Available Methods');
            }
        });
    });
    
    function displayMethodsList(methods) {
        var html = '';
        for (var key in methods) {
            var method = methods[key];
            var available = method.available ? 'method-available' : 'method-unavailable';
            var status = method.available ? '✓' : '✗';
            html += '<div class="method-item ' + available + '" style="margin-bottom:8px;">';
            html += '<strong>' + status + ' ' + method.name + '</strong><br>';
            html += '<small style="color:#666;">' + method.description + '</small>';
            html += '</div>';
        }
        $('#methods-list').html(html);
    }
    
    // Helper functions
    function escapeHtml(text) {
        if (typeof text !== 'string') return text;
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var dm = decimals < 0 ? 0 : decimals;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    // ===== SETTINGS PAGE =====
    
    // Test PDF.co connection
    $('#test-pdfco').on('click', function() {
        var button = $(this);
        var resultSpan = $('#pdfco-test-result');
        
        button.prop('disabled', true).text('Testing...');
        resultSpan.html('');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_pdfco',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">✓ ' + escapeHtml(response.data.message) + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">✗ ' + escapeHtml(response.data) + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">✗ Connection failed</span>');
            },
            complete: function() {
                button.prop('disabled', false).text('Test PDF.co Connection');
            }
        });
    });
    
    // Test AI Parser
    $('#test-ai-parser').on('click', function() {
        var button = $(this);
        var resultSpan = $('#pdfco-test-result');
        
        button.prop('disabled', true).text('Testing AI Parser...');
        resultSpan.html('');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_ai_parser',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: green;">✓ ' + escapeHtml(response.data.message) + '</span>');
                } else {
                    resultSpan.html('<span style="color: red;">✗ ' + escapeHtml(response.data) + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: red;">✗ AI Parser test failed</span>');
            },
            complete: function() {
                button.prop('disabled', false).text('Test AI Parser');
            }
        });
    });
    
    // ===== DASHBOARD PAGE =====
    
    // Force email check
    $(document).on('click', '#force-email-check', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true);
        button.html('<span class="dashicons dashicons-update spin"></span> Checking Emails...');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_force_email_check',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                console.log('Force email check response:', response);
                button.prop('disabled', false);
                button.html(originalText);
                
                if (response.success) {
                    var message = 'Success!';
                    if (response.data && response.data.message) {
                        message = response.data.message;
                    }
                    alert(message);
                    // Reload the page to show updated stats
                    location.reload();
                } else {
                    var errorMsg = 'Unknown error';
                    if (typeof response.data === 'string') {
                        errorMsg = response.data;
                    } else if (response.data && response.data.message) {
                        errorMsg = response.data.message;
                    }
                    alert('Error: ' + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('Email check error:', xhr, status, error);
                console.error('Response text:', xhr.responseText);
                button.prop('disabled', false);
                button.html(originalText);
                
                var errorMsg = 'Failed to check emails';
                if (xhr.responseText) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.data) {
                            errorMsg = resp.data;
                        }
                    } catch (e) {
                        errorMsg = xhr.responseText.substring(0, 200);
                    }
                }
                alert('Error: ' + errorMsg + '. Check console for details.');
            }
        });
    });
    
    // Process queue now (Dashboard)
    $(document).on('click', '#process-queue-now', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true);
        button.html('<span class="dashicons dashicons-update spin"></span> Processing...');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_process_queue_manually',
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                console.log('Process queue response:', response);
                button.prop('disabled', false);
                button.html(originalText);
                
                if (response.success) {
                    var message = typeof response.data === 'string' ? response.data : 'Queue processed successfully';
                    alert('Success: ' + message);
                    location.reload();
                } else {
                    var errorMsg = 'Unknown error';
                    if (typeof response.data === 'string') {
                        errorMsg = response.data;
                    } else if (response.data && response.data.message) {
                        errorMsg = response.data.message;
                    }
                    alert('Error: ' + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('Process queue error:', xhr, status, error);
                console.error('Response text:', xhr.responseText);
                button.prop('disabled', false);
                button.html(originalText);
                
                var errorMsg = 'Failed to process queue';
                if (xhr.responseText) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.data) {
                            errorMsg = resp.data;
                        }
                    } catch (e) {
                        errorMsg = xhr.responseText.substring(0, 200);
                    }
                }
                alert('Error: ' + errorMsg + '. Check console for details.');
            }
        });
    });
    
    // Cancel queue item (dashboard)
    $(document).on('click', '.cancel-queue-item', function() {
        var button = $(this);
        var queueId = button.data('queue-id');
        
        if (!confirm('Are you sure you want to cancel this item? It will be marked as failed.')) {
            return;
        }
        
        button.prop('disabled', true).text('Cancelling...');
        
        $.ajax({
            url: idoklad_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_cancel_queue_item',
                queue_id: queueId,
                nonce: idoklad_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Success: ' + response.data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error: Failed to cancel item');
            },
            complete: function() {
                button.prop('disabled', false).text('Cancel');
            }
        });
    });
    
    // Add spinning animation for dashicons
    $('<style>')
        .text('.dashicons.spin { animation: dashicons-spin 1s linear infinite; } ' +
              '@keyframes dashicons-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }')
        .appendTo('head');
});

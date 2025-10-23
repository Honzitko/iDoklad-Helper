/**
 * JavaScript for iDoklad API v3 Integration Admin Interface
 */

jQuery(document).ready(function($) {
    
    // Test Integration Button
    $('#test-integration-btn').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('Testing Integration...');
        $('#integration-results').hide();
        $('#integration-output').html('Starting integration test...\n');
        
        $.ajax({
            url: idoklad_integration_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_integration',
                nonce: idoklad_integration_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var result = response.data.result;
                    var output = 'Integration Test Completed Successfully!\n\n';
                    output += 'Invoice ID: ' + result.invoice_id + '\n';
                    output += 'Document Number: ' + result.document_number + '\n';
                    output += 'Status Code: ' + result.status_code + '\n\n';
                    output += 'Full Response:\n';
                    output += JSON.stringify(result.response_data, null, 2);
                    
                    $('#integration-output').html(output);
                    $('#integration-results').show();
                } else {
                    $('#integration-output').html('Integration Test Failed:\n' + response.data.message);
                    $('#integration-results').show();
                }
            },
            error: function(xhr, status, error) {
                $('#integration-output').html('Integration Test Failed:\nAJAX Error: ' + error);
                $('#integration-results').show();
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Create Test Invoice Button
    $('#create-test-invoice-btn').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('Creating Test Invoice...');
        $('#integration-results').hide();
        $('#integration-output').html('Creating test invoice...\n');
        
        $.ajax({
            url: idoklad_integration_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_create_test_invoice',
                nonce: idoklad_integration_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var result = response.data.result;
                    var output = 'Test Invoice Created Successfully!\n\n';
                    output += 'Invoice ID: ' + result.invoice_id + '\n';
                    output += 'Document Number: ' + result.document_number + '\n';
                    output += 'Status Code: ' + result.status_code + '\n\n';
                    output += 'Full Response:\n';
                    output += JSON.stringify(result.response_data, null, 2);
                    
                    $('#integration-output').html(output);
                    $('#integration-results').show();
                } else {
                    $('#integration-output').html('Test Invoice Creation Failed:\n' + response.data.message);
                    $('#integration-results').show();
                }
            },
            error: function(xhr, status, error) {
                $('#integration-output').html('Test Invoice Creation Failed:\nAJAX Error: ' + error);
                $('#integration-results').show();
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Form validation
    $('form').on('submit', function() {
        var clientId = $('#idoklad_client_id').val();
        var clientSecret = $('#idoklad_client_secret').val();
        
        if (!clientId || !clientSecret) {
            alert('Client ID and Client Secret are required.');
            return false;
        }
        
        return true;
    });
    
    // Real-time validation feedback
    $('#idoklad_client_id, #idoklad_client_secret').on('blur', function() {
        var field = $(this);
        var value = field.val().trim();
        
        if (value === '') {
            field.addClass('error');
            field.next('.validation-message').remove();
            field.after('<span class="validation-message" style="color: red;">This field is required.</span>');
        } else {
            field.removeClass('error');
            field.next('.validation-message').remove();
        }
    });
    
    // Clear validation on input
    $('#idoklad_client_id, #idoklad_client_secret').on('input', function() {
        $(this).removeClass('error');
        $(this).next('.validation-message').remove();
    });
});

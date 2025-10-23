/**
 * JavaScript for PDF.co AI Parser Debug Admin Interface
 */

jQuery(document).ready(function($) {
    
    // Test Connection Button
    $('#test-connection-btn').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('Testing Connection...');
        $('#connection-results').hide();
        $('#connection-output').html('Testing API connection...\n');
        
        $.ajax({
            url: idoklad_pdf_co_debug_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_pdf_co_connection',
                nonce: idoklad_pdf_co_debug_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var output = 'Connection Test Completed Successfully!\n\n';
                    output += 'Success: ' + response.data.success + '\n';
                    output += 'Message: ' + response.data.message + '\n\n';
                    output += 'Full Response:\n';
                    output += JSON.stringify(response.data, null, 2);
                    
                    $('#connection-output').html(output);
                    $('#connection-results').show();
                } else {
                    $('#connection-output').html('Connection Test Failed:\n' + response.data.message);
                    $('#connection-results').show();
                }
            },
            error: function(xhr, status, error) {
                $('#connection-output').html('Connection Test Failed:\nAJAX Error: ' + error);
                $('#connection-results').show();
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Test Parser Button
    $('#test-parser-btn').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        var pdfUrl = $('#test-pdf-url').val();
        
        if (!pdfUrl) {
            alert('Please enter a PDF URL');
            return;
        }
        
        button.prop('disabled', true).text('Testing Parser...');
        $('#parser-results').hide();
        $('#parser-output').html('Testing AI parser...\n');
        
        $.ajax({
            url: idoklad_pdf_co_debug_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_pdf_co_parser',
                nonce: idoklad_pdf_co_debug_ajax.nonce,
                pdf_url: pdfUrl
            },
            success: function(response) {
                if (response.success) {
                    var output = 'Parser Test Completed Successfully!\n\n';
                    output += 'Message: ' + response.data.message + '\n\n';
                    output += 'Parsed Data:\n';
                    output += JSON.stringify(response.data.result, null, 2);
                    
                    $('#parser-output').html(output);
                    $('#parser-results').show();
                } else {
                    $('#parser-output').html('Parser Test Failed:\n' + response.data.message);
                    $('#parser-results').show();
                }
            },
            error: function(xhr, status, error) {
                $('#parser-output').html('Parser Test Failed:\nAJAX Error: ' + error);
                $('#parser-results').show();
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Test Step by Step Button
    $('#test-step-by-step-btn').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        var pdfUrl = $('#test-pdf-url').val();
        
        if (!pdfUrl) {
            alert('Please enter a PDF URL');
            return;
        }
        
        button.prop('disabled', true).text('Testing Step by Step...');
        $('#parser-results').hide();
        $('#parser-output').html('Testing step-by-step parsing...\n');
        
        $.ajax({
            url: idoklad_pdf_co_debug_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_test_pdf_co_step_by_step',
                nonce: idoklad_pdf_co_debug_ajax.nonce,
                pdf_url: pdfUrl
            },
            success: function(response) {
                if (response.success) {
                    var output = 'Step-by-Step Test Completed Successfully!\n\n';
                    output += 'Success: ' + response.data.success + '\n\n';
                    
                    if (response.data.debug_info) {
                        output += 'Debug Information:\n';
                        output += 'Job ID: ' + response.data.debug_info.job_id + '\n';
                        output += 'Steps Completed: ' + response.data.debug_info.steps_completed.join(', ') + '\n\n';
                        
                        output += 'Raw Result:\n';
                        output += JSON.stringify(response.data.debug_info.raw_result, null, 2) + '\n\n';
                        
                        output += 'Extracted Data:\n';
                        output += JSON.stringify(response.data.debug_info.extracted_data, null, 2) + '\n\n';
                        
                        output += 'iDoklad Data:\n';
                        output += JSON.stringify(response.data.data, null, 2) + '\n\n';
                        
                        output += 'Validation:\n';
                        output += JSON.stringify(response.data.debug_info.validation, null, 2);
                    }
                    
                    $('#parser-output').html(output);
                    $('#parser-results').show();
                } else {
                    $('#parser-output').html('Step-by-Step Test Failed:\n' + response.data.message);
                    $('#parser-results').show();
                }
            },
            error: function(xhr, status, error) {
                $('#parser-output').html('Step-by-Step Test Failed:\nAJAX Error: ' + error);
                $('#parser-results').show();
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Validate Payload Button
    $('#validate-payload-btn').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('Validating Payload...');
        $('#validation-results').hide();
        $('#validation-output').html('Validating iDoklad payload...\n');
        
        $.ajax({
            url: idoklad_pdf_co_debug_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'idoklad_validate_idoklad_payload',
                nonce: idoklad_pdf_co_debug_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var output = 'Payload Validation Completed!\n\n';
                    output += 'Message: ' + response.data.message + '\n\n';
                    
                    output += 'Sample Payload:\n';
                    output += JSON.stringify(response.data.payload, null, 2) + '\n\n';
                    
                    output += 'Validation Results:\n';
                    output += 'Is Valid: ' + response.data.validation.is_valid + '\n';
                    output += 'Required Fields Present: ' + response.data.validation.required_fields_present.join(', ') + '\n';
                    output += 'Required Fields Missing: ' + response.data.validation.required_fields_missing.join(', ') + '\n';
                    output += 'Errors: ' + response.data.validation.errors.join(', ') + '\n';
                    output += 'Warnings: ' + response.data.validation.warnings.join(', ') + '\n\n';
                    
                    output += 'Full Validation:\n';
                    output += JSON.stringify(response.data.validation, null, 2);
                    
                    $('#validation-output').html(output);
                    $('#validation-results').show();
                } else {
                    $('#validation-output').html('Payload Validation Failed:\n' + response.data.message);
                    $('#validation-results').show();
                }
            },
            error: function(xhr, status, error) {
                $('#validation-output').html('Payload Validation Failed:\nAJAX Error: ' + error);
                $('#validation-results').show();
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Form validation
    $('#test-pdf-url').on('blur', function() {
        var url = $(this).val();
        if (url && !isValidUrl(url)) {
            $(this).addClass('error');
            $(this).next('.validation-message').remove();
            $(this).after('<span class="validation-message" style="color: red;">Please enter a valid URL.</span>');
        } else {
            $(this).removeClass('error');
            $(this).next('.validation-message').remove();
        }
    });
    
    // Clear validation on input
    $('#test-pdf-url').on('input', function() {
        $(this).removeClass('error');
        $(this).next('.validation-message').remove();
    });
    
    // URL validation function
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
});

<?php
/**
 * Notification system class
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_Notification {
    
    private $notification_email;
    
    public function __construct() {
        $this->notification_email = get_option('idoklad_notification_email', get_option('admin_email'));
    }
    
    /**
     * Send success notification to user
     */
    public function send_success_notification($user_email, $extracted_data, $idoklad_response) {
        $subject = __('Invoice Successfully Processed', 'idoklad-invoice-processor');
        $message = $this->build_success_message($extracted_data, $idoklad_response);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $this->notification_email . '>'
        );
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Notification: Sending success notification to ' . $user_email);
        }
        
        $sent = wp_mail($user_email, $subject, $message, $headers);
        
        if (!$sent) {
            error_log('iDoklad Notification: Failed to send success notification to ' . $user_email);
        }
        
        return $sent;
    }
    
    /**
     * Send failure notification to user
     */
    public function send_failure_notification($user_email, $error_message, $error_type = 'processing') {
        $subject = __('Invoice Processing Failed', 'idoklad-invoice-processor');
        $message = $this->build_failure_message($error_message, $error_type);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $this->notification_email . '>'
        );
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Notification: Sending failure notification to ' . $user_email);
        }
        
        $sent = wp_mail($user_email, $subject, $message, $headers);
        
        if (!$sent) {
            error_log('iDoklad Notification: Failed to send failure notification to ' . $user_email);
        }
        
        return $sent;
    }
    
    /**
     * Build success message HTML
     */
    private function build_success_message($extracted_data, $idoklad_response) {
        $invoice_id = isset($idoklad_response['Id']) ? $idoklad_response['Id'] : 'N/A';
        $invoice_number = isset($extracted_data['invoice_number']) ? $extracted_data['invoice_number'] : 'N/A';
        $supplier_name = isset($extracted_data['supplier_name']) ? $extracted_data['supplier_name'] : 'N/A';
        $total_amount = isset($extracted_data['total_amount']) ? $extracted_data['total_amount'] : 'N/A';
        $currency = isset($extracted_data['currency']) ? $extracted_data['currency'] : 'CZK';
        
        $message = '<html><body>';
        $message .= '<h2>' . __('Invoice Successfully Processed', 'idoklad-invoice-processor') . '</h2>';
        $message .= '<p>' . __('Your invoice has been successfully processed and added to iDoklad.', 'idoklad-invoice-processor') . '</p>';
        
        $message .= '<h3>' . __('Invoice Details', 'idoklad-invoice-processor') . '</h3>';
        $message .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">';
        $message .= '<tr><td><strong>' . __('Invoice Number', 'idoklad-invoice-processor') . ':</strong></td><td>' . esc_html($invoice_number) . '</td></tr>';
        $message .= '<tr><td><strong>' . __('Supplier', 'idoklad-invoice-processor') . ':</strong></td><td>' . esc_html($supplier_name) . '</td></tr>';
        $message .= '<tr><td><strong>' . __('Total Amount', 'idoklad-invoice-processor') . ':</strong></td><td>' . esc_html($total_amount) . ' ' . esc_html($currency) . '</td></tr>';
        $message .= '<tr><td><strong>' . __('iDoklad ID', 'idoklad-invoice-processor') . ':</strong></td><td>' . esc_html($invoice_id) . '</td></tr>';
        $message .= '</table>';
        
        $message .= '<p>' . __('Thank you for using our automated invoice processing system.', 'idoklad-invoice-processor') . '</p>';
        $message .= '<p><em>' . __('This is an automated message. Please do not reply.', 'idoklad-invoice-processor') . '</em></p>';
        $message .= '</body></html>';
        
        return $message;
    }
    
    /**
     * Build failure message HTML
     */
    private function build_failure_message($error_message, $error_type) {
        $message = '<html><body>';
        $message .= '<h2>' . __('Invoice Processing Failed', 'idoklad-invoice-processor') . '</h2>';
        $message .= '<p>' . __('Unfortunately, we were unable to process your invoice. Please see the details below:', 'idoklad-invoice-processor') . '</p>';
        
        $message .= '<h3>' . __('Error Details', 'idoklad-invoice-processor') . '</h3>';
        $message .= '<p><strong>' . __('Error Type', 'idoklad-invoice-processor') . ':</strong> ' . esc_html($this->get_error_type_description($error_type)) . '</p>';
        $message .= '<p><strong>' . __('Error Message', 'idoklad-invoice-processor') . ':</strong> ' . esc_html($error_message) . '</p>';
        
        $message .= '<h3>' . __('What to do next', 'idoklad-invoice-processor') . '</h3>';
        $message .= '<ul>';
        $message .= '<li>' . __('Check that your PDF is clear and readable', 'idoklad-invoice-processor') . '</li>';
        $message .= '<li>' . __('Ensure the invoice contains all required information', 'idoklad-invoice-processor') . '</li>';
        $message .= '<li>' . __('Try sending the invoice again', 'idoklad-invoice-processor') . '</li>';
        $message .= '<li>' . __('Contact support if the problem persists', 'idoklad-invoice-processor') . '</li>';
        $message .= '</ul>';
        
        $message .= '<p><em>' . __('This is an automated message. Please do not reply.', 'idoklad-invoice-processor') . '</em></p>';
        $message .= '</body></html>';
        
        return $message;
    }
    
    /**
     * Get error type description
     */
    private function get_error_type_description($error_type) {
        $descriptions = array(
            'bad_scan' => __('Poor quality scan - PDF is not readable', 'idoklad-invoice-processor'),
            'incomplete_invoice' => __('Incomplete invoice - missing required information', 'idoklad-invoice-processor'),
            'fault_pdf' => __('Corrupted or invalid PDF file', 'idoklad-invoice-processor'),
            'wrong_format' => __('Wrong invoice format - not a standard invoice', 'idoklad-invoice-processor'),
            'unauthorized' => __('Unauthorized sender - your email is not in the authorized list', 'idoklad-invoice-processor'),
            'processing' => __('General processing error', 'idoklad-invoice-processor'),
            'api_error' => __('iDoklad API error', 'idoklad-invoice-processor'),
            'ai_error' => __('AI processing error', 'idoklad-invoice-processor')
        );
        
        return isset($descriptions[$error_type]) ? $descriptions[$error_type] : __('Unknown error', 'idoklad-invoice-processor');
    }
}

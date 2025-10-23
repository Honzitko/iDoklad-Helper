<?php
/**
 * Enhanced Notification System with comprehensive email integration
 * Provides rich email notifications that touch multiple system functions
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_NotificationV3 {
    
    private $logger;
    private $database;
    
    public function __construct() {
        $this->logger = IDokladProcessor_Logger::get_instance();
        $this->database = new IDokladProcessor_Database();
    }
    
    /**
     * Send comprehensive success notification
     */
    public function send_success_notification($recipient_email, $invoice_data, $idoklad_response, $email_context = null) {
        try {
            $subject = 'Invoice Successfully Processed - ' . ($invoice_data['document_number'] ?? 'Unknown');
            
            $message = $this->build_success_notification_template($invoice_data, $idoklad_response, $email_context);
            
            $this->send_email($recipient_email, $subject, $message);
            
            // Also send internal notification to admin
            $this->send_admin_notification('Invoice Processing Success', $message);
            
            $this->logger->info('Success notification sent to: ' . $recipient_email);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending success notification: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send invoice to customer with rich formatting
     */
    public function send_invoice_to_customer($customer_email, $invoice_data, $options = array()) {
        try {
            $subject = 'Invoice #' . ($invoice_data['DocumentNumber'] ?? 'Unknown') . ' - ' . ($invoice_data['CompanyName'] ?? 'Your Company');
            
            $message = $this->build_customer_invoice_template($invoice_data, $options);
            
            $this->send_email($customer_email, $subject, $message);
            
            $this->logger->info('Invoice sent to customer: ' . $customer_email);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending invoice to customer: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send payment reminder with multiple touchpoints
     */
    public function send_payment_reminder($customer_email, $invoice_data, $reminder_type = 'first') {
        try {
            $subject = 'Payment Reminder - Invoice #' . ($invoice_data['DocumentNumber'] ?? 'Unknown');
            
            $message = $this->build_payment_reminder_template($invoice_data, $reminder_type);
            
            $this->send_email($customer_email, $subject, $message);
            
            // Log reminder sent
            $this->log_reminder_sent($customer_email, $invoice_data, $reminder_type);
            
            $this->logger->info('Payment reminder sent to: ' . $customer_email . ' (Type: ' . $reminder_type . ')');
            
        } catch (Exception $e) {
            $this->logger->info('Error sending payment reminder: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send payment confirmation with receipt
     */
    public function send_payment_confirmation($customer_email, $payment_data, $invoice_data) {
        try {
            $subject = 'Payment Received - Invoice #' . ($invoice_data['DocumentNumber'] ?? 'Unknown');
            
            $message = $this->build_payment_confirmation_template($payment_data, $invoice_data);
            
            $this->send_email($customer_email, $subject, $message);
            
            // Send receipt as attachment if available
            if (!empty($payment_data['receipt_path'])) {
                $this->send_email_with_attachment($customer_email, $subject, $message, $payment_data['receipt_path']);
            }
            
            $this->logger->info('Payment confirmation sent to: ' . $customer_email);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending payment confirmation: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send system status report
     */
    public function send_system_status_report($recipient_email, $status_data) {
        try {
            $subject = 'iDoklad System Status Report - ' . date('Y-m-d H:i:s');
            
            $message = $this->build_system_status_template($status_data);
            
            $this->send_email($recipient_email, $subject, $message);
            
            $this->logger->info('System status report sent to: ' . $recipient_email);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending system status report: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send monthly summary report
     */
    public function send_monthly_summary($user_id, $summary_data) {
        try {
            $user = $this->database->get_user($user_id);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            $subject = 'Monthly iDoklad Summary - ' . date('F Y');
            
            $message = $this->build_monthly_summary_template($summary_data, $user);
            
            $this->send_email($user->email, $subject, $message);
            
            $this->logger->info('Monthly summary sent to user: ' . $user_id);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending monthly summary: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send error notification with context
     */
    public function send_error_notification($error_message, $context = array()) {
        try {
            $admin_email = get_option('admin_email');
            if (!$admin_email) {
                return;
            }
            
            $subject = 'iDoklad System Error - ' . date('Y-m-d H:i:s');
            
            $message = $this->build_error_notification_template($error_message, $context);
            
            $this->send_email($admin_email, $subject, $message);
            
            $this->logger->info('Error notification sent to admin');
            
        } catch (Exception $e) {
            $this->logger->info('Error sending error notification: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send admin notification
     */
    public function send_admin_notification($subject, $message) {
        try {
            $admin_email = get_option('admin_email');
            if (!$admin_email) {
                return;
            }
            
            $this->send_email($admin_email, $subject, $message);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending admin notification: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send bulk processing summary
     */
    public function send_bulk_processing_summary($recipient_email, $processing_results) {
        try {
            $subject = 'Bulk Processing Summary - ' . count($processing_results) . ' documents processed';
            
            $message = $this->build_bulk_processing_template($processing_results);
            
            $this->send_email($recipient_email, $subject, $message);
            
            $this->logger->info('Bulk processing summary sent to: ' . $recipient_email);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending bulk processing summary: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send contact update notification
     */
    public function send_contact_update_notification($contact_email, $contact_data, $update_type = 'created') {
        try {
            $subject = 'Contact ' . ucfirst($update_type) . ' - ' . ($contact_data['CompanyName'] ?? 'Contact');
            
            $message = $this->build_contact_update_template($contact_data, $update_type);
            
            $this->send_email($contact_email, $subject, $message);
            
            $this->logger->info('Contact update notification sent to: ' . $contact_email);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending contact update notification: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send scheduled report
     */
    public function send_scheduled_report($report_type, $recipients, $report_data) {
        try {
            foreach ($recipients as $recipient) {
                $subject = 'Scheduled ' . ucfirst($report_type) . ' Report - ' . date('Y-m-d');
                
                $message = $this->build_scheduled_report_template($report_type, $report_data, $recipient);
                
                $this->send_email($recipient['email'], $subject, $message);
            }
            
            $this->logger->info('Scheduled report sent: ' . $report_type . ' to ' . count($recipients) . ' recipients');
            
        } catch (Exception $e) {
            $this->logger->info('Error sending scheduled report: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send email with attachment
     */
    public function send_email_with_attachment($to, $subject, $message, $attachment_path) {
        try {
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
            );
            
            $attachments = array($attachment_path);
            
            wp_mail($to, $subject, $message, $headers, $attachments);
            
            $this->logger->info('Email with attachment sent to: ' . $to);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending email with attachment: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Send email using WordPress mail function
     */
    public function send_email($to, $subject, $message) {
        try {
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
            );
            
            wp_mail($to, $subject, $message, $headers);
            
        } catch (Exception $e) {
            $this->logger->info('Error sending email: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Build success notification template
     */
    private function build_success_notification_template($invoice_data, $idoklad_response, $email_context = null) {
        $template = '<html><body>';
        $template .= '<h2>Invoice Successfully Processed</h2>';
        $template .= '<p>Your invoice has been successfully processed and added to the system.</p>';
        
        $template .= '<h3>Invoice Details:</h3>';
        $template .= '<ul>';
        $template .= '<li><strong>Document Number:</strong> ' . ($invoice_data['document_number'] ?? 'N/A') . '</li>';
        $template .= '<li><strong>Amount:</strong> ' . ($invoice_data['total_amount'] ?? 'N/A') . '</li>';
        $template .= '<li><strong>Supplier:</strong> ' . ($invoice_data['supplier_name'] ?? 'N/A') . '</li>';
        $template .= '<li><strong>Date:</strong> ' . ($invoice_data['date'] ?? 'N/A') . '</li>';
        $template .= '</ul>';
        
        if ($idoklad_response) {
            $template .= '<h3>iDoklad Information:</h3>';
            $template .= '<ul>';
            $template .= '<li><strong>iDoklad ID:</strong> ' . ($idoklad_response['Id'] ?? 'N/A') . '</li>';
            $template .= '<li><strong>Status:</strong> Successfully created</li>';
            $template .= '</ul>';
        }
        
        if ($email_context) {
            $template .= '<h3>Processing Information:</h3>';
            $template .= '<ul>';
            $template .= '<li><strong>Processed from email:</strong> ' . $email_context['email_from'] . '</li>';
            $template .= '<li><strong>Email subject:</strong> ' . $email_context['email_subject'] . '</li>';
            $template .= '<li><strong>Processed at:</strong> ' . date('Y-m-d H:i:s') . '</li>';
            $template .= '</ul>';
        }
        
        $template .= '<p>Thank you for using our invoice processing system.</p>';
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Build customer invoice template
     */
    private function build_customer_invoice_template($invoice_data, $options = array()) {
        $template = '<html><body>';
        $template .= '<h2>Invoice #' . ($invoice_data['DocumentNumber'] ?? 'Unknown') . '</h2>';
        
        $template .= '<h3>Bill To:</h3>';
        $template .= '<p>' . ($invoice_data['PurchaserName'] ?? 'N/A') . '</p>';
        
        $template .= '<h3>Invoice Details:</h3>';
        $template .= '<table border="1" cellpadding="5" cellspacing="0">';
        $template .= '<tr><th>Description</th><th>Quantity</th><th>Unit Price</th><th>Total</th></tr>';
        
        if (!empty($invoice_data['Items'])) {
            foreach ($invoice_data['Items'] as $item) {
                $template .= '<tr>';
                $template .= '<td>' . ($item['Name'] ?? 'N/A') . '</td>';
                $template .= '<td>' . ($item['Amount'] ?? 'N/A') . '</td>';
                $template .= '<td>' . ($item['UnitPrice'] ?? 'N/A') . '</td>';
                $template .= '<td>' . (($item['Amount'] ?? 0) * ($item['UnitPrice'] ?? 0)) . '</td>';
                $template .= '</tr>';
            }
        }
        
        $template .= '</table>';
        
        $template .= '<h3>Total Amount: ' . ($invoice_data['TotalWithVat'] ?? 'N/A') . '</h3>';
        
        if (!empty($options['payment_instructions'])) {
            $template .= '<h3>Payment Instructions:</h3>';
            $template .= '<p>' . $options['payment_instructions'] . '</p>';
        }
        
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Build payment reminder template
     */
    private function build_payment_reminder_template($invoice_data, $reminder_type) {
        $template = '<html><body>';
        
        $reminder_messages = array(
            'first' => 'This is a friendly reminder that your invoice is due for payment.',
            'second' => 'This is a second reminder that your invoice payment is overdue.',
            'final' => 'This is a final notice that your invoice payment is significantly overdue.'
        );
        
        $message = $reminder_messages[$reminder_type] ?? $reminder_messages['first'];
        
        $template .= '<h2>Payment Reminder</h2>';
        $template .= '<p>' . $message . '</p>';
        
        $template .= '<h3>Invoice Details:</h3>';
        $template .= '<ul>';
        $template .= '<li><strong>Invoice Number:</strong> ' . ($invoice_data['DocumentNumber'] ?? 'N/A') . '</li>';
        $template .= '<li><strong>Amount Due:</strong> ' . ($invoice_data['TotalWithVat'] ?? 'N/A') . '</li>';
        $template .= '<li><strong>Due Date:</strong> ' . ($invoice_data['DateOfMaturity'] ?? 'N/A') . '</li>';
        $template .= '</ul>';
        
        if ($reminder_type === 'final') {
            $template .= '<p><strong>Please note:</strong> Further action may be taken if payment is not received promptly.</p>';
        }
        
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Build payment confirmation template
     */
    private function build_payment_confirmation_template($payment_data, $invoice_data) {
        $template = '<html><body>';
        $template .= '<h2>Payment Confirmation</h2>';
        $template .= '<p>Thank you for your payment. We have received and processed your payment.</p>';
        
        $template .= '<h3>Payment Details:</h3>';
        $template .= '<ul>';
        $template .= '<li><strong>Invoice Number:</strong> ' . ($invoice_data['DocumentNumber'] ?? 'N/A') . '</li>';
        $template .= '<li><strong>Payment Amount:</strong> ' . ($payment_data['amount'] ?? 'N/A') . '</li>';
        $template .= '<li><strong>Payment Date:</strong> ' . ($payment_data['date'] ?? date('Y-m-d')) . '</li>';
        $template .= '<li><strong>Payment Method:</strong> ' . ($payment_data['method'] ?? 'N/A') . '</li>';
        $template .= '</ul>';
        
        $template .= '<p>Your invoice has been marked as paid in our system.</p>';
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Build system status template
     */
    private function build_system_status_template($status_data) {
        $template = '<html><body>';
        $template .= '<h2>iDoklad System Status Report</h2>';
        
        $template .= '<h3>Connection Status:</h3>';
        $template .= '<p><strong>Status:</strong> ' . ($status_data['connected'] ? 'Connected' : 'Disconnected') . '</p>';
        $template .= '<p><strong>Last Check:</strong> ' . ($status_data['last_check'] ?? 'N/A') . '</p>';
        
        if ($status_data['connected']) {
            $template .= '<h3>System Information:</h3>';
            $template .= '<ul>';
            $template .= '<li><strong>User:</strong> ' . ($status_data['user_info']['UserName'] ?? 'N/A') . '</li>';
            $template .= '<li><strong>Company:</strong> ' . ($status_data['company_info']['Name'] ?? 'N/A') . '</li>';
            $template .= '</ul>';
        }
        
        $template .= '<h3>Recent Activity:</h3>';
        $template .= '<p>System is functioning normally.</p>';
        
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Build monthly summary template
     */
    private function build_monthly_summary_template($summary_data, $user) {
        $template = '<html><body>';
        $template .= '<h2>Monthly iDoklad Summary - ' . date('F Y') . '</h2>';
        
        $template .= '<h3>Summary for: ' . ($user->name ?? $user->email) . '</h3>';
        
        $template .= '<h3>Statistics:</h3>';
        $template .= '<ul>';
        $template .= '<li><strong>Total Invoices Processed:</strong> ' . ($summary_data['total_invoices'] ?? 0) . '</li>';
        $template .= '<li><strong>Total Amount Processed:</strong> ' . ($summary_data['total_amount'] ?? 0) . '</li>';
        $template .= '<li><strong>Emails Processed:</strong> ' . ($summary_data['total_emails'] ?? 0) . '</li>';
        $template .= '<li><strong>Contacts Created:</strong> ' . ($summary_data['total_contacts'] ?? 0) . '</li>';
        $template .= '</ul>';
        
        $template .= '<h3>Top Suppliers:</h3>';
        if (!empty($summary_data['top_suppliers'])) {
            $template .= '<ul>';
            foreach ($summary_data['top_suppliers'] as $supplier) {
                $template .= '<li>' . $supplier['name'] . ' - ' . $supplier['count'] . ' invoices</li>';
            }
            $template .= '</ul>';
        }
        
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Build error notification template
     */
    private function build_error_notification_template($error_message, $context = array()) {
        $template = '<html><body>';
        $template .= '<h2>iDoklad System Error</h2>';
        
        $template .= '<h3>Error Details:</h3>';
        $template .= '<p><strong>Error Message:</strong> ' . $error_message . '</p>';
        $template .= '<p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>';
        
        if (!empty($context)) {
            $template .= '<h3>Context:</h3>';
            $template .= '<ul>';
            foreach ($context as $key => $value) {
                $template .= '<li><strong>' . $key . ':</strong> ' . $value . '</li>';
            }
            $template .= '</ul>';
        }
        
        $template .= '<p>Please check the system logs for more details.</p>';
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Build bulk processing template
     */
    private function build_bulk_processing_template($processing_results) {
        $template = '<html><body>';
        $template .= '<h2>Bulk Processing Summary</h2>';
        
        $template .= '<h3>Processing Results:</h3>';
        $template .= '<ul>';
        $template .= '<li><strong>Total Documents:</strong> ' . count($processing_results) . '</li>';
        
        $successful = array_filter($processing_results, function($result) {
            return $result['status'] === 'success';
        });
        $template .= '<li><strong>Successful:</strong> ' . count($successful) . '</li>';
        
        $failed = array_filter($processing_results, function($result) {
            return $result['status'] === 'failed';
        });
        $template .= '<li><strong>Failed:</strong> ' . count($failed) . '</li>';
        $template .= '</ul>';
        
        if (!empty($failed)) {
            $template .= '<h3>Failed Documents:</h3>';
            $template .= '<ul>';
            foreach ($failed as $failure) {
                $template .= '<li>' . ($failure['document_number'] ?? 'Unknown') . ' - ' . ($failure['error'] ?? 'Unknown error') . '</li>';
            }
            $template .= '</ul>';
        }
        
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Build contact update template
     */
    private function build_contact_update_template($contact_data, $update_type) {
        $template = '<html><body>';
        $template .= '<h2>Contact ' . ucfirst($update_type) . '</h2>';
        
        $template .= '<h3>Contact Details:</h3>';
        $template .= '<ul>';
        $template .= '<li><strong>Company Name:</strong> ' . ($contact_data['CompanyName'] ?? 'N/A') . '</li>';
        $template .= '<li><strong>Email:</strong> ' . ($contact_data['Email'] ?? 'N/A') . '</li>';
        $template .= '<li><strong>Phone:</strong> ' . ($contact_data['Mobile'] ?? 'N/A') . '</li>';
        $template .= '<li><strong>Address:</strong> ' . ($contact_data['Street'] ?? 'N/A') . '</li>';
        $template .= '</ul>';
        
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Build scheduled report template
     */
    private function build_scheduled_report_template($report_type, $report_data, $recipient) {
        $template = '<html><body>';
        $template .= '<h2>Scheduled ' . ucfirst($report_type) . ' Report</h2>';
        
        $template .= '<h3>Report Period:</h3>';
        $template .= '<p>' . ($report_data['period'] ?? 'N/A') . '</p>';
        
        $template .= '<h3>Summary:</h3>';
        $template .= '<p>' . ($report_data['summary'] ?? 'No summary available') . '</p>';
        
        $template .= '</body></html>';
        
        return $template;
    }
    
    /**
     * Log reminder sent
     */
    private function log_reminder_sent($customer_email, $invoice_data, $reminder_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_reminders';
        
        $wpdb->insert(
            $table,
            array(
                'customer_email' => $customer_email,
                'invoice_id' => $invoice_data['Id'] ?? null,
                'document_number' => $invoice_data['DocumentNumber'] ?? null,
                'reminder_type' => $reminder_type,
                'sent_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );
    }
}

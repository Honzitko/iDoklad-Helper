<?php
/**
 * Email monitoring class - Updated to use per-user iDoklad credentials
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include required classes
require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-zapier-integration.php';

class IDokladProcessor_EmailMonitor {
    
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $connection;
    
    public function __construct() {
        $this->host = get_option('idoklad_email_host');
        $this->port = get_option('idoklad_email_port', 993);
        $this->username = get_option('idoklad_email_username');
        $this->password = get_option('idoklad_email_password');
        $this->encryption = get_option('idoklad_email_encryption', 'ssl');
        
        // Hook into WordPress cron
        add_action('idoklad_check_emails', array($this, 'check_for_new_emails'));
    }
    
    /**
     * Check for new emails and process them
     */
    public function check_for_new_emails() {
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Email Monitor: Starting email check');
        }
        
        try {
            $this->connect_to_email();
            $emails = $this->get_unread_emails();
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad Email Monitor: Found ' . count($emails) . ' unread emails');
            }
            
            foreach ($emails as $email_id) {
                $this->process_email($email_id);
            }
            
            $this->disconnect_from_email();
            
        } catch (Exception $e) {
            error_log('iDoklad Email Monitor Error: ' . $e->getMessage());
            $this->send_error_notification('Email monitoring failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Connect to email server
     */
    private function connect_to_email() {
        if (empty($this->host) || empty($this->username) || empty($this->password)) {
            throw new Exception('Email settings are not configured');
        }
        
        $connection_string = '{' . $this->host . ':' . $this->port . '/imap/' . $this->encryption . '}INBOX';
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Email Monitor: Connecting to ' . $connection_string);
        }
        
        $this->connection = imap_open($connection_string, $this->username, $this->password);
        
        if (!$this->connection) {
            throw new Exception('Failed to connect to email server: ' . imap_last_error());
        }
    }
    
    /**
     * Disconnect from email server
     */
    private function disconnect_from_email() {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }
    
    /**
     * Get unread emails
     */
    private function get_unread_emails() {
        $emails = array();
        
        // Search for unread emails
        $search_criteria = 'UNSEEN';
        $email_ids = imap_search($this->connection, $search_criteria);
        
        if ($email_ids) {
            $emails = $email_ids;
        }
        
        return $emails;
    }
    
    /**
     * Process individual email
     */
    private function process_email($email_id) {
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Email Monitor: Processing email ID ' . $email_id);
        }
        
        try {
            // Get email header
            $header = imap_headerinfo($this->connection, $email_id);
            $from_email = $this->extract_email_address($header->from[0]->mailbox . '@' . $header->from[0]->host);
            $subject = isset($header->subject) ? $header->subject : '';
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad Email Monitor: Email from ' . $from_email . ', subject: ' . $subject);
            }
            
            // Check if sender is authorized
            $authorized_user = IDokladProcessor_Database::get_authorized_user($from_email);
            if (!$authorized_user) {
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad Email Monitor: Unauthorized sender ' . $from_email);
                }
                
                // Mark email as read to avoid reprocessing
                imap_setflag_full($this->connection, $email_id, '\\Seen');
                
                // Log unauthorized attempt
                IDokladProcessor_Database::add_log(array(
                    'email_from' => $from_email,
                    'email_subject' => $subject,
                    'processing_status' => 'failed',
                    'error_message' => 'Unauthorized sender'
                ));
                
                return;
            }
            
            // Get email attachments
            $attachments = $this->get_email_attachments($email_id);
            
            if (empty($attachments)) {
                if (get_option('idoklad_debug_mode')) {
                    error_log('iDoklad Email Monitor: No attachments found in email from ' . $from_email);
                }
                
                // Mark email as read
                imap_setflag_full($this->connection, $email_id, '\\Seen');
                
                // Log no attachment
                IDokladProcessor_Database::add_log(array(
                    'email_from' => $from_email,
                    'email_subject' => $subject,
                    'processing_status' => 'failed',
                    'error_message' => 'No PDF attachments found'
                ));
                
                return;
            }
            
            // Process each PDF attachment
            foreach ($attachments as $attachment) {
                if ($this->is_pdf_attachment($attachment)) {
                    $this->process_pdf_attachment($email_id, $from_email, $subject, $attachment, $authorized_user);
                }
            }
            
            // Mark email as read
            imap_setflag_full($this->connection, $email_id, '\\Seen');
            
        } catch (Exception $e) {
            error_log('iDoklad Email Monitor: Error processing email ' . $email_id . ': ' . $e->getMessage());
            
            // Log error
            IDokladProcessor_Database::add_log(array(
                'email_from' => isset($from_email) ? $from_email : 'unknown',
                'email_subject' => isset($subject) ? $subject : '',
                'processing_status' => 'failed',
                'error_message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get email attachments
     */
    private function get_email_attachments($email_id) {
        $attachments = array();
        
        $structure = imap_fetchstructure($this->connection, $email_id);
        
        if (isset($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $part_number => $part) {
                $this->extract_attachments_from_part($email_id, $part, $part_number + 1, $attachments);
            }
        }
        
        return $attachments;
    }
    
    /**
     * Extract attachments from email part
     */
    private function extract_attachments_from_part($email_id, $part, $part_number, &$attachments) {
        $disposition = '';
        $filename = '';
        
        if (isset($part->dparameters) && is_array($part->dparameters)) {
            foreach ($part->dparameters as $param) {
                if (strtolower($param->attribute) === 'filename') {
                    $filename = $param->value;
                }
            }
        }
        
        if (isset($part->parameters) && is_array($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) === 'name') {
                    $filename = $param->value;
                }
            }
        }
        
        if (isset($part->disposition)) {
            $disposition = strtolower($part->disposition);
        }
        
        if ($disposition === 'attachment' || !empty($filename)) {
            $attachment_data = imap_fetchbody($this->connection, $email_id, $part_number);
            
            // Decode attachment
            if ($part->encoding == 3) { // base64
                $attachment_data = base64_decode($attachment_data);
            } elseif ($part->encoding == 4) { // quoted-printable
                $attachment_data = quoted_printable_decode($attachment_data);
            }
            
            $attachments[] = array(
                'filename' => $filename,
                'data' => $attachment_data,
                'size' => strlen($attachment_data)
            );
        }
        
        // Check for nested parts
        if (isset($part->parts) && is_array($part->parts)) {
            foreach ($part->parts as $nested_part_number => $nested_part) {
                $this->extract_attachments_from_part($email_id, $nested_part, $part_number . '.' . ($nested_part_number + 1), $attachments);
            }
        }
    }
    
    /**
     * Check if attachment is PDF
     */
    private function is_pdf_attachment($attachment) {
        $filename = strtolower($attachment['filename']);
        return substr($filename, -4) === '.pdf' || substr($filename, -4) === '.PDF';
    }
    
    /**
     * Process PDF attachment
     */
    private function process_pdf_attachment($email_id, $from_email, $subject, $attachment, $authorized_user) {
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Email Monitor: Processing PDF attachment: ' . $attachment['filename']);
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $idoklad_dir = $upload_dir['basedir'] . '/idoklad-invoices';
        
        if (!file_exists($idoklad_dir)) {
            wp_mkdir_p($idoklad_dir);
        }
        
        // Generate unique filename
        $filename = sanitize_file_name($attachment['filename']);
        $unique_filename = date('Y-m-d_H-i-s') . '_' . $email_id . '_' . $filename;
        $file_path = $idoklad_dir . '/' . $unique_filename;
        
        // Save attachment to file
        if (file_put_contents($file_path, $attachment['data']) === false) {
            throw new Exception('Failed to save PDF attachment');
        }
        
        // Add to processing queue
        $queue_data = array(
            'email_id' => $email_id . '_' . md5($attachment['filename']),
            'email_from' => $from_email,
            'email_subject' => $subject,
            'attachment_path' => $file_path
        );
        
        IDokladProcessor_Database::add_to_queue($queue_data);
        
        // Log processing start
        $log_id = IDokladProcessor_Database::add_log(array(
            'email_from' => $from_email,
            'email_subject' => $subject,
            'attachment_name' => $attachment['filename'],
            'processing_status' => 'pending'
        ));
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Email Monitor: Added to queue - Email: ' . $from_email . ', File: ' . $attachment['filename']);
        }
    }
    
    /**
     * Process pending emails from queue - Updated to use per-user credentials
     */
    public function process_pending_emails() {
        $pending_emails = IDokladProcessor_Database::get_pending_queue(10);
        $processed_count = 0;
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Email Monitor: Processing ' . count($pending_emails) . ' pending emails');
        }
        
        foreach ($pending_emails as $email) {
            try {
                // Mark as processing
                IDokladProcessor_Database::update_queue_status($email->id, 'processing', true);
                IDokladProcessor_Database::add_queue_step($email->id, 'Started processing');
                
                // Process the email
                $this->process_single_email($email);
                
                // Mark as completed
                IDokladProcessor_Database::update_queue_status($email->id, 'completed');
                $processed_count++;
                
            } catch (Exception $e) {
                error_log('iDoklad Email Monitor: Error processing email ' . $email->id . ': ' . $e->getMessage());
                
                // Add error to queue steps
                IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: Processing failed', array(
                    'error' => $e->getMessage(),
                    'trace' => substr($e->getTraceAsString(), 0, 500)
                ));
                
                // Mark as failed
                IDokladProcessor_Database::update_queue_status($email->id, 'failed', true);
                
                // Update log
                $this->update_log_for_email($email, 'failed', null, null, $e->getMessage());
            } catch (Error $e) {
                // Catch fatal errors (PHP 7+)
                error_log('iDoklad Email Monitor: Fatal error processing email ' . $email->id . ': ' . $e->getMessage());
                
                // Add error to queue steps
                IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: Fatal error occurred', array(
                    'error' => $e->getMessage(),
                    'trace' => substr($e->getTraceAsString(), 0, 500)
                ));
                
                // Mark as failed
                IDokladProcessor_Database::update_queue_status($email->id, 'failed', true);
                
                // Update log
                $this->update_log_for_email($email, 'failed', null, null, 'Fatal error: ' . $e->getMessage());
            }
        }
        
        return $processed_count;
    }
    
    /**
     * Process single email from queue - Updated to use per-user credentials
     */
    private function process_single_email($email) {
        // Step 1: Check file exists
        IDokladProcessor_Database::add_queue_step($email->id, 'Checking PDF file', array(
            'path' => $email->attachment_path
        ));
        
        if (!file_exists($email->attachment_path)) {
            IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: PDF file not found', array(
                'path' => $email->attachment_path
            ));
            throw new Exception('PDF file not found: ' . $email->attachment_path);
        }
        
        IDokladProcessor_Database::add_queue_step($email->id, 'PDF file found', array(
            'size' => filesize($email->attachment_path) . ' bytes'
        ));
        
        // Step 2: Get user credentials
        IDokladProcessor_Database::add_queue_step($email->id, 'Looking up authorized user', array(
            'email' => $email->email_from
        ));
        
        $authorized_user = IDokladProcessor_Database::get_authorized_user($email->email_from);
        if (!$authorized_user) {
            IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: User not authorized', array(
                'email' => $email->email_from
            ));
            throw new Exception('User not found or not authorized: ' . $email->email_from);
        }
        
        IDokladProcessor_Database::add_queue_step($email->id, 'User authorized', array(
            'user_id' => $authorized_user->id,
            'user_name' => $authorized_user->name
        ));
        
        // Step 3: Initialize processors
        IDokladProcessor_Database::add_queue_step($email->id, 'Initializing processors');
        
        $pdf_processor = new IDokladProcessor_PDFProcessor();
        $zapier = new IDokladProcessor_ZapierIntegration();
        $idoklad_api = new IDokladProcessor_IDokladAPI($authorized_user); // Pass user credentials
        $notification = new IDokladProcessor_Notification();
        
        // Step 4: Extract text from PDF
        IDokladProcessor_Database::add_queue_step($email->id, 'Extracting text from PDF', array(
            'filename' => basename($email->attachment_path)
        ));
        
        // Pass queue ID to PDF processor for detailed logging
        $pdf_text = $pdf_processor->extract_text($email->attachment_path, $email->id);
        
        if (empty($pdf_text)) {
            IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: Could not extract text from PDF');
            throw new Exception('Could not extract text from PDF');
        }
        
        IDokladProcessor_Database::add_queue_step($email->id, 'Text extracted successfully', array(
            'text_length' => strlen($pdf_text) . ' characters',
            'preview' => substr($pdf_text, 0, 100) . '...'
        ));
        
        // Step 5: Prepare email data for Zapier
        IDokladProcessor_Database::add_queue_step($email->id, 'Preparing data for Zapier');
        
        $email_data = array(
            'email_from' => $email->email_from,
            'email_subject' => $email->email_subject,
            'attachment_name' => basename($email->attachment_path)
        );
        
        // Step 6: Process through Zapier webhook
        IDokladProcessor_Database::add_queue_step($email->id, 'Sending to Zapier webhook');
        
        try {
            $zapier_response = $zapier->process_invoice($pdf_text, $email_data);
            
            IDokladProcessor_Database::add_queue_step($email->id, 'Zapier processing successful', array(
                'response_received' => !empty($zapier_response)
            ));
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad Email Monitor: Zapier processing successful');
            }
            
            // If Zapier returns structured data, use it for iDoklad
            if (isset($zapier_response['response']) && is_array($zapier_response['response'])) {
                $extracted_data = $zapier_response['response'];
            } else {
                // If no structured data returned, create basic structure
                $extracted_data = array(
                    'invoice_number' => 'ZAPIER-' . date('YmdHis'),
                    'date' => date('Y-m-d'),
                    'total_amount' => 0,
                    'currency' => 'CZK',
                    'supplier_name' => $email->email_from,
                    'pdf_text' => $pdf_text,
                    'zapier_processed' => true,
                    'zapier_response' => $zapier_response
                );
            }
            
        } catch (Exception $e) {
            IDokladProcessor_Database::add_queue_step($email->id, 'Zapier processing failed (using fallback)', array(
                'error' => $e->getMessage()
            ));
            
            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad Email Monitor: Zapier processing failed: ' . $e->getMessage());
            }
            
            // Create basic structure if Zapier fails
            $extracted_data = array(
                'invoice_number' => 'FALLBACK-' . date('YmdHis'),
                'date' => date('Y-m-d'),
                'total_amount' => 0,
                'currency' => 'CZK',
                'supplier_name' => $email->email_from,
                'pdf_text' => $pdf_text,
                'zapier_processed' => false,
                'zapier_error' => $e->getMessage()
            );
        }
        
        // Step 7: Validate extracted data
        IDokladProcessor_Database::add_queue_step($email->id, 'Validating extracted data');
        
        $validation_result = $this->validate_extracted_data($extracted_data);
        if (!$validation_result['valid']) {
            IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: Validation failed', array(
                'errors' => $validation_result['errors']
            ));
            throw new Exception('Invalid invoice data: ' . implode(', ', $validation_result['errors']));
        }
        
        IDokladProcessor_Database::add_queue_step($email->id, 'Data validated successfully', array(
            'invoice_number' => $extracted_data['invoice_number'] ?? 'N/A',
            'supplier' => $extracted_data['supplier_name'] ?? 'N/A',
            'amount' => $extracted_data['total_amount'] ?? 'N/A'
        ));
        
        // Step 8: Create invoice in iDoklad using user's credentials
        IDokladProcessor_Database::add_queue_step($email->id, 'Creating invoice in iDoklad', array(
            'invoice_number' => $extracted_data['invoice_number'] ?? 'N/A'
        ));
        
        $idoklad_response = $idoklad_api->create_invoice($extracted_data);
        
        if (!$idoklad_response) {
            IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: Failed to create invoice in iDoklad');
            throw new Exception('Failed to create invoice in iDoklad');
        }
        
        IDokladProcessor_Database::add_queue_step($email->id, 'Invoice created in iDoklad successfully', array(
            'response' => is_array($idoklad_response) ? array_keys($idoklad_response) : 'Response received'
        ));
        
        // Step 9: Update log with success
        $this->update_log_for_email($email, 'success', $extracted_data, $idoklad_response);
        
        // Step 10: Send success notification
        IDokladProcessor_Database::add_queue_step($email->id, 'Sending success notification');
        $notification->send_success_notification($email->email_from, $extracted_data, $idoklad_response);
        
        // Step 11: Clean up PDF file
        IDokladProcessor_Database::add_queue_step($email->id, 'Cleaning up PDF file');
        unlink($email->attachment_path);
        
        IDokladProcessor_Database::add_queue_step($email->id, 'Processing completed successfully');
        
        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Email Monitor: Successfully processed email from ' . $email->email_from);
        }
    }
    
    /**
     * Update log for email processing
     */
    private function update_log_for_email($email, $status, $extracted_data = null, $idoklad_response = null, $error_message = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_logs';
        
        $update_data = array(
            'processing_status' => $status,
            'processed_at' => current_time('mysql')
        );
        
        if ($extracted_data) {
            $update_data['extracted_data'] = json_encode($extracted_data);
        }
        
        if ($idoklad_response) {
            $update_data['idoklad_response'] = json_encode($idoklad_response);
        }
        
        if ($error_message) {
            $update_data['error_message'] = $error_message;
        }
        
        $wpdb->update(
            $table,
            $update_data,
            array('email_from' => $email->email_from, 'email_subject' => $email->email_subject),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%s', '%s')
        );
    }
    
    /**
     * Validate extracted invoice data
     */
    private function validate_extracted_data($data) {
        $errors = array();
        $required_fields = array('invoice_number', 'date', 'total_amount', 'supplier_name');
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        // Validate date format
        if (!empty($data['date']) && !strtotime($data['date'])) {
            $errors[] = "Invalid date format";
        }
        
        // Validate amount
        if (!empty($data['total_amount']) && !is_numeric($data['total_amount'])) {
            $errors[] = "Invalid total amount";
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Extract email address from header
     */
    private function extract_email_address($email_string) {
        if (preg_match('/<(.+)>/', $email_string, $matches)) {
            return $matches[1];
        }
        return $email_string;
    }
    
    /**
     * Send error notification
     */
    private function send_error_notification($message) {
        $notification_email = get_option('idoklad_notification_email');
        if ($notification_email) {
            wp_mail(
                $notification_email,
                'iDoklad Invoice Processor Error',
                $message,
                array('Content-Type: text/html; charset=UTF-8')
            );
        }
    }
}

<?php
/**
 * Enhanced Email Monitor with comprehensive iDoklad integration
 * Provides deep email functionality that touches multiple system functions
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_EmailMonitorV3 {
    
    private $logger;
    private $notification;
    private $database;
    
    public function __construct() {
        $this->logger = IDokladProcessor_Logger::get_instance();
        $this->notification = new IDokladProcessor_Notification();
        $this->database = new IDokladProcessor_Database();
        
        // Hook into WordPress cron
        add_action('idoklad_check_emails_v3', array($this, 'check_for_new_emails'));
        
        // Hook into email processing completion
        add_action('idoklad_email_processed', array($this, 'handle_email_processed'), 10, 3);
    }
    
    /**
     * Enhanced email checking with comprehensive processing
     */
    public function check_for_new_emails() {
        $this->logger->info('Starting enhanced email monitoring');
        
        try {
            $email_connections = $this->get_email_connections();
            
            foreach ($email_connections as $connection) {
                $this->process_email_connection($connection);
            }
            
            // Process pending emails from queue
            $this->process_pending_emails();
            
            // Clean up old processed emails
            $this->cleanup_old_emails();
            
        } catch (Exception $e) {
            $this->logger->error('Email monitoring error: ' . $e->getMessage());
            $this->notification->send_error_notification('Email monitoring failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all configured email connections
     */
    private function get_email_connections() {
        $connections = array();
        
        // Get global email settings
        $global_connection = array(
            'type' => 'global',
            'host' => get_option('idoklad_email_host'),
            'port' => get_option('idoklad_email_port', 993),
            'username' => get_option('idoklad_email_username'),
            'password' => get_option('idoklad_email_password'),
            'encryption' => get_option('idoklad_email_encryption', 'ssl'),
            'user_id' => null
        );
        
        if (!empty($global_connection['host']) && !empty($global_connection['username'])) {
            $connections[] = $global_connection;
        }
        
        // Get per-user email settings
        $users = $this->database->get_all_users();
        foreach ($users as $user) {
            if (!empty($user->email_host) && !empty($user->email_username)) {
                $connections[] = array(
                    'type' => 'user',
                    'host' => $user->email_host,
                    'port' => $user->email_port ?: 993,
                    'username' => $user->email_username,
                    'password' => $user->email_password,
                    'encryption' => $user->email_encryption ?: 'ssl',
                    'user_id' => $user->id,
                    'user_data' => $user
                );
            }
        }
        
        return $connections;
    }
    
    /**
     * Process individual email connection
     */
    private function process_email_connection($connection) {
        $this->logger->info('Processing email connection: ' . $connection['username']);
        
        try {
            $imap_connection = $this->connect_to_email($connection);
            $emails = $this->get_unread_emails($imap_connection);
            
            $this->logger->info('Found ' . count($emails) . ' unread emails for ' . $connection['username']);
            
            foreach ($emails as $email_id) {
                $this->process_single_email($email_id, $imap_connection, $connection);
            }
            
            $this->disconnect_from_email($imap_connection);
            
        } catch (Exception $e) {
            $this->logger->error('Error processing email connection: ' . $e->getMessage());
        }
    }
    
    /**
     * Connect to email server
     */
    private function connect_to_email($connection) {
        $connection_string = '{' . $connection['host'] . ':' . $connection['port'] . '/imap/' . $connection['encryption'] . '}INBOX';
        
        $imap_connection = imap_open($connection_string, $connection['username'], $connection['password']);
        
        if (!$imap_connection) {
            throw new Exception('Failed to connect to email server: ' . imap_last_error());
        }
        
        return $imap_connection;
    }
    
    /**
     * Disconnect from email server
     */
    private function disconnect_from_email($imap_connection) {
        if ($imap_connection) {
            imap_close($imap_connection);
        }
    }
    
    /**
     * Get unread emails
     */
    private function get_unread_emails($imap_connection) {
        $emails = array();
        
        // Search for unread emails
        $email_ids = imap_search($imap_connection, 'UNSEEN');
        
        if ($email_ids) {
            $emails = $email_ids;
        }
        
        return $emails;
    }
    
    /**
     * Process single email with comprehensive functionality
     */
    private function process_single_email($email_id, $imap_connection, $connection) {
        $this->logger->info('Processing email ID: ' . $email_id);
        
        try {
            // Get email details
            $email_details = $this->get_email_details($email_id, $imap_connection);
            
            // Check if sender is authorized
            $authorized_user = $this->get_authorized_user($email_details['from_email'], $connection);
            if (!$authorized_user) {
                $this->handle_unauthorized_email($email_id, $imap_connection, $email_details);
                return;
            }
            
            // Process email based on content and attachments
            $processing_result = $this->process_email_content($email_id, $imap_connection, $email_details, $authorized_user);
            
            // Mark email as read
            imap_setflag_full($imap_connection, $email_id, '\\Seen');
            
            // Trigger email processed action
            do_action('idoklad_email_processed', $email_details, $processing_result, $authorized_user);
            
        } catch (Exception $e) {
            $this->logger->error('Error processing email ' . $email_id . ': ' . $e->getMessage());
            $this->handle_email_processing_error($email_id, $imap_connection, $e);
        }
    }
    
    /**
     * Get comprehensive email details
     */
    private function get_email_details($email_id, $imap_connection) {
        $header = imap_headerinfo($imap_connection, $email_id);
        
        $details = array(
            'email_id' => $email_id,
            'from_email' => $this->extract_email_address($header->from[0]->mailbox . '@' . $header->from[0]->host),
            'from_name' => $header->from[0]->personal ?? '',
            'subject' => isset($header->subject) ? $header->subject : '',
            'date' => date('Y-m-d H:i:s', $header->udate),
            'message_id' => $header->message_id ?? '',
            'reply_to' => isset($header->reply_to) ? $this->extract_email_address($header->reply_to[0]->mailbox . '@' . $header->reply_to[0]->host) : '',
            'attachments' => array(),
            'body' => '',
            'body_html' => ''
        );
        
        // Get email body
        $body = imap_fetchbody($imap_connection, $email_id, 1);
        $details['body'] = $body;
        
        // Try to get HTML body
        $structure = imap_fetchstructure($imap_connection, $email_id);
        if (isset($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $part_number => $part) {
                if ($part->subtype === 'HTML') {
                    $details['body_html'] = imap_fetchbody($imap_connection, $email_id, $part_number + 1);
                    break;
                }
            }
        }
        
        // Get attachments
        $details['attachments'] = $this->get_email_attachments($email_id, $imap_connection);
        
        return $details;
    }
    
    /**
     * Get authorized user for email sender
     */
    private function get_authorized_user($email_address, $connection) {
        // First check if it's the same user as the connection
        if ($connection['type'] === 'user' && $connection['user_data']->email === $email_address) {
            return $connection['user_data'];
        }
        
        // Check database for authorized users
        return $this->database->get_authorized_user($email_address);
    }
    
    /**
     * Handle unauthorized email
     */
    private function handle_unauthorized_email($email_id, $imap_connection, $email_details) {
        $this->logger->info('Unauthorized email from: ' . $email_details['from_email']);
        
        // Log unauthorized attempt
        $this->database->add_log(array(
            'email_from' => $email_details['from_email'],
            'email_subject' => $email_details['subject'],
            'processing_status' => 'unauthorized',
            'error_message' => 'Unauthorized sender'
        ));
        
        // Send notification to admin
        $this->notification->send_admin_notification(
            'Unauthorized Email Access Attempt',
            "Unauthorized email received from: " . $email_details['from_email'] . "\n" .
            "Subject: " . $email_details['subject'] . "\n" .
            "Date: " . $email_details['date']
        );
        
        // Optionally send auto-reply to sender
        $this->send_unauthorized_reply($email_details, $imap_connection);
    }
    
    /**
     * Send auto-reply for unauthorized emails
     */
    private function send_unauthorized_reply($email_details, $imap_connection) {
        $auto_reply_enabled = get_option('idoklad_send_unauthorized_reply', false);
        
        if ($auto_reply_enabled) {
            $reply_subject = 'Re: ' . $email_details['subject'];
            $reply_body = "Thank you for your email. However, your email address is not authorized to use this invoice processing system.\n\n" .
                         "Please contact the administrator to request access.\n\n" .
                         "This is an automated response.";
            
            // Note: WordPress wp_mail would be used here for sending
            // This is a simplified version - in reality you'd need proper email sending
            $this->logger->info('Would send auto-reply to: ' . $email_details['from_email']);
        }
    }
    
    /**
     * Process email content with multiple functions
     */
    private function process_email_content($email_id, $imap_connection, $email_details, $authorized_user) {
        $processing_result = array(
            'email_id' => $email_id,
            'processing_type' => 'unknown',
            'documents_processed' => 0,
            'errors' => array(),
            'warnings' => array(),
            'created_records' => array()
        );
        
        // Determine processing type based on email content and attachments
        $processing_type = $this->determine_processing_type($email_details);
        $processing_result['processing_type'] = $processing_type;
        
        switch ($processing_type) {
            case 'invoice_pdf':
                $processing_result = $this->process_invoice_email($email_details, $authorized_user, $processing_result);
                break;
                
            case 'expense_receipt':
                $processing_result = $this->process_expense_email($email_details, $authorized_user, $processing_result);
                break;
                
            case 'contact_update':
                $processing_result = $this->process_contact_update_email($email_details, $authorized_user, $processing_result);
                break;
                
            case 'bulk_processing':
                $processing_result = $this->process_bulk_email($email_details, $authorized_user, $processing_result);
                break;
                
            case 'command_email':
                $processing_result = $this->process_command_email($email_details, $authorized_user, $processing_result);
                break;
                
            default:
                $processing_result['warnings'][] = 'Unknown email type, processing as general invoice';
                $processing_result = $this->process_invoice_email($email_details, $authorized_user, $processing_result);
        }
        
        return $processing_result;
    }
    
    /**
     * Determine email processing type
     */
    private function determine_processing_type($email_details) {
        $subject = strtolower($email_details['subject']);
        $body = strtolower($email_details['body']);
        
        // Check for PDF attachments
        $has_pdf = false;
        foreach ($email_details['attachments'] as $attachment) {
            if (strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION)) === 'pdf') {
                $has_pdf = true;
                break;
            }
        }
        
        // Command-based processing
        if (strpos($subject, '[command]') !== false || strpos($body, '[command]') !== false) {
            return 'command_email';
        }
        
        // Bulk processing
        if (strpos($subject, '[bulk]') !== false || strpos($body, '[bulk]') !== false) {
            return 'bulk_processing';
        }
        
        // Contact update
        if (strpos($subject, 'contact') !== false || strpos($subject, 'update') !== false) {
            return 'contact_update';
        }
        
        // Expense receipt
        if (strpos($subject, 'receipt') !== false || strpos($subject, 'expense') !== false) {
            return 'expense_receipt';
        }
        
        // Default to invoice processing if PDF present
        if ($has_pdf) {
            return 'invoice_pdf';
        }
        
        return 'general';
    }
    
    /**
     * Process invoice email
     */
    private function process_invoice_email($email_details, $authorized_user, $processing_result) {
        try {
            $this->logger->info('Processing invoice email from: ' . $email_details['from_email']);
            
            // Initialize iDoklad API v3
            // TODO: iDoklad API integration removed - to be rebuilt
            // if (class_exists('IDokladProcessor_IDokladAPIV3')) {
            //     $idoklad_api = new IDokladProcessor_IDokladAPIV3($authorized_user);
            // } else {
            //     $idoklad_api = new IDokladProcessor_IDokladAPI($authorized_user);
            // }
            
            foreach ($email_details['attachments'] as $attachment) {
                if (strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION)) === 'pdf') {
                    // Process PDF attachment
                    $pdf_result = $this->process_pdf_attachment($attachment, $authorized_user, $email_details);
                    
                    if ($pdf_result['success']) {
                        // Create invoice in iDoklad
                        $invoice_data = $pdf_result['invoice_data'];
                        $invoice_result = $idoklad_api->create_received_invoice($invoice_data, $email_details);
                        
                        $processing_result['created_records'][] = array(
                            'type' => 'invoice',
                            'id' => $invoice_result['Id'],
                            'document_number' => $invoice_result['DocumentNumber']
                        );
                        
                        $processing_result['documents_processed']++;
                    } else {
                        $processing_result['errors'][] = 'Failed to process PDF: ' . $pdf_result['error'];
                    }
                }
            }
            
        } catch (Exception $e) {
            $processing_result['errors'][] = 'Invoice processing error: ' . $e->getMessage();
            $this->logger->error('Invoice processing error: ' . $e->getMessage());
        }
        
        return $processing_result;
    }
    
    /**
     * Process expense email
     */
    private function process_expense_email($email_details, $authorized_user, $processing_result) {
        try {
            $this->logger->info('Processing expense email from: ' . $email_details['from_email']);
            
            // TODO: iDoklad API integration removed - to be rebuilt
            // if (class_exists('IDokladProcessor_IDokladAPIV3')) {
            //     $idoklad_api = new IDokladProcessor_IDokladAPIV3($authorized_user);
            // } else {
            //     $idoklad_api = new IDokladProcessor_IDokladAPI($authorized_user);
            // }
            
            foreach ($email_details['attachments'] as $attachment) {
                if (strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION)) === 'pdf') {
                    $pdf_result = $this->process_pdf_attachment($attachment, $authorized_user, $email_details);
                    
                    if ($pdf_result['success']) {
                        // Convert to expense data
                        $expense_data = $this->convert_to_expense_data($pdf_result['invoice_data']);
                        $expense_result = $idoklad_api->create_expense($expense_data, $email_details);
                        
                        $processing_result['created_records'][] = array(
                            'type' => 'expense',
                            'id' => $expense_result['Id'],
                            'document_number' => $expense_result['DocumentNumber']
                        );
                        
                        $processing_result['documents_processed']++;
                    } else {
                        $processing_result['errors'][] = 'Failed to process expense PDF: ' . $pdf_result['error'];
                    }
                }
            }
            
        } catch (Exception $e) {
            $processing_result['errors'][] = 'Expense processing error: ' . $e->getMessage();
            $this->logger->error('Expense processing error: ' . $e->getMessage());
        }
        
        return $processing_result;
    }
    
    /**
     * Process contact update email
     */
    private function process_contact_update_email($email_details, $authorized_user, $processing_result) {
        try {
            $this->logger->info('Processing contact update email from: ' . $email_details['from_email']);
            
            // Extract contact information from email body
            $contact_data = $this->extract_contact_data_from_email($email_details);
            
            if (!empty($contact_data)) {
                // TODO: iDoklad API integration removed - to be rebuilt
            // if (class_exists('IDokladProcessor_IDokladAPIV3')) {
            //     $idoklad_api = new IDokladProcessor_IDokladAPIV3($authorized_user);
            // } else {
            //     $idoklad_api = new IDokladProcessor_IDokladAPI($authorized_user);
            // }
                $contact_result = $idoklad_api->get_or_create_contact($contact_data, $email_details);
                
                $processing_result['created_records'][] = array(
                    'type' => 'contact',
                    'id' => $contact_result['Id'],
                    'name' => $contact_result['CompanyName'] ?? $contact_result['Firstname'] . ' ' . $contact_result['Surname']
                );
                
                $processing_result['documents_processed']++;
            } else {
                $processing_result['warnings'][] = 'No contact information found in email';
            }
            
        } catch (Exception $e) {
            $processing_result['errors'][] = 'Contact update error: ' . $e->getMessage();
            $this->logger->error('Contact update error: ' . $e->getMessage());
        }
        
        return $processing_result;
    }
    
    /**
     * Process bulk email
     */
    private function process_bulk_email($email_details, $authorized_user, $processing_result) {
        try {
            $this->logger->info('Processing bulk email from: ' . $email_details['from_email']);
            
            // Process multiple attachments
            foreach ($email_details['attachments'] as $attachment) {
                if (strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION)) === 'pdf') {
                    $pdf_result = $this->process_pdf_attachment($attachment, $authorized_user, $email_details);
                    
                    if ($pdf_result['success']) {
                        // TODO: iDoklad API integration removed - to be rebuilt
            // if (class_exists('IDokladProcessor_IDokladAPIV3')) {
            //     $idoklad_api = new IDokladProcessor_IDokladAPIV3($authorized_user);
            // } else {
            //     $idoklad_api = new IDokladProcessor_IDokladAPI($authorized_user);
            // }
                        $invoice_result = $idoklad_api->create_received_invoice($pdf_result['invoice_data'], $email_details);
                        
                        $processing_result['created_records'][] = array(
                            'type' => 'invoice',
                            'id' => $invoice_result['Id'],
                            'document_number' => $invoice_result['DocumentNumber']
                        );
                        
                        $processing_result['documents_processed']++;
                    } else {
                        $processing_result['errors'][] = 'Failed to process PDF: ' . $pdf_result['error'];
                    }
                }
            }
            
        } catch (Exception $e) {
            $processing_result['errors'][] = 'Bulk processing error: ' . $e->getMessage();
            $this->logger->error('Bulk processing error: ' . $e->getMessage());
        }
        
        return $processing_result;
    }
    
    /**
     * Process command email
     */
    private function process_command_email($email_details, $authorized_user, $processing_result) {
        try {
            $this->logger->info('Processing command email from: ' . $email_details['from_email']);
            
            // Extract command from email
            $command = $this->extract_command_from_email($email_details);
            
            switch ($command['type']) {
                case 'status':
                    $this->send_status_report($email_details, $authorized_user);
                    break;
                    
                case 'export':
                    $this->handle_export_command($email_details, $authorized_user, $command);
                    break;
                    
                case 'update':
                    $this->handle_update_command($email_details, $authorized_user, $command);
                    break;
                    
                default:
                    $processing_result['warnings'][] = 'Unknown command: ' . $command['type'];
            }
            
        } catch (Exception $e) {
            $processing_result['errors'][] = 'Command processing error: ' . $e->getMessage();
            $this->logger->error('Command processing error: ' . $e->getMessage());
        }
        
        return $processing_result;
    }
    
    /**
     * Process PDF attachment
     */
    private function process_pdf_attachment($attachment, $authorized_user, $email_details) {
        try {
            // Save attachment to temporary file
            $temp_file = $this->save_attachment_to_temp($attachment);

            // Process PDF using existing processor
            // Use PDF processor if available
            if (class_exists('IDokladProcessor_PDFProcessor')) {
                $pdf_processor = new IDokladProcessor_PDFProcessor();
            } else {
                throw new Exception('PDF processor class not available');
            }
            $processing_engine = get_option('idoklad_processing_engine', 'pdfco');
            $use_chatgpt_engine = ($processing_engine === 'chatgpt');

            if ($use_chatgpt_engine) {
                $chatgpt_api_key = get_option('idoklad_chatgpt_api_key');
                if (empty($chatgpt_api_key)) {
                    throw new Exception('ChatGPT API key is not configured');
                }

                $pdf_text = $pdf_processor->extract_text($temp_file);
                if (empty($pdf_text)) {
                    throw new Exception('Could not extract text from PDF for ChatGPT processing');
                }

                $chatgpt = new IDokladProcessor_ChatGPTIntegration();
                $chatgpt_data = $chatgpt->extract_invoice_data($pdf_text);
                $chatgpt_data['source'] = 'chatgpt';
                $chatgpt_data['pdf_text'] = $pdf_text;
                $chatgpt_data['extracted_at'] = current_time('mysql');

                require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-co-ai-parser-enhanced.php';
                $parser = new IDokladProcessor_PDFCoAIParserEnhanced();
                $transform = $parser->transform_structured_data($chatgpt_data, 'chatgpt_v3');

                if (!$transform['success']) {
                    $errors = $transform['validation']['errors'] ?? array('Unknown validation error');
                    throw new Exception('ChatGPT validation failed: ' . implode(', ', $errors));
                }

                unlink($temp_file);

                return array(
                    'success' => true,
                    'invoice_data' => $transform['data'],
                    'extracted_data' => $chatgpt_data
                );

            } else {
                $extracted_data = $pdf_processor->extract_text($temp_file);

                if (empty($extracted_data) && class_exists('IDokladProcessor_PDFCoAIParser')) {
                    $ai_parser = new IDokladProcessor_PDFCoAIParser();
                    $extracted_data = $ai_parser->parse_invoice($temp_file);
                }

                if (!empty($extracted_data)) {
                    require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-co-ai-parser-enhanced.php';
                    $parser = new IDokladProcessor_PDFCoAIParserEnhanced();

                    // For extracted data, create a basic iDoklad structure
                    $invoice_data = array(
                        'DocumentNumber' => $extracted_data['document_number'] ?? 'EMAIL-' . date('YmdHis'),
                        'DateOfIssue' => $extracted_data['date'] ?? date('Y-m-d'),
                        'Description' => $extracted_data['description'] ?? 'Email-processed invoice',
                        'Items' => array(
                            array(
                                'Name' => 'Email-processed item',
                                'Amount' => 1.0,
                                'UnitPrice' => $extracted_data['total'] ?? 0.0,
                                'PriceType' => 0,
                                'VatRateType' => 0
                            )
                        ),
                        'CurrencyId' => 1,
                        'DateOfReceiving' => date('Y-m-d')
                    );

                    unlink($temp_file);

                    return array(
                        'success' => true,
                        'invoice_data' => $invoice_data,
                        'extracted_data' => $extracted_data
                    );
                } else {
                    unlink($temp_file);
                    return array(
                        'success' => false,
                        'error' => 'Could not extract data from PDF'
                    );
                }
            }

        } catch (Exception $e) {
            if (isset($temp_file) && file_exists($temp_file)) {
                unlink($temp_file);
            }
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Save attachment to temporary file
     */
    private function save_attachment_to_temp($attachment) {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/idoklad-temp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $temp_file = $temp_dir . '/' . uniqid() . '_' . $attachment['filename'];
        
        if (file_put_contents($temp_file, $attachment['data']) === false) {
            throw new Exception('Failed to save attachment to temporary file');
        }
        
        return $temp_file;
    }
    
    /**
     * Get email attachments
     */
    private function get_email_attachments($email_id, $imap_connection) {
        $attachments = array();
        
        $structure = imap_fetchstructure($imap_connection, $email_id);
        
        if (isset($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $part_number => $part) {
                $this->extract_attachments_from_part($email_id, $part, $part_number + 1, $attachments, $imap_connection);
            }
        }
        
        return $attachments;
    }
    
    /**
     * Extract attachments from email part
     */
    private function extract_attachments_from_part($email_id, $part, $part_number, &$attachments, $imap_connection) {
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
            $attachment_data = imap_fetchbody($imap_connection, $email_id, $part_number);
            
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
                $this->extract_attachments_from_part($email_id, $nested_part, $part_number . '.' . ($nested_part_number + 1), $attachments, $imap_connection);
            }
        }
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
     * Extract contact data from email
     */
    private function extract_contact_data_from_email($email_details) {
        $contact_data = array();
        
        // Extract from email body
        $body = $email_details['body'];
        
        // Look for structured contact information
        if (preg_match('/Company:\s*(.+)/i', $body, $matches)) {
            $contact_data['CompanyName'] = trim($matches[1]);
        }
        
        if (preg_match('/Email:\s*(.+)/i', $body, $matches)) {
            $contact_data['Email'] = trim($matches[1]);
        }
        
        if (preg_match('/Phone:\s*(.+)/i', $body, $matches)) {
            $contact_data['Mobile'] = trim($matches[1]);
        }
        
        if (preg_match('/Address:\s*(.+)/i', $body, $matches)) {
            $contact_data['Street'] = trim($matches[1]);
        }
        
        return $contact_data;
    }
    
    /**
     * Convert invoice data to expense data
     */
    private function convert_to_expense_data($invoice_data) {
        $expense_data = array(
            'PartnerId' => $invoice_data['PartnerId'] ?? null,
            'DocumentNumber' => $invoice_data['DocumentNumber'] ?? 'EXP-' . date('YmdHis'),
            'DateOfIssue' => $invoice_data['DateOfIssue'] ?? date('Y-m-d'),
            'DateOfTaxing' => $invoice_data['DateOfTaxing'] ?? date('Y-m-d'),
            'Description' => 'Expense Option: ' . ($invoice_data['Description'] ?? ''),
            'CurrencyId' => $invoice_data['CurrencyId'] ?? 1,
            'Items' => array()
        );
        
        // Convert invoice items to expense items
        if (!empty($invoice_data['Items'])) {
            foreach ($invoice_data['Items'] as $item) {
                $expense_data['Items'][] = array(
                    'Name' => $item['Name'],
                    'Amount' => $item['Amount'],
                    'UnitPrice' => $item['UnitPrice'],
                    'VatRateType' => $item['VatRateType'] ?? 0,
                    'IsTaxMovement' => true
                );
            }
        }
        
        return $expense_data;
    }
    
    /**
     * Extract command from email
     */
    private function extract_command_from_email($email_details) {
        $subject = $email_details['subject'];
        $body = $email_details['body'];
        
        $command = array(
            'type' => 'unknown',
            'parameters' => array()
        );
        
        // Check for command in subject
        if (preg_match('/\[command:(\w+)\]/i', $subject, $matches)) {
            $command['type'] = strtolower($matches[1]);
        }
        
        // Check for command in body
        if (preg_match('/\[command:(\w+)\]/i', $body, $matches)) {
            $command['type'] = strtolower($matches[1]);
        }
        
        // Extract parameters
        if (preg_match('/\[params:(.+)\]/i', $body, $matches)) {
            $params = explode(',', $matches[1]);
            foreach ($params as $param) {
                $param_parts = explode('=', trim($param));
                if (count($param_parts) === 2) {
                    $command['parameters'][trim($param_parts[0])] = trim($param_parts[1]);
                }
            }
        }
        
        return $command;
    }
    
    /**
     * Send status report
     */
    private function send_status_report($email_details, $authorized_user) {
        try {
            // TODO: iDoklad API integration removed - to be rebuilt
            // if (class_exists('IDokladProcessor_IDokladAPIV3')) {
            //     $idoklad_api = new IDokladProcessor_IDokladAPIV3($authorized_user);
            // } else {
            //     $idoklad_api = new IDokladProcessor_IDokladAPI($authorized_user);
            // }
            $status = $idoklad_api->get_api_status();
            
            $report = "iDoklad System Status Report\n\n";
            $report .= "Connection Status: " . ($status['connected'] ? 'Connected' : 'Disconnected') . "\n";
            $report .= "Last Check: " . $status['last_check'] . "\n";
            
            if ($status['connected']) {
                $report .= "User: " . ($status['user_info']['UserName'] ?? 'Unknown') . "\n";
                $report .= "Company: " . ($status['company_info']['Name'] ?? 'Unknown') . "\n";
            }
            
            // Send email report
            $this->notification->send_email(
                $email_details['from_email'],
                'iDoklad System Status Report',
                $report
            );
            
        } catch (Exception $e) {
            $this->logger->error('Error sending status report: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle export command
     */
    private function handle_export_command($email_details, $authorized_user, $command) {
        // Implementation for export command
        $this->logger->info('Handling export command with parameters: ' . json_encode($command['parameters']));
    }
    
    /**
     * Handle update command
     */
    private function handle_update_command($email_details, $authorized_user, $command) {
        // Implementation for update command
        $this->logger->info('Handling update command with parameters: ' . json_encode($command['parameters']));
    }
    
    /**
     * Handle email processing completion
     */
    public function handle_email_processed($email_details, $processing_result, $authorized_user) {
        // Log processing completion
        $this->logger->info('Email processing completed: ' . $processing_result['documents_processed'] . ' documents processed');
        
        // Send summary notification if configured
        if (get_option('idoklad_send_processing_summary', false)) {
            $this->send_processing_summary($email_details, $processing_result, $authorized_user);
        }
        
        // Update user statistics
        $this->update_user_statistics($authorized_user->id, $processing_result);
    }
    
    /**
     * Send processing summary
     */
    private function send_processing_summary($email_details, $processing_result, $authorized_user) {
        $summary = "Email Processing Summary\n\n";
        $summary .= "From: " . $email_details['from_email'] . "\n";
        $summary .= "Subject: " . $email_details['subject'] . "\n";
        $summary .= "Processing Type: " . $processing_result['processing_type'] . "\n";
        $summary .= "Documents Processed: " . $processing_result['documents_processed'] . "\n";
        
        if (!empty($processing_result['created_records'])) {
            $summary .= "\nCreated Records:\n";
            foreach ($processing_result['created_records'] as $record) {
                $summary .= "- " . $record['type'] . ": " . ($record['document_number'] ?? $record['id']) . "\n";
            }
        }
        
        if (!empty($processing_result['errors'])) {
            $summary .= "\nErrors:\n";
            foreach ($processing_result['errors'] as $error) {
                $summary .= "- " . $error . "\n";
            }
        }
        
        if (!empty($processing_result['warnings'])) {
            $summary .= "\nWarnings:\n";
            foreach ($processing_result['warnings'] as $warning) {
                $summary .= "- " . $warning . "\n";
            }
        }
        
        $this->notification->send_email(
            $email_details['from_email'],
            'Email Processing Summary',
            $summary
        );
    }
    
    /**
     * Update user statistics
     */
    private function update_user_statistics($user_id, $processing_result) {
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_users';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET 
                total_emails_processed = total_emails_processed + 1,
                total_documents_processed = total_documents_processed + %d,
                last_email_processed = %s
            WHERE id = %d",
            $processing_result['documents_processed'],
            current_time('mysql'),
            $user_id
        ));
    }
    
    /**
     * Process pending emails from queue
     */
    private function process_pending_emails() {
        $pending_emails = $this->database->get_pending_queue(10);
        
        foreach ($pending_emails as $email) {
            try {
                $this->database->update_queue_status($email->id, 'processing', true);
                
                // Process the email
                $this->process_single_email($email->id, null, array('type' => 'queue', 'user_data' => $email));
                
                $this->database->update_queue_status($email->id, 'completed');
                
            } catch (Exception $e) {
                $this->database->update_queue_status($email->id, 'failed', true);
                $this->logger->error('Queue processing error: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Clean up old processed emails
     */
    private function cleanup_old_emails() {
        $cleanup_days = get_option('idoklad_email_cleanup_days', 30);
        $cleanup_date = date('Y-m-d H:i:s', strtotime("-{$cleanup_days} days"));
        
        global $wpdb;
        $table = $wpdb->prefix . 'idoklad_logs';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE processed_at < %s AND processing_status IN ('success', 'failed')",
            $cleanup_date
        ));
        
        $this->logger->info('Cleaned up old email logs older than ' . $cleanup_days . ' days');
    }
    
    /**
     * Handle email processing error
     */
    private function handle_email_processing_error($email_id, $imap_connection, $exception) {
        // Log error
        $this->database->add_log(array(
            'email_from' => 'unknown',
            'email_subject' => 'unknown',
            'processing_status' => 'failed',
            'error_message' => $exception->getMessage()
        ));
        
        // Send error notification
        $this->notification->send_error_notification(
            'Email processing failed for email ID ' . $email_id . ': ' . $exception->getMessage()
        );
    }
}

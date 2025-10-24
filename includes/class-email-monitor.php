<?php
/**
 * Email monitoring class - Updated to use per-user iDoklad credentials
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include required classes
// No additional requires needed here

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
        return $this->check_emails();
    }

    /**
     * Shared email check implementation used by cron and manual trigger
     */
    public function check_emails() {
        $result = array(
            'success' => true,
            'emails_found' => 0,
            'pdfs_processed' => 0,
            'queue_items_added' => 0,
            'message' => ''
        );

        if (get_option('idoklad_debug_mode')) {
            error_log('iDoklad Email Monitor: Starting email check');
        }

        try {
            $this->connect_to_email();
            $emails = $this->get_unread_emails();
            $result['emails_found'] = count($emails);

            if (get_option('idoklad_debug_mode')) {
                error_log('iDoklad Email Monitor: Found ' . $result['emails_found'] . ' unread emails');
            }

            foreach ($emails as $email_id) {
                $email_result = $this->process_email($email_id);
                $result['pdfs_processed'] += $email_result['pdfs_processed'];
                $result['queue_items_added'] += $email_result['queue_items_added'];
            }

            $this->disconnect_from_email();

            if ($result['emails_found'] === 0) {
                $result['message'] = __('No new emails found.', 'idoklad-invoice-processor');
            } elseif ($result['pdfs_processed'] === 0) {
                $result['message'] = __('No PDF attachments were found in the unread emails.', 'idoklad-invoice-processor');
            } else {
                $result['message'] = sprintf(
                    __('Processed %1$d PDF attachments from %2$d email(s).', 'idoklad-invoice-processor'),
                    $result['pdfs_processed'],
                    $result['emails_found']
                );
            }

        } catch (Exception $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();

            error_log('iDoklad Email Monitor Error: ' . $e->getMessage());
            $this->send_error_notification('Email monitoring failed: ' . $e->getMessage());
        }

        return $result;
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
        $email_result = array(
            'pdfs_processed' => 0,
            'queue_items_added' => 0
        );

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
                
                return $email_result;
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
                
                return $email_result;
            }

            // Process each PDF attachment
            foreach ($attachments as $attachment) {
                if ($this->is_pdf_attachment($attachment)) {
                    if ($this->process_pdf_attachment($email_id, $from_email, $subject, $attachment, $authorized_user)) {
                        $email_result['pdfs_processed']++;
                        $email_result['queue_items_added']++;
                    }
                }
            }

            // Mark email as read
            imap_setflag_full($this->connection, $email_id, '\\Seen');

            return $email_result;

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

        return $email_result;
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
        
        $queue_inserted = IDokladProcessor_Database::add_to_queue($queue_data);

        if (!$queue_inserted) {
            throw new Exception('Failed to add PDF attachment to the processing queue');
        }

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

        return true;
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
        // TODO: iDoklad API integration removed - to be rebuilt
        // $idoklad_api = new IDokladProcessor_IDokladAPI($authorized_user);
        $notification = new IDokladProcessor_Notification();
        
        // Step 4: Process PDF with AI Parser (preferred) or fallback to text extraction
        IDokladProcessor_Database::add_queue_step($email->id, 'Processing PDF with AI Parser', array(
            'filename' => basename($email->attachment_path)
        ));
        
        $extracted_data = array();
        $pdf_text = '';
        
        // Try AI Parser first if API key is configured
        $pdf_co_api_key = get_option('idoklad_pdfco_api_key');
        $use_ai_parser = get_option('idoklad_use_ai_parser');
        if (!empty($pdf_co_api_key) && $use_ai_parser) {
            try {
                require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-co-ai-parser.php';
                $ai_parser = new IDokladProcessor_PDFCoAIParser();
                
                // Upload PDF to get URL for AI Parser
                $pdf_url = $pdf_processor->upload_to_pdf_co($email->attachment_path, $email->id);
                
                if (!empty($pdf_url)) {
                    IDokladProcessor_Database::add_queue_step($email->id, 'PDF uploaded to PDF.co, starting AI parsing');
                    
                    $ai_parsed_data = $ai_parser->parse_invoice($pdf_url);
                    
                    // AI parser returns data in iDoklad format, so we can use it directly
                    $extracted_data = $ai_parsed_data;
                    
                    // Add email metadata for reference
                    $extracted_data['email_metadata'] = array(
                        'email_from' => $email->email_from,
                        'email_subject' => $email->email_subject,
                        'attachment_name' => basename($email->attachment_path),
                        'extracted_at' => current_time('mysql')
                    );
                    
                    IDokladProcessor_Database::add_queue_step($email->id, 'AI parsing completed successfully', array(
                        'fields_extracted' => count($ai_parsed_data),
                        'has_invoice_number' => !empty($ai_parsed_data['invoice_number']),
                        'has_total_amount' => !empty($ai_parsed_data['total_amount']),
                        'has_supplier_name' => !empty($ai_parsed_data['supplier_name']),
                        'items_count' => count($ai_parsed_data['items'] ?? array())
                    ));
                } else {
                    throw new Exception('Failed to upload PDF to PDF.co');
                }
                
            } catch (Exception $e) {
                IDokladProcessor_Database::add_queue_step($email->id, 'AI parsing failed, falling back to text extraction', array(
                    'error' => $e->getMessage()
                ));
                
                // Fall back to text extraction
                $pdf_text = $pdf_processor->extract_text($email->attachment_path, $email->id);
                if (empty($pdf_text)) {
                    IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: Could not extract text from PDF');
                    throw new Exception('Could not extract text from PDF');
                }
                
                IDokladProcessor_Database::add_queue_step($email->id, 'Text extracted successfully (fallback)', array(
                    'text_length' => strlen($pdf_text) . ' characters',
                    'preview' => substr($pdf_text, 0, 100) . '...'
                ));
            }
        } else {
            // No AI Parser API key, use text extraction
            $pdf_text = $pdf_processor->extract_text($email->attachment_path, $email->id);
            
            if (empty($pdf_text)) {
                IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: Could not extract text from PDF');
                throw new Exception('Could not extract text from PDF');
            }
            
            IDokladProcessor_Database::add_queue_step($email->id, 'Text extracted successfully', array(
                'text_length' => strlen($pdf_text) . ' characters',
                'preview' => substr($pdf_text, 0, 100) . '...'
            ));
        }
        
        // Step 5: Prepare invoice data (if not already done by AI Parser)
        if (empty($extracted_data)) {
            IDokladProcessor_Database::add_queue_step($email->id, 'Preparing invoice data from extracted text');
            
            // Create basic data structure from PDF text
            // The data transformer will handle the conversion to iDoklad format
            $extracted_data = array(
                'pdf_text' => $pdf_text,
                'email_from' => $email->email_from,
                'email_subject' => $email->email_subject,
                'attachment_name' => basename($email->attachment_path),
                'extracted_at' => current_time('mysql')
            );
            
            IDokladProcessor_Database::add_queue_step($email->id, 'Basic data prepared from text', array(
                'text_length' => strlen($pdf_text),
                'email_from' => $email->email_from
            ));
        } else {
            IDokladProcessor_Database::add_queue_step($email->id, 'Using AI-parsed invoice data');
        }
        
        // Step 6: Validate extracted data (basic validation)
        IDokladProcessor_Database::add_queue_step($email->id, 'Validating extracted data');
        
        $validation_result = $this->validate_extracted_data($extracted_data);
        if (!$validation_result['valid']) {
            IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: Validation failed', array(
                'errors' => $validation_result['errors']
            ));
            throw new Exception('Invalid invoice data: ' . implode(', ', $validation_result['errors']));
        }
        
        // Log validation warnings if any
        if (!empty($validation_result['warnings'])) {
            IDokladProcessor_Database::add_queue_step($email->id, 'Validation warnings (will use fallbacks)', array(
                'warnings' => $validation_result['warnings']
            ), false);
        }
        
        IDokladProcessor_Database::add_queue_step($email->id, 'Basic data validated successfully', array(
            'has_invoice_number' => !empty($extracted_data['invoice_number']) || !empty($extracted_data['document_number']),
            'has_supplier' => !empty($extracted_data['supplier_name']) || !empty($extracted_data['vendor_name']),
            'has_amount_or_items' => !empty($extracted_data['total_amount']) || !empty($extracted_data['items'])
        ));
        
        // Step 7: Prepare data for iDoklad API format
        IDokladProcessor_Database::add_queue_step($email->id, 'Preparing data for iDoklad API format');
        
        // Check if data is already in iDoklad format (from AI parser)
        if (isset($extracted_data['ai_parsed']) && $extracted_data['ai_parsed'] === true) {
            // AI parser already returned iDoklad-formatted data
            $idoklad_data = $extracted_data;
            
            IDokladProcessor_Database::add_queue_step($email->id, 'Using AI-parsed iDoklad data directly', array(
                'document_number' => $idoklad_data['DocumentNumber'] ?? 'N/A',
                'items_count' => count($idoklad_data['Items'] ?? array()),
                'currency_id' => $idoklad_data['CurrencyId'] ?? 'N/A',
                'has_description' => !empty($idoklad_data['Description']),
                'has_date_of_receiving' => !empty($idoklad_data['DateOfReceiving'])
            ));
        } else {
            // Use enhanced PDF.co AI parser for text-parsed data
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-co-ai-parser-enhanced.php';
            $parser = new IDokladProcessor_PDFCoAIParserEnhanced();
            
            // For text-parsed data, use the actual PDF file if available
            if (!empty($email->attachment_path) && file_exists($email->attachment_path)) {
                // Convert local file path to accessible URL
                $upload_dir = wp_upload_dir();
                $upload_path = $upload_dir['basedir'];
                $upload_url = $upload_dir['baseurl'];
                
                if (strpos($email->attachment_path, $upload_path) === 0) {
                    $relative_path = str_replace($upload_path, '', $email->attachment_path);
                    $temp_pdf_url = $upload_url . $relative_path;
                } else {
                    // Create temp URL
                    $file_name = basename($email->attachment_path);
                    $temp_url = $upload_url . '/idoklad-temp/' . $file_name;
                    $temp_dir = $upload_path . '/idoklad-temp/';
                    
                    if (!file_exists($temp_dir)) {
                        wp_mkdir_p($temp_dir);
                    }
                    
                    $temp_path = $temp_dir . $file_name;
                    if (!file_exists($temp_path)) {
                        copy($email->attachment_path, $temp_path);
                    }
                    
                    $temp_pdf_url = $temp_url;
                }
            } else {
                // No PDF file available, skip PDF.co parsing and use fallback
                throw new Exception('No PDF file available for parsing');
            }
            
            try {
                $parse_result = $parser->parse_invoice_with_debug($temp_pdf_url);
                if ($parse_result['success']) {
                    $idoklad_data = $parse_result['data'];
                } else {
                    throw new Exception('PDF.co parsing failed: ' . ($parse_result['message'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                // Fallback to basic data structure if PDF.co parsing fails
                $idoklad_data = array(
                    'DocumentNumber' => $extracted_data['document_number'] ?? 'TEXT-' . date('YmdHis'),
                    'DateOfIssue' => $extracted_data['date'] ?? date('Y-m-d'),
                    'Description' => $extracted_data['description'] ?? 'Text-parsed invoice',
                    'Items' => array(
                        array(
                            'Name' => 'Text-parsed item',
                            'Amount' => 1.0,
                            'UnitPrice' => $extracted_data['total'] ?? 0.0,
                            'PriceType' => 0,
                            'VatRateType' => 0
                        )
                    ),
                    'CurrencyId' => 1,
                    'DateOfReceiving' => date('Y-m-d')
                );
            }
            
            IDokladProcessor_Database::add_queue_step($email->id, 'Data transformed successfully (text parsing)', array(
                'document_number' => $idoklad_data['DocumentNumber'] ?? 'N/A',
                'items_count' => count($idoklad_data['Items'] ?? array()),
                'currency_id' => $idoklad_data['CurrencyId'] ?? 'N/A',
                'has_description' => !empty($idoklad_data['Description']),
                'has_date_of_receiving' => !empty($idoklad_data['DateOfReceiving'])
            ));
        }
        
        // Validate iDoklad payload
        if (isset($parser)) {
            // Use parser validation for text-parsed data
            $idoklad_validation = $parser->validate_idoklad_payload($idoklad_data);
            if (!$idoklad_validation['is_valid']) {
                IDokladProcessor_Database::add_queue_step($email->id, 'ERROR: iDoklad payload validation failed', array(
                    'errors' => $idoklad_validation['errors']
                ));
                throw new Exception('iDoklad payload validation failed: ' . implode(', ', $idoklad_validation['errors']));
            }
            
            // Log warnings if any
            if (!empty($idoklad_validation['warnings'])) {
                IDokladProcessor_Database::add_queue_step($email->id, 'Validation warnings (non-critical)', array(
                    'warnings' => $idoklad_validation['warnings']
                ), false);
            }
        } else {
            // Basic validation for AI-parsed data
            $this->validate_ai_parsed_data($idoklad_data, $email->id);
        }
        
        // Step 8: Create invoice in iDoklad using user's credentials
        IDokladProcessor_Database::add_queue_step($email->id, 'Creating invoice in iDoklad', array(
            'document_number' => $idoklad_data['DocumentNumber'] ?? 'N/A'
        ));
        
        // Use transformed iDoklad data (already in correct API format)
        $idoklad_response = $idoklad_api->create_invoice($idoklad_data);
        
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
     * Validate extracted invoice data (flexible validation - data transformer handles missing fields)
     */
    private function validate_extracted_data($data) {
        $errors = array();
        $warnings = array();
        
        // We need at least SOME data to work with
        if (empty($data) || !is_array($data)) {
            $errors[] = "No data extracted from invoice";
            return array('valid' => false, 'errors' => $errors, 'warnings' => $warnings);
        }
        
        // Check if we have at least ONE of these identifiers
        $has_identifier = false;
        $identifier_fields = array('invoice_number', 'document_number', 'number', 'invoice_no');
        foreach ($identifier_fields as $field) {
            if (!empty($data[$field])) {
                $has_identifier = true;
                break;
            }
        }
        if (!$has_identifier) {
            $warnings[] = "No invoice number found - will auto-generate";
        }
        
        // Check if we have at least ONE of these date fields
        $has_date = false;
        $date_fields = array('date', 'invoice_date', 'issue_date', 'document_date');
        foreach ($date_fields as $field) {
            if (!empty($data[$field])) {
                $has_date = true;
                break;
            }
        }
        if (!$has_date) {
            $warnings[] = "No date found - will use current date";
        }
        
        // Check if we have financial data (amount OR items OR pdf_text)
        $has_financial_data = false;
        if (!empty($data['total_amount']) || !empty($data['amount']) || !empty($data['total'])) {
            $has_financial_data = true;
        }
        if (!empty($data['items']) && is_array($data['items']) && count($data['items']) > 0) {
            $has_financial_data = true;
        }
        if (!empty($data['line_items']) && is_array($data['line_items']) && count($data['line_items']) > 0) {
            $has_financial_data = true;
        }
        
        // If we have PDF text, the transformer will parse it to extract financial data
        if (!empty($data['pdf_text']) && strlen($data['pdf_text']) > 100) {
            $has_financial_data = true; // Trust that transformer will parse amounts from text
            $warnings[] = "Financial data will be extracted from PDF text during transformation";
        }
        
        if (!$has_financial_data) {
            $errors[] = "No financial data found (need total_amount OR items OR PDF text to parse)";
        }
        
        // Validate amount format if present
        $amount_fields = array('total_amount', 'amount', 'total');
        foreach ($amount_fields as $field) {
            if (!empty($data[$field])) {
                // Try to convert to number
                $amount_str = str_replace(array(',', ' '), array('.', ''), $data[$field]);
                if (!is_numeric($amount_str)) {
                    $warnings[] = "Amount field '{$field}' has invalid format: {$data[$field]} - will try to parse";
                }
            }
        }
        
        // Validate items if present
        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $index => $item) {
                if (empty($item['price']) && empty($item['amount']) && empty($item['unit_price'])) {
                    $warnings[] = "Item #{$index} has no price";
                }
            }
        }
        
        // Check for supplier info (warning only)
        if (empty($data['supplier_name']) && empty($data['vendor_name']) && empty($data['from'])) {
            $warnings[] = "No supplier name found";
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
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
     * Validate AI-parsed iDoklad data
     */
    private function validate_ai_parsed_data($data, $email_id) {
        $errors = array();
        $warnings = array();
        
        // Check required iDoklad fields
        if (empty($data['DocumentNumber'])) {
            $errors[] = 'Missing DocumentNumber';
        }
        
        if (empty($data['Description'])) {
            $errors[] = 'Missing Description';
        }
        
        if (empty($data['DateOfReceiving'])) {
            $errors[] = 'Missing DateOfReceiving';
        }
        
        if (empty($data['Items']) || !is_array($data['Items']) || count($data['Items']) === 0) {
            $errors[] = 'Missing or empty Items array';
        }
        
        // Check currency
        if (empty($data['CurrencyId'])) {
            $warnings[] = 'No CurrencyId specified - will use default (CZK)';
        }
        
        // Check partner information
        if (empty($data['PartnerName']) && empty($data['PartnerId'])) {
            $warnings[] = 'No PartnerName or PartnerId - will create new partner';
        }
        
        if (!empty($errors)) {
            IDokladProcessor_Database::add_queue_step($email_id, 'ERROR: AI-parsed data validation failed', array(
                'errors' => $errors
            ));
            throw new Exception('AI-parsed data validation failed: ' . implode(', ', $errors));
        }
        
        if (!empty($warnings)) {
            IDokladProcessor_Database::add_queue_step($email_id, 'AI-parsed data validation warnings', array(
                'warnings' => $warnings
            ), false);
        }
        
        IDokladProcessor_Database::add_queue_step($email_id, 'AI-parsed data validated successfully');
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

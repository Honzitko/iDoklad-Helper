<?php
/**
 * Streamlined email monitor focusing on ChatGPT extraction and iDoklad REST integration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_EmailMonitor {

    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $connection;
    private $last_check_result = array();
    private $logger;

    public function __construct() {
        $this->host = get_option('idoklad_email_host');
        $this->port = get_option('idoklad_email_port', 993);
        $this->username = get_option('idoklad_email_username');
        $this->password = get_option('idoklad_email_password');
        $this->encryption = get_option('idoklad_email_encryption', 'ssl');

        if (class_exists('IDokladProcessor_Logger')) {
            $this->logger = IDokladProcessor_Logger::get_instance();
        }

        add_action('idoklad_check_emails', array($this, 'check_for_new_emails'));
    }

    public function check_for_new_emails() {
        $result = $this->check_emails();
        $this->last_check_result = $result;

        return $result;
    }

    public function check_emails() {
        $result = array(
            'success' => true,
            'emails_found' => 0,
            'queue_items_added' => 0,
            'message' => ''
        );

        try {
            $this->connect_to_email();
            $emails = $this->get_unread_emails();
            $result['emails_found'] = count($emails);

            foreach ($emails as $email_id) {
                $result['queue_items_added'] += $this->queue_email_for_processing($email_id);
            }

            $this->disconnect_from_email();

            if ($result['emails_found'] === 0) {
                $result['message'] = __('No new emails found.', 'idoklad-invoice-processor');
            } elseif ($result['queue_items_added'] === 0) {
                $result['message'] = __('New emails detected but no PDF attachments were queued.', 'idoklad-invoice-processor');
            } else {
                $result['message'] = sprintf(
                    __('Queued %1$d PDF attachment(s) from %2$d email(s).', 'idoklad-invoice-processor'),
                    $result['queue_items_added'],
                    $result['emails_found']
                );
            }
        } catch (Exception $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
            error_log('iDoklad Email Monitor: ' . $e->getMessage());
            $this->send_error_notification('Email monitoring failed: ' . $e->getMessage());
        }

        return $result;
    }

    public function process_pending_emails() {
        $processed_count = 0;
        $batch_size = (int) apply_filters('idoklad_processor_queue_batch_size', 5);
        if ($batch_size <= 0) {
            $batch_size = 5;
        }

        $chatgpt = new IDokladProcessor_ChatGPTIntegration();
        $pdfco = new IDokladProcessor_PDFCoProcessor();
        $client_id = get_option('idoklad_client_id');
        $client_secret = get_option('idoklad_client_secret');

        while (true) {
            $pending_items = IDokladProcessor_Database::get_pending_queue($batch_size);

            if (empty($pending_items)) {
                break;
            }

            foreach ($pending_items as $item) {
                $item_id = (int) $item->id;
                $log_id = null;
                $parsed_data = null;
                $payload = null;
                $invoice_response = null;

                try {
                    $log_id = $this->resolve_queue_log_id($item);

                    if ($this->logger) {
                        $this->logger->info('Processing queue item', array(
                            'queue_id' => $item_id,
                            'log_id' => $log_id,
                            'attempt' => ((int) $item->attempts) + 1,
                        ));
                    }

                    $marked = IDokladProcessor_Database::update_queue_status($item_id, 'processing', true);
                    if ($marked === false) {
                        throw new Exception('Unable to mark queue item as processing');
                    }

                    IDokladProcessor_Database::add_queue_step($item_id, 'Processing started', array(
                        'attempt' => ((int) $item->attempts) + 1,
                        'started_at' => current_time('mysql'),
                    ));

                    if ($log_id) {
                        $this->update_log_processing($log_id);
                    }

                    $attachment_path = isset($item->attachment_path) ? $item->attachment_path : '';
                    if (empty($attachment_path) || !file_exists($attachment_path)) {
                        throw new Exception('Attachment file is missing or inaccessible');
                    }

                    $file_size = filesize($attachment_path);
                    if ($file_size === false) {
                        throw new Exception('Unable to determine PDF size');
                    }

                    $attachment_name = $this->get_queue_attachment_name($item);

                    IDokladProcessor_Database::add_queue_step($item_id, 'PDF.co extraction started', array(
                        'attachment' => $attachment_name,
                        'filesize' => $file_size,
                    ));

                    $pdf_text = $pdfco->extract_text($attachment_path, array(
                        'queue_id' => $item_id,
                        'file_name' => $attachment_name,
                    ));

                    IDokladProcessor_Database::add_queue_step($item_id, 'PDF.co extraction complete', array(
                        'text_length' => strlen($pdf_text),
                    ));

                    $payload = $chatgpt->generate_idoklad_payload_from_text($pdf_text, array(
                        'email_from' => $item->email_from,
                        'email_subject' => $item->email_subject,
                        'queue_id' => $item_id,
                        'file_name' => $attachment_name,
                    ));

                    $parsed_data = $this->wrap_payload_for_logging($payload, $pdf_text);

                    IDokladProcessor_Database::add_queue_step($item_id, 'ChatGPT payload prepared', $this->summarize_chatgpt_output($parsed_data));

                    IDokladProcessor_Database::add_queue_step($item_id, 'iDoklad payload ready', $this->summarize_payload($payload));

                    if (empty($client_id) || empty($client_secret)) {
                        throw new Exception('iDoklad API credentials are not configured');
                    }

                    $integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
                    $invoice_response = $integration->create_invoice_complete_workflow($payload);

                    if (empty($invoice_response['success'])) {
                        $error_message = isset($invoice_response['message']) ? $invoice_response['message'] : __('Unknown response from iDoklad API', 'idoklad-invoice-processor');
                        throw new Exception($error_message);
                    }

                    IDokladProcessor_Database::update_queue_status($item_id, 'completed');

                    IDokladProcessor_Database::add_queue_step($item_id, 'Processing completed', array(
                        'invoice_id' => $invoice_response['invoice_id'] ?? null,
                        'document_number' => $invoice_response['document_number'] ?? null,
                        'completed_at' => current_time('mysql'),
                    ));

                    if ($log_id) {
                        $this->update_log_success($log_id, $parsed_data, $payload, $invoice_response);
                    }

                    if ($this->logger) {
                        $this->logger->info('Queue item processed successfully', array(
                            'queue_id' => $item_id,
                            'invoice_id' => $invoice_response['invoice_id'] ?? null,
                            'document_number' => $invoice_response['document_number'] ?? null,
                        ));
                    }

                    $processed_count++;
                } catch (Exception $e) {
                    $error_message = $e->getMessage();

                    IDokladProcessor_Database::update_queue_status($item_id, 'failed');
                    IDokladProcessor_Database::add_queue_step($item_id, 'Processing failed', array(
                        'error' => $error_message,
                        'failed_at' => current_time('mysql'),
                    ));

                    if ($log_id) {
                        $this->update_log_failure($log_id, $error_message, $parsed_data, $payload);
                    }

                    if ($this->logger) {
                        $this->logger->error('Queue item processing failed', array(
                            'queue_id' => $item_id,
                            'error' => $error_message,
                        ));
                    }
                }
            }
        }

        return $processed_count;
    }

    private function connect_to_email() {
        if (empty($this->host) || empty($this->username) || empty($this->password)) {
            throw new Exception('Email settings are not configured');
        }

        $connection_string = '{' . $this->host . ':' . $this->port . '/imap/' . $this->encryption . '}INBOX';
        $this->connection = imap_open($connection_string, $this->username, $this->password);

        if (!$this->connection) {
            throw new Exception('Failed to connect to email server: ' . imap_last_error());
        }
    }

    private function disconnect_from_email() {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    private function get_unread_emails() {
        $emails = imap_search($this->connection, 'UNSEEN');
        return $emails ? $emails : array();
    }

    private function process_email($email_id) {
        return $this->queue_email_for_processing($email_id);
    }

    private function queue_email_for_processing($email_id) {
        global $wpdb;
        $queued = 0;

        try {
            $header = imap_headerinfo($this->connection, $email_id);
            $from_email = $this->extract_email_address($header->from[0]->mailbox . '@' . $header->from[0]->host);
            $subject = isset($header->subject) ? $header->subject : '';

            $authorized_user = IDokladProcessor_Database::get_authorized_user($from_email);
            if (!$authorized_user) {
                IDokladProcessor_Database::add_log(array(
                    'email_from' => $from_email,
                    'email_subject' => $subject,
                    'processing_status' => 'failed',
                    'error_message' => 'Unauthorized sender'
                ));

                imap_setflag_full($this->connection, $email_id, '\\Seen');
                return 0;
            }

            $attachments = $this->get_email_attachments($email_id);

            if (empty($attachments)) {
                IDokladProcessor_Database::add_log(array(
                    'email_from' => $from_email,
                    'email_subject' => $subject,
                    'processing_status' => 'failed',
                    'error_message' => 'No PDF attachments found'
                ));

                imap_setflag_full($this->connection, $email_id, '\\Seen');
                return 0;
            }

            $pdf_index = 0;
            foreach ($attachments as $attachment) {
                if (!$this->is_pdf_attachment($attachment)) {
                    continue;
                }

                $file_path = $this->save_attachment($attachment, $email_id);
                $queue_email_id = $this->generate_queue_email_id($email_id, $attachment['filename'], $pdf_index);

                $queue_id = IDokladProcessor_Database::add_to_queue(array(
                    'email_id' => $queue_email_id,
                    'email_from' => $from_email,
                    'email_subject' => $subject,
                    'attachment_path' => $file_path
                ));

                if ($queue_id) {
                    $log_id = IDokladProcessor_Database::add_log(array(
                        'email_from' => $from_email,
                        'email_subject' => $subject,
                        'attachment_name' => $attachment['filename'],
                        'processing_status' => 'pending'
                    ));

                    $step_data = array(
                        'attachment' => $attachment['filename'],
                        'queued_at' => current_time('mysql')
                    );

                    if ($log_id) {
                        $step_data['log_id'] = (int) $log_id;
                    }

                    IDokladProcessor_Database::add_queue_step($queue_id, 'Queued from email', $step_data);

                    if ($this->logger) {
                        $this->logger->info('Queued email attachment for processing', array(
                            'queue_id' => $queue_id,
                            'email_from' => $from_email,
                            'attachment' => $attachment['filename'],
                            'log_id' => $log_id,
                        ));
                    }

                    $queued++;
                    $pdf_index++;
                } else {
                    error_log('iDoklad Email Monitor: Failed to queue attachment ' . $attachment['filename'] . ' for email ' . $email_id . ': ' . $wpdb->last_error);

                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }

            imap_setflag_full($this->connection, $email_id, '\\Seen');
        } catch (Exception $e) {
            error_log('iDoklad Email Monitor: Error processing email ' . $email_id . ': ' . $e->getMessage());
        }

        return $queued;
    }

    private function generate_queue_email_id($email_id, $filename, $index) {
        $hash = substr(md5($filename . '|' . $email_id . '|' . $index), 0, 8);
        $sequence = $index + 1;
        $queue_id = $email_id . '-' . $sequence . '-' . $hash;

        return substr($queue_id, 0, 100);
    }

    private function resolve_queue_log_id($item) {
        $details_raw = '';

        if (is_object($item) && isset($item->processing_details)) {
            $details_raw = $item->processing_details;
        } elseif (is_array($item) && isset($item['processing_details'])) {
            $details_raw = $item['processing_details'];
        }

        $details = $this->decode_processing_details($details_raw);

        if (!empty($details)) {
            foreach (array_reverse($details) as $detail) {
                if (isset($detail['data']['log_id']) && $detail['data']['log_id']) {
                    return (int) $detail['data']['log_id'];
                }
            }
        }

        $email_from = is_object($item) ? ($item->email_from ?? '') : ($item['email_from'] ?? '');
        $email_subject = is_object($item) ? ($item->email_subject ?? '') : ($item['email_subject'] ?? '');
        $attachment_name = $this->get_queue_attachment_name($item);

        $log_id = IDokladProcessor_Database::find_latest_log_id($email_from, $email_subject, $attachment_name);

        return $log_id ? (int) $log_id : null;
    }

    private function decode_processing_details($details_raw) {
        if (empty($details_raw)) {
            return array();
        }

        if (is_array($details_raw)) {
            return $details_raw;
        }

        $decoded = json_decode($details_raw, true);

        return is_array($decoded) ? $decoded : array();
    }

    private function get_queue_attachment_name($item) {
        $attachment_path = is_object($item) ? ($item->attachment_path ?? '') : ($item['attachment_path'] ?? '');

        if (!empty($attachment_path)) {
            return basename($attachment_path);
        }

        return '';
    }

    private function summarize_chatgpt_output($parsed_data) {
        $parsed_array = is_array($parsed_data) ? $parsed_data : array();

        if (isset($parsed_array['payload']) && is_array($parsed_array['payload'])) {
            $payload = $parsed_array['payload'];
            $summary = array(
                'payload_keys' => array_slice(array_keys($payload), 0, 10),
                'items_count' => (isset($payload['Items']) && is_array($payload['Items'])) ? count($payload['Items']) : 0,
            );

            if (!empty($payload['DocumentNumber'])) {
                $summary['document_number'] = $payload['DocumentNumber'];
            }

            if (!empty($parsed_array['warnings']) && is_array($parsed_array['warnings'])) {
                $summary['warnings'] = array_slice($parsed_array['warnings'], 0, 5);
            }

            return $summary;
        }

        $summary = array(
            'detected_keys' => array_slice(array_keys($parsed_array), 0, 10),
            'items_count' => (isset($parsed_array['items']) && is_array($parsed_array['items'])) ? count($parsed_array['items']) : 0,
        );

        if (!empty($parsed_array['invoice_number'])) {
            $summary['invoice_number'] = $parsed_array['invoice_number'];
        }

        if (!empty($parsed_array['warnings']) && is_array($parsed_array['warnings'])) {
            $summary['warnings'] = array_slice($parsed_array['warnings'], 0, 5);
        }

        return $summary;
    }

    private function summarize_payload($payload) {
        $payload_array = is_array($payload) ? $payload : array();

        $total_amount = null;
        if (isset($payload_array['TotalAmount'])) {
            $total_amount = $payload_array['TotalAmount'];
        } elseif (isset($payload_array['TotalWithVat'])) {
            $total_amount = $payload_array['TotalWithVat'];
        }

        $summary = array(
            'document_number' => $payload_array['DocumentNumber'] ?? ($payload_array['document_number'] ?? ''),
            'total_amount' => $total_amount,
            'items' => (isset($payload_array['Items']) && is_array($payload_array['Items'])) ? count($payload_array['Items']) : 0,
            'currency' => $payload_array['Currency'] ?? ($payload_array['CurrencyCode'] ?? ''),
        );

        return array_filter($summary, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function wrap_payload_for_logging($payload, $pdf_text) {
        $payload_array = is_array($payload) ? $payload : array();

        $wrapped = array(
            'payload' => $payload_array,
            'source' => 'chatgpt_payload',
        );

        if (isset($payload_array['Items']) && is_array($payload_array['Items'])) {
            $wrapped['items'] = $payload_array['Items'];
        }

        if (!empty($payload_array['DocumentNumber'])) {
            $wrapped['invoice_number'] = $payload_array['DocumentNumber'];
        }

        if (isset($payload_array['warnings'])) {
            $wrapped['warnings'] = is_array($payload_array['warnings']) ? $payload_array['warnings'] : array($payload_array['warnings']);
        }

        if (!empty($pdf_text)) {
            $wrapped['pdf_text_preview'] = $this->create_text_preview($pdf_text);
            $wrapped['text_length'] = strlen($pdf_text);
        }

        return $wrapped;
    }

    private function create_text_preview($text) {
        if (empty($text)) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 500);
        }

        return substr($text, 0, 500);
    }

    private function update_log_processing($log_id) {
        IDokladProcessor_Database::update_log($log_id, array(
            'processing_status' => 'processing',
            'error_message' => '',
        ));
    }

    private function update_log_success($log_id, $parsed_data, $payload, $invoice_response) {
        $parsed_array = is_array($parsed_data) ? $parsed_data : array();
        $payload_array = is_array($payload) ? $payload : array();
        $response_array = is_array($invoice_response) ? $invoice_response : array();

        $log_update = array(
            'processing_status' => 'success',
            'extracted_data' => array(
                'parsed' => $parsed_array,
                'idoklad_payload' => $payload_array,
                'summary' => array(
                    'warnings' => isset($parsed_array['warnings']) && is_array($parsed_array['warnings']) ? $parsed_array['warnings'] : array(),
                    'checklist' => isset($parsed_array['checklist']) && is_array($parsed_array['checklist']) ? $parsed_array['checklist'] : array(),
                ),
            ),
            'idoklad_response' => $response_array,
            'error_message' => '',
            'processed_at' => current_time('mysql'),
        );

        IDokladProcessor_Database::update_log($log_id, $log_update);
    }

    private function update_log_failure($log_id, $error_message, $parsed_data = null, $payload = null) {
        $parsed_array = is_array($parsed_data) ? $parsed_data : array();
        $payload_array = is_array($payload) ? $payload : array();

        $log_update = array(
            'processing_status' => 'failed',
            'error_message' => $error_message,
            'processed_at' => current_time('mysql'),
        );

        if (!empty($parsed_array) || !empty($payload_array)) {
            $log_update['extracted_data'] = array(
                'parsed' => $parsed_array,
                'idoklad_payload' => $payload_array,
            );
        }

        IDokladProcessor_Database::update_log($log_id, $log_update);
    }

    private function get_email_attachments($email_id) {
        $attachments = array();
        $structure = imap_fetchstructure($this->connection, $email_id);

        if (!isset($structure->parts) || !is_array($structure->parts)) {
            return $attachments;
        }

        foreach ($structure->parts as $part_number => $part) {
            $this->extract_attachments_from_part($email_id, $part, $part_number + 1, $attachments);
        }

        return $attachments;
    }

    private function extract_attachments_from_part($email_id, $part, $part_number, array &$attachments) {
        $filename = '';

        if (isset($part->dparameters)) {
            foreach ($part->dparameters as $param) {
                if (strtolower($param->attribute) === 'filename') {
                    $filename = $param->value;
                }
            }
        }

        if (isset($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) === 'name') {
                    $filename = $param->value;
                }
            }
        }

        $disposition = isset($part->disposition) ? strtolower($part->disposition) : '';

        if ($disposition === 'attachment' || !empty($filename)) {
            $attachment_data = imap_fetchbody($this->connection, $email_id, $part_number);

            if ($part->encoding == 3) {
                $attachment_data = base64_decode($attachment_data);
            } elseif ($part->encoding == 4) {
                $attachment_data = quoted_printable_decode($attachment_data);
            }

            $attachments[] = array(
                'filename' => $filename,
                'data' => $attachment_data
            );
        }

        if (isset($part->parts) && is_array($part->parts)) {
            foreach ($part->parts as $nested_part_number => $nested_part) {
                $this->extract_attachments_from_part($email_id, $nested_part, $part_number . '.' . ($nested_part_number + 1), $attachments);
            }
        }
    }

    private function is_pdf_attachment($attachment) {
        $filename = strtolower($attachment['filename']);
        return substr($filename, -4) === '.pdf';
    }

    private function save_attachment($attachment, $email_id) {
        $upload_dir = wp_upload_dir();
        $idoklad_dir = $upload_dir['basedir'] . '/idoklad-invoices';

        if (!file_exists($idoklad_dir)) {
            wp_mkdir_p($idoklad_dir);
        }

        $filename = sanitize_file_name($attachment['filename']);
        $unique_filename = date('Y-m-d_H-i-s') . '_' . $email_id . '_' . $filename;
        $file_path = $idoklad_dir . '/' . $unique_filename;

        if (file_put_contents($file_path, $attachment['data']) === false) {
            throw new Exception('Failed to save PDF attachment');
        }

        return $file_path;
    }

    private function extract_email_address($email_string) {
        if (preg_match('/<(.+)>/', $email_string, $matches)) {
            return $matches[1];
        }
        return $email_string;
    }

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

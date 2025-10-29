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

    public function __construct() {
        $this->host = get_option('idoklad_email_host');
        $this->port = get_option('idoklad_email_port', 993);
        $this->username = get_option('idoklad_email_username');
        $this->password = get_option('idoklad_email_password');
        $this->encryption = get_option('idoklad_email_encryption', 'ssl');

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
        return 0;
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
                    IDokladProcessor_Database::add_log(array(
                        'email_from' => $from_email,
                        'email_subject' => $subject,
                        'attachment_name' => $attachment['filename'],
                        'processing_status' => 'pending'
                    ));

                    IDokladProcessor_Database::add_queue_step($queue_id, 'Queued from email', array(
                        'attachment' => $attachment['filename'],
                        'queued_at' => current_time('mysql')
                    ));

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

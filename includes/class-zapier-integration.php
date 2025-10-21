<?php
/**
 * Zapier integration class for invoice processing
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_ZapierIntegration {
    
    private $webhook_url;
    private $debug_mode;
    
    public function __construct() {
        $this->webhook_url = get_option('idoklad_zapier_webhook_url', '');
        $this->debug_mode = get_option('idoklad_debug_mode', false);
    }
    
    /**
     * Process invoice data through Zapier webhook
     */
    public function process_invoice($pdf_text, $email_data = array()) {
        if (empty($this->webhook_url)) {
            throw new Exception('Zapier webhook URL is not configured');
        }
        
        if ($this->debug_mode) {
            error_log('iDoklad Zapier: Processing invoice through Zapier webhook');
        }
        
        // Prepare data for Zapier
        $zapier_data = array(
            'pdf_text' => $pdf_text,
            'email_from' => isset($email_data['email_from']) ? $email_data['email_from'] : '',
            'email_subject' => isset($email_data['email_subject']) ? $email_data['email_subject'] : '',
            'attachment_name' => isset($email_data['attachment_name']) ? $email_data['attachment_name'] : '',
            'timestamp' => current_time('mysql'),
            'source' => 'idoklad-wordpress-plugin'
        );
        
        // Send to Zapier webhook
        $response = $this->send_webhook($zapier_data);
        
        if ($this->debug_mode) {
            error_log('iDoklad Zapier: Webhook response: ' . json_encode($response));
        }
        
        return $response;
    }
    
    /**
     * Send data to Zapier webhook
     */
    private function send_webhook($data) {
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress-iDoklad-Processor/1.1.0'
            ),
            'body' => json_encode($data),
            'timeout' => 30,
            'method' => 'POST',
            'sslverify' => true
        );
        
        if ($this->debug_mode) {
            error_log('iDoklad Zapier: Sending webhook to: ' . $this->webhook_url);
            error_log('iDoklad Zapier: Webhook data: ' . json_encode($data));
        }
        
        $response = wp_remote_request($this->webhook_url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if ($this->debug_mode) {
                error_log('iDoklad Zapier: Webhook error: ' . $error_message);
            }
            throw new Exception('Zapier webhook request failed: ' . $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($this->debug_mode) {
            error_log('iDoklad Zapier: Response code: ' . $response_code);
            error_log('iDoklad Zapier: Response body: ' . $response_body);
        }
        
        if ($response_code >= 400) {
            throw new Exception('Zapier webhook error (' . $response_code . '): ' . $response_body);
        }
        
        // Try to parse response as JSON
        $response_data = json_decode($response_body, true);
        
        return array(
            'success' => true,
            'status_code' => $response_code,
            'response' => $response_data ? $response_data : $response_body,
            'raw_response' => $response_body
        );
    }
    
    /**
     * Test Zapier webhook connection
     */
    public function test_webhook() {
        if (empty($this->webhook_url)) {
            return array(
                'success' => false,
                'message' => 'Zapier webhook URL is not configured'
            );
        }
        
        try {
            $test_data = array(
                'test' => true,
                'message' => 'Test webhook from iDoklad WordPress Plugin',
                'timestamp' => current_time('mysql'),
                'source' => 'idoklad-wordpress-plugin-test'
            );
            
            $response = $this->send_webhook($test_data);
            
            return array(
                'success' => true,
                'message' => 'Zapier webhook test successful. Response: ' . substr($response['raw_response'], 0, 200)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Process invoice with structured data (if Zapier returns parsed data)
     */
    public function process_structured_invoice($invoice_data, $email_data = array()) {
        if (empty($this->webhook_url)) {
            throw new Exception('Zapier webhook URL is not configured');
        }
        
        if ($this->debug_mode) {
            error_log('iDoklad Zapier: Processing structured invoice data');
        }
        
        // Prepare structured data for Zapier
        $zapier_data = array(
            'invoice_data' => $invoice_data,
            'email_from' => isset($email_data['email_from']) ? $email_data['email_from'] : '',
            'email_subject' => isset($email_data['email_subject']) ? $email_data['email_subject'] : '',
            'attachment_name' => isset($email_data['attachment_name']) ? $email_data['attachment_name'] : '',
            'timestamp' => current_time('mysql'),
            'source' => 'idoklad-wordpress-plugin-structured'
        );
        
        // Send to Zapier webhook
        $response = $this->send_webhook($zapier_data);
        
        return $response;
    }
    
    /**
     * Get webhook URL
     */
    public function get_webhook_url() {
        return $this->webhook_url;
    }
    
    /**
     * Set webhook URL
     */
    public function set_webhook_url($url) {
        $this->webhook_url = $url;
        update_option('idoklad_zapier_webhook_url', $url);
    }
    
    /**
     * Validate webhook URL format
     */
    public function validate_webhook_url($url) {
        if (empty($url)) {
            return false;
        }
        
        // Check if it's a valid URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check if it's a Zapier webhook URL
        if (strpos($url, 'zapier.com') === false && strpos($url, 'hooks.zapier.com') === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get Zapier setup instructions
     */
    public function get_setup_instructions() {
        return array(
            'title' => 'Zapier Integration Setup',
            'steps' => array(
                '1. Go to Zapier.com and create a new Zap',
                '2. Choose "Webhooks by Zapier" as the trigger',
                '3. Select "Catch Hook" as the trigger event',
                '4. Copy the webhook URL provided by Zapier',
                '5. Paste the webhook URL in the plugin settings below',
                '6. Test the connection using the "Test Webhook" button',
                '7. Configure your Zap actions (e.g., create iDoklad invoice)',
                '8. Activate your Zap'
            ),
            'webhook_url_help' => 'The webhook URL should look like: https://hooks.zapier.com/hooks/catch/123456/abcdef/',
            'data_format' => array(
                'pdf_text' => 'The extracted text from the PDF invoice',
                'email_from' => 'The sender email address',
                'email_subject' => 'The email subject line',
                'attachment_name' => 'The name of the PDF attachment',
                'timestamp' => 'When the email was processed',
                'source' => 'Always "idoklad-wordpress-plugin"'
            )
        );
    }
}

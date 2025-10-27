<?php
/**
 * Admin interface for PDF.co AI Parser debugging and testing
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_PDFCoAdminDebug {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_idoklad_test_pdf_co_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_idoklad_test_pdf_co_parser', array($this, 'ajax_test_parser'));
        add_action('wp_ajax_idoklad_test_pdf_co_step_by_step', array($this, 'ajax_test_step_by_step'));
        add_action('wp_ajax_idoklad_validate_idoklad_payload', array($this, 'ajax_validate_payload'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu for PDF.co debugging
     */
    public function add_admin_menu() {
        add_submenu_page(
            'idoklad-processor',
            __('PDF.co AI Parser Debug', 'idoklad-invoice-processor'),
            __('PDF.co AI Parser Debug', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor-pdf-co-debug',
            array($this, 'render_debug_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'idoklad-processor-pdf-co-debug') !== false) {
            wp_enqueue_script(
                'idoklad-pdf-co-debug-js',
                IDOKLAD_PROCESSOR_PLUGIN_URL . 'assets/pdf-co-debug.js',
                array('jquery'),
                IDOKLAD_PROCESSOR_VERSION,
                true
            );
            
            wp_localize_script('idoklad-pdf-co-debug-js', 'idoklad_pdf_co_debug_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('idoklad_pdf_co_debug_nonce')
            ));
        }
    }
    
    /**
     * Render the debug page
     */
    public function render_debug_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('PDF.co AI Parser Debug & Testing', 'idoklad-invoice-processor'); ?></h1>
            
            <div class="idoklad-pdf-co-debug-container">
                
                <!-- Configuration Section -->
                <div class="idoklad-card">
                    <h2><?php esc_html_e('Configuration', 'idoklad-invoice-processor'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('API Key', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <?php 
                                $api_key = get_option('idoklad_pdfco_api_key');
                                if ($api_key) {
                                    echo '<span style="color: green;">✓ Configured (' . substr($api_key, 0, 8) . '...)</span>';
                                } else {
                                    echo '<span style="color: red;">✗ Not configured</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Debug Mode', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <?php 
                                $debug_mode = get_option('idoklad_debug_mode');
                                echo $debug_mode ? '<span style="color: green;">✓ Enabled</span>' : '<span style="color: orange;">⚠ Disabled</span>';
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Connection Testing Section -->
                <div class="idoklad-card">
                    <h2><?php esc_html_e('Connection Testing', 'idoklad-invoice-processor'); ?></h2>
                    <p><?php esc_html_e('Test the connection to PDF.co API endpoints:', 'idoklad-invoice-processor'); ?></p>
                    
                    <div class="debug-actions">
                        <button type="button" id="test-connection-btn" class="button button-primary">
                            <?php esc_html_e('Test API Connection', 'idoklad-invoice-processor'); ?>
                        </button>
                    </div>
                    
                    <div id="connection-results" style="display: none;">
                        <h3><?php esc_html_e('Connection Test Results', 'idoklad-invoice-processor'); ?></h3>
                        <div id="connection-output"></div>
                    </div>
                </div>
                
                <!-- Parser Testing Section -->
                <div class="idoklad-card">
                    <h2><?php esc_html_e('Parser Testing', 'idoklad-invoice-processor'); ?></h2>
                    <p><?php esc_html_e('Test the PDF.co AI Parser with a sample PDF:', 'idoklad-invoice-processor'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="test-pdf-url"><?php esc_html_e('Test PDF URL', 'idoklad-invoice-processor'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="test-pdf-url" class="regular-text" 
                                       placeholder="https://example.com/test-invoice.pdf"
                                       value="https://example.com/test-invoice.pdf">
                                <p class="description"><?php esc_html_e('URL to a test PDF invoice', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="debug-actions">
                        <button type="button" id="test-parser-btn" class="button button-primary">
                            <?php esc_html_e('Test AI Parser', 'idoklad-invoice-processor'); ?>
                        </button>
                        <button type="button" id="test-step-by-step-btn" class="button button-secondary">
                            <?php esc_html_e('Test Step by Step', 'idoklad-invoice-processor'); ?>
                        </button>
                    </div>
                    
                    <div id="parser-results" style="display: none;">
                        <h3><?php esc_html_e('Parser Test Results', 'idoklad-invoice-processor'); ?></h3>
                        <div id="parser-output"></div>
                    </div>
                </div>
                
                <!-- Payload Validation Section -->
                <div class="idoklad-card">
                    <h2><?php esc_html_e('iDoklad Payload Validation', 'idoklad-invoice-processor'); ?></h2>
                    <p><?php esc_html_e('Validate extracted data against iDoklad requirements:', 'idoklad-invoice-processor'); ?></p>
                    
                    <div class="debug-actions">
                        <button type="button" id="validate-payload-btn" class="button button-primary">
                            <?php esc_html_e('Validate Sample Payload', 'idoklad-invoice-processor'); ?>
                        </button>
                    </div>
                    
                    <div id="validation-results" style="display: none;">
                        <h3><?php esc_html_e('Validation Results', 'idoklad-invoice-processor'); ?></h3>
                        <div id="validation-output"></div>
                    </div>
                </div>
                
                <!-- Custom Fields Section -->
                <div class="idoklad-card">
                    <h2><?php esc_html_e('Custom Fields for iDoklad', 'idoklad-invoice-processor'); ?></h2>
                    <p><?php esc_html_e('These fields are extracted by the AI Parser for iDoklad integration:', 'idoklad-invoice-processor'); ?></p>
                    
                    <div class="custom-fields-list">
                        <h4><?php esc_html_e('Document Fields', 'idoklad-invoice-processor'); ?></h4>
                        <ul>
                            <li>DocumentNumber</li>
                            <li>DateOfIssue</li>
                            <li>DateOfTaxing</li>
                            <li>DateOfMaturity</li>
                        </ul>
                        
                        <h4><?php esc_html_e('Partner Fields', 'idoklad-invoice-processor'); ?></h4>
                        <ul>
                            <li>PartnerName</li>
                            <li>PartnerAddress</li>
                            <li>PartnerIdentificationNumber</li>
                        </ul>
                        
                        <h4><?php esc_html_e('Financial Fields', 'idoklad-invoice-processor'); ?></h4>
                        <ul>
                            <li>Currency</li>
                            <li>ExchangeRate</li>
                            <li>TotalAmount</li>
                        </ul>
                        
                        <h4><?php esc_html_e('Payment Fields', 'idoklad-invoice-processor'); ?></h4>
                        <ul>
                            <li>VariableSymbol</li>
                            <li>ConstantSymbol</li>
                            <li>SpecificSymbol</li>
                        </ul>
                        
                        <h4><?php esc_html_e('Bank Fields', 'idoklad-invoice-processor'); ?></h4>
                        <ul>
                            <li>BankAccountNumber</li>
                            <li>Iban</li>
                            <li>Swift</li>
                        </ul>
                        
                        <h4><?php esc_html_e('Other Fields', 'idoklad-invoice-processor'); ?></h4>
                        <ul>
                            <li>Items</li>
                            <li>Description</li>
                            <li>Note</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Debug Information Section -->
                <div class="idoklad-card">
                    <h2><?php esc_html_e('Debug Information', 'idoklad-invoice-processor'); ?></h2>
                    <p><?php esc_html_e('Enable debug mode to see detailed logging information:', 'idoklad-invoice-processor'); ?></p>
                    
                    <ol>
                        <li><?php esc_html_e('Go to Settings → iDoklad Processor → General Settings', 'idoklad-invoice-processor'); ?></li>
                        <li><?php esc_html_e('Enable "Debug Mode"', 'idoklad-invoice-processor'); ?></li>
                        <li><?php esc_html_e('Check WordPress error logs for detailed debugging information', 'idoklad-invoice-processor'); ?></li>
                    </ol>
                    
                    <p><strong><?php esc_html_e('Log Location:', 'idoklad-invoice-processor'); ?></strong></p>
                    <code><?php echo wp_upload_dir()['basedir'] . '/debug.log'; ?></code>
                </div>
                
            </div>
        </div>
        
        <style>
        .idoklad-pdf-co-debug-container {
            max-width: 1200px;
        }
        
        .idoklad-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .debug-actions {
            margin: 20px 0;
        }
        
        .debug-actions button {
            margin-right: 10px;
        }
        
        #connection-output, #parser-output, #validation-output {
            background: #f1f1f1;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .custom-fields-list ul {
            list-style-type: disc;
            margin-left: 20px;
        }
        
        .custom-fields-list h4 {
            margin-top: 20px;
            margin-bottom: 10px;
            color: #0073aa;
        }
        
        .custom-fields-list li {
            margin-bottom: 5px;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for testing connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('idoklad_pdf_co_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-co-ai-parser.php';
            
            $parser = new IDokladProcessor_PDFCoAIParser();
            $result = $parser->test_connection();
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Connection test failed: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX handler for testing parser
     */
    public function ajax_test_parser() {
        check_ajax_referer('idoklad_pdf_co_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $pdf_url = sanitize_url($_POST['pdf_url']);
            
            if (empty($pdf_url)) {
                throw new Exception('PDF URL is required');
            }
            
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-co-ai-parser.php';
            
            $parser = new IDokladProcessor_PDFCoAIParser();
            $result = $parser->parse_invoice($pdf_url);
            
            wp_send_json_success(array(
                'message' => 'Parser test completed successfully',
                'result' => $result
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Parser test failed: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX handler for step-by-step testing
     */
    public function ajax_test_step_by_step() {
        check_ajax_referer('idoklad_pdf_co_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $pdf_url = sanitize_url($_POST['pdf_url']);
            
            if (empty($pdf_url)) {
                throw new Exception('PDF URL is required');
            }
            
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-pdf-co-ai-parser-enhanced.php';
            
            $parser = new IDokladProcessor_PDFCoAIParserEnhanced();
            $result = $parser->parse_invoice_with_debug($pdf_url);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Step-by-step test failed: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX handler for payload validation
     */
    public function ajax_validate_payload() {
        check_ajax_referer('idoklad_pdf_co_debug_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            // Sample iDoklad payload for validation
            $sample_payload = array(
                'DocumentNumber' => 'INV-2025-001',
                'DateOfIssue' => '2025-01-15',
                'PartnerName' => 'Test Company s.r.o.',
                'Items' => array(
                    array(
                        'Name' => 'Consulting Service',
                        'Amount' => 2.0,
                        'UnitPrice' => 750.0
                    )
                )
            );
            
            // Validate the payload
            $validation = $this->validate_idoklad_payload($sample_payload);
            
            wp_send_json_success(array(
                'message' => 'Payload validation completed',
                'payload' => $sample_payload,
                'validation' => $validation
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Payload validation failed: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Validate iDoklad payload
     */
    private function validate_idoklad_payload($data) {
        $validation = array(
            'is_valid' => true,
            'errors' => array(),
            'warnings' => array(),
            'required_fields_present' => array(),
            'required_fields_missing' => array(),
            'auto_fill_fields' => array()
        );

        $document_number = isset($data['DocumentNumber']) ? trim((string) $data['DocumentNumber']) : '';
        if ($document_number !== '') {
            $validation['required_fields_present'][] = 'DocumentNumber';
        } else {
            $validation['required_fields_missing'][] = 'DocumentNumber';
            $validation['warnings'][] = 'DocumentNumber missing - a sequential number will be generated based on successful exports.';
            $validation['auto_fill_fields']['DocumentNumber'] = 'sequential_counter';
        }

        $date_of_issue = isset($data['DateOfIssue']) ? trim((string) $data['DateOfIssue']) : '';
        if ($date_of_issue !== '') {
            $validation['required_fields_present'][] = 'DateOfIssue';
        } else {
            $validation['required_fields_missing'][] = 'DateOfIssue';

            if (!empty($data['DateOfReceiving'])) {
                $validation['warnings'][] = 'DateOfIssue missing - DateOfReceiving will be reused when building the payload.';
                $validation['auto_fill_fields']['DateOfIssue'] = 'use_date_of_receiving';
            } else {
                $validation['warnings'][] = 'DateOfIssue missing - email received timestamp will be used during payload build.';
                $validation['auto_fill_fields']['DateOfIssue'] = 'email_received_timestamp';
            }
        }

        $partner_id = isset($data['PartnerId']) ? $data['PartnerId'] : null;
        $partner_name = isset($data['PartnerName']) ? trim((string) $data['PartnerName']) : '';

        if (!empty($partner_id)) {
            if (!in_array('PartnerId', $validation['required_fields_present'], true)) {
                $validation['required_fields_present'][] = 'PartnerId';
            }
        } else {
            if (!in_array('PartnerId', $validation['required_fields_missing'], true)) {
                $validation['required_fields_missing'][] = 'PartnerId';
            }

            if ($partner_name !== '') {
                if (!in_array('PartnerName', $validation['required_fields_present'], true)) {
                    $validation['required_fields_present'][] = 'PartnerName';
                }
                $validation['warnings'][] = 'PartnerId missing - the integration will attempt to resolve it via the iDoklad REST API.';
                $validation['auto_fill_fields']['PartnerId'] = 'rest_lookup';
            } else {
                if (!in_array('PartnerName', $validation['required_fields_missing'], true)) {
                    $validation['required_fields_missing'][] = 'PartnerName';
                }
                $validation['errors'][] = 'Missing partner identification (PartnerId or PartnerName).';
                $validation['is_valid'] = false;
            }
        }

        $items_present = isset($data['Items']) && is_array($data['Items']) && !empty($data['Items']);

        if ($items_present) {
            $validation['required_fields_present'][] = 'Items';

            foreach ($data['Items'] as $index => $item) {
                if (!is_array($item)) {
                    $validation['warnings'][] = "Item $index is not a valid array - default items will be used.";
                    $validation['auto_fill_fields']['Items'] = 'default_items';
                    $items_present = false;
                    break;
                }

                if (!isset($item['Name']) || trim((string) $item['Name']) === '') {
                    $validation['warnings'][] = "Item $index missing name - default items will be used.";
                    $validation['auto_fill_fields']['Items'] = 'default_items';
                    $items_present = false;
                    break;
                }

                if (!isset($item['Amount']) || $item['Amount'] <= 0) {
                    $validation['warnings'][] = "Item $index has invalid amount.";
                }
                if (!isset($item['UnitPrice']) || $item['UnitPrice'] <= 0) {
                    $validation['warnings'][] = "Item $index has invalid unit price.";
                }
            }
        }

        if (!$items_present) {
            if (!in_array('Items', $validation['required_fields_missing'], true)) {
                $validation['required_fields_missing'][] = 'Items';
            }
            $validation['warnings'][] = 'Items missing - default service item will be injected when preparing the invoice payload.';
            $validation['auto_fill_fields']['Items'] = 'default_items';
        }

        return $validation;
    }
}

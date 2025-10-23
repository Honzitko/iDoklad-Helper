<?php
/**
 * Admin interface for iDoklad API v3 Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_AdminIntegration {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_idoklad_test_integration', array($this, 'ajax_test_integration'));
        add_action('wp_ajax_idoklad_create_test_invoice', array($this, 'ajax_create_test_invoice'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu for iDoklad integration
     */
    public function add_admin_menu() {
        add_submenu_page(
            'idoklad-processor',
            __('iDoklad API Integration', 'idoklad-invoice-processor'),
            __('iDoklad API Integration', 'idoklad-invoice-processor'),
            'manage_options',
            'idoklad-processor-integration',
            array($this, 'render_integration_page')
        );
    }
    
    /**
     * Register settings for iDoklad integration
     */
    public function register_settings() {
        register_setting('idoklad_integration_settings', 'idoklad_client_id');
        register_setting('idoklad_integration_settings', 'idoklad_client_secret');
        // Note: Default partner ID removed - partners are now managed dynamically
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'idoklad-processor-integration') !== false) {
            wp_enqueue_script(
                'idoklad-integration-admin-js',
                IDOKLAD_PROCESSOR_PLUGIN_URL . 'assets/integration-admin.js',
                array('jquery'),
                IDOKLAD_PROCESSOR_VERSION,
                true
            );
            
            wp_localize_script('idoklad-integration-admin-js', 'idoklad_integration_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('idoklad_integration_nonce')
            ));
        }
    }
    
    /**
     * Render the integration page
     */
    public function render_integration_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('iDoklad API v3 Integration', 'idoklad-invoice-processor'); ?></h1>
            
            <div class="idoklad-integration-container">
                
                <!-- Configuration Section -->
                <div class="idoklad-card">
                    <h2><?php esc_html_e('API Configuration', 'idoklad-invoice-processor'); ?></h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('idoklad_integration_settings'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="idoklad_client_id"><?php esc_html_e('Client ID', 'idoklad-invoice-processor'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="idoklad_client_id" id="idoklad_client_id" 
                                           value="<?php echo esc_attr(get_option('idoklad_client_id')); ?>" 
                                           class="regular-text" required>
                                    <p class="description"><?php esc_html_e('Your iDoklad API Client ID', 'idoklad-invoice-processor'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="idoklad_client_secret"><?php esc_html_e('Client Secret', 'idoklad-invoice-processor'); ?></label>
                                </th>
                                <td>
                                    <input type="password" name="idoklad_client_secret" id="idoklad_client_secret" 
                                           value="<?php echo esc_attr(get_option('idoklad_client_secret')); ?>" 
                                           class="regular-text" required>
                                    <p class="description"><?php esc_html_e('Your iDoklad API Client Secret', 'idoklad-invoice-processor'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Partner Management', 'idoklad-invoice-processor'); ?></label>
                                </th>
                                <td>
                                    <p class="description">
                                        <?php esc_html_e('Partner management follows the exact Postman collection workflow. If partner data is provided, a new partner will be created. If no partner data is provided, the default partner ID (22429105) will be used.', 'idoklad-invoice-processor'); ?>
                                    </p>
                                    <p class="description">
                                        <strong><?php esc_html_e('Workflow:', 'idoklad-invoice-processor'); ?></strong><br>
                                        1. <?php esc_html_e('If partner data provided: Create new partner', 'idoklad-invoice-processor'); ?><br>
                                        2. <?php esc_html_e('If no partner data: Use default partner ID (22429105)', 'idoklad-invoice-processor'); ?><br>
                                        3. <?php esc_html_e('Use partner ID in invoice creation', 'idoklad-invoice-processor'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(__('Save Configuration', 'idoklad-invoice-processor')); ?>
                    </form>
                </div>
                
                <!-- Test Integration Section -->
                <div class="idoklad-card">
                    <h2><?php esc_html_e('Test Integration', 'idoklad-invoice-processor'); ?></h2>
                    <p><?php esc_html_e('Test the complete iDoklad API integration workflow:', 'idoklad-invoice-processor'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Authentication using OAuth2 Client Credentials', 'idoklad-invoice-processor'); ?></li>
                        <li><?php esc_html_e('Resolve NumericSequence for issued invoices', 'idoklad-invoice-processor'); ?></li>
                        <li><?php esc_html_e('Create a test issued invoice', 'idoklad-invoice-processor'); ?></li>
                    </ol>
                    
                    <div class="integration-actions">
                        <button type="button" id="test-integration-btn" class="button button-primary">
                            <?php esc_html_e('Test Complete Integration', 'idoklad-invoice-processor'); ?>
                        </button>
                        <button type="button" id="create-test-invoice-btn" class="button button-secondary">
                            <?php esc_html_e('Create Test Invoice', 'idoklad-invoice-processor'); ?>
                        </button>
                    </div>
                    
                    <div id="integration-results" style="display: none;">
                        <h3><?php esc_html_e('Integration Results', 'idoklad-invoice-processor'); ?></h3>
                        <div id="integration-output"></div>
                    </div>
                </div>
                
                <!-- Integration Details Section -->
                <div class="idoklad-card">
                    <h2><?php esc_html_e('Integration Details', 'idoklad-invoice-processor'); ?></h2>
                    <div class="integration-details">
                        <h3><?php esc_html_e('Workflow Steps', 'idoklad-invoice-processor'); ?></h3>
                        <ol>
                            <li>
                                <strong><?php esc_html_e('Authentication:', 'idoklad-invoice-processor'); ?></strong>
                                <?php esc_html_e('POST request to https://identity.idoklad.cz/server/connect/token with client credentials', 'idoklad-invoice-processor'); ?>
                            </li>
                            <li>
                                <strong><?php esc_html_e('NumericSequence Resolution:', 'idoklad-invoice-processor'); ?></strong>
                                <?php esc_html_e('GET request to https://api.idoklad.cz/v3/NumericSequences to find DocumentType = 0', 'idoklad-invoice-processor'); ?>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Invoice Creation:', 'idoklad-invoice-processor'); ?></strong>
                                <?php esc_html_e('POST request to https://api.idoklad.cz/v3/IssuedInvoices with complete payload', 'idoklad-invoice-processor'); ?>
                            </li>
                        </ol>
                        
                        <h3><?php esc_html_e('Payload Structure', 'idoklad-invoice-processor'); ?></h3>
                        <p><?php esc_html_e('The integration uses the exact payload structure as specified in the requirements:', 'idoklad-invoice-processor'); ?></p>
                        <ul>
                            <li><?php esc_html_e('PartnerId, Description, Note, OrderNumber, VariableSymbol', 'idoklad-invoice-processor'); ?></li>
                            <li><?php esc_html_e('Date fields (DateOfIssue, DateOfTaxing, DateOfMaturity, etc.)', 'idoklad-invoice-processor'); ?></li>
                            <li><?php esc_html_e('Currency and Exchange Rate settings', 'idoklad-invoice-processor'); ?></li>
                            <li><?php esc_html_e('Payment and Constant Symbol configuration', 'idoklad-invoice-processor'); ?></li>
                            <li><?php esc_html_e('Dynamically injected NumericSequenceId and DocumentSerialNumber', 'idoklad-invoice-processor'); ?></li>
                            <li><?php esc_html_e('EET and VAT settings', 'idoklad-invoice-processor'); ?></li>
                            <li><?php esc_html_e('Items array with consulting and license services', 'idoklad-invoice-processor'); ?></li>
                        </ul>
                    </div>
                </div>
                
            </div>
        </div>
        
        <style>
        .idoklad-integration-container {
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
        
        .integration-actions {
            margin: 20px 0;
        }
        
        .integration-actions button {
            margin-right: 10px;
        }
        
        #integration-output {
            background: #f1f1f1;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .integration-details ul {
            list-style-type: disc;
            margin-left: 20px;
        }
        
        .integration-details ol {
            margin-left: 20px;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for testing integration
     */
    public function ajax_test_integration() {
        check_ajax_referer('idoklad_integration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $client_id = get_option('idoklad_client_id');
            $client_secret = get_option('idoklad_client_secret');
            
            if (empty($client_id) || empty($client_secret)) {
                throw new Exception('Client ID and Client Secret must be configured');
            }
            
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-idoklad-api-v3-integration.php';
            
            $integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
            $result = $integration->test_integration();
            
            wp_send_json_success(array(
                'message' => 'Integration test completed successfully',
                'result' => $result
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Integration test failed: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX handler for creating test invoice
     */
    public function ajax_create_test_invoice() {
        check_ajax_referer('idoklad_integration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $client_id = get_option('idoklad_client_id');
            $client_secret = get_option('idoklad_client_secret');
            
            if (empty($client_id) || empty($client_secret)) {
                throw new Exception('Client ID and Client Secret must be configured');
            }
            
            require_once IDOKLAD_PROCESSOR_PLUGIN_DIR . 'includes/class-idoklad-api-v3-integration.php';
            
            $integration = new IDokladProcessor_IDokladAPIV3Integration($client_id, $client_secret);
            
            // Custom invoice data for testing - following Postman collection structure
            $custom_invoice_data = array(
                'description' => 'Custom test invoice created via admin interface',
                'note' => 'This is a custom test invoice created through the WordPress admin interface',
                'order_number' => 'ADMIN-TEST-' . date('YmdHis'),
                'variable_symbol' => 'ADMIN' . date('Ymd'),
                'partner_data' => array(
                    'company' => 'ADMIN TEST COMPANY s.r.o.',
                    'email' => 'admin-test@example.com',
                    'address' => 'Admin Test Street 123',
                    'city' => 'Prague',
                    'postal_code' => '11000'
                ),
                'items' => array(
                    array(
                        'Name' => 'Custom Test Service',
                        'Description' => 'Custom test service for admin interface',
                        'Code' => 'CUSTOM001',
                        'ItemType' => 0,
                        'Unit' => 'pcs',
                        'Amount' => 1.0,
                        'UnitPrice' => 1000.0,
                        'PriceType' => 1,
                        'VatRateType' => 2,
                        'VatRate' => 0.0,
                        'IsTaxMovement' => false,
                        'DiscountPercentage' => 0.0
                    )
                )
            );
            
            $result = $integration->create_invoice_complete_workflow($custom_invoice_data);
            
            wp_send_json_success(array(
                'message' => 'Test invoice created successfully',
                'result' => $result
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Test invoice creation failed: ' . $e->getMessage()
            ));
        }
    }
}

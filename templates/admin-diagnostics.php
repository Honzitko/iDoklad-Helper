<?php
/**
 * Admin diagnostics & testing template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get authorized users for dropdown
$users = IDokladProcessor_Database::get_all_authorized_users();
?>

<div class="wrap">
    <h1><?php _e('Diagnostics & Testing', 'idoklad-invoice-processor'); ?></h1>
    
    <div class="idoklad-admin-container">
        <div class="idoklad-admin-main">
            
            <!-- Test 1: PDF Parsing -->
            <div class="idoklad-settings-section">
                <h2>📄 <?php _e('Test PDF Parsing', 'idoklad-invoice-processor'); ?></h2>
                <p><?php _e('Upload a PDF to test text extraction and see which parsing methods work on your server.', 'idoklad-invoice-processor'); ?></p>
                
                <form id="test-pdf-parsing-form" enctype="multipart/form-data">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Upload PDF', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="file" name="pdf_file" id="pdf-file-input" accept=".pdf" required />
                                <p class="description"><?php _e('Select a PDF invoice to test parsing', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" class="button button-primary">
                            <?php _e('Test PDF Parsing', 'idoklad-invoice-processor'); ?>
                        </button>
                    </p>
                </form>
                
                <div id="pdf-parsing-result" style="display:none; margin-top:20px;">
                    <h3><?php _e('Parsing Results', 'idoklad-invoice-processor'); ?></h3>
                    <div id="pdf-parsing-content"></div>
                </div>
            </div>
            
            <!-- Test ChatGPT Extraction -->
            <div class="idoklad-settings-section">
                <h2>🤖 <?php _e('Test ChatGPT Invoice Extraction', 'idoklad-invoice-processor'); ?></h2>
                <p><?php _e('Upload a PDF or paste extracted text to validate the ChatGPT pipeline end-to-end.', 'idoklad-invoice-processor'); ?></p>

                <form id="test-chatgpt-form" enctype="multipart/form-data">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Upload PDF (optional)', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="file" name="pdf_file" accept=".pdf" />
                                <p class="description"><?php _e('If provided, the PDF will be parsed using the configured extraction engine before sending to ChatGPT.', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Invoice Text (optional)', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <textarea id="chatgpt-text-input" name="pdf_text" rows="8" style="width:100%; max-width:600px; font-family: monospace;" placeholder="Paste extracted invoice text here..."></textarea>
                                <p class="description"><?php _e('Provide text if you prefer to bypass PDF parsing. You can reuse the output from the PDF parsing test.', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <button type="submit" class="button button-primary"><?php _e('Run ChatGPT Extraction', 'idoklad-invoice-processor'); ?></button>
                        <button type="button" id="chatgpt-use-last-text" class="button button-secondary"><?php _e('Use Last Extracted Text', 'idoklad-invoice-processor'); ?></button>
                    </p>
                </form>

                <div id="chatgpt-result" style="display:none; margin-top:20px;">
                    <h3><?php _e('ChatGPT Results', 'idoklad-invoice-processor'); ?></h3>
                    <div id="chatgpt-content"></div>
                </div>
            </div>

            <!-- Test 3: Zapier Webhook -->
            <div class="idoklad-settings-section">
                <h2>⚡ <?php _e('Test Zapier Webhook', 'idoklad-invoice-processor'); ?></h2>
                <p><?php _e('Send test data to your Zapier webhook to see the response.', 'idoklad-invoice-processor'); ?></p>
                
                <form id="test-zapier-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('PDF Text / Data', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <textarea name="pdf_text" id="zapier-text" rows="10" style="width:100%; max-width:600px;" placeholder='Enter extracted PDF text or paste from "Test PDF Parsing" above...'></textarea>
                                <p class="description"><?php _e('Enter text to send to Zapier (or copy from PDF parsing test above)', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" class="button button-primary">
                            <?php _e('Send to Zapier', 'idoklad-invoice-processor'); ?>
                        </button>
                        <button type="button" id="copy-from-pdf" class="button button-secondary">
                            <?php _e('Copy from PDF Test Above', 'idoklad-invoice-processor'); ?>
                        </button>
                    </p>
                </form>
                
                <div id="zapier-result" style="display:none; margin-top:20px;">
                    <h3><?php _e('Zapier Response', 'idoklad-invoice-processor'); ?></h3>
                    <div id="zapier-content"></div>
                </div>
            </div>
            
            <!-- Test 4: iDoklad API -->
            <div class="idoklad-settings-section">
                <h2>🏢 <?php _e('Test iDoklad API', 'idoklad-invoice-processor'); ?></h2>
                <p><?php _e('Send test invoice data to iDoklad API.', 'idoklad-invoice-processor'); ?></p>
                
                <form id="test-idoklad-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Select User', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <select name="user_email" id="idoklad-user" required style="max-width:400px;">
                                    <option value=""><?php _e('-- Select User --', 'idoklad-invoice-processor'); ?></option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo esc_attr($user->email); ?>">
                                            <?php echo esc_html($user->name . ' (' . $user->email . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Select which user\'s iDoklad credentials to use', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Invoice Data (JSON)', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <textarea name="invoice_data" id="idoklad-data" rows="15" style="width:100%; max-width:600px; font-family: monospace;" placeholder='Enter invoice data in JSON format...'><?php 
echo htmlspecialchars(json_encode(array(
    'invoice_number' => 'TEST-' . date('YmdHis'),
    'date' => date('Y-m-d'),
    'total_amount' => 1000.00,
    'currency' => 'CZK',
    'supplier_name' => 'Test Supplier s.r.o.',
    'supplier_vat_number' => 'CZ12345678',
    'items' => array(
        array(
            'name' => 'Test Item',
            'quantity' => 1,
            'price' => 1000.00
        )
    )
), JSON_PRETTY_PRINT));
?></textarea>
                                <p class="description"><?php _e('Edit the JSON to test different invoice data', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" class="button button-primary">
                            <?php _e('Send to iDoklad', 'idoklad-invoice-processor'); ?>
                        </button>
                        <button type="button" id="copy-from-zapier" class="button button-secondary">
                            <?php _e('Copy from Zapier Response', 'idoklad-invoice-processor'); ?>
                        </button>
                    </p>
                </form>
                
                <div id="idoklad-result" style="display:none; margin-top:20px;">
                    <h3><?php _e('iDoklad Response', 'idoklad-invoice-processor'); ?></h3>
                    <div id="idoklad-content"></div>
                </div>
            </div>
            
        </div>
        
        <div class="idoklad-admin-sidebar">
            
            <!-- Available Methods -->
            <div class="idoklad-widget">
                <h3><?php _e('Available Parsing Methods', 'idoklad-invoice-processor'); ?></h3>
                <p>
                    <button type="button" id="check-methods" class="button button-secondary" style="width: 100%;">
                        <?php _e('Check Available Methods', 'idoklad-invoice-processor'); ?>
                    </button>
                </p>
                <div id="methods-list" style="margin-top:10px;"></div>
            </div>
            
            <!-- Quick Tips -->
            <div class="idoklad-widget">
                <h3><?php _e('Testing Tips', 'idoklad-invoice-processor'); ?></h3>
                <ul style="font-size: 12px; line-height: 1.6;">
                    <li><?php _e('Test with real invoice PDFs for accurate results', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Check parsing time to identify slow methods', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Copy extracted text between tests using buttons', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Zapier response can be used to test iDoklad', 'idoklad-invoice-processor'); ?></li>
                </ul>
            </div>
            
            <!-- Current Settings -->
            <div class="idoklad-widget">
                <h3><?php _e('Current Settings', 'idoklad-invoice-processor'); ?></h3>
                <table style="width:100%; font-size:12px;">
                    <tr>
                        <td><strong><?php _e('Zapier URL:', 'idoklad-invoice-processor'); ?></strong></td>
                        <td><?php echo get_option('idoklad_zapier_webhook_url') ? '✓ Set' : '✗ Not set'; ?></td>
                    </tr>
                </table>
            </div>
            
        </div>
    </div>
</div>

<style>
.result-box {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
}
.result-box h4 {
    margin-top: 0;
    color: #333;
}
.result-success {
    border-left: 4px solid #46b450;
    background: #ecf7ed;
}
.result-error {
    border-left: 4px solid #dc3232;
    background: #fbeaea;
}
.result-info {
    border-left: 4px solid #0073aa;
    background: #e5f3ff;
}
.code-block {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 10px;
    font-family: monospace;
    font-size: 12px;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 400px;
    overflow-y: auto;
}
.stat-item {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}
.stat-item:last-child {
    border-bottom: none;
}
.method-item {
    padding: 8px;
    margin: 5px 0;
    background: #f5f5f5;
    border-radius: 3px;
    font-size: 12px;
}
.method-available {
    border-left: 3px solid #46b450;
}
.method-unavailable {
    border-left: 3px solid #dc3232;
    opacity: 0.6;
}
</style>


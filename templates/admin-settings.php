<?php
/**
 * Streamlined admin settings template focusing on ChatGPT and iDoklad REST integration.
 */

if (!defined('ABSPATH')) {
    exit;
}

$chatgpt_models = array();
if (class_exists('IDokladProcessor_ChatGPTIntegration')) {
    $chatgpt_instance = new IDokladProcessor_ChatGPTIntegration();
    $chatgpt_models = $chatgpt_instance->get_available_models();
}
?>

<div class="wrap">
    <h1><?php _e('iDoklad Invoice Processor Settings', 'idoklad-invoice-processor'); ?></h1>

    <?php settings_errors('idoklad_settings'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('idoklad_settings_nonce'); ?>

        <div class="idoklad-settings-section">
            <h2><?php _e('Email Inbox', 'idoklad-invoice-processor'); ?></h2>
            <p class="description"><?php _e('Configure the IMAP mailbox that receives supplier invoices.', 'idoklad-invoice-processor'); ?></p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="email_host"><?php _e('IMAP Host', 'idoklad-invoice-processor'); ?></label></th>
                    <td><input type="text" name="email_host" id="email_host" class="regular-text" value="<?php echo esc_attr(get_option('idoklad_email_host')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="email_port"><?php _e('Port', 'idoklad-invoice-processor'); ?></label></th>
                    <td><input type="number" name="email_port" id="email_port" class="small-text" value="<?php echo esc_attr(get_option('idoklad_email_port', 993)); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="email_username"><?php _e('Username', 'idoklad-invoice-processor'); ?></label></th>
                    <td><input type="text" name="email_username" id="email_username" class="regular-text" value="<?php echo esc_attr(get_option('idoklad_email_username')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="email_password"><?php _e('Password', 'idoklad-invoice-processor'); ?></label></th>
                    <td><input type="password" name="email_password" id="email_password" class="regular-text" value="<?php echo esc_attr(get_option('idoklad_email_password')); ?>" autocomplete="new-password" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="email_encryption"><?php _e('Encryption', 'idoklad-invoice-processor'); ?></label></th>
                    <td>
                        <select name="email_encryption" id="email_encryption">
                            <?php $current_enc = get_option('idoklad_email_encryption', 'ssl'); ?>
                            <option value="ssl" <?php selected($current_enc, 'ssl'); ?>>SSL</option>
                            <option value="tls" <?php selected($current_enc, 'tls'); ?>>TLS</option>
                            <option value="notls" <?php selected($current_enc, 'notls'); ?>>None</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="idoklad-settings-section">
            <h2><?php _e('ChatGPT Extraction', 'idoklad-invoice-processor'); ?></h2>
            <p class="description"><?php _e('Invoices are converted to text locally and analysed by ChatGPT to build a JSON payload.', 'idoklad-invoice-processor'); ?></p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="chatgpt_api_key"><?php _e('OpenAI API Key', 'idoklad-invoice-processor'); ?></label></th>
                    <td>
                        <input type="password" name="chatgpt_api_key" id="chatgpt_api_key" class="regular-text" value="<?php echo esc_attr(get_option('idoklad_chatgpt_api_key')); ?>" />
                        <p class="description"><?php _e('Required to call the ChatGPT API.', 'idoklad-invoice-processor'); ?></p>
                        <p>
                            <button type="button" id="test-chatgpt-connection" class="button button-secondary"><?php _e('Test ChatGPT Connection', 'idoklad-invoice-processor'); ?></button>
                            <span id="chatgpt-test-result" style="margin-left: 10px;"></span>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="chatgpt_model"><?php _e('Preferred Model', 'idoklad-invoice-processor'); ?></label></th>
                    <td>
                        <select name="chatgpt_model" id="chatgpt_model" style="min-width: 260px;">
                            <?php
                            $selected_model = get_option('idoklad_chatgpt_model', 'gpt-5-nano');
                            if (!empty($chatgpt_models)) {
                                foreach ($chatgpt_models as $model_key => $label) {
                                    printf('<option value="%s" %s>%s</option>', esc_attr($model_key), selected($selected_model, $model_key, false), esc_html($label));
                                }
                            } else {
                                printf('<option value="%s" selected>%s</option>', esc_attr($selected_model), esc_html($selected_model));
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="chatgpt_prompt"><?php _e('Extraction Prompt', 'idoklad-invoice-processor'); ?></label></th>
                    <td>
                        <textarea name="chatgpt_prompt" id="chatgpt_prompt" rows="5" style="width:100%; max-width:600px; font-family:monospace;"><?php echo esc_textarea(get_option('idoklad_chatgpt_prompt', 'Extract invoice data from this PDF. Return JSON with: invoice_number, date, total_amount, supplier_name, supplier_vat_number, items (array with name, quantity, price), currency. Validate data completeness.')); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="chatgpt_prompt_id"><?php _e('OpenAI Prompt ID', 'idoklad-invoice-processor'); ?></label></th>
                    <td><input type="text" name="chatgpt_prompt_id" id="chatgpt_prompt_id" class="regular-text" value="<?php echo esc_attr(get_option('idoklad_openai_prompt_id')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="chatgpt_prompt_version"><?php _e('OpenAI Prompt Version', 'idoklad-invoice-processor'); ?></label></th>
                    <td><input type="text" name="chatgpt_prompt_version" id="chatgpt_prompt_version" class="regular-text" value="<?php echo esc_attr(get_option('idoklad_openai_prompt_version')); ?>" /></td>
                </tr>
            </table>
        </div>

        <div class="idoklad-settings-section">
            <h2><?php _e('iDoklad REST API', 'idoklad-invoice-processor'); ?></h2>
            <p class="description"><?php _e('Provide the client credentials used to authenticate against the iDoklad API.', 'idoklad-invoice-processor'); ?></p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="client_id"><?php _e('Client ID', 'idoklad-invoice-processor'); ?></label></th>
                    <td><input type="text" name="client_id" id="client_id" class="regular-text" value="<?php echo esc_attr(get_option('idoklad_client_id')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="client_secret"><?php _e('Client Secret', 'idoklad-invoice-processor'); ?></label></th>
                    <td><input type="password" name="client_secret" id="client_secret" class="regular-text" value="<?php echo esc_attr(get_option('idoklad_client_secret')); ?>" autocomplete="new-password" /></td>
                </tr>
            </table>
        </div>

        <div class="idoklad-settings-section">
            <h2><?php _e('Notifications & Logging', 'idoklad-invoice-processor'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="notification_email"><?php _e('Notification Email', 'idoklad-invoice-processor'); ?></label></th>
                    <td><input type="email" name="notification_email" id="notification_email" class="regular-text" value="<?php echo esc_attr(get_option('idoklad_notification_email', get_option('admin_email'))); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Debug Mode', 'idoklad-invoice-processor'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="debug_mode" value="1" <?php checked(get_option('idoklad_debug_mode')); ?> />
                            <?php _e('Log detailed events to wp-content/debug.log', 'idoklad-invoice-processor'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" name="submit" value="1" class="button button-primary"><?php _e('Save Changes', 'idoklad-invoice-processor'); ?></button>
        </p>
    </form>
</div>

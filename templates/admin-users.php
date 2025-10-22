<?php
/**
 * Admin users template - Updated for per-user iDoklad credentials
 */

if (!defined('ABSPATH')) {
    exit;
}

$users = IDokladProcessor_Database::get_all_authorized_users();
?>

<div class="wrap">
    <h1><?php _e('Authorized Users', 'idoklad-invoice-processor'); ?></h1>
    
    <div class="idoklad-admin-container">
        <div class="idoklad-admin-main">
            <!-- Add New User Form -->
            <div class="idoklad-settings-section">
                <h2><?php _e('Add New Authorized User', 'idoklad-invoice-processor'); ?></h2>
                <p class="description"><?php _e('Add email addresses that are allowed to send invoices to your processing email. Each user must have their own iDoklad API credentials.', 'idoklad-invoice-processor'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('idoklad_users_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Email Address', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="email" name="user_email" required class="regular-text" />
                                <p class="description"><?php _e('Email address that can send invoices', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Display Name', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="user_name" required class="regular-text" />
                                <p class="description"><?php _e('Friendly name for this user', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('iDoklad Client ID', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="idoklad_client_id" required class="regular-text" />
                                <p class="description"><?php _e('iDoklad application Client ID for this user', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('iDoklad Client Secret', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="password" name="idoklad_client_secret" required class="regular-text" />
                                <p class="description"><?php _e('iDoklad application Client Secret for this user', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('iDoklad API URL', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="url" name="idoklad_api_url" value="https://api.idoklad.cz/api/v3" class="regular-text" />
                                <p class="description"><?php _e('iDoklad API endpoint URL (usually https://api.idoklad.cz/api/v3)', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('iDoklad User ID', 'idoklad-invoice-processor'); ?></th>
                            <td>
                                <input type="text" name="idoklad_user_id" class="regular-text" />
                                <p class="description"><?php _e('Optional: iDoklad user ID for this email sender', 'idoklad-invoice-processor'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Add User', 'idoklad-invoice-processor'), 'primary', 'add_user'); ?>
                </form>
            </div>
            
            <!-- Users List -->
            <div class="idoklad-settings-section">
                <h2><?php _e('Current Authorized Users', 'idoklad-invoice-processor'); ?></h2>
                
                <?php if (empty($users)): ?>
                    <p><?php _e('No authorized users found.', 'idoklad-invoice-processor'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Name', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Email', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('iDoklad Client ID', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('API URL', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Status', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Created', 'idoklad-invoice-processor'); ?></th>
                                <th><?php _e('Actions', 'idoklad-invoice-processor'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo esc_html($user->name); ?></td>
                                    <td><?php echo esc_html($user->email); ?></td>
                                    <td>
                                        <?php if ($user->idoklad_client_id): ?>
                                            <?php echo esc_html(substr($user->idoklad_client_id, 0, 10) . '...'); ?>
                                        <?php else: ?>
                                            <span style="color: red;"><?php _e('Not configured', 'idoklad-invoice-processor'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($user->idoklad_api_url ?: 'https://api.idoklad.cz/api/v3'); ?></td>
                                    <td>
                                        <?php if ($user->is_active): ?>
                                            <span style="color: green;"><?php _e('Active', 'idoklad-invoice-processor'); ?></span>
                                        <?php else: ?>
                                            <span style="color: red;"><?php _e('Inactive', 'idoklad-invoice-processor'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->created_at))); ?></td>
                                    <td>
                                        <button type="button" class="button button-small edit-user" data-user-id="<?php echo $user->id; ?>">
                                            <?php _e('Edit', 'idoklad-invoice-processor'); ?>
                                        </button>
                                        <button type="button" class="button button-small test-user-connection" data-user-id="<?php echo $user->id; ?>">
                                            <?php _e('Test Connection', 'idoklad-invoice-processor'); ?>
                                        </button>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=idoklad-processor-users&action=delete&user_id=' . $user->id), 'idoklad_delete_user_nonce'); ?>" 
                                           class="button button-small" 
                                           onclick="return confirm('<?php _e('Are you sure you want to delete this user?', 'idoklad-invoice-processor'); ?>')">
                                            <?php _e('Delete', 'idoklad-invoice-processor'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="idoklad-admin-sidebar">
            <div class="idoklad-widget">
                <h3><?php _e('User Statistics', 'idoklad-invoice-processor'); ?></h3>
                <?php
                global $wpdb;
                $logs_table = $wpdb->prefix . 'idoklad_logs';
                $total_emails = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
                $successful_emails = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE processing_status = 'success'");
                $failed_emails = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE processing_status = 'failed'");
                ?>
                <p><strong><?php _e('Total Emails Processed:', 'idoklad-invoice-processor'); ?></strong> <?php echo $total_emails; ?></p>
                <p><strong><?php _e('Successful:', 'idoklad-invoice-processor'); ?></strong> <?php echo $successful_emails; ?></p>
                <p><strong><?php _e('Failed:', 'idoklad-invoice-processor'); ?></strong> <?php echo $failed_emails; ?></p>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('Recent Activity', 'idoklad-invoice-processor'); ?></h3>
                <?php
                $recent_logs = IDokladProcessor_Database::get_logs(5);
                if ($recent_logs):
                ?>
                    <ul>
                        <?php foreach ($recent_logs as $log): ?>
                            <li>
                                <strong><?php echo esc_html($log->email_from); ?></strong><br>
                                <small>
                                    <?php echo esc_html(date_i18n('M j, Y H:i', strtotime($log->created_at))); ?> - 
                                    <span style="color: <?php echo $log->processing_status === 'success' ? 'green' : ($log->processing_status === 'failed' ? 'red' : 'orange'); ?>;">
                                        <?php echo ucfirst($log->processing_status); ?>
                                    </span>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><?php _e('No recent activity', 'idoklad-invoice-processor'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('iDoklad OAuth Setup Help', 'idoklad-invoice-processor'); ?></h3>
                <p><?php _e('Each user needs their own iDoklad OAuth credentials:', 'idoklad-invoice-processor'); ?></p>
                <ol>
                    <li><?php _e('Log in to iDoklad', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Go to Settings > API', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Create a new OAuth application', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Copy Client ID and Client Secret', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Add them to the user settings above', 'idoklad-invoice-processor'); ?></li>
                </ol>
                <p><strong><?php _e('OAuth Endpoint:', 'idoklad-invoice-processor'); ?></strong> <code>https://app.idoklad.cz/identity/server/connect/token</code></p>
                <p><strong><?php _e('API Endpoint:', 'idoklad-invoice-processor'); ?></strong> <code>https://api.idoklad.cz/api/v3</code></p>
            </div>
            
            <div class="idoklad-widget">
                <h3><?php _e('Troubleshooting', 'idoklad-invoice-processor'); ?></h3>
                <p><strong><?php _e('"Unauthorized sender" Error:', 'idoklad-invoice-processor'); ?></strong></p>
                <ul>
                    <li><?php _e('Make sure the sender\'s email is added to authorized users', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Check that the user is marked as "Active"', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Verify the email address matches exactly (case-sensitive)', 'idoklad-invoice-processor'); ?></li>
                </ul>
                <p><strong><?php _e('iDoklad OAuth Connection Issues:', 'idoklad-invoice-processor'); ?></strong></p>
                <ul>
                    <li><?php _e('Enable debug mode in settings for detailed logs', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Check WordPress error logs', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Verify Client ID and Secret are correct', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Ensure OAuth application is properly configured in iDoklad', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Test connection using the "Test Connection" button', 'idoklad-invoice-processor'); ?></li>
                    <li><?php _e('Check that your iDoklad account has API access enabled', 'idoklad-invoice-processor'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-user-modal" class="idoklad-modal" style="display: none;">
    <div class="idoklad-modal-content">
        <div class="idoklad-modal-header">
            <h2><?php _e('Edit User', 'idoklad-invoice-processor'); ?></h2>
            <span class="idoklad-modal-close">&times;</span>
        </div>
        <div class="idoklad-modal-body">
            <form id="edit-user-form">
                <input type="hidden" id="edit-user-id" name="user_id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Email Address', 'idoklad-invoice-processor'); ?></th>
                        <td>
                            <input type="email" id="edit-user-email" name="email" required class="regular-text" />
                            <p class="description"><?php _e('Email address that can send invoices', 'idoklad-invoice-processor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Display Name', 'idoklad-invoice-processor'); ?></th>
                        <td>
                            <input type="text" id="edit-user-name" name="name" required class="regular-text" />
                            <p class="description"><?php _e('Friendly name for this user', 'idoklad-invoice-processor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('iDoklad Client ID', 'idoklad-invoice-processor'); ?></th>
                        <td>
                            <input type="text" id="edit-idoklad-client-id" name="idoklad_client_id" class="regular-text" />
                            <p class="description"><?php _e('iDoklad application Client ID for this user', 'idoklad-invoice-processor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('iDoklad Client Secret', 'idoklad-invoice-processor'); ?></th>
                        <td>
                            <input type="password" id="edit-idoklad-client-secret" name="idoklad_client_secret" class="regular-text" />
                            <p class="description"><?php _e('iDoklad application Client Secret for this user (leave blank to keep current)', 'idoklad-invoice-processor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('iDoklad API URL', 'idoklad-invoice-processor'); ?></th>
                        <td>
                            <input type="url" id="edit-idoklad-api-url" name="idoklad_api_url" class="regular-text" />
                            <p class="description"><?php _e('iDoklad API endpoint URL (usually https://api.idoklad.cz/api/v3)', 'idoklad-invoice-processor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('iDoklad User ID', 'idoklad-invoice-processor'); ?></th>
                        <td>
                            <input type="text" id="edit-idoklad-user-id" name="idoklad_user_id" class="regular-text" />
                            <p class="description"><?php _e('Optional: iDoklad user ID for this email sender', 'idoklad-invoice-processor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Status', 'idoklad-invoice-processor'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="edit-user-active" name="is_active" value="1" />
                                <?php _e('Active', 'idoklad-invoice-processor'); ?>
                            </label>
                            <p class="description"><?php _e('Whether this user can send invoices', 'idoklad-invoice-processor'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Update User', 'idoklad-invoice-processor'); ?></button>
                    <button type="button" class="button button-secondary" onclick="closeEditModal()"><?php _e('Cancel', 'idoklad-invoice-processor'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Edit user functionality
    $('.edit-user').on('click', function() {
        var userId = $(this).data('user-id');
        
        // Get user data
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_get_user_data',
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var user = response.data;
                    
                    // Populate form
                    $('#edit-user-id').val(user.id);
                    $('#edit-user-email').val(user.email);
                    $('#edit-user-name').val(user.name);
                    $('#edit-idoklad-client-id').val(user.idoklad_client_id || '');
                    $('#edit-idoklad-api-url').val(user.idoklad_api_url || 'https://api.idoklad.cz/api/v3');
                    $('#edit-idoklad-user-id').val(user.idoklad_user_id || '');
                    $('#edit-user-active').prop('checked', user.is_active == 1);
                    
                    // Clear client secret field
                    $('#edit-idoklad-client-secret').val('');
                    
                    // Show modal
                    $('#edit-user-modal').show();
                } else {
                    alert('Error loading user data: ' + response.data);
                }
            },
            error: function() {
                alert('Error loading user data');
            }
        });
    });
    
    // Update user form submission
    $('#edit-user-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'idoklad_update_user',
            user_id: $('#edit-user-id').val(),
            email: $('#edit-user-email').val(),
            name: $('#edit-user-name').val(),
            idoklad_client_id: $('#edit-idoklad-client-id').val(),
            idoklad_client_secret: $('#edit-idoklad-client-secret').val(),
            idoklad_api_url: $('#edit-idoklad-api-url').val(),
            idoklad_user_id: $('#edit-idoklad-user-id').val(),
            is_active: $('#edit-user-active').is(':checked') ? 1 : 0,
            nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
        };
        
        var submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).text('<?php _e('Updating...', 'idoklad-invoice-processor'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('User updated successfully!');
                    location.reload(); // Refresh page to show updated data
                } else {
                    alert('Error updating user: ' + response.data);
                }
            },
            error: function() {
                alert('Error updating user');
            },
            complete: function() {
                submitButton.prop('disabled', false).text('<?php _e('Update User', 'idoklad-invoice-processor'); ?>');
            }
        });
    });
    
    // Test user iDoklad connection
    $('.test-user-connection').on('click', function() {
        var button = $(this);
        var userId = button.data('user-id');
        
        button.prop('disabled', true);
        button.text('Testing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idoklad_test_user_idoklad_connection',
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('idoklad_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    button.text('✓ Connected');
                    button.css('color', 'green');
                } else {
                    button.text('✗ Failed');
                    button.css('color', 'red');
                    alert('Connection failed: ' + response.data);
                }
            },
            error: function() {
                button.text('✗ Error');
                button.css('color', 'red');
                alert('Connection test failed');
            },
            complete: function() {
                setTimeout(function() {
                    button.prop('disabled', false);
                    button.text('<?php _e('Test Connection', 'idoklad-invoice-processor'); ?>');
                    button.css('color', '');
                }, 3000);
            }
        });
    });
    
    // Close modal when clicking outside or on close button
    $('.idoklad-modal-close, .idoklad-modal').on('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });
});

function closeEditModal() {
    $('#edit-user-modal').hide();
}
</script>

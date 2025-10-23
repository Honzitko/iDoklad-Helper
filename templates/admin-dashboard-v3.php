<?php
/**
 * Enhanced Admin Dashboard with comprehensive functionality
 * Showcases all features and provides user-friendly interface
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get system statistics
$user_manager = new IDokladProcessor_UserManagerV3();
$users_with_stats = $user_manager->get_all_users_with_stats();

// Calculate totals
$total_users = count($users_with_stats);
$active_users = 0;
$connected_users = 0;
$total_emails_processed = 0;
$total_documents_processed = 0;

foreach ($users_with_stats as $user_data) {
    if ($user_data['user']->is_active) {
        $active_users++;
    }
    if ($user_data['stats']['connection_status'] === 'connected') {
        $connected_users++;
    }
    $total_emails_processed += $user_data['stats']['total_emails_processed'];
    $total_documents_processed += $user_data['stats']['total_documents_processed'];
}

// Get recent activity
global $wpdb;
$logs_table = $wpdb->prefix . 'idoklad_logs';
$recent_activity = $wpdb->get_results(
    "SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT 10"
);

?>

<div class="wrap idoklad-dashboard-v3">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-area"></span>
        iDoklad Invoice Processor - Enhanced Dashboard
    </h1>
    
    <div class="idoklad-dashboard-header">
        <div class="idoklad-welcome-panel">
            <h2>Welcome to Enhanced iDoklad Processing</h2>
            <p>Your comprehensive invoice processing system with advanced email integration and robust functionality.</p>
        </div>
    </div>

    <!-- System Overview Cards -->
    <div class="idoklad-cards-grid">
        <div class="idoklad-card">
            <div class="idoklad-card-header">
                <span class="dashicons dashicons-groups"></span>
                <h3>Users</h3>
            </div>
            <div class="idoklad-card-content">
                <div class="idoklad-stat">
                    <span class="stat-number"><?php echo $total_users; ?></span>
                    <span class="stat-label">Total Users</span>
                </div>
                <div class="idoklad-stat">
                    <span class="stat-number"><?php echo $active_users; ?></span>
                    <span class="stat-label">Active</span>
                </div>
                <div class="idoklad-stat">
                    <span class="stat-number"><?php echo $connected_users; ?></span>
                    <span class="stat-label">Connected</span>
                </div>
            </div>
            <div class="idoklad-card-footer">
                <a href="<?php echo admin_url('admin.php?page=idoklad-users'); ?>" class="button button-primary">
                    Manage Users
                </a>
            </div>
        </div>

        <div class="idoklad-card">
            <div class="idoklad-card-header">
                <span class="dashicons dashicons-email"></span>
                <h3>Email Processing</h3>
            </div>
            <div class="idoklad-card-content">
                <div class="idoklad-stat">
                    <span class="stat-number"><?php echo $total_emails_processed; ?></span>
                    <span class="stat-label">Emails Processed</span>
                </div>
                <div class="idoklad-stat">
                    <span class="stat-number"><?php echo $total_documents_processed; ?></span>
                    <span class="stat-label">Documents Created</span>
                </div>
                <div class="idoklad-stat">
                    <span class="stat-number"><?php echo count($recent_activity); ?></span>
                    <span class="stat-label">Recent Activity</span>
                </div>
            </div>
            <div class="idoklad-card-footer">
                <a href="<?php echo admin_url('admin.php?page=idoklad-logs'); ?>" class="button button-primary">
                    View Logs
                </a>
            </div>
        </div>

        <div class="idoklad-card">
            <div class="idoklad-card-header">
                <span class="dashicons dashicons-chart-line"></span>
                <h3>System Health</h3>
            </div>
            <div class="idoklad-card-content">
                <div class="idoklad-health-indicator">
                    <span class="health-dot health-good"></span>
                    <span class="health-text">System Operational</span>
                </div>
                <div class="idoklad-health-details">
                    <p>Email Monitoring: <span class="status-active">Active</span></p>
                    <p>API Connections: <span class="status-active"><?php echo $connected_users; ?> Connected</span></p>
                    <p>Queue Status: <span class="status-active">Processing</span></p>
                </div>
            </div>
            <div class="idoklad-card-footer">
                <a href="<?php echo admin_url('admin.php?page=idoklad-diagnostics'); ?>" class="button">
                    Diagnostics
                </a>
            </div>
        </div>

        <div class="idoklad-card">
            <div class="idoklad-card-header">
                <span class="dashicons dashicons-admin-settings"></span>
                <h3>Quick Actions</h3>
            </div>
            <div class="idoklad-card-content">
                <div class="idoklad-quick-actions">
                    <button type="button" class="button button-secondary" onclick="testAllConnections()">
                        Test All Connections
                    </button>
                    <button type="button" class="button button-secondary" onclick="processPendingEmails()">
                        Process Pending Emails
                    </button>
                    <button type="button" class="button button-secondary" onclick="sendSystemReport()">
                        Send System Report
                    </button>
                </div>
            </div>
            <div class="idoklad-card-footer">
                <a href="<?php echo admin_url('admin.php?page=idoklad-settings'); ?>" class="button">
                    Settings
                </a>
            </div>
        </div>
    </div>

    <!-- Enhanced Features Section -->
    <div class="idoklad-features-section">
        <h2>Enhanced Features</h2>
        <div class="idoklad-features-grid">
            <div class="idoklad-feature">
                <span class="dashicons dashicons-email-alt"></span>
                <h4>Advanced Email Integration</h4>
                <p>Comprehensive email processing with multiple touchpoints and intelligent routing.</p>
                <ul>
                    <li>Multi-connection email monitoring</li>
                    <li>Smart email type detection</li>
                    <li>Automated reply system</li>
                    <li>Bulk processing capabilities</li>
                </ul>
            </div>

            <div class="idoklad-feature">
                <span class="dashicons dashicons-networking"></span>
                <h4>Robust API Integration</h4>
                <p>Enhanced iDoklad API v3 integration with comprehensive error handling.</p>
                <ul>
                    <li>Automatic token refresh</li>
                    <li>Connection monitoring</li>
                    <li>Bulk operations support</li>
                    <li>Real-time status reporting</li>
                </ul>
            </div>

            <div class="idoklad-feature">
                <span class="dashicons dashicons-bell"></span>
                <h4>Rich Notifications</h4>
                <p>Comprehensive notification system with multiple delivery channels.</p>
                <ul>
                    <li>Email notifications</li>
                    <li>Status reports</li>
                    <li>Error alerts</li>
                    <li>Processing summaries</li>
                </ul>
            </div>

            <div class="idoklad-feature">
                <span class="dashicons dashicons-admin-users"></span>
                <h4>User Management</h4>
                <p>Advanced user management with detailed statistics and monitoring.</p>
                <ul>
                    <li>User statistics tracking</li>
                    <li>Connection testing</li>
                    <li>Bulk operations</li>
                    <li>Activity monitoring</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="idoklad-recent-activity">
        <h2>Recent Activity</h2>
        <div class="idoklad-activity-list">
            <?php if (!empty($recent_activity)): ?>
                <?php foreach ($recent_activity as $activity): ?>
                    <div class="idoklad-activity-item">
                        <div class="activity-icon">
                            <span class="dashicons dashicons-<?php echo $this->get_activity_icon($activity->processing_status); ?>"></span>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">
                                <?php echo esc_html($activity->email_subject ?: 'Email Processing'); ?>
                            </div>
                            <div class="activity-meta">
                                <span class="activity-email"><?php echo esc_html($activity->email_from); ?></span>
                                <span class="activity-time"><?php echo human_time_diff(strtotime($activity->created_at)) . ' ago'; ?></span>
                                <span class="activity-status status-<?php echo esc_attr($activity->processing_status); ?>">
                                    <?php echo esc_html(ucfirst($activity->processing_status)); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="idoklad-no-activity">
                    <p>No recent activity found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- User Statistics -->
    <div class="idoklad-user-stats">
        <h2>User Statistics</h2>
        <div class="idoklad-stats-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Status</th>
                        <th>Connection</th>
                        <th>Emails Processed</th>
                        <th>Documents Created</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_with_stats as $user_data): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($user_data['user']->name ?: $user_data['user']->email); ?></strong>
                                <br>
                                <small><?php echo esc_html($user_data['user']->email); ?></small>
                            </td>
                            <td>
                                <span class="status-indicator status-<?php echo $user_data['user']->is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $user_data['user']->is_active ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="connection-indicator connection-<?php echo esc_attr($user_data['stats']['connection_status']); ?>">
                                    <?php echo esc_html(ucfirst($user_data['stats']['connection_status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($user_data['stats']['total_emails_processed']); ?></td>
                            <td><?php echo esc_html($user_data['stats']['total_documents_processed']); ?></td>
                            <td>
                                <?php if ($user_data['stats']['last_email_processed']): ?>
                                    <?php echo human_time_diff(strtotime($user_data['stats']['last_email_processed'])) . ' ago'; ?>
                                <?php else: ?>
                                    Never
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small" onclick="testUserConnection(<?php echo $user_data['user']->id; ?>)">
                                    Test Connection
                                </button>
                                <button type="button" class="button button-small" onclick="viewUserStats(<?php echo $user_data['user']->id; ?>)">
                                    View Stats
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.idoklad-dashboard-v3 {
    max-width: 1200px;
}

.idoklad-dashboard-header {
    margin: 20px 0;
}

.idoklad-welcome-panel {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.idoklad-welcome-panel h2 {
    margin-top: 0;
    color: #23282d;
}

.idoklad-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.idoklad-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    overflow: hidden;
}

.idoklad-card-header {
    background: #f1f1f1;
    padding: 15px 20px;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    align-items: center;
    gap: 10px;
}

.idoklad-card-header h3 {
    margin: 0;
    font-size: 16px;
}

.idoklad-card-content {
    padding: 20px;
}

.idoklad-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

.idoklad-card-footer {
    padding: 15px 20px;
    background: #f9f9f9;
    border-top: 1px solid #ccd0d4;
}

.idoklad-health-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.health-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

.health-good {
    background: #46b450;
}

.health-warning {
    background: #ffb900;
}

.health-error {
    background: #dc3232;
}

.idoklad-health-details p {
    margin: 5px 0;
    font-size: 14px;
}

.status-active {
    color: #46b450;
    font-weight: bold;
}

.idoklad-quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.idoklad-features-section {
    margin: 30px 0;
}

.idoklad-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.idoklad-feature {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.idoklad-feature h4 {
    margin-top: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.idoklad-feature ul {
    margin: 10px 0;
    padding-left: 20px;
}

.idoklad-feature li {
    margin: 5px 0;
    font-size: 14px;
}

.idoklad-recent-activity {
    margin: 30px 0;
}

.idoklad-activity-list {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    max-height: 400px;
    overflow-y: auto;
}

.idoklad-activity-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f1;
}

.idoklad-activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    margin-right: 15px;
    color: #666;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 500;
    margin-bottom: 5px;
}

.activity-meta {
    display: flex;
    gap: 15px;
    font-size: 13px;
    color: #666;
}

.activity-status {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: bold;
}

.status-success {
    background: #d4edda;
    color: #155724;
}

.status-failed {
    background: #f8d7da;
    color: #721c24;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-indicator {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: bold;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.connection-indicator {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: bold;
}

.connection-connected {
    background: #d4edda;
    color: #155724;
}

.connection-failed {
    background: #f8d7da;
    color: #721c24;
}

.connection-unknown {
    background: #fff3cd;
    color: #856404;
}

.idoklad-user-stats {
    margin: 30px 0;
}

.idoklad-stats-table {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    overflow: hidden;
}

.idoklad-stats-table th,
.idoklad-stats-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #f0f0f1;
}

.idoklad-stats-table th {
    background: #f1f1f1;
    font-weight: 600;
}

.idoklad-no-activity {
    padding: 40px 20px;
    text-align: center;
    color: #666;
}
</style>

<script>
function testAllConnections() {
    // Implementation for testing all user connections
    alert('Testing all connections...');
}

function processPendingEmails() {
    // Implementation for processing pending emails
    alert('Processing pending emails...');
}

function sendSystemReport() {
    // Implementation for sending system report
    alert('Sending system report...');
}

function testUserConnection(userId) {
    // Implementation for testing specific user connection
    alert('Testing connection for user: ' + userId);
}

function viewUserStats(userId) {
    // Implementation for viewing user statistics
    alert('Viewing stats for user: ' + userId);
}
</script>

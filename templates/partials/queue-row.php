<?php
/**
 * Queue row partial template
 * Variables: $item
 */

if (!defined('ABSPATH')) {
    exit;
}

$status_colors = array(
    'pending' => 'orange',
    'processing' => 'blue',
    'completed' => 'green',
    'failed' => 'red'
);
$color = $status_colors[$item->status] ?? 'gray';
?>

<tr data-queue-id="<?php echo $item->id; ?>">
    <td><?php echo $item->id; ?></td>
    <td><?php echo esc_html($item->email_from); ?></td>
    <td><?php echo esc_html($item->email_subject ?: '-'); ?></td>
    <td><?php echo esc_html(basename($item->attachment_path)); ?></td>
    <td>
        <span class="status-badge status-<?php echo esc_attr($item->status); ?>">
            <?php echo ucfirst($item->status); ?>
        </span>
    </td>
    <td>
        <span class="current-step-text">
            <?php echo esc_html($item->current_step ?: '-'); ?>
        </span>
    </td>
    <td>
        <?php echo $item->attempts; ?> / <?php echo $item->max_attempts; ?>
    </td>
    <td>
        <?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($item->created_at))); ?>
    </td>
    <td>
        <button type="button" class="button button-small view-queue-details" data-queue-id="<?php echo $item->id; ?>">
            <?php _e('Details', 'idoklad-invoice-processor'); ?>
        </button>
        <?php if (in_array($item->status, array('pending', 'processing', 'failed'))): ?>
            <button type="button" class="button button-small cancel-queue-item" data-queue-id="<?php echo $item->id; ?>" style="margin-left: 5px;">
                <?php _e('Cancel', 'idoklad-invoice-processor'); ?>
            </button>
        <?php endif; ?>
    </td>
</tr>


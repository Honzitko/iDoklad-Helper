<?php
/**
 * Test script to verify queue item updates work
 */

// Load WordPress
require_once('../../../wp-config.php');

echo "=== Queue Update Test ===\n\n";

try {
    // Initialize database
    require_once 'includes/class-database.php';
    $database = new IDokladProcessor_Database();
    
    // Get a queue item to test with
    global $wpdb;
    $table = $wpdb->prefix . 'idoklad_queue';
    $item = $wpdb->get_row("SELECT * FROM $table LIMIT 1");
    
    if (!$item) {
        echo "No queue items found to test with.\n";
        echo "Creating a test item...\n";
        
        // Create a test item
        $test_data = array(
            'email_id' => 'test-' . time(),
            'email_from' => 'test@example.com',
            'email_subject' => 'Test Email',
            'status' => 'completed'
        );
        
        $result = $wpdb->insert(
            $table,
            $test_data,
            array('%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            $item_id = $wpdb->insert_id;
            echo "✓ Test item created with ID: $item_id\n";
        } else {
            echo "✗ Failed to create test item\n";
            exit;
        }
    } else {
        $item_id = $item->id;
        echo "✓ Using existing item ID: $item_id\n";
    }
    
    // Test the update
    echo "\nTesting queue item update...\n";
    
    $update_data = array(
        'status' => 'pending',
        'current_step' => 'Test update',
        'attempts' => 0
    );
    
    echo "Update data: " . print_r($update_data, true) . "\n";
    
    $result = $database->update_queue_item($item_id, $update_data);
    
    if ($result !== false) {
        echo "✓ Update successful! Rows affected: $result\n";
        
        // Verify the update
        $updated_item = $database->get_queue_item($item_id);
        if ($updated_item) {
            echo "✓ Verification successful:\n";
            echo "  - Status: " . $updated_item['status'] . "\n";
            echo "  - Current Step: " . $updated_item['current_step'] . "\n";
            echo "  - Attempts: " . $updated_item['attempts'] . "\n";
        } else {
            echo "✗ Verification failed - could not retrieve updated item\n";
        }
    } else {
        echo "✗ Update failed\n";
        echo "Last SQL Error: " . $wpdb->last_error . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";

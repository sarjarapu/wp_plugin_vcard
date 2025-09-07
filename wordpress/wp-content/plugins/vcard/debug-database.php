<?php
/**
 * Database Diagnostic Script for vCard Plugin
 * Run this to check database table structure and troubleshoot sync issues
 */

// WordPress bootstrap
require_once('/var/www/html/wp-config.php');

echo "=== vCard Plugin Database Diagnostic ===\n\n";

global $wpdb;

// Check if tables exist
$tables_to_check = array(
    'wp_vcard_analytics' => VCARD_ANALYTICS_TABLE,
    'wp_vcard_subscriptions' => VCARD_SUBSCRIPTIONS_TABLE,
    'wp_vcard_saved_contacts' => VCARD_SAVED_CONTACTS_TABLE
);

foreach ($tables_to_check as $table_name => $table_constant) {
    echo "Checking table: $table_name\n";
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_constant'") === $table_constant;
    
    if ($table_exists) {
        echo "✅ Table exists: $table_constant\n";
        
        // Show table structure
        $columns = $wpdb->get_results("DESCRIBE $table_constant");
        echo "Columns:\n";
        foreach ($columns as $column) {
            echo "  - {$column->Field} ({$column->Type})\n";
        }
        
        // Show row count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_constant");
        echo "Row count: $count\n";
        
    } else {
        echo "❌ Table missing: $table_constant\n";
    }
    echo "\n";
}

// Test contact sync functionality
echo "=== Testing Contact Sync Functionality ===\n";

// Check if VCard_Contact_Manager class exists
if (class_exists('VCard_Contact_Manager')) {
    echo "✅ VCard_Contact_Manager class loaded\n";
    
    // Test database connection
    try {
        $test_query = $wpdb->get_var("SELECT 1");
        echo "✅ Database connection working\n";
    } catch (Exception $e) {
        echo "❌ Database connection error: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ VCard_Contact_Manager class not found\n";
}

// Check WordPress user capabilities
echo "\n=== User Capabilities Check ===\n";
$current_user = wp_get_current_user();
if ($current_user->ID) {
    echo "Current user ID: " . $current_user->ID . "\n";
    echo "User login: " . $current_user->user_login . "\n";
    echo "User roles: " . implode(', ', $current_user->roles) . "\n";
} else {
    echo "No user logged in\n";
}

// Check AJAX endpoints
echo "\n=== AJAX Endpoints Check ===\n";
$ajax_actions = array(
    'vcard_save_contact_cloud',
    'vcard_get_saved_contacts', 
    'vcard_remove_saved_contact',
    'vcard_sync_contacts'
);

foreach ($ajax_actions as $action) {
    $hook_exists = has_action("wp_ajax_$action") || has_action("wp_ajax_nopriv_$action");
    if ($hook_exists) {
        echo "✅ AJAX action registered: $action\n";
    } else {
        echo "❌ AJAX action missing: $action\n";
    }
}

echo "\n=== Diagnostic Complete ===\n";
?>
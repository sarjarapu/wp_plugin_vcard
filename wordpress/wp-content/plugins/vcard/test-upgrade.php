<?php
/**
 * Test Database Upgrade System
 * Simulates plugin activation to test the database upgrade functionality
 */

// WordPress bootstrap
require_once('/var/www/html/wp-config.php');

echo "=== Testing vCard Plugin Database Upgrade ===\n\n";

// Get current database version
$current_db_version = get_option('vcard_db_version', 'not set');
echo "Current database version: $current_db_version\n";
echo "Plugin version: " . VCARD_VERSION . "\n\n";

// Get plugin instance and trigger activation
$plugin = VCardPlugin::get_instance();

// Manually call the activation method to test upgrade
echo "Triggering plugin activation (which includes database upgrade)...\n";
$plugin->activate();

// Check new database version
$new_db_version = get_option('vcard_db_version');
echo "New database version: $new_db_version\n\n";

// Verify table structure
echo "=== Verifying Table Structure ===\n";
global $wpdb;
$table_name = VCARD_SAVED_CONTACTS_TABLE;

$columns = $wpdb->get_results("DESCRIBE $table_name");
$required_columns = array('contact_data', 'updated_at');

foreach ($required_columns as $required_col) {
    $found = false;
    foreach ($columns as $column) {
        if ($column->Field === $required_col) {
            echo "✅ Column exists: $required_col ({$column->Type})\n";
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "❌ Column missing: $required_col\n";
    }
}

echo "\n=== Upgrade Test Complete ===\n";
?>
<?php
/**
 * Database Fix Script for vCard Plugin
 * Adds missing contact_data column to wp_vcard_saved_contacts table
 */

// WordPress bootstrap
require_once('/var/www/html/wp-config.php');

echo "=== vCard Plugin Database Fix ===\n\n";

global $wpdb;

$table_name = VCARD_SAVED_CONTACTS_TABLE;

echo "Checking table structure for: $table_name\n";

// Check if contact_data column exists
$columns = $wpdb->get_results("DESCRIBE $table_name");
$has_contact_data = false;

foreach ($columns as $column) {
    if ($column->Field === 'contact_data') {
        $has_contact_data = true;
        break;
    }
}

if ($has_contact_data) {
    echo "✅ contact_data column already exists\n";
} else {
    echo "❌ contact_data column missing - adding it now...\n";
    
    // Add the missing column
    $alter_query = "ALTER TABLE $table_name ADD COLUMN contact_data longtext NOT NULL COMMENT 'JSON data containing contact information' AFTER profile_id";
    
    $result = $wpdb->query($alter_query);
    
    if ($result !== false) {
        echo "✅ Successfully added contact_data column\n";
    } else {
        echo "❌ Failed to add contact_data column\n";
        echo "Error: " . $wpdb->last_error . "\n";
    }
}

// Also check if updated_at column exists (needed for our contact manager)
$has_updated_at = false;
foreach ($columns as $column) {
    if ($column->Field === 'updated_at') {
        $has_updated_at = true;
        break;
    }
}

if (!$has_updated_at) {
    echo "❌ updated_at column missing - adding it now...\n";
    
    $alter_query = "ALTER TABLE $table_name ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp'";
    
    $result = $wpdb->query($alter_query);
    
    if ($result !== false) {
        echo "✅ Successfully added updated_at column\n";
    } else {
        echo "❌ Failed to add updated_at column\n";
        echo "Error: " . $wpdb->last_error . "\n";
    }
}

// Show final table structure
echo "\n=== Final Table Structure ===\n";
$final_columns = $wpdb->get_results("DESCRIBE $table_name");
foreach ($final_columns as $column) {
    echo "- {$column->Field} ({$column->Type})\n";
}

echo "\n=== Database Fix Complete ===\n";
?>
<?php
// Test UPDATE statement with updated_by and updated_at columns
require_once '/var/www/html/wp-config.php';

global $wpdb;

$minisite_id = '0ad219ebc198828cea6fdb6d';
$lat = 19.076;
$lng = 72.8777;
$updated_by = 1;

echo "Testing UPDATE statement with updated_by and updated_at for minisite: $minisite_id\n";
echo "Lat: $lat, Lng: $lng, Updated_by: $updated_by\n\n";

// Test 1: Check if minisite exists
echo "=== Test 1: Check if minisite exists ===\n";
$check_sql = $wpdb->prepare("SELECT id, title, updated_by, updated_at FROM {$wpdb->prefix}minisites WHERE id = %s", $minisite_id);
$result = $wpdb->get_row($check_sql);
if ($result) {
    echo "✓ Minisite exists: ID={$result->id}, Title={$result->title}\n";
    echo "Current updated_by: {$result->updated_by}, updated_at: {$result->updated_at}\n";
} else {
    echo "✗ Minisite NOT found\n";
    exit;
}

// Test 2: Run the UPDATE statement with updated_by and updated_at
echo "\n=== Test 2: Run UPDATE statement with updated_by and updated_at ===\n";
$update_sql = $wpdb->prepare(
    "UPDATE {$wpdb->prefix}minisites SET updated_by = %d, updated_at = NOW(), location_point = POINT(%f, %f) WHERE id = %s",
    $updated_by,
    $lng,
    $lat,
    $minisite_id
);

echo "SQL Query: $update_sql\n";

$rows_affected = $wpdb->query($update_sql);

echo "Rows affected: $rows_affected\n";
echo "Last error: " . ($wpdb->last_error ?: 'None') . "\n";
echo "Last query: " . $wpdb->last_query . "\n";

if ($rows_affected > 0) {
    echo "✓ UPDATE successful!\n";
} else {
    echo "✗ UPDATE failed - no rows affected\n";
}

// Test 3: Verify the update
echo "\n=== Test 3: Verify the update ===\n";
$verify_sql = $wpdb->prepare(
    "SELECT id, title, updated_by, updated_at, ST_AsText(location_point) as location FROM {$wpdb->prefix}minisites WHERE id = %s",
    $minisite_id
);
$verify_result = $wpdb->get_row($verify_sql);
if ($verify_result) {
    echo "✓ Verification: ID={$verify_result->id}, Title={$verify_result->title}\n";
    echo "Updated_by: {$verify_result->updated_by}, Updated_at: {$verify_result->updated_at}\n";
    echo "Location: " . ($verify_result->location ?: 'NULL') . "\n";
} else {
    echo "✗ Verification failed\n";
}

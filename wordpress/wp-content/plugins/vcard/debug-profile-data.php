<?php
/**
 * Debug Profile Data - Direct Database Query
 * 
 * Access this file directly to see raw database data
 * URL: /wp-content/plugins/vcard/debug-profile-data.php?profile_id=X
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Admin only.');
}

$profile_id = $_GET['profile_id'] ?? null;

if (!$profile_id) {
    echo '<h1>Debug Profile Data</h1>';
    echo '<p>Add ?profile_id=X to the URL to debug a specific profile</p>';
    
    // Show all vCard profiles
    $profiles = get_posts([
        'post_type' => 'vcard_profile',
        'post_status' => 'any',
        'numberposts' => 10
    ]);
    
    echo '<h2>Available Profiles:</h2>';
    foreach ($profiles as $profile) {
        echo '<p><a href="?profile_id=' . $profile->ID . '">' . $profile->post_title . ' (ID: ' . $profile->ID . ')</a></p>';
    }
    exit;
}

echo '<h1>Debug Profile Data - ID: ' . $profile_id . '</h1>';

// Get raw post data
$post = get_post($profile_id);
echo '<h2>1. Raw Post Data:</h2>';
echo '<pre>' . print_r($post, true) . '</pre>';

// Get all meta data
$meta = get_post_meta($profile_id);
echo '<h2>2. All Meta Data (Raw):</h2>';
echo '<pre>' . print_r($meta, true) . '</pre>';

// Process meta data like VCardDatabaseHelper does
echo '<h2>2b. Processed Meta Data:</h2>';
$processed_meta = [];
foreach ($meta as $key => $value) {
    $clean_key = str_replace('_vcard_', '', $key);
    $processed_meta[$clean_key] = is_array($value) ? $value[0] : $value;
}
echo '<pre>' . print_r($processed_meta, true) . '</pre>';

// Check specific contact fields
echo '<h2>2c. Contact Fields Check:</h2>';
echo '<p>phone: ' . ($processed_meta['phone'] ?? 'NOT FOUND') . '</p>';
echo '<p>email: ' . ($processed_meta['email'] ?? 'NOT FOUND') . '</p>';
echo '<p>whatsapp: ' . ($processed_meta['whatsapp'] ?? 'NOT FOUND') . '</p>';

// Test VCardDatabaseHelper::getProfileData
echo '<h2>3. VCardDatabaseHelper::getProfileData():</h2>';
$profile_data = \VCard\VCardDatabaseHelper::getProfileData($profile_id);
echo '<pre>' . print_r($profile_data, true) . '</pre>';

// Test VCardDatabaseHelper::getBusinessProfileData
echo '<h2>4. VCardDatabaseHelper::getBusinessProfileData():</h2>';
$business_data = \VCard\VCardDatabaseHelper::getBusinessProfileData($profile_id);
echo '<pre>' . print_r($business_data, true) . '</pre>';

// Test VCardProfileController
echo '<h2>5. VCardProfileController::get_profile_data():</h2>';
$controller = new \VCard\VCardProfileController();
$controller_data = $controller->get_profile_data($profile_id);
echo '<pre>' . print_r($controller_data, true) . '</pre>';

// Test VCardTemplateRenderer
echo '<h2>6. VCardTemplateRenderer Template Variables:</h2>';
$renderer = new \VCard\VCardTemplateRenderer();
// Use reflection to access private method
$reflection = new ReflectionClass($renderer);
$method = $reflection->getMethod('prepareTemplateVariables');
$method->setAccessible(true);
$template_vars = $method->invoke($renderer, $controller_data);
echo '<pre>' . print_r($template_vars, true) . '</pre>';

echo '<p><a href="' . get_permalink($profile_id) . '">View Profile Frontend</a></p>';
echo '<p><a href="' . get_edit_post_link($profile_id) . '">Edit Profile</a></p>';
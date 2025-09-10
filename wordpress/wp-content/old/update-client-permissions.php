<?php
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-load.php');

echo 'Updating vcard_client role to include vcard_user capabilities...' . PHP_EOL;

// Get the vcard_client role
$client_role = get_role('vcard_client');

if ($client_role) {
    // Add all vcard_user capabilities to vcard_client
    $user_capabilities = array(
        // vCard viewing capabilities
        'read_vcard_profiles' => true,
        
        // Contact management capabilities
        'save_vcard_contacts' => true,
        'manage_own_saved_contacts' => true,
        'export_own_saved_contacts' => true,
        'download_vcard_files' => true,
        
        // Interaction capabilities
        'submit_vcard_inquiries' => true,
        'share_vcard_profiles' => true,
        'access_vcard_user_dashboard' => true,
    );
    
    foreach ($user_capabilities as $cap => $granted) {
        $client_role->add_cap($cap, $granted);
        echo "Added capability to vcard_client: $cap" . PHP_EOL;
    }
    
    echo PHP_EOL . 'vcard_client role updated successfully!' . PHP_EOL;
    
    // Test with testclient user
    $testclient = get_user_by('login', 'testclient');
    if ($testclient) {
        echo PHP_EOL . 'testclient capabilities:' . PHP_EOL;
        echo 'Can save_vcard_contacts: ' . ($testclient->has_cap('save_vcard_contacts') ? 'YES' : 'NO') . PHP_EOL;
        echo 'Can access_vcard_user_dashboard: ' . ($testclient->has_cap('access_vcard_user_dashboard') ? 'YES' : 'NO') . PHP_EOL;
        echo 'Can access_vcard_client_dashboard: ' . ($testclient->has_cap('access_vcard_client_dashboard') ? 'YES' : 'NO') . PHP_EOL;
    }
    
} else {
    echo 'vcard_client role not found!' . PHP_EOL;
}
?>
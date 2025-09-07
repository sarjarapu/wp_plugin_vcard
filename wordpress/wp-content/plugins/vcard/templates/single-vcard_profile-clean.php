<?php
/**
 * Clean vCard Profile Template
 * 
 * Uses Twig templating and separated concerns
 * 
 * @package VCard
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Initialize the profile controller
use VCard\VCardProfileController;

try {
    // Create controller instance
    $controller = new VCardProfileController();
    
    // Display the profile
    echo $controller->displayProfile(get_the_ID());
    
} catch (Exception $e) {
    // Fallback for any errors
    if (WP_DEBUG) {
        echo '<div class="error">Error loading profile: ' . esc_html($e->getMessage()) . '</div>';
    } else {
        echo '<div class="vcard-error">Unable to load profile. Please try again later.</div>';
    }
}

get_footer();
?>
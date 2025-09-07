<?php
/**
 * Single vCard Profile Template - Twig Version
 * Uses the new VCardProfileController and VCardTemplateRenderer
 */

get_header(); 

// Initialize the profile controller
$controller = new \VCard\VCardProfileController();
$profile_data = $controller->get_profile_data(get_the_ID());

// Initialize template renderer
$renderer = new \VCard\VCardTemplateRenderer();

// Track profile view
$current_views = (int) get_post_meta(get_the_ID(), '_vcard_profile_views', true);
update_post_meta(get_the_ID(), '_vcard_profile_views', $current_views + 1);

// Render the profile using Twig
echo $renderer->renderProfile($profile_data);

get_footer();
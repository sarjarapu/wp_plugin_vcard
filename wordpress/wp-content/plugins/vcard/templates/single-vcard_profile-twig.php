<?php
/**
 * Single vCard Profile Template (Twig-based)
 * 
 * This template uses the new VCardTemplateRenderer with Twig templating
 */

get_header(); 

// Initialize the new template renderer
$renderer = new \VCard\VCardTemplateRenderer();
$controller = new \VCard\VCardProfileController();

// Get profile data using the new controller
$profile_data = $controller->get_profile_data(get_the_ID());

// Render the profile using Twig
echo $renderer->renderProfile($profile_data);

get_footer();
<?php
/**
 * Single vCard Profile Template - Standalone Version
 * Clean vCard page without WordPress header/footer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Initialize the profile controller
$controller = new \VCard\VCardProfileController();
$profile_data = $controller->get_profile_data(get_the_ID());

// Initialize template renderer
$renderer = new \VCard\VCardTemplateRenderer();

// Track profile view
$current_views = (int) get_post_meta(get_the_ID(), '_vcard_profile_views', true);
update_post_meta(get_the_ID(), '_vcard_profile_views', $current_views + 1);

// Get basic profile info for meta tags
$business_profile = new VCard_Business_Profile(get_the_ID());
$is_business = $business_profile->is_business_profile();
$profile_name = $is_business ? 
    $business_profile->get_data('business_name') : 
    trim($business_profile->get_data('first_name') . ' ' . $business_profile->get_data('last_name'));
$profile_description = $is_business ? 
    $business_profile->get_data('business_description') : 
    get_the_excerpt();

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    
    <!-- Profile Meta Tags -->
    <title><?php echo esc_html($profile_name); ?> - Digital Business Card</title>
    <meta name="description" content="<?php echo esc_attr(wp_trim_words($profile_description, 25)); ?>">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo esc_attr($profile_name); ?>">
    <meta property="og:description" content="<?php echo esc_attr(wp_trim_words($profile_description, 25)); ?>">
    <meta property="og:type" content="profile">
    <meta property="og:url" content="<?php echo esc_url(get_permalink()); ?>">
    <?php if (has_post_thumbnail()) : ?>
    <meta property="og:image" content="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'large')); ?>">
    <?php endif; ?>
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr($profile_name); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr(wp_trim_words($profile_description, 25)); ?>">
    <?php if (has_post_thumbnail()) : ?>
    <meta name="twitter:image" content="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'large')); ?>">
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo esc_url(get_site_icon_url(32)); ?>">
    
    <?php 
    // Load only essential WordPress head elements (CSS, JS)
    wp_head(); 
    ?>
    
    <style>
        /* Remove WordPress admin bar space */
        html { margin-top: 0 !important; }
        * html body { margin-top: 0 !important; }
        
        /* Clean body styling */
        body {
            margin: 0;
            padding: 0;
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Hide WordPress elements */
        #wpadminbar,
        .admin-bar,
        .wp-toolbar {
            display: none !important;
        }
    </style>
</head>
<body <?php body_class('vcard-standalone'); ?>>

<?php
// Render the profile using Twig
echo $renderer->renderProfile($profile_data);
?>

<?php wp_footer(); ?>
</body>
</html>
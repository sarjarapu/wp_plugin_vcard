<?php
/**
 * Single Business Profile Template
 * 
 * This template displays individual business profiles
 * Following WordPress template hierarchy conventions
 *
 * @package BizCard_Pro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div class="bizcard-single-profile">
    <?php
    while (have_posts()) :
        the_post();
        
        // Get the business profile data
        $profile_id = get_post_meta(get_the_ID(), '_bizcard_profile_id', true);
        
        if ($profile_id) {
            global $wpdb;
            $table_name = BizCard_Pro_Database::get_table_name('profiles');
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d AND is_public = 1",
                $profile_id
            ));
            
            if ($profile) {
                // Get styling
                $styling_table = BizCard_Pro_Database::get_table_name('styling');
                $styling = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$styling_table} WHERE profile_id = %d",
                    $profile->id
                ));
                
                // Parse JSON data
                $contact_info = json_decode($profile->contact_info, true) ?: array();
                $business_hours = json_decode($profile->business_hours, true) ?: array();
                $social_media = json_decode($profile->social_media, true) ?: array();
                
                // Get styling or use defaults
                $theme = $styling->style_theme ?? 'professional';
                $primary_color = $styling->primary_color ?? '#667eea';
                $secondary_color = $styling->secondary_color ?? '#764ba2';
                
                // Include the profile display template
                include plugin_dir_path(__FILE__) . 'profile-display-content.php';
            } else {
                echo '<p>Profile not found or not public.</p>';
            }
        } else {
            echo '<p>No profile associated with this post.</p>';
        }
        
    endwhile;
    ?>
</div>

<?php get_footer(); ?>
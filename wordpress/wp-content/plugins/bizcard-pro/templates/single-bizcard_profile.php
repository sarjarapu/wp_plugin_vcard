<?php
/**
 * Single Business Profile Template
 * Following the movies plugin pattern
 *
 * @package BizCard_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;
get_header();
?>

<main class="bizcard-wrap">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article <?php post_class('bizcard-single'); ?>>
            
            <h1><?php the_title(); ?></h1>
            
            <?php if (has_post_thumbnail()) : ?>
                <div class="bizcard-single__thumb"><?php the_post_thumbnail('large'); ?></div>
            <?php endif; ?>
            
            <div class="bizcard-single__content">
                <?php the_content(); ?>
            </div>
            
            <?php
            // Get additional profile data from our custom table
            $profile_id = get_post_meta(get_the_ID(), '_bizcard_profile_id', true);
            
            if ($profile_id) {
                global $wpdb;
                $table_name = BizCard_Pro_Database::get_table_name('profiles');
                $profile = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE id = %d AND is_public = 1",
                    $profile_id
                ));
                
                if ($profile) {
                    $contact_info = json_decode($profile->contact_info, true) ?: array();
                    $social_media = json_decode($profile->social_media, true) ?: array();
                    ?>
                    
                    <div class="bizcard-profile-details">
                        <?php if ($profile->business_tagline): ?>
                            <p class="bizcard-tagline"><?php echo esc_html($profile->business_tagline); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($contact_info)): ?>
                            <div class="bizcard-contact">
                                <h3>Contact Information</h3>
                                <?php foreach ($contact_info as $key => $value): ?>
                                    <?php if ($value): ?>
                                        <p><strong><?php echo ucfirst($key); ?>:</strong> <?php echo esc_html($value); ?></p>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($social_media)): ?>
                            <div class="bizcard-social">
                                <h3>Follow Us</h3>
                                <?php foreach ($social_media as $platform => $url): ?>
                                    <?php if ($url): ?>
                                        <a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo ucfirst($platform); ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php
                }
            }
            ?>
            
            <a href="<?php echo esc_url(get_post_type_archive_link('bizcard_profile')); ?>">&larr; Back to Business Profiles</a>
            
        </article>
    <?php endwhile; endif; ?>
</main>

<?php get_footer(); ?>
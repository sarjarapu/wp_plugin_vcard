<?php
get_header(); ?>

<div class="vcard-archive-container">
    <header class="vcard-archive-header">
        <h1><?php _e('vCard Directory', 'vcard'); ?></h1>
        <p><?php _e('Browse our collection of professional vCards', 'vcard'); ?></p>
    </header>
    
    <?php if (have_posts()) : ?>
        <div class="vcard-grid">
            <?php while (have_posts()) : the_post(); ?>
                <div class="vcard-card">
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="vcard-card-photo">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('thumbnail'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <h2 class="vcard-card-name">
                        <a href="<?php the_permalink(); ?>">
                            <?php 
                            $first_name = get_post_meta(get_the_ID(), '_vcard_first_name', true);
                            $last_name = get_post_meta(get_the_ID(), '_vcard_last_name', true);
                            $business_name = get_post_meta(get_the_ID(), '_vcard_business_name', true);
                            
                            // Show business name if available, otherwise personal name
                            if ($business_name) {
                                echo esc_html($business_name);
                            } else {
                                echo esc_html($first_name . ' ' . $last_name);
                            }
                            ?>
                        </a>
                    </h2>
                    
                    <?php 
                    $job_title = get_post_meta(get_the_ID(), '_vcard_job_title', true);
                    $company = get_post_meta(get_the_ID(), '_vcard_company', true);
                    $business_tagline = get_post_meta(get_the_ID(), '_vcard_business_tagline', true);
                    
                    if ($business_tagline) : ?>
                        <p class="vcard-card-tagline"><?php echo esc_html($business_tagline); ?></p>
                    <?php elseif ($job_title || $company) : ?>
                        <p class="vcard-card-title">
                            <?php echo esc_html($job_title); ?>
                            <?php if ($job_title && $company) echo ' at '; ?>
                            <?php echo esc_html($company); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="vcard-card-contact">
                        <?php 
                        $phone = get_post_meta(get_the_ID(), '_vcard_phone', true);
                        $email = get_post_meta(get_the_ID(), '_vcard_email', true);
                        $website = get_post_meta(get_the_ID(), '_vcard_website', true);
                        
                        if ($phone) : ?>
                            <div><strong><?php _e('Phone:', 'vcard'); ?></strong> <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></div>
                        <?php endif;
                        
                        if ($email) : ?>
                            <div><strong><?php _e('Email:', 'vcard'); ?></strong> <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></div>
                        <?php endif;
                        
                        if ($website) : ?>
                            <div><strong><?php _e('Website:', 'vcard'); ?></strong> <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener"><?php echo esc_html($website); ?></a></div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (get_the_excerpt()) : ?>
                        <div class="vcard-card-excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
        
        <div class="vcard-pagination">
            <?php
            the_posts_pagination(array(
                'mid_size' => 2,
                'prev_text' => __('&laquo; Previous', 'vcard'),
                'next_text' => __('Next &raquo;', 'vcard'),
            ));
            ?>
        </div>
        
    <?php else : ?>
        <div class="vcard-no-results">
            <h2><?php _e('No vCards found', 'vcard'); ?></h2>
            <p><?php _e('There are currently no vCards available.', 'vcard'); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
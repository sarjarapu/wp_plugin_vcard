<?php
/**
 * Single vCard Profile Template with Contact Management Demo
 * 
 * This template demonstrates how to integrate the contact management system
 * into vCard profile templates. Copy the relevant sections to your theme's
 * single-vcard_profile.php template.
 * 
 * @package vCard
 * @version 1.0.0
 */

get_header(); ?>

<div class="vcard-profile-container">
    <?php while (have_posts()) : the_post(); ?>
        
        <?php
        // Initialize business profile
        $business_profile = new VCard_Business_Profile(get_the_ID());
        $profile_data = $business_profile->get_all_data();
        ?>
        
        <article id="post-<?php the_ID(); ?>" <?php post_class('vcard-profile'); ?> data-profile-id="<?php echo esc_attr(get_the_ID()); ?>">
            
            <!-- Profile Header -->
            <header class="vcard-profile-header">
                <div class="vcard-profile-info">
                    <h1 class="vcard-business-name business-name"><?php echo esc_html($profile_data['business_name'] ?: get_the_title()); ?></h1>
                    
                    <?php if ($profile_data['owner_name']) : ?>
                        <h2 class="vcard-owner-name owner-name"><?php echo esc_html($profile_data['owner_name']); ?></h2>
                    <?php endif; ?>
                    
                    <?php if ($profile_data['job_title']) : ?>
                        <p class="vcard-job-title job-title"><?php echo esc_html($profile_data['job_title']); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if ($profile_data['business_logo']) : ?>
                    <div class="vcard-logo">
                        <img src="<?php echo esc_url($profile_data['business_logo']); ?>" alt="<?php echo esc_attr($profile_data['business_name']); ?>" class="logo">
                    </div>
                <?php endif; ?>
            </header>
            
            <!-- Contact Management Buttons -->
            <div class="vcard-contact-management">
                <button class="vcard-save-contact-btn" data-profile-id="<?php echo esc_attr(get_the_ID()); ?>">
                    <i class="fas fa-bookmark"></i> <?php _e('Save Contact', 'vcard'); ?>
                </button>
                
                <button class="vcard-view-contacts-btn">
                    <i class="fas fa-address-book"></i> <?php _e('My Contacts', 'vcard'); ?>
                    <span class="vcard-contact-count" style="display: none;">0</span>
                </button>
                
                <div class="vcard-sharing-buttons">
                    <button class="vcard-share-btn" data-platform="whatsapp">
                        <i class="fab fa-whatsapp"></i> <?php _e('Share', 'vcard'); ?>
                    </button>
                    
                    <button class="vcard-download-btn" data-profile-id="<?php echo esc_attr(get_the_ID()); ?>">
                        <i class="fas fa-download"></i> <?php _e('Download vCard', 'vcard'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Contact Information -->
            <section class="vcard-contact-info">
                <h3><?php _e('Contact Information', 'vcard'); ?></h3>
                
                <?php if ($profile_data['phone']) : ?>
                    <p class="vcard-phone">
                        <i class="fas fa-phone"></i>
                        <a href="tel:<?php echo esc_attr($profile_data['phone']); ?>"><?php echo esc_html($profile_data['phone']); ?></a>
                    </p>
                <?php endif; ?>
                
                <?php if ($profile_data['email']) : ?>
                    <p class="vcard-email">
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:<?php echo esc_attr($profile_data['email']); ?>"><?php echo esc_html($profile_data['email']); ?></a>
                    </p>
                <?php endif; ?>
                
                <?php if ($profile_data['website']) : ?>
                    <p class="vcard-website">
                        <i class="fas fa-globe"></i>
                        <a href="<?php echo esc_url($profile_data['website']); ?>" class="website-link" target="_blank"><?php echo esc_html($profile_data['website']); ?></a>
                    </p>
                <?php endif; ?>
                
                <?php 
                $address = array_filter(array(
                    $profile_data['address'],
                    $profile_data['city'],
                    $profile_data['state'],
                    $profile_data['zip_code'],
                    $profile_data['country']
                ));
                if (!empty($address)) : ?>
                    <p class="vcard-address address">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo esc_html(implode(', ', $address)); ?>
                    </p>
                <?php endif; ?>
            </section>
            
            <!-- Business Description -->
            <?php if ($profile_data['business_description']) : ?>
                <section class="vcard-description">
                    <h3><?php _e('About', 'vcard'); ?></h3>
                    <div class="vcard-description business-description">
                        <?php echo wp_kses_post(wpautop($profile_data['business_description'])); ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Services -->
            <?php 
            $services = json_decode($profile_data['services'], true);
            if (!empty($services)) : ?>
                <section class="vcard-services">
                    <h3><?php _e('Services', 'vcard'); ?></h3>
                    <div class="vcard-services-grid">
                        <?php foreach ($services as $service) : ?>
                            <div class="vcard-service-item">
                                <h4><?php echo esc_html($service['name']); ?></h4>
                                <?php if (!empty($service['description'])) : ?>
                                    <p><?php echo esc_html($service['description']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($service['price'])) : ?>
                                    <span class="vcard-service-price"><?php echo esc_html($service['price']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Products -->
            <?php 
            $products = json_decode($profile_data['products'], true);
            if (!empty($products)) : ?>
                <section class="vcard-products">
                    <h3><?php _e('Products', 'vcard'); ?></h3>
                    <div class="vcard-products-grid">
                        <?php foreach ($products as $product) : ?>
                            <div class="vcard-product-item">
                                <h4><?php echo esc_html($product['name']); ?></h4>
                                <?php if (!empty($product['description'])) : ?>
                                    <p><?php echo esc_html($product['description']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($product['price'])) : ?>
                                    <span class="vcard-product-price"><?php echo esc_html($product['price']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Gallery -->
            <?php 
            $gallery = json_decode($profile_data['gallery'], true);
            if (!empty($gallery)) : ?>
                <section class="vcard-gallery">
                    <h3><?php _e('Gallery', 'vcard'); ?></h3>
                    <div class="vcard-gallery-grid">
                        <?php foreach ($gallery as $image) : ?>
                            <div class="vcard-gallery-item">
                                <img src="<?php echo esc_url($image['image_url']); ?>" alt="<?php echo esc_attr($image['title']); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Social Media -->
            <?php 
            $social_media = array(
                'facebook' => $profile_data['social_facebook'],
                'instagram' => $profile_data['social_instagram'],
                'linkedin' => $profile_data['social_linkedin'],
                'twitter' => $profile_data['social_twitter'],
                'youtube' => $profile_data['social_youtube'],
                'tiktok' => $profile_data['social_tiktok']
            );
            $social_media = array_filter($social_media);
            
            if (!empty($social_media)) : ?>
                <section class="vcard-social-media">
                    <h3><?php _e('Follow Us', 'vcard'); ?></h3>
                    <div class="vcard-social-links">
                        <?php foreach ($social_media as $platform => $url) : ?>
                            <a href="<?php echo esc_url($url); ?>" class="vcard-social-link vcard-social-<?php echo esc_attr($platform); ?>" target="_blank">
                                <i class="fab fa-<?php echo esc_attr($platform); ?>"></i>
                                <span><?php echo esc_html(ucfirst($platform)); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Contact Form -->
            <section class="vcard-contact-form-section">
                <h3><?php _e('Get in Touch', 'vcard'); ?></h3>
                <form class="vcard-contact-form" method="post">
                    <div class="vcard-form-row">
                        <div class="vcard-form-field">
                            <label for="contact_name"><?php _e('Name', 'vcard'); ?> *</label>
                            <input type="text" id="contact_name" name="contact_name" required>
                        </div>
                        <div class="vcard-form-field">
                            <label for="contact_email"><?php _e('Email', 'vcard'); ?> *</label>
                            <input type="email" id="contact_email" name="contact_email" required>
                        </div>
                    </div>
                    
                    <div class="vcard-form-row">
                        <div class="vcard-form-field">
                            <label for="contact_phone"><?php _e('Phone', 'vcard'); ?></label>
                            <input type="tel" id="contact_phone" name="contact_phone">
                        </div>
                        <div class="vcard-form-field">
                            <label for="contact_subject"><?php _e('Subject', 'vcard'); ?></label>
                            <input type="text" id="contact_subject" name="contact_subject">
                        </div>
                    </div>
                    
                    <div class="vcard-form-field">
                        <label for="contact_message"><?php _e('Message', 'vcard'); ?> *</label>
                        <textarea id="contact_message" name="contact_message" rows="5" required></textarea>
                    </div>
                    
                    <input type="hidden" name="profile_id" value="<?php echo esc_attr(get_the_ID()); ?>">
                    
                    <div class="vcard-form-submit">
                        <input type="submit" value="<?php _e('Send Message', 'vcard'); ?>" class="vcard-submit-btn">
                    </div>
                </form>
            </section>
            
        </article>
        
    <?php endwhile; ?>
</div>

<style>
/* Basic styling for the contact management demo */
.vcard-profile-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.vcard-profile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
}

.vcard-contact-management {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.vcard-sharing-buttons {
    display: flex;
    gap: 10px;
}

.vcard-contact-info,
.vcard-description,
.vcard-services,
.vcard-products,
.vcard-gallery,
.vcard-social-media,
.vcard-contact-form-section {
    margin-bottom: 30px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.vcard-contact-info p {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 10px 0;
}

.vcard-contact-info i {
    width: 20px;
    color: #007cba;
}

.vcard-services-grid,
.vcard-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.vcard-service-item,
.vcard-product-item {
    background: white;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.vcard-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.vcard-gallery-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 6px;
}

.vcard-social-links {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 15px;
}

.vcard-social-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
    transition: all 0.2s ease;
}

.vcard-social-link:hover {
    background: #007cba;
    color: white;
}

.vcard-contact-form {
    background: white;
    padding: 20px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.vcard-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.vcard-form-field {
    display: flex;
    flex-direction: column;
}

.vcard-form-field label {
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.vcard-form-field input,
.vcard-form-field textarea {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.vcard-submit-btn {
    background: #007cba;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.2s ease;
}

.vcard-submit-btn:hover {
    background: #005a87;
}

@media (max-width: 768px) {
    .vcard-profile-header {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .vcard-contact-management {
        justify-content: center;
    }
    
    .vcard-form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php get_footer(); ?>
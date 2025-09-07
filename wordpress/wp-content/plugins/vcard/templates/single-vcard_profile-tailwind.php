<?php
/**
 * Single vCard Profile Template - Tailwind Migration Demo
 * 
 * This template demonstrates Phase 3 migration with Tailwind-style navigation and forms
 * 
 * @package vCard
 * @version 1.0.0
 */

get_header(); 

// Initialize business profile
$business_profile = new VCard_Business_Profile(get_the_ID());

// Track profile view
$current_views = (int) get_post_meta(get_the_ID(), '_vcard_profile_views', true);
update_post_meta(get_the_ID(), '_vcard_profile_views', $current_views + 1);

// Get template name for styling
$template_name = $business_profile->get_data('template_name') ?: 'default';
$is_business = $business_profile->is_business_profile();
?>

<div class="vcard-modern-wrapper">
<div class="container-tailwind">
    <div class="vcard-single-container vcard-template-<?php echo esc_attr($template_name); ?> <?php echo $is_business ? 'vcard-business-profile' : 'vcard-personal-profile'; ?>" data-profile-id="<?php echo get_the_ID(); ?>">
        
        <?php while (have_posts()) : the_post(); ?>
            <article class="vcard-single" data-profile-id="<?php echo get_the_ID(); ?>">
                
                <!-- Profile Header Section -->
                <div class="card-tailwind elevated mb-8">
                    <div class="card-body">
                        <div class="flex flex-col md:flex-row items-center gap-6">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="flex-shrink-0">
                                    <?php the_post_thumbnail('medium', array('class' => 'w-32 h-32 rounded-full object-cover border-4 border-white shadow-lg')); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex-1 text-center md:text-left">
                                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                                    <?php 
                                    if ($is_business) {
                                        $business_name = $business_profile->get_data('business_name');
                                        echo esc_html($business_name);
                                    } else {
                                        $first_name = $business_profile->get_data('first_name');
                                        $last_name = $business_profile->get_data('last_name');
                                        echo esc_html(trim($first_name . ' ' . $last_name));
                                    }
                                    ?>
                                </h1>
                                
                                <?php 
                                $job_title = $business_profile->get_data('job_title');
                                $company = $business_profile->get_data('company');
                                $business_tagline = $business_profile->get_data('business_tagline');
                                
                                if ($is_business && $business_tagline) : ?>
                                    <p class="text-lg text-gray-600 mb-3"><?php echo esc_html($business_tagline); ?></p>
                                <?php elseif ($job_title || $company) : ?>
                                    <p class="text-lg text-gray-600 mb-3">
                                        <?php echo esc_html($job_title); ?>
                                        <?php if ($job_title && $company) echo ' at '; ?>
                                        <?php echo esc_html($company); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <!-- Profile View Counter -->
                                <div class="flex items-center justify-center md:justify-start gap-2 text-sm text-gray-500">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <?php printf(__('%d views', 'vcard'), $current_views + 1); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        
                        <!-- Business Description or Personal Bio -->
                        <?php 
                        $description = '';
                        if ($is_business) {
                            $description = $business_profile->get_data('business_description');
                        } else {
                            $description = get_the_content();
                        }
                        
                        if ($description) : ?>
                            <div id="about" class="card-tailwind vcard-section">
                                <div class="card-header">
                                    <h3 class="card-title"><?php echo $is_business ? __('About Our Business', 'vcard') : __('About', 'vcard'); ?></h3>
                                </div>
                                <div class="card-body typography-tailwind">
                                    <?php echo $is_business ? wp_kses_post(wpautop($description)) : apply_filters('the_content', $description); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Business Services Section -->
                        <?php if ($is_business) :
                            $services = $business_profile->get_services();
                            if (!empty($services)) : ?>
                                <div id="services" class="card-tailwind vcard-section">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php _e('Our Services', 'vcard'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <?php foreach ($services as $service) : ?>
                                                <div class="card-tailwind interactive">
                                                    <?php if (!empty($service['image'])) : ?>
                                                        <div class="h-48 overflow-hidden">
                                                            <img src="<?php echo esc_url($service['image']); ?>" alt="<?php echo esc_attr($service['name']); ?>" class="w-full h-full object-cover">
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <h4 class="font-semibold text-lg text-gray-900 mb-2"><?php echo esc_html($service['name']); ?></h4>
                                                        <?php if (!empty($service['description'])) : ?>
                                                            <p class="text-gray-600 mb-3"><?php echo esc_html($service['description']); ?></p>
                                                        <?php endif; ?>
                                                        <div class="flex justify-between items-center">
                                                            <?php if (!empty($service['price'])) : ?>
                                                                <div class="text-lg font-semibold text-green-600"><?php echo esc_html($service['price']); ?></div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($service['category'])) : ?>
                                                                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full"><?php echo esc_html($service['category']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Business Products Section -->
                            <?php 
                            $products = $business_profile->get_products();
                            if (!empty($products)) : ?>
                                <div id="products" class="card-tailwind vcard-section">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php _e('Our Products', 'vcard'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                            <?php foreach ($products as $product) : ?>
                                                <div class="card-tailwind interactive">
                                                    <?php if (!empty($product['images']) && is_array($product['images'])) : ?>
                                                        <div class="relative h-48 overflow-hidden">
                                                            <img src="<?php echo esc_url($product['images'][0]); ?>" alt="<?php echo esc_attr($product['name']); ?>" class="w-full h-full object-cover">
                                                            <?php if (count($product['images']) > 1) : ?>
                                                                <div class="absolute top-2 right-2 bg-black/70 text-white px-2 py-1 rounded text-xs">
                                                                    <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                    <?php printf(__('%d photos', 'vcard'), count($product['images'])); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <h4 class="font-semibold text-lg text-gray-900 mb-2"><?php echo esc_html($product['name']); ?></h4>
                                                        <?php if (!empty($product['description'])) : ?>
                                                            <p class="text-gray-600 mb-3"><?php echo esc_html($product['description']); ?></p>
                                                        <?php endif; ?>
                                                        <div class="flex justify-between items-center">
                                                            <?php if (!empty($product['price'])) : ?>
                                                                <div class="text-lg font-semibold text-green-600"><?php echo esc_html($product['price']); ?></div>
                                                            <?php endif; ?>
                                                            <?php if (isset($product['in_stock'])) : ?>
                                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $product['in_stock'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                                    <?php echo $product['in_stock'] ? __('In Stock', 'vcard') : __('Out of Stock', 'vcard'); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Business Gallery Section -->
                            <?php 
                            $gallery = $business_profile->get_data('gallery');
                            if (!empty($gallery)) :
                                $gallery_items = is_string($gallery) ? json_decode($gallery, true) : $gallery;
                                if (is_array($gallery_items) && !empty($gallery_items)) : ?>
                                    <div id="gallery" class="card-tailwind vcard-section">
                                        <div class="card-header">
                                            <h3 class="card-title"><?php _e('Gallery', 'vcard'); ?></h3>
                                        </div>
                                        <div class="card-body">
                                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                                <?php foreach ($gallery_items as $item) : ?>
                                                    <div class="relative group cursor-pointer overflow-hidden rounded-lg">
                                                        <img src="<?php echo esc_url($item['thumbnail_url'] ?? $item['image_url']); ?>" 
                                                             alt="<?php echo esc_attr($item['title'] ?? ''); ?>"
                                                             class="w-full h-32 object-cover transition-transform duration-200 group-hover:scale-110"
                                                             data-full="<?php echo esc_url($item['image_url']); ?>">
                                                        <?php if (!empty($item['title'])) : ?>
                                                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                                <div class="absolute bottom-2 left-2 text-white text-sm font-medium"><?php echo esc_html($item['title']); ?></div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Business Hours Section -->
                            <?php 
                            $business_hours = $business_profile->get_formatted_business_hours();
                            if (!empty($business_hours)) : ?>
                                <div id="hours" class="card-tailwind vcard-section">
                                    <div class="card-header">
                                        <h3 class="card-title"><?php _e('Business Hours', 'vcard'); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="space-y-3">
                                            <?php foreach ($business_hours as $day => $schedule) : ?>
                                                <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
                                                    <span class="font-medium text-gray-900"><?php echo esc_html($schedule['label']); ?></span>
                                                    <span class="<?php echo $schedule['closed'] ? 'text-red-600' : 'text-green-600'; ?> font-medium">
                                                        <?php echo esc_html($schedule['status']); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Contact Form Section -->
                        <?php 
                        $contact_form_enabled = get_post_meta(get_the_ID(), '_vcard_contact_form_enabled', true);
                        $contact_form_title = get_post_meta(get_the_ID(), '_vcard_contact_form_title', true);
                        
                        if ($is_business && $contact_form_enabled !== '0') : ?>
                            <div id="contact-form" class="form-container-tailwind elevated vcard-section">
                                <div class="form-header">
                                    <h3 class="form-title"><?php echo esc_html($contact_form_title ?: __('Leave a Message', 'vcard')); ?></h3>
                                    <p class="form-subtitle"><?php _e('Get in touch with us. We\'d love to hear from you!', 'vcard'); ?></p>
                                </div>
                                
                                <form id="vcard-contact-form" class="contact-form" method="post" action="">
                                    <?php wp_nonce_field('vcard_contact_form', 'vcard_contact_nonce'); ?>
                                    <input type="hidden" name="profile_id" value="<?php echo get_the_ID(); ?>">
                                    
                                    <div class="form-grid two-column">
                                        <div class="form-group-tailwind">
                                            <label for="contact_name" class="form-label-tailwind required"><?php _e('Your Name', 'vcard'); ?></label>
                                            <input type="text" id="contact_name" name="contact_name" class="form-input-tailwind" required>
                                        </div>
                                        <div class="form-group-tailwind">
                                            <label for="contact_email" class="form-label-tailwind required"><?php _e('Your Email', 'vcard'); ?></label>
                                            <input type="email" id="contact_email" name="contact_email" class="form-input-tailwind" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-grid two-column">
                                        <div class="form-group-tailwind">
                                            <label for="contact_phone" class="form-label-tailwind"><?php _e('Your Phone', 'vcard'); ?></label>
                                            <input type="tel" id="contact_phone" name="contact_phone" class="form-input-tailwind">
                                        </div>
                                        <div class="form-group-tailwind">
                                            <label for="contact_subject" class="form-label-tailwind"><?php _e('Subject', 'vcard'); ?></label>
                                            <input type="text" id="contact_subject" name="contact_subject" class="form-input-tailwind">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group-tailwind">
                                        <label for="contact_message" class="form-label-tailwind required"><?php _e('Message', 'vcard'); ?></label>
                                        <textarea id="contact_message" name="contact_message" rows="5" class="form-textarea-tailwind" required placeholder="<?php esc_attr_e('Tell us about your inquiry...', 'vcard'); ?>"></textarea>
                                    </div>
                                    
                                    <!-- Honeypot field for spam protection -->
                                    <div class="honeypot-field" style="display: none;">
                                        <label for="website_url"><?php _e('Website', 'vcard'); ?></label>
                                        <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
                                    </div>
                                    
                                    <div class="form-actions center">
                                        <button type="submit" class="btn-tailwind-primary relative">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                            </svg>
                                            <span class="btn-text"><?php _e('Send Message', 'vcard'); ?></span>
                                            <div class="btn-loading-spinner" style="display: none;">
                                                <div class="loading-spinner"></div>
                                            </div>
                                        </button>
                                    </div>
                                    
                                    <div class="form-messages mt-4"></div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Contact Information Section -->
                        <div id="contact" class="card-tailwind vcard-section">
                            <div class="card-header">
                                <h3 class="card-title"><?php _e('Contact Information', 'vcard'); ?></h3>
                            </div>
                            <div class="card-body space-y-4">
                                <?php 
                                $contact_fields = array(
                                    'phone' => array('label' => __('Phone', 'vcard'), 'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'),
                                    'secondary_phone' => array('label' => __('Secondary Phone', 'vcard'), 'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'),
                                    'whatsapp' => array('label' => __('WhatsApp', 'vcard'), 'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'),
                                    'email' => array('label' => __('Email', 'vcard'), 'icon' => 'M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'),
                                    'website' => array('label' => __('Website', 'vcard'), 'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9'),
                                );
                                
                                foreach ($contact_fields as $field => $config) :
                                    $value = $business_profile->get_data($field);
                                    if ($value) : ?>
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                            <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $config['icon']; ?>"/>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium text-gray-900"><?php echo $config['label']; ?></div>
                                                <div class="text-sm text-gray-600 truncate">
                                                    <?php if ($field === 'email') : ?>
                                                        <a href="mailto:<?php echo esc_attr($value); ?>" class="hover:text-blue-600"><?php echo esc_html($value); ?></a>
                                                    <?php elseif ($field === 'website') : ?>
                                                        <a href="<?php echo esc_url($value); ?>" target="_blank" rel="noopener" class="hover:text-blue-600"><?php echo esc_html($value); ?></a>
                                                    <?php elseif (in_array($field, array('phone', 'secondary_phone'))) : ?>
                                                        <a href="tel:<?php echo esc_attr($value); ?>" class="hover:text-blue-600"><?php echo esc_html($value); ?></a>
                                                    <?php elseif ($field === 'whatsapp') : ?>
                                                        <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^\d+]/', '', $value)); ?>" target="_blank" rel="noopener" class="hover:text-blue-600"><?php echo esc_html($value); ?></a>
                                                    <?php else : ?>
                                                        <?php echo esc_html($value); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif;
                                endforeach; ?>
                                
                                <!-- Social Media Links -->
                                <?php 
                                $social_links = $business_profile->get_social_media_links();
                                if (!empty($social_links)) : ?>
                                    <div class="pt-4 border-t border-gray-200">
                                        <h4 class="text-sm font-medium text-gray-900 mb-3"><?php _e('Follow Us', 'vcard'); ?></h4>
                                        <div class="flex flex-wrap gap-2">
                                            <?php 
                                            $social_colors = array(
                                                'facebook' => 'bg-blue-600 hover:bg-blue-700',
                                                'instagram' => 'bg-pink-600 hover:bg-pink-700',
                                                'linkedin' => 'bg-blue-700 hover:bg-blue-800',
                                                'twitter' => 'bg-blue-400 hover:bg-blue-500',
                                                'youtube' => 'bg-red-600 hover:bg-red-700',
                                                'tiktok' => 'bg-gray-900 hover:bg-black'
                                            );
                                            
                                            foreach ($social_links as $platform => $url) : 
                                                $color = $social_colors[$platform] ?? 'bg-gray-600 hover:bg-gray-700';
                                            ?>
                                                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" 
                                                   class="inline-flex items-center justify-center w-10 h-10 <?php echo $color; ?> text-white rounded-full transition-colors duration-200">
                                                    <span class="sr-only"><?php echo ucfirst($platform); ?></span>
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                                        <!-- Social media icons would go here -->
                                                        <circle cx="12" cy="12" r="10"/>
                                                    </svg>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Address Section -->
                        <?php 
                        $address_fields = array(
                            'address' => $business_profile->get_data('address'),
                            'city' => $business_profile->get_data('city'),
                            'state' => $business_profile->get_data('state'),
                            'zip_code' => $business_profile->get_data('zip_code'),
                            'country' => $business_profile->get_data('country'),
                        );
                        
                        $has_address = array_filter($address_fields);
                        if ($has_address) : ?>
                            <div class="card-tailwind">
                                <div class="card-header">
                                    <h3 class="card-title flex items-center gap-2">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <?php _e('Address', 'vcard'); ?>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="space-y-1 text-gray-600">
                                        <?php if ($address_fields['address']) : ?>
                                            <div><?php echo esc_html($address_fields['address']); ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <?php echo esc_html($address_fields['city']); ?>
                                            <?php if ($address_fields['city'] && $address_fields['state']) echo ', '; ?>
                                            <?php echo esc_html($address_fields['state']); ?>
                                            <?php if ($address_fields['zip_code']) echo ' ' . esc_html($address_fields['zip_code']); ?>
                                        </div>
                                        <?php if ($address_fields['country']) : ?>
                                            <div><?php echo esc_html($address_fields['country']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Map Integration (if coordinates available) -->
                                    <?php 
                                    $latitude = $business_profile->get_data('latitude');
                                    $longitude = $business_profile->get_data('longitude');
                                    if ($latitude && $longitude) : ?>
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <a href="https://maps.google.com/?q=<?php echo esc_attr($latitude); ?>,<?php echo esc_attr($longitude); ?>" 
                                               target="_blank" rel="noopener" 
                                               class="btn-tailwind-outline btn-tailwind-sm w-full">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                </svg>
                                                <?php _e('View on Map', 'vcard'); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="card-tailwind">
                            <div class="card-body space-y-3">
                                <!-- Contact Management Buttons (for all users) -->
                                <button class="btn-tailwind-primary w-full vcard-save-contact-btn" data-profile-id="<?php echo get_the_ID(); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                                    </svg>
                                    <?php _e('Save Contact', 'vcard'); ?>
                                </button>
                                
                                <button class="btn-tailwind-outline w-full vcard-view-contacts-btn">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <?php _e('My Contacts', 'vcard'); ?>
                                    <span class="vcard-contact-count bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full ml-auto" style="display: none;">0</span>
                                </button>
                                
                                <div class="grid grid-cols-2 gap-3">
                                    <button class="btn-tailwind-success vcard-download-btn" data-profile-id="<?php echo get_the_ID(); ?>" data-format="vcf">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <?php _e('Download', 'vcard'); ?>
                                    </button>
                                    
                                    <button class="btn-tailwind-secondary vcard-share-btn" onclick="shareProfile()">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
                                        </svg>
                                        <?php _e('Share', 'vcard'); ?>
                                    </button>
                                </div>
                                
                                <?php if ($is_business) : ?>
                                    <button class="btn-tailwind-outline w-full vcard-qr-btn" data-profile-id="<?php echo get_the_ID(); ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                        </svg>
                                        <?php _e('QR Code', 'vcard'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</div>

</div> <!-- Close vcard-modern-wrapper -->

<?php get_footer(); ?>
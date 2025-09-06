<?php
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

<div class="vcard-single-container vcard-template-<?php echo esc_attr($template_name); ?> <?php echo $is_business ? 'vcard-business-profile' : 'vcard-personal-profile'; ?>">
    <?php while (have_posts()) : the_post(); ?>
        <article class="vcard-single">
            <!-- Profile Header Section -->
            <div class="vcard-header">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="vcard-photo">
                        <?php the_post_thumbnail('medium'); ?>
                    </div>
                <?php endif; ?>
                
                <div class="vcard-basic-info">
                    <h1 class="vcard-name">
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
                        <p class="vcard-tagline"><?php echo esc_html($business_tagline); ?></p>
                    <?php elseif ($job_title || $company) : ?>
                        <p class="vcard-title">
                            <?php echo esc_html($job_title); ?>
                            <?php if ($job_title && $company) echo ' at '; ?>
                            <?php echo esc_html($company); ?>
                        </p>
                    <?php endif; ?>
                    
                    <!-- Profile View Counter -->
                    <div class="vcard-stats">
                        <span class="profile-views">
                            <i class="fas fa-eye"></i>
                            <?php printf(__('%d views', 'vcard'), $current_views + 1); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="vcard-content">
                <!-- Business Description or Personal Bio -->
                <?php 
                $description = '';
                if ($is_business) {
                    $description = $business_profile->get_data('business_description');
                } else {
                    $description = get_the_content();
                }
                
                if ($description) : ?>
                    <div class="vcard-description">
                        <h3><?php echo $is_business ? __('About Our Business', 'vcard') : __('About', 'vcard'); ?></h3>
                        <?php echo $is_business ? wp_kses_post(wpautop($description)) : apply_filters('the_content', $description); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Business Services Section -->
                <?php if ($is_business) :
                    $services = $business_profile->get_services();
                    if (!empty($services)) : ?>
                        <div class="vcard-services">
                            <h3><?php _e('Our Services', 'vcard'); ?></h3>
                            <div class="services-grid">
                                <?php foreach ($services as $service) : ?>
                                    <div class="service-item">
                                        <?php if (!empty($service['image'])) : ?>
                                            <div class="service-image">
                                                <img src="<?php echo esc_url($service['image']); ?>" alt="<?php echo esc_attr($service['name']); ?>">
                                            </div>
                                        <?php endif; ?>
                                        <div class="service-content">
                                            <h4 class="service-name"><?php echo esc_html($service['name']); ?></h4>
                                            <?php if (!empty($service['description'])) : ?>
                                                <p class="service-description"><?php echo esc_html($service['description']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($service['price'])) : ?>
                                                <div class="service-price"><?php echo esc_html($service['price']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($service['category'])) : ?>
                                                <div class="service-category"><?php echo esc_html($service['category']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Business Products Section -->
                    <?php 
                    $products = $business_profile->get_products();
                    if (!empty($products)) : ?>
                        <div class="vcard-products">
                            <h3><?php _e('Our Products', 'vcard'); ?></h3>
                            <div class="products-grid">
                                <?php foreach ($products as $product) : ?>
                                    <div class="product-item">
                                        <?php if (!empty($product['images']) && is_array($product['images'])) : ?>
                                            <div class="product-images">
                                                <img src="<?php echo esc_url($product['images'][0]); ?>" alt="<?php echo esc_attr($product['name']); ?>">
                                                <?php if (count($product['images']) > 1) : ?>
                                                    <div class="product-gallery-indicator">
                                                        <i class="fas fa-images"></i>
                                                        <?php printf(__('%d photos', 'vcard'), count($product['images'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="product-content">
                                            <h4 class="product-name"><?php echo esc_html($product['name']); ?></h4>
                                            <?php if (!empty($product['description'])) : ?>
                                                <p class="product-description"><?php echo esc_html($product['description']); ?></p>
                                            <?php endif; ?>
                                            <div class="product-details">
                                                <?php if (!empty($product['price'])) : ?>
                                                    <div class="product-price"><?php echo esc_html($product['price']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($product['category'])) : ?>
                                                    <div class="product-category"><?php echo esc_html($product['category']); ?></div>
                                                <?php endif; ?>
                                                <?php if (isset($product['in_stock'])) : ?>
                                                    <div class="product-stock <?php echo $product['in_stock'] ? 'in-stock' : 'out-of-stock'; ?>">
                                                        <?php echo $product['in_stock'] ? __('In Stock', 'vcard') : __('Out of Stock', 'vcard'); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Business Gallery Section -->
                    <?php 
                    $gallery = $business_profile->get_data('gallery');
                    if (!empty($gallery)) :
                        $gallery_items = is_string($gallery) ? json_decode($gallery, true) : $gallery;
                        if (is_array($gallery_items) && !empty($gallery_items)) : ?>
                            <div class="vcard-gallery">
                                <h3><?php _e('Gallery', 'vcard'); ?></h3>
                                <div class="gallery-grid">
                                    <?php foreach ($gallery_items as $item) : ?>
                                        <div class="gallery-item">
                                            <img src="<?php echo esc_url($item['thumbnail_url'] ?? $item['image_url']); ?>" 
                                                 alt="<?php echo esc_attr($item['title'] ?? ''); ?>"
                                                 data-full="<?php echo esc_url($item['image_url']); ?>">
                                            <?php if (!empty($item['title'])) : ?>
                                                <div class="gallery-item-title"><?php echo esc_html($item['title']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Business Hours Section -->
                    <?php 
                    $business_hours = $business_profile->get_formatted_business_hours();
                    if (!empty($business_hours)) : ?>
                        <div class="vcard-business-hours">
                            <h3><?php _e('Business Hours', 'vcard'); ?></h3>
                            <div class="business-hours-list">
                                <?php foreach ($business_hours as $day => $schedule) : ?>
                                    <div class="business-hours-item">
                                        <span class="day-label"><?php echo esc_html($schedule['label']); ?></span>
                                        <span class="hours-status <?php echo $schedule['closed'] ? 'closed' : 'open'; ?>">
                                            <?php echo esc_html($schedule['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Contact Information Section -->
                <div class="vcard-contact-info">
                    <h3><?php _e('Contact Information', 'vcard'); ?></h3>
                    
                    <?php 
                    $contact_fields = array(
                        'phone' => array('label' => __('Phone', 'vcard'), 'icon' => 'fas fa-phone'),
                        'secondary_phone' => array('label' => __('Secondary Phone', 'vcard'), 'icon' => 'fas fa-phone-alt'),
                        'whatsapp' => array('label' => __('WhatsApp', 'vcard'), 'icon' => 'fab fa-whatsapp'),
                        'email' => array('label' => __('Email', 'vcard'), 'icon' => 'fas fa-envelope'),
                        'website' => array('label' => __('Website', 'vcard'), 'icon' => 'fas fa-globe'),
                    );
                    
                    foreach ($contact_fields as $field => $config) :
                        $value = $business_profile->get_data($field);
                        if ($value) : ?>
                            <div class="vcard-contact-item">
                                <i class="<?php echo esc_attr($config['icon']); ?>"></i>
                                <div class="contact-details">
                                    <strong><?php echo $config['label']; ?>:</strong>
                                    <?php if ($field === 'email') : ?>
                                        <a href="mailto:<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?></a>
                                    <?php elseif ($field === 'website') : ?>
                                        <a href="<?php echo esc_url($value); ?>" target="_blank" rel="noopener"><?php echo esc_html($value); ?></a>
                                    <?php elseif (in_array($field, array('phone', 'secondary_phone'))) : ?>
                                        <a href="tel:<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?></a>
                                    <?php elseif ($field === 'whatsapp') : ?>
                                        <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^\d+]/', '', $value)); ?>" target="_blank" rel="noopener"><?php echo esc_html($value); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html($value); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif;
                    endforeach; ?>
                    
                    <!-- Social Media Links -->
                    <?php 
                    $social_links = $business_profile->get_social_media_links();
                    if (!empty($social_links)) : ?>
                        <div class="vcard-social-media">
                            <h4><?php _e('Follow Us', 'vcard'); ?></h4>
                            <div class="social-links">
                                <?php 
                                $social_icons = array(
                                    'facebook' => 'fab fa-facebook-f',
                                    'instagram' => 'fab fa-instagram',
                                    'linkedin' => 'fab fa-linkedin-in',
                                    'twitter' => 'fab fa-twitter',
                                    'youtube' => 'fab fa-youtube',
                                    'tiktok' => 'fab fa-tiktok'
                                );
                                
                                foreach ($social_links as $platform => $url) : 
                                    $icon = $social_icons[$platform] ?? 'fas fa-link';
                                ?>
                                    <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="social-link social-<?php echo esc_attr($platform); ?>">
                                        <i class="<?php echo esc_attr($icon); ?>"></i>
                                        <span class="sr-only"><?php echo ucfirst($platform); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
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
                    <div class="vcard-address">
                        <h3><i class="fas fa-map-marker-alt"></i> <?php _e('Address', 'vcard'); ?></h3>
                        <div class="vcard-address-details">
                            <?php if ($address_fields['address']) : ?>
                                <div class="address-line"><?php echo esc_html($address_fields['address']); ?></div>
                            <?php endif; ?>
                            <div class="address-line">
                                <?php echo esc_html($address_fields['city']); ?>
                                <?php if ($address_fields['city'] && $address_fields['state']) echo ', '; ?>
                                <?php echo esc_html($address_fields['state']); ?>
                                <?php if ($address_fields['zip_code']) echo ' ' . esc_html($address_fields['zip_code']); ?>
                            </div>
                            <?php if ($address_fields['country']) : ?>
                                <div class="address-line"><?php echo esc_html($address_fields['country']); ?></div>
                            <?php endif; ?>
                            
                            <!-- Map Integration (if coordinates available) -->
                            <?php 
                            $latitude = $business_profile->get_data('latitude');
                            $longitude = $business_profile->get_data('longitude');
                            if ($latitude && $longitude) : ?>
                                <div class="address-map">
                                    <a href="https://maps.google.com/?q=<?php echo esc_attr($latitude); ?>,<?php echo esc_attr($longitude); ?>" 
                                       target="_blank" rel="noopener" class="map-link">
                                        <i class="fas fa-external-link-alt"></i>
                                        <?php _e('View on Map', 'vcard'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Contact Form Section -->
                <?php 
                $contact_form_enabled = get_post_meta(get_the_ID(), '_vcard_contact_form_enabled', true);
                $contact_form_title = get_post_meta(get_the_ID(), '_vcard_contact_form_title', true);
                
                if ($is_business && $contact_form_enabled !== '0') : ?>
                    <div class="vcard-contact-form">
                        <h3><?php echo esc_html($contact_form_title ?: __('Leave a Message', 'vcard')); ?></h3>
                        <form id="vcard-contact-form" class="contact-form" method="post" action="">
                            <?php wp_nonce_field('vcard_contact_form', 'vcard_contact_nonce'); ?>
                            <input type="hidden" name="profile_id" value="<?php echo get_the_ID(); ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contact_name"><?php _e('Your Name', 'vcard'); ?> <span class="required">*</span></label>
                                    <input type="text" id="contact_name" name="contact_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="contact_email"><?php _e('Your Email', 'vcard'); ?> <span class="required">*</span></label>
                                    <input type="email" id="contact_email" name="contact_email" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contact_phone"><?php _e('Your Phone', 'vcard'); ?></label>
                                    <input type="tel" id="contact_phone" name="contact_phone">
                                </div>
                                <div class="form-group">
                                    <label for="contact_subject"><?php _e('Subject', 'vcard'); ?></label>
                                    <input type="text" id="contact_subject" name="contact_subject">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_message"><?php _e('Message', 'vcard'); ?> <span class="required">*</span></label>
                                <textarea id="contact_message" name="contact_message" rows="5" required placeholder="<?php esc_attr_e('Tell us about your inquiry...', 'vcard'); ?>"></textarea>
                            </div>
                            
                            <!-- Honeypot field for spam protection -->
                            <div class="honeypot-field" style="display: none;">
                                <label for="website_url"><?php _e('Website', 'vcard'); ?></label>
                                <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="contact-submit-btn">
                                    <i class="fas fa-paper-plane"></i>
                                    <?php _e('Send Message', 'vcard'); ?>
                                </button>
                                <div class="form-loading" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <?php _e('Sending...', 'vcard'); ?>
                                </div>
                            </div>
                            
                            <div class="form-messages"></div>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="vcard-actions">
                    <div class="vcard-download-group">
                        <button class="vcard-download-btn" data-profile-id="<?php echo get_the_ID(); ?>" data-format="vcf">
                            <i class="fas fa-download"></i>
                            <?php _e('Download vCard', 'vcard'); ?>
                        </button>
                        <div class="vcard-download-options">
                            <button class="vcard-export-vcf" data-profile-id="<?php echo get_the_ID(); ?>">
                                <i class="fas fa-file-alt"></i>
                                <?php _e('VCF Format', 'vcard'); ?>
                            </button>
                            <button class="vcard-export-csv" data-profile-id="<?php echo get_the_ID(); ?>">
                                <i class="fas fa-table"></i>
                                <?php _e('CSV Format', 'vcard'); ?>
                            </button>
                        </div>
                    </div>
                    <button class="vcard-share-btn" onclick="shareProfile()">
                        <i class="fas fa-share-alt"></i>
                        <?php _e('Share Profile', 'vcard'); ?>
                    </button>
                    <?php if ($is_business) : ?>
                        <button class="vcard-qr-btn" onclick="generateQR()">
                            <i class="fas fa-qrcode"></i>
                            <?php _e('QR Code', 'vcard'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endwhile; ?>
</div>

<!-- QR Code Modal (for business profiles) -->
<?php if ($is_business) : ?>
<div id="qr-modal" class="vcard-modal" style="display: none;">
    <div class="modal-content">
        <span class="close-modal" onclick="closeQRModal()">&times;</span>
        <h3><?php _e('QR Code for Profile', 'vcard'); ?></h3>
        <div id="qr-code-container"></div>
        <p><?php _e('Scan this QR code to quickly access this business profile', 'vcard'); ?></p>
    </div>
</div>
<?php endif; ?>

<script>
// Legacy function for backward compatibility
function downloadVCard() {
    // Use the enhanced export system
    if (typeof VCardExport !== 'undefined') {
        VCardExport.exportFormat('vcf');
    } else {
        // Fallback to basic vCard generation
        generateBasicVCard();
    }
}

// Basic vCard generation fallback
function generateBasicVCard() {
    <?php 
    $vcard_data = $business_profile->get_vcard_export_data();
    ?>
    
    // Create vCard 4.0 data with business information
    var vcard = "BEGIN:VCARD\nVERSION:4.0\n";
    
    <?php if (!empty($vcard_data['fn'])) : ?>
    vcard += "FN:<?php echo esc_js($vcard_data['fn']); ?>\n";
    <?php endif; ?>
    
    <?php if (!empty($vcard_data['org'])) : ?>
    vcard += "ORG:<?php echo esc_js($vcard_data['org']); ?>\n";
    <?php endif; ?>
    
    <?php if (!empty($vcard_data['title'])) : ?>
    vcard += "TITLE:<?php echo esc_js($vcard_data['title']); ?>\n";
    <?php endif; ?>
    
    <?php if (!empty($vcard_data['tel_work'])) : ?>
    vcard += "TEL;TYPE=work,voice:<?php echo esc_js($vcard_data['tel_work']); ?>\n";
    <?php endif; ?>
    
    <?php if (!empty($vcard_data['tel_cell'])) : ?>
    vcard += "TEL;TYPE=work,cell:<?php echo esc_js($vcard_data['tel_cell']); ?>\n";
    <?php endif; ?>
    
    <?php if (!empty($vcard_data['email'])) : ?>
    vcard += "EMAIL;TYPE=work:<?php echo esc_js($vcard_data['email']); ?>\n";
    <?php endif; ?>
    
    <?php if (!empty($vcard_data['url'])) : ?>
    vcard += "URL:<?php echo esc_js($vcard_data['url']); ?>\n";
    <?php endif; ?>
    
    <?php if (!empty($vcard_data['adr'])) : ?>
    vcard += "ADR;TYPE=work:;;<?php echo esc_js(implode(';', $vcard_data['adr'])); ?>\n";
    <?php endif; ?>
    
    <?php if (!empty($vcard_data['note'])) : ?>
    vcard += "NOTE:<?php echo esc_js($vcard_data['note']); ?>\n";
    <?php endif; ?>
    
    // Add social media as extended properties
    <?php if (!empty($vcard_data['social_media'])) : 
        foreach ($vcard_data['social_media'] as $platform => $url) : ?>
    vcard += "X-SOCIALPROFILE;TYPE=<?php echo esc_js($platform); ?>:<?php echo esc_js($url); ?>\n";
        <?php endforeach; 
    endif; ?>
    
    // Add revision timestamp
    vcard += "REV:" + new Date().toISOString().replace(/[-:]/g, '').split('.')[0] + "Z\n";
    
    vcard += "END:VCARD";
    
    // Track download
    trackVCardDownload();
    
    // Create download
    var element = document.createElement('a');
    element.setAttribute('href', 'data:text/vcard;charset=utf-8,' + encodeURIComponent(vcard));
    
    <?php if ($is_business) : ?>
    element.setAttribute('download', '<?php echo esc_js(sanitize_file_name($business_profile->get_data('business_name') ?: 'business')); ?>.vcf');
    <?php else : ?>
    element.setAttribute('download', '<?php echo esc_js(sanitize_file_name(trim($business_profile->get_data('first_name') . '_' . $business_profile->get_data('last_name')))); ?>.vcf');
    <?php endif; ?>
    
    element.style.display = 'none';
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
}

function shareProfile() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo esc_js($is_business ? $business_profile->get_data('business_name') : trim($business_profile->get_data('first_name') . ' ' . $business_profile->get_data('last_name'))); ?>',
            text: '<?php echo esc_js($business_profile->get_data('business_tagline') ?: $business_profile->get_data('business_description') ?: 'Check out this profile'); ?>',
            url: window.location.href
        });
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(window.location.href).then(function() {
            alert('<?php _e('Profile URL copied to clipboard!', 'vcard'); ?>');
        });
    }
}

<?php if ($is_business) : ?>
function generateQR() {
    // Show QR modal
    document.getElementById('qr-modal').style.display = 'block';
    
    // Generate QR code (you would integrate with a QR code library here)
    var qrContainer = document.getElementById('qr-code-container');
    qrContainer.innerHTML = '<div class="qr-placeholder">QR Code would be generated here<br><small>URL: ' + window.location.href + '</small></div>';
    
    // Track QR generation
    trackQRGeneration();
}

function closeQRModal() {
    document.getElementById('qr-modal').style.display = 'none';
}
<?php endif; ?>

function trackVCardDownload() {
    // AJAX call to track download
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=track_vcard_download&post_id=<?php echo get_the_ID(); ?>&nonce=<?php echo wp_create_nonce('vcard_tracking'); ?>'
    });
}

<?php if ($is_business) : ?>
function trackQRGeneration() {
    // AJAX call to track QR generation
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=track_qr_generation&post_id=<?php echo get_the_ID(); ?>&nonce=<?php echo wp_create_nonce('vcard_tracking'); ?>'
    });
}
<?php endif; ?>

// Contact form handling
document.addEventListener('DOMContentLoaded', function() {
    // Gallery lightbox functionality
    const galleryItems = document.querySelectorAll('.gallery-item img');
    galleryItems.forEach(function(img) {
        img.addEventListener('click', function() {
            const fullUrl = this.getAttribute('data-full');
            if (fullUrl) {
                // Simple lightbox implementation
                const lightbox = document.createElement('div');
                lightbox.className = 'vcard-lightbox';
                lightbox.innerHTML = '<div class="lightbox-content"><img src="' + fullUrl + '"><span class="lightbox-close">&times;</span></div>';
                document.body.appendChild(lightbox);
                
                lightbox.addEventListener('click', function(e) {
                    if (e.target === lightbox || e.target.className === 'lightbox-close') {
                        document.body.removeChild(lightbox);
                    }
                });
            }
        });
    });
    
    // Contact form submission
    const contactForm = document.getElementById('vcard-contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = contactForm.querySelector('.contact-submit-btn');
            const loadingDiv = contactForm.querySelector('.form-loading');
            const messagesDiv = contactForm.querySelector('.form-messages');
            
            // Show loading state
            submitBtn.style.display = 'none';
            loadingDiv.style.display = 'block';
            messagesDiv.innerHTML = '';
            
            // Prepare form data
            const formData = new FormData(contactForm);
            formData.append('action', 'submit_vcard_contact_form');
            
            // Submit form via AJAX
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Hide loading state
                submitBtn.style.display = 'block';
                loadingDiv.style.display = 'none';
                
                if (data.success) {
                    messagesDiv.innerHTML = '<div class="form-success"><i class="fas fa-check-circle"></i> ' + data.data.message + '</div>';
                    contactForm.reset();
                } else {
                    messagesDiv.innerHTML = '<div class="form-error"><i class="fas fa-exclamation-triangle"></i> ' + data.data.message + '</div>';
                }
            })
            .catch(error => {
                // Hide loading state
                submitBtn.style.display = 'block';
                loadingDiv.style.display = 'none';
                messagesDiv.innerHTML = '<div class="form-error"><i class="fas fa-exclamation-triangle"></i> <?php _e('An error occurred. Please try again.', 'vcard'); ?></div>';
            });
        });
    }
});
</script>

<?php get_footer(); ?>
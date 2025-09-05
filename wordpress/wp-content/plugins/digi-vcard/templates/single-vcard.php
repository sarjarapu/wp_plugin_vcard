<?php
/**
 * Single vCard Template
 */

$post_id = $post_id ?? get_the_ID();
$post = get_post($post_id);

// Get vCard data
$job_title = get_vcard_meta($post_id, 'job_title');
$company = get_vcard_meta($post_id, 'company');
$email = get_vcard_meta($post_id, 'email');
$phone = get_vcard_meta($post_id, 'phone');
$office_phone = get_vcard_meta($post_id, 'office_phone');
$address = get_vcard_meta($post_id, 'address');
?>

<div class="vcard-container">
    <!-- vCard Header -->
    <div class="vcard-header">
        <?php if (has_post_thumbnail($post_id)) : ?>
            <div class="vcard-profile-image">
                <?php echo get_the_post_thumbnail($post_id, 'medium'); ?>
            </div>
        <?php endif; ?>
        
        <h1 class="vcard-name"><?php echo esc_html(get_the_title($post_id)); ?></h1>
        
        <?php if ($job_title) : ?>
            <p class="vcard-job-title"><?php echo esc_html($job_title); ?></p>
        <?php endif; ?>
        
        <?php if ($company) : ?>
            <p class="vcard-company"><?php echo esc_html($company); ?></p>
        <?php endif; ?>
    </div>

    <div class="vcard-content">
        <!-- About Section -->
        <?php if ($post->post_content) : ?>
            <div class="vcard-section">
                <h2 class="vcard-section-title">About</h2>
                <div class="vcard-bio">
                    <?php echo wp_kses_post(wpautop($post->post_content)); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Contact Information -->
        <?php if ($email || $phone || $office_phone || $address) : ?>
            <div class="vcard-section">
                <h2 class="vcard-section-title">Contact Information</h2>
                <div class="vcard-contact-grid">
                    
                    <?php if ($email) : ?>
                        <div class="vcard-contact-item">
                            <div class="vcard-contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="vcard-contact-details">
                                <h4>Email</h4>
                                <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($phone) : ?>
                        <div class="vcard-contact-item">
                            <div class="vcard-contact-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="vcard-contact-details">
                                <h4>Phone</h4>
                                <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($office_phone) : ?>
                        <div class="vcard-contact-item">
                            <div class="vcard-contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="vcard-contact-details">
                                <h4>Office</h4>
                                <a href="tel:<?php echo esc_attr($office_phone); ?>"><?php echo esc_html($office_phone); ?></a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($address) : ?>
                        <div class="vcard-contact-item">
                            <div class="vcard-contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="vcard-contact-details">
                                <h4>Address</h4>
                                <div><?php echo wp_kses_post(nl2br($address)); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="vcard-actions">
        <button class="vcard-btn primary" onclick="downloadVCard()">
            <i class="fas fa-download"></i>
            <span>Add to Contacts</span>
        </button>
        <button class="vcard-btn secondary" onclick="shareVCard()">
            <i class="fas fa-share-alt"></i>
            <span>Share vCard</span>
        </button>
    </div>
</div>

<script>
// Initialize vCard data
document.addEventListener('DOMContentLoaded', function() {
    initVCard({
        name: <?php echo json_encode(get_the_title($post_id)); ?>,
        title: <?php echo json_encode($job_title); ?>,
        company: <?php echo json_encode($company); ?>,
        email: <?php echo json_encode($email); ?>,
        phone: <?php echo json_encode($phone); ?>,
        address: <?php echo json_encode(str_replace(array("\r\n", "\n", "\r"), ";", $address)); ?>,
        filename: <?php echo json_encode(sanitize_file_name(get_the_title($post_id)) . '.vcf'); ?>
    });
});
</script>
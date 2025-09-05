<?php
get_header(); ?>

<div class="vcard-single-container">
    <?php while (have_posts()) : the_post(); ?>
        <article class="vcard-single">
            <div class="vcard-header">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="vcard-photo">
                        <?php the_post_thumbnail('medium'); ?>
                    </div>
                <?php endif; ?>
                
                <div class="vcard-basic-info">
                    <h1 class="vcard-name">
                        <?php 
                        $first_name = get_post_meta(get_the_ID(), '_vcard_first_name', true);
                        $last_name = get_post_meta(get_the_ID(), '_vcard_last_name', true);
                        echo esc_html($first_name . ' ' . $last_name);
                        ?>
                    </h1>
                    
                    <?php 
                    $job_title = get_post_meta(get_the_ID(), '_vcard_job_title', true);
                    $company = get_post_meta(get_the_ID(), '_vcard_company', true);
                    if ($job_title || $company) : ?>
                        <p class="vcard-title">
                            <?php echo esc_html($job_title); ?>
                            <?php if ($job_title && $company) echo ' at '; ?>
                            <?php echo esc_html($company); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="vcard-content">
                <?php if (get_the_content()) : ?>
                    <div class="vcard-description">
                        <?php the_content(); ?>
                    </div>
                <?php endif; ?>
                
                <div class="vcard-contact-info">
                    <h3><?php _e('Contact Information', 'vcard'); ?></h3>
                    
                    <?php 
                    $contact_fields = array(
                        'phone' => __('Phone', 'vcard'),
                        'email' => __('Email', 'vcard'),
                        'website' => __('Website', 'vcard'),
                    );
                    
                    foreach ($contact_fields as $field => $label) :
                        $value = get_post_meta(get_the_ID(), '_vcard_' . $field, true);
                        if ($value) : ?>
                            <div class="vcard-contact-item">
                                <strong><?php echo $label; ?>:</strong>
                                <?php if ($field === 'email') : ?>
                                    <a href="mailto:<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?></a>
                                <?php elseif ($field === 'website') : ?>
                                    <a href="<?php echo esc_url($value); ?>" target="_blank"><?php echo esc_html($value); ?></a>
                                <?php elseif ($field === 'phone') : ?>
                                    <a href="tel:<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($value); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif;
                    endforeach; ?>
                </div>
                
                <?php 
                $address_fields = array(
                    'address' => get_post_meta(get_the_ID(), '_vcard_address', true),
                    'city' => get_post_meta(get_the_ID(), '_vcard_city', true),
                    'state' => get_post_meta(get_the_ID(), '_vcard_state', true),
                    'zip_code' => get_post_meta(get_the_ID(), '_vcard_zip_code', true),
                    'country' => get_post_meta(get_the_ID(), '_vcard_country', true),
                );
                
                $has_address = array_filter($address_fields);
                if ($has_address) : ?>
                    <div class="vcard-address">
                        <h3><?php _e('Address', 'vcard'); ?></h3>
                        <div class="vcard-address-details">
                            <?php if ($address_fields['address']) echo '<div>' . esc_html($address_fields['address']) . '</div>'; ?>
                            <div>
                                <?php echo esc_html($address_fields['city']); ?>
                                <?php if ($address_fields['city'] && $address_fields['state']) echo ', '; ?>
                                <?php echo esc_html($address_fields['state']); ?>
                                <?php if ($address_fields['zip_code']) echo ' ' . esc_html($address_fields['zip_code']); ?>
                            </div>
                            <?php if ($address_fields['country']) echo '<div>' . esc_html($address_fields['country']) . '</div>'; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="vcard-actions">
                    <button class="vcard-download-btn" onclick="downloadVCard()"><?php _e('Download vCard', 'vcard'); ?></button>
                </div>
            </div>
        </article>
    <?php endwhile; ?>
</div>

<script>
function downloadVCard() {
    // Create vCard data
    var vcard = "BEGIN:VCARD\nVERSION:3.0\n";
    vcard += "FN:<?php echo esc_js($first_name . ' ' . $last_name); ?>\n";
    vcard += "N:<?php echo esc_js($last_name); ?>;<?php echo esc_js($first_name); ?>;;;\n";
    <?php if ($company) : ?>vcard += "ORG:<?php echo esc_js($company); ?>\n";<?php endif; ?>
    <?php if ($job_title) : ?>vcard += "TITLE:<?php echo esc_js($job_title); ?>\n";<?php endif; ?>
    <?php if (get_post_meta(get_the_ID(), '_vcard_phone', true)) : ?>vcard += "TEL:<?php echo esc_js(get_post_meta(get_the_ID(), '_vcard_phone', true)); ?>\n";<?php endif; ?>
    <?php if (get_post_meta(get_the_ID(), '_vcard_email', true)) : ?>vcard += "EMAIL:<?php echo esc_js(get_post_meta(get_the_ID(), '_vcard_email', true)); ?>\n";<?php endif; ?>
    <?php if (get_post_meta(get_the_ID(), '_vcard_website', true)) : ?>vcard += "URL:<?php echo esc_js(get_post_meta(get_the_ID(), '_vcard_website', true)); ?>\n";<?php endif; ?>
    vcard += "END:VCARD";
    
    // Create download
    var element = document.createElement('a');
    element.setAttribute('href', 'data:text/vcard;charset=utf-8,' + encodeURIComponent(vcard));
    element.setAttribute('download', '<?php echo esc_js($first_name . '_' . $last_name); ?>.vcf');
    element.style.display = 'none';
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
}
</script>

<?php get_footer(); ?>
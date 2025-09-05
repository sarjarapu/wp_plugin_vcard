<?php
/**
 * Business Profile Display Template
 *
 * @package BizCard_Pro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get profile data from query
global $wp_query, $wpdb;

$profile = null;
$styling = null;

// Get profile based on query parameters
if ($wp_query->get('bizcard_profile_id')) {
    $profile_id = $wp_query->get('bizcard_profile_id');
    $table_name = BizCard_Pro_Database::get_table_name('profiles');
    $profile = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d AND is_public = 1",
        $profile_id
    ));
} elseif ($wp_query->get('bizcard_profile_name')) {
    $profile_name = $wp_query->get('bizcard_profile_name');
    $table_name = BizCard_Pro_Database::get_table_name('profiles');
    $profile = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE business_name = %s AND is_public = 1",
        $profile_name
    ));
}

// If no profile found, show 404
if (!$profile) {
    status_header(404);
    nocache_headers();
    include(get_query_template('404'));
    exit;
}

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
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($profile->business_name); ?> - Business Profile</title>
    
    <!-- SEO Meta Tags -->
    <?php if ($profile->meta_title): ?>
        <meta name="title" content="<?php echo esc_attr($profile->meta_title); ?>">
    <?php endif; ?>
    
    <?php if ($profile->meta_description): ?>
        <meta name="description" content="<?php echo esc_attr($profile->meta_description); ?>">
    <?php endif; ?>
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo esc_attr($profile->business_name); ?>">
    <meta property="og:description" content="<?php echo esc_attr($profile->business_tagline ?: $profile->business_description); ?>">
    <meta property="og:type" content="business.business">
    
    <?php if ($profile->business_logo): ?>
        <meta property="og:image" content="<?php echo esc_url($profile->business_logo); ?>">
    <?php endif; ?>
    
    <!-- Inline CSS for styling -->
    <style>
        :root {
            --primary-color: <?php echo esc_attr($primary_color); ?>;
            --secondary-color: <?php echo esc_attr($secondary_color); ?>;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8fafc;
        }
        
        .bizcard-profile-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }
        
        .business-logo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            overflow: hidden;
            border: 4px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
        }
        
        .business-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .business-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .business-tagline {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .profile-content {
            padding: 2rem;
        }
        
        .section {
            margin-bottom: 3rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-left: 1rem;
            position: relative;
        }
        
        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: var(--primary-color);
            border-radius: 2px;
        }
        
        .description {
            font-size: 1.1rem;
            line-height: 1.7;
            color: #4a5568;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
        }
        
        .contact-grid {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .contact-item:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .contact-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-right: 1rem;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .contact-details h4 {
            margin: 0 0 4px 0;
            color: #4a5568;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .contact-details a {
            color: #2d3748;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .contact-details a:hover {
            color: var(--primary-color);
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .social-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .actions {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 2rem;
            text-align: center;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 180px;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e0;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .profile-header {
                padding: 2rem 1rem;
            }
            
            .business-name {
                font-size: 2rem;
            }
            
            .profile-content {
                padding: 1.5rem;
            }
            
            .actions {
                flex-direction: column;
                padding: 1.5rem;
            }
            
            .btn {
                width: 100%;
                min-width: auto;
            }
        }
    </style>
    
    <?php wp_head(); ?>
</head>
<body class="bizcard-profile-<?php echo esc_attr($theme); ?>">
    <div class="bizcard-profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <?php if ($profile->business_logo): ?>
                <div class="business-logo">
                    <img src="<?php echo esc_url($profile->business_logo); ?>" alt="<?php echo esc_attr($profile->business_name); ?> Logo">
                </div>
            <?php endif; ?>
            
            <h1 class="business-name"><?php echo esc_html($profile->business_name); ?></h1>
            
            <?php if ($profile->business_tagline): ?>
                <p class="business-tagline"><?php echo esc_html($profile->business_tagline); ?></p>
            <?php endif; ?>
        </div>

        <div class="profile-content">
            <!-- About Section -->
            <?php if ($profile->business_description): ?>
                <div class="section">
                    <h2 class="section-title">About Us</h2>
                    <div class="description">
                        <?php echo wp_kses_post(wpautop($profile->business_description)); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Contact Information -->
            <?php if (!empty($contact_info)): ?>
                <div class="section">
                    <h2 class="section-title">Contact Information</h2>
                    <div class="contact-grid">
                        
                        <?php if (!empty($contact_info['email'])): ?>
                            <div class="contact-item">
                                <div class="contact-icon">📧</div>
                                <div class="contact-details">
                                    <h4>Email</h4>
                                    <a href="mailto:<?php echo esc_attr($contact_info['email']); ?>"><?php echo esc_html($contact_info['email']); ?></a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($contact_info['phone'])): ?>
                            <div class="contact-item">
                                <div class="contact-icon">📞</div>
                                <div class="contact-details">
                                    <h4>Phone</h4>
                                    <a href="tel:<?php echo esc_attr($contact_info['phone']); ?>"><?php echo esc_html($contact_info['phone']); ?></a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($contact_info['website'])): ?>
                            <div class="contact-item">
                                <div class="contact-icon">🌐</div>
                                <div class="contact-details">
                                    <h4>Website</h4>
                                    <a href="<?php echo esc_url($contact_info['website']); ?>" target="_blank"><?php echo esc_html($contact_info['website']); ?></a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($profile->address_street): ?>
                            <div class="contact-item">
                                <div class="contact-icon">📍</div>
                                <div class="contact-details">
                                    <h4>Address</h4>
                                    <div>
                                        <?php echo esc_html($profile->address_street); ?><br>
                                        <?php echo esc_html($profile->address_city . ', ' . $profile->address_state . ' ' . $profile->address_zip); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            <?php endif; ?>

            <!-- Social Media -->
            <?php if (!empty($social_media)): ?>
                <div class="section">
                    <h2 class="section-title">Follow Us</h2>
                    <div class="social-links">
                        <?php foreach ($social_media as $platform => $url): ?>
                            <?php if ($url): ?>
                                <a href="<?php echo esc_url($url); ?>" class="social-link" target="_blank">
                                    <?php echo ucfirst($platform); ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="actions">
            <button class="btn btn-primary" onclick="downloadVCard()">
                📇 Add to Contacts
            </button>
            <button class="btn btn-secondary" onclick="shareProfile()">
                📤 Share Profile
            </button>
        </div>
    </div>

    <script>
        // Simple vCard generation
        function downloadVCard() {
            const profile = <?php echo json_encode(array(
                'name' => $profile->business_name,
                'email' => $contact_info['email'] ?? '',
                'phone' => $contact_info['phone'] ?? '',
                'website' => $contact_info['website'] ?? '',
                'address' => $profile->address_street ?? ''
            )); ?>;
            
            let vcard = 'BEGIN:VCARD\n';
            vcard += 'VERSION:3.0\n';
            vcard += 'FN:' + profile.name + '\n';
            vcard += 'ORG:' + profile.name + '\n';
            
            if (profile.email) vcard += 'EMAIL:' + profile.email + '\n';
            if (profile.phone) vcard += 'TEL:' + profile.phone + '\n';
            if (profile.website) vcard += 'URL:' + profile.website + '\n';
            if (profile.address) vcard += 'ADR:;;' + profile.address + ';;;\n';
            
            vcard += 'END:VCARD';

            const blob = new Blob([vcard], { type: 'text/vcard' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = profile.name.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '.vcf';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        function shareProfile() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo esc_js($profile->business_name); ?>',
                    text: 'Check out this business profile',
                    url: window.location.href
                });
            } else {
                // Fallback - copy URL to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Profile URL copied to clipboard!');
                }).catch(() => {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = window.location.href;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    alert('Profile URL copied to clipboard!');
                });
            }
        }
    </script>

    <?php wp_footer(); ?>
</body>
</html>
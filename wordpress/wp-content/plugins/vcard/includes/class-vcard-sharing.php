<?php
/**
 * vCard Sharing Class
 * 
 * Handles QR code generation, social media sharing, URL shortening, and sharing analytics
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_Sharing {
    
    /**
     * Business profile instance
     * @var VCard_Business_Profile
     */
    private $business_profile;
    
    /**
     * Profile URL
     * @var string
     */
    private $profile_url;
    
    /**
     * Constructor
     * 
     * @param VCard_Business_Profile $business_profile
     */
    public function __construct($business_profile) {
        $this->business_profile = $business_profile;
        $this->profile_url = get_permalink($business_profile->get_data('post_id'));
    }
    
    /**
     * Generate QR code for profile
     * 
     * @param array $options QR code customization options
     * @return array QR code data
     */
    public function generate_qr_code($options = array()) {
        $defaults = array(
            'size' => 300,
            'format' => 'png',
            'error_correction' => 'M',
            'margin' => 4,
            'foreground_color' => '000000',
            'background_color' => 'FFFFFF',
            'logo' => null,
            'logo_size' => 60
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Use Google Charts API for QR code generation (fallback)
        $qr_url = $this->generate_qr_with_google_charts($options);
        
        // Try to use a more advanced QR library if available
        if (class_exists('QRCode')) {
            $qr_url = $this->generate_qr_with_library($options);
        }
        
        // Track QR generation
        $this->track_qr_generation();
        
        return array(
            'url' => $qr_url,
            'profile_url' => $this->profile_url,
            'options' => $options,
            'download_url' => $this->get_qr_download_url($options)
        );
    }
    
    /**
     * Generate QR code using Google Charts API
     * 
     * @param array $options
     * @return string
     */
    private function generate_qr_with_google_charts($options) {
        $base_url = 'https://chart.googleapis.com/chart';
        
        $params = array(
            'chs' => $options['size'] . 'x' . $options['size'],
            'cht' => 'qr',
            'chl' => urlencode($this->profile_url),
            'choe' => 'UTF-8',
            'chld' => $options['error_correction'] . '|' . $options['margin']
        );
        
        // Add colors if not default
        if ($options['foreground_color'] !== '000000' || $options['background_color'] !== 'FFFFFF') {
            $params['chco'] = $options['foreground_color'] . ',' . $options['background_color'];
        }
        
        return $base_url . '?' . http_build_query($params);
    }
    
    /**
     * Generate QR code with advanced library (if available)
     * 
     * @param array $options
     * @return string
     */
    private function generate_qr_with_library($options) {
        // This would use a more advanced QR code library
        // For now, fallback to Google Charts
        return $this->generate_qr_with_google_charts($options);
    }
    
    /**
     * Get QR code download URL
     * 
     * @param array $options
     * @return string
     */
    private function get_qr_download_url($options) {
        return add_query_arg(array(
            'action' => 'download_qr_code',
            'profile_id' => $this->business_profile->get_data('post_id'),
            'size' => $options['size'],
            'format' => $options['format'],
            'nonce' => wp_create_nonce('vcard_qr_download')
        ), admin_url('admin-ajax.php'));
    }
    
    /**
     * Generate social media sharing links
     * 
     * @return array
     */
    public function get_social_sharing_links() {
        $profile_title = $this->get_profile_title();
        $profile_description = $this->get_profile_description();
        $encoded_url = urlencode($this->profile_url);
        $encoded_title = urlencode($profile_title);
        $encoded_description = urlencode($profile_description);
        
        $sharing_links = array(
            'facebook' => array(
                'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url,
                'label' => __('Share on Facebook', 'vcard'),
                'icon' => 'fab fa-facebook-f',
                'color' => '#1877F2'
            ),
            'twitter' => array(
                'url' => 'https://twitter.com/intent/tweet?url=' . $encoded_url . '&text=' . $encoded_title,
                'label' => __('Share on Twitter', 'vcard'),
                'icon' => 'fab fa-twitter',
                'color' => '#1DA1F2'
            ),
            'linkedin' => array(
                'url' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encoded_url,
                'label' => __('Share on LinkedIn', 'vcard'),
                'icon' => 'fab fa-linkedin-in',
                'color' => '#0A66C2'
            ),
            'whatsapp' => array(
                'url' => 'https://wa.me/?text=' . $encoded_title . '%20' . $encoded_url,
                'label' => __('Share on WhatsApp', 'vcard'),
                'icon' => 'fab fa-whatsapp',
                'color' => '#25D366'
            ),
            'telegram' => array(
                'url' => 'https://t.me/share/url?url=' . $encoded_url . '&text=' . $encoded_title,
                'label' => __('Share on Telegram', 'vcard'),
                'icon' => 'fab fa-telegram-plane',
                'color' => '#0088CC'
            ),
            'email' => array(
                'url' => 'mailto:?subject=' . $encoded_title . '&body=' . $encoded_description . '%0A%0A' . $encoded_url,
                'label' => __('Share via Email', 'vcard'),
                'icon' => 'fas fa-envelope',
                'color' => '#6B7280'
            ),
            'copy' => array(
                'url' => 'javascript:void(0)',
                'label' => __('Copy Link', 'vcard'),
                'icon' => 'fas fa-copy',
                'color' => '#6B7280',
                'action' => 'copy-link'
            )
        );
        
        return apply_filters('vcard_social_sharing_links', $sharing_links, $this->business_profile);
    }
    
    /**
     * Generate short URL for profile
     * 
     * @return string
     */
    public function generate_short_url() {
        $post_id = $this->business_profile->get_data('post_id');
        
        // Check if short URL already exists
        $existing_short_url = get_post_meta($post_id, '_vcard_short_url', true);
        if ($existing_short_url) {
            return $existing_short_url;
        }
        
        // Generate new short URL
        $short_code = $this->generate_short_code();
        $short_url = home_url('/vc/' . $short_code);
        
        // Store short URL mapping
        update_post_meta($post_id, '_vcard_short_url', $short_url);
        update_post_meta($post_id, '_vcard_short_code', $short_code);
        
        // Store reverse mapping for redirects
        $this->store_short_url_mapping($short_code, $post_id);
        
        return $short_url;
    }
    
    /**
     * Generate unique short code
     * 
     * @return string
     */
    private function generate_short_code() {
        $post_id = $this->business_profile->get_data('post_id');
        
        // Try business name or personal name first
        if ($this->business_profile->is_business_profile()) {
            $base_name = $this->business_profile->get_data('business_name');
        } else {
            $base_name = trim($this->business_profile->get_data('first_name') . '-' . $this->business_profile->get_data('last_name'));
        }
        
        if ($base_name) {
            $short_code = sanitize_title($base_name);
            $short_code = substr($short_code, 0, 20); // Limit length
            
            // Check if code is available
            if (!$this->short_code_exists($short_code)) {
                return $short_code;
            }
            
            // Try with post ID suffix
            $short_code_with_id = $short_code . '-' . $post_id;
            if (!$this->short_code_exists($short_code_with_id)) {
                return $short_code_with_id;
            }
        }
        
        // Fallback to random code
        do {
            $short_code = $this->generate_random_code();
        } while ($this->short_code_exists($short_code));
        
        return $short_code;
    }
    
    /**
     * Generate random short code
     * 
     * @return string
     */
    private function generate_random_code() {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $code;
    }
    
    /**
     * Check if short code exists
     * 
     * @param string $short_code
     * @return bool
     */
    private function short_code_exists($short_code) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_vcard_short_code' AND meta_value = %s",
            $short_code
        ));
        
        return $count > 0;
    }
    
    /**
     * Store short URL mapping for redirects
     * 
     * @param string $short_code
     * @param int $post_id
     */
    private function store_short_url_mapping($short_code, $post_id) {
        // Store in options table for quick lookup
        $mappings = get_option('vcard_short_url_mappings', array());
        $mappings[$short_code] = $post_id;
        update_option('vcard_short_url_mappings', $mappings);
    }
    
    /**
     * Track sharing event
     * 
     * @param string $platform
     * @param array $additional_data
     */
    public function track_share($platform, $additional_data = array()) {
        $post_id = $this->business_profile->get_data('post_id');
        
        // Update share count
        $current_shares = (int) get_post_meta($post_id, '_vcard_shares', true);
        update_post_meta($post_id, '_vcard_shares', $current_shares + 1);
        
        // Track platform-specific shares
        $platform_key = '_vcard_shares_' . sanitize_key($platform);
        $platform_shares = (int) get_post_meta($post_id, $platform_key, true);
        update_post_meta($post_id, $platform_key, $platform_shares + 1);
        
        // Store detailed analytics if analytics table exists
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'vcard_analytics';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") === $analytics_table) {
            $wpdb->insert(
                $analytics_table,
                array(
                    'profile_id' => $post_id,
                    'event_type' => 'share',
                    'event_data' => wp_json_encode(array_merge(array(
                        'platform' => $platform,
                        'url' => $this->profile_url,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                        'ip_address' => $this->get_client_ip()
                    ), $additional_data)),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s')
            );
        }
        
        do_action('vcard_share_tracked', $post_id, $platform, $additional_data);
    }
    
    /**
     * Track QR code generation
     */
    private function track_qr_generation() {
        $post_id = $this->business_profile->get_data('post_id');
        
        // Update QR scan count
        $current_qr_scans = (int) get_post_meta($post_id, '_vcard_qr_scans', true);
        update_post_meta($post_id, '_vcard_qr_scans', $current_qr_scans + 1);
        
        do_action('vcard_qr_generated', $post_id);
    }
    
    /**
     * Track short URL click
     * 
     * @param string $short_code
     */
    public static function track_short_url_click($short_code) {
        $mappings = get_option('vcard_short_url_mappings', array());
        
        if (isset($mappings[$short_code])) {
            $post_id = $mappings[$short_code];
            
            // Update click count
            $click_key = '_vcard_short_url_clicks';
            $current_clicks = (int) get_post_meta($post_id, $click_key, true);
            update_post_meta($post_id, $click_key, $current_clicks + 1);
            
            // Store detailed analytics
            global $wpdb;
            $analytics_table = $wpdb->prefix . 'vcard_analytics';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") === $analytics_table) {
                $wpdb->insert(
                    $analytics_table,
                    array(
                        'profile_id' => $post_id,
                        'event_type' => 'short_url_click',
                        'event_data' => wp_json_encode(array(
                            'short_code' => $short_code,
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                            'ip_address' => self::get_client_ip_static(),
                            'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
                        )),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s')
                );
            }
            
            do_action('vcard_short_url_clicked', $post_id, $short_code);
        }
    }
    
    /**
     * Get sharing analytics for profile
     * 
     * @return array
     */
    public function get_sharing_analytics() {
        $post_id = $this->business_profile->get_data('post_id');
        
        $analytics = array(
            'total_shares' => (int) get_post_meta($post_id, '_vcard_shares', true),
            'qr_scans' => (int) get_post_meta($post_id, '_vcard_qr_scans', true),
            'short_url_clicks' => (int) get_post_meta($post_id, '_vcard_short_url_clicks', true),
            'platform_shares' => array()
        );
        
        // Get platform-specific shares
        $platforms = array('facebook', 'twitter', 'linkedin', 'whatsapp', 'telegram', 'email', 'copy');
        
        foreach ($platforms as $platform) {
            $platform_key = '_vcard_shares_' . $platform;
            $analytics['platform_shares'][$platform] = (int) get_post_meta($post_id, $platform_key, true);
        }
        
        return $analytics;
    }
    
    /**
     * Get profile title for sharing
     * 
     * @return string
     */
    private function get_profile_title() {
        if ($this->business_profile->is_business_profile()) {
            $title = $this->business_profile->get_data('business_name');
            $tagline = $this->business_profile->get_data('business_tagline');
            
            if ($tagline) {
                $title .= ' - ' . $tagline;
            }
        } else {
            $first_name = $this->business_profile->get_data('first_name');
            $last_name = $this->business_profile->get_data('last_name');
            $title = trim($first_name . ' ' . $last_name);
            
            $job_title = $this->business_profile->get_data('job_title');
            $company = $this->business_profile->get_data('company');
            
            if ($job_title && $company) {
                $title .= ' - ' . $job_title . ' at ' . $company;
            } elseif ($job_title) {
                $title .= ' - ' . $job_title;
            } elseif ($company) {
                $title .= ' - ' . $company;
            }
        }
        
        return $title ?: __('vCard Profile', 'vcard');
    }
    
    /**
     * Get profile description for sharing
     * 
     * @return string
     */
    private function get_profile_description() {
        $description = '';
        
        if ($this->business_profile->is_business_profile()) {
            $description = $this->business_profile->get_data('business_description');
            
            if (!$description) {
                $services = $this->business_profile->get_services();
                if (!empty($services)) {
                    $service_names = array_column($services, 'name');
                    $description = __('Services: ', 'vcard') . implode(', ', array_slice($service_names, 0, 3));
                    
                    if (count($service_names) > 3) {
                        $description .= __(' and more', 'vcard');
                    }
                }
            }
        }
        
        if (!$description) {
            $description = __('Check out this professional profile', 'vcard');
        }
        
        return wp_trim_words($description, 20);
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip() {
        return self::get_client_ip_static();
    }
    
    /**
     * Get client IP address (static version)
     * 
     * @return string
     */
    private static function get_client_ip_static() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Generate NFC data for profile
     * 
     * @return array
     */
    public function generate_nfc_data() {
        return array(
            'type' => 'url',
            'data' => $this->profile_url,
            'format' => 'text/plain',
            'instructions' => array(
                'android' => __('Use NFC Tools app to write this URL to an NFC tag', 'vcard'),
                'ios' => __('Use NFC TagInfo app to write this URL to an NFC tag', 'vcard')
            )
        );
    }
    
    /**
     * Get embeddable widget code
     * 
     * @param array $options
     * @return string
     */
    public function get_embed_code($options = array()) {
        $defaults = array(
            'width' => 300,
            'height' => 400,
            'theme' => 'light',
            'show_qr' => true,
            'show_contact_form' => false
        );
        
        $options = wp_parse_args($options, $defaults);
        $post_id = $this->business_profile->get_data('post_id');
        
        $embed_url = add_query_arg(array(
            'vcard_embed' => 1,
            'width' => $options['width'],
            'height' => $options['height'],
            'theme' => $options['theme'],
            'show_qr' => $options['show_qr'] ? 1 : 0,
            'show_contact_form' => $options['show_contact_form'] ? 1 : 0
        ), get_permalink($post_id));
        
        $embed_code = sprintf(
            '<iframe src="%s" width="%d" height="%d" frameborder="0" scrolling="auto" title="%s"></iframe>',
            esc_url($embed_url),
            intval($options['width']),
            intval($options['height']),
            esc_attr($this->get_profile_title())
        );
        
        return $embed_code;
    }
}
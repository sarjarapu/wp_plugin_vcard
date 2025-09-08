<?php
/**
 * VCard Database Helper Class
 * 
 * Handles all database operations for vCard profiles
 * 
 * @package VCard
 * @version 1.0.0
 */

namespace VCard;

class VCardDatabaseHelper {
    
    /**
     * Get vCard profile data by ID
     * 
     * @param int $profile_id
     * @return array|null
     */
    public static function getProfileData($profile_id) {
        $post = get_post($profile_id);
        
        if (!$post || $post->post_type !== 'vcard_profile') {
            return null;
        }
        
        // Get all meta data
        $meta_data = get_post_meta($profile_id);
        
        // Process meta data to remove array wrapping and decode JSON
        $processed_meta = [];
        foreach ($meta_data as $key => $value) {
            $clean_key = str_replace('_vcard_', '', $key);
            $processed_meta[$clean_key] = is_array($value) ? $value[0] : $value;
            
            // Decode JSON fields
            if (self::isJsonField($clean_key)) {
                $decoded = json_decode($processed_meta[$clean_key], true);
                $processed_meta[$clean_key] = $decoded ?: [];
            }
        }
        
        // Debug: Log processed meta data
        error_log('VCardDatabaseHelper - Processed Meta: ' . print_r($processed_meta, true));
        
        return [
            'id' => $profile_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'status' => $post->post_status,
            'date_created' => $post->post_date,
            'date_modified' => $post->post_modified,
            'thumbnail_id' => get_post_thumbnail_id($profile_id),
            'thumbnail_url' => get_the_post_thumbnail_url($profile_id, 'medium'),
            'meta' => $processed_meta
        ];
    }
    
    /**
     * Get formatted business profile data
     * 
     * @param int $profile_id
     * @return array
     */
    public static function getBusinessProfileData($profile_id) {
        $data = self::getProfileData($profile_id);
        
        if (!$data) {
            return [];
        }
        
        $meta = $data['meta'];
        
        // Debug: Check what's in meta array
        error_log('VCardDatabaseHelper - Meta keys available: ' . print_r(array_keys($meta), true));
        error_log('VCardDatabaseHelper - Contact fields: phone=' . ($meta['phone'] ?? 'NOT FOUND') . ', email=' . ($meta['email'] ?? 'NOT FOUND'));
        
        $result = [
            'basic_info' => [
                'business_name' => $meta['business_name'] ?? '',
                'business_tagline' => $meta['business_tagline'] ?? '',
                'business_description' => $meta['business_description'] ?? '',
                'first_name' => $meta['first_name'] ?? '',
                'last_name' => $meta['last_name'] ?? '',
                'job_title' => $meta['job_title'] ?? '',
                'company' => $meta['company'] ?? '',
            ],
            'contact_info' => [
                'phone' => $meta['phone'] ?? '',
                'secondary_phone' => $meta['secondary_phone'] ?? '',
                'whatsapp' => $meta['whatsapp'] ?? '',
                'email' => $meta['email'] ?? '',
                'website' => $meta['website'] ?? '',
            ],
            'address' => [
                'address' => $meta['address'] ?? '',
                'city' => $meta['city'] ?? '',
                'state' => $meta['state'] ?? '',
                'zip_code' => $meta['zip_code'] ?? '',
                'country' => $meta['country'] ?? '',
                'latitude' => $meta['latitude'] ?? '',
                'longitude' => $meta['longitude'] ?? '',
            ],
            'social_media' => [
                'facebook' => $meta['social_facebook'] ?? '',
                'instagram' => $meta['social_instagram'] ?? '',
                'linkedin' => $meta['social_linkedin'] ?? '',
                'twitter' => $meta['social_twitter'] ?? '',
                'youtube' => $meta['social_youtube'] ?? '',
                'tiktok' => $meta['social_tiktok'] ?? '',
            ],
            'business_data' => [
                'services' => $meta['services'] ?? [],
                'products' => $meta['products'] ?? [],
                'gallery' => $meta['gallery'] ?? [],
                'business_hours' => $meta['business_hours'] ?? [],
            ],
            'template_settings' => [
                'template_name' => $meta['template_name'] ?? 'default',
                'primary_color' => $meta['primary_color'] ?? '',
                'secondary_color' => $meta['secondary_color'] ?? '',
                'font_family' => $meta['font_family'] ?? '',
            ],
            'analytics' => [
                'profile_views' => (int) ($meta['profile_views'] ?? 0),
                'vcard_downloads' => (int) ($meta['vcard_downloads'] ?? 0),
                'qr_scans' => (int) ($meta['qr_scans'] ?? 0),
                'shares' => (int) ($meta['shares'] ?? 0),
                'contact_saves' => (int) ($meta['contact_saves'] ?? 0),
            ],
            'settings' => [
                'contact_form_enabled' => $meta['contact_form_enabled'] ?? '1',
                'contact_form_title' => $meta['contact_form_title'] ?? '',
            ],
            'raw_data' => $data
        ];
        
        // Add helper flags for template rendering
        $result['is_business'] = self::isBusinessProfile($result);
        $result['has_services'] = !empty($result['business_data']['services']);
        $result['has_products'] = !empty($result['business_data']['products']);
        $result['has_gallery'] = !empty($result['business_data']['gallery']);
        $result['has_business_hours'] = !empty($result['business_data']['business_hours']);
        $result['has_address'] = !empty($result['address']['address']) || !empty($result['address']['city']);
        $result['has_social_media'] = !empty(array_filter($result['social_media']));
        $result['profile_id'] = $profile_id;
        $result['thumbnail_url'] = get_the_post_thumbnail_url($profile_id, 'medium');
        $result['contact_form_nonce'] = wp_create_nonce('vcard_contact_form_' . $profile_id);
        
        // Preserve raw contact info and add formatted version
        $result['raw_contact_info'] = $result['contact_info']; // Keep raw data
        $result['contact_info'] = self::formatContactInfo($result['contact_info']); // Format for display
        
        return $result;
    }
    
    /**
     * Update profile view count
     * 
     * @param int $profile_id
     * @return bool
     */
    public static function incrementProfileViews($profile_id) {
        $current_views = (int) get_post_meta($profile_id, '_vcard_profile_views', true);
        return update_post_meta($profile_id, '_vcard_profile_views', $current_views + 1);
    }
    
    /**
     * Get formatted business hours
     * 
     * @param array $business_hours
     * @return array
     */
    public static function formatBusinessHours($business_hours) {
        if (empty($business_hours) || !is_array($business_hours)) {
            return [];
        }
        
        $days = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday', 
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday'
        ];
        
        $formatted = [];
        
        foreach ($days as $key => $label) {
            $day_data = $business_hours[$key] ?? [];
            
            if (empty($day_data) || ($day_data['closed'] ?? false)) {
                $formatted[$key] = [
                    'label' => $label,
                    'status' => 'Closed',
                    'closed' => true
                ];
            } else {
                $open_time = $day_data['open'] ?? '';
                $close_time = $day_data['close'] ?? '';
                
                $formatted[$key] = [
                    'label' => $label,
                    'status' => $open_time && $close_time ? "$open_time - $close_time" : 'Closed',
                    'closed' => !($open_time && $close_time),
                    'open_time' => $open_time,
                    'close_time' => $close_time
                ];
            }
        }
        
        return $formatted;
    }
    
    /**
     * Check if a field contains JSON data
     * 
     * @param string $field_name
     * @return bool
     */
    private static function isJsonField($field_name) {
        $json_fields = [
            'services',
            'products', 
            'gallery',
            'business_hours',
            'template_customizations',
            'vcard_config'
        ];
        
        return in_array($field_name, $json_fields);
    }
    
    /**
     * Get social media links with proper formatting
     * 
     * @param array $social_data
     * @return array
     */
    public static function formatSocialMediaLinks($social_data) {
        $formatted = [];
        
        foreach ($social_data as $platform => $url) {
            if (!empty($url)) {
                $formatted[$platform] = [
                    'url' => $url,
                    'platform' => ucfirst($platform),
                    'icon_class' => self::getSocialIconClass($platform)
                ];
            }
        }
        
        return $formatted;
    }
    
    /**
     * Get icon class for social media platform
     * 
     * @param string $platform
     * @return string
     */
    private static function getSocialIconClass($platform) {
        $icons = [
            'facebook' => 'fab fa-facebook-f',
            'instagram' => 'fab fa-instagram',
            'linkedin' => 'fab fa-linkedin-in',
            'twitter' => 'fab fa-twitter',
            'youtube' => 'fab fa-youtube',
            'tiktok' => 'fab fa-tiktok'
        ];
        
        return $icons[$platform] ?? 'fas fa-link';
    }
    
    /**
     * Check if profile is a business profile
     * 
     * @param array $profile_data
     * @return bool
     */
    public static function isBusinessProfile($profile_data) {
        // Check if we have the new structured data format
        if (isset($profile_data['basic_info'])) {
            $business_indicators = [
                'business_name' => $profile_data['basic_info']['business_name'] ?? '',
                'business_description' => $profile_data['basic_info']['business_description'] ?? '',
                'services' => $profile_data['business_data']['services'] ?? [],
                'products' => $profile_data['business_data']['products'] ?? [],
                'business_hours' => $profile_data['business_data']['business_hours'] ?? []
            ];
            
            foreach ($business_indicators as $value) {
                if (!empty($value)) {
                    return true;
                }
            }
        } else {
            // Fallback to old format
            $business_indicators = [
                'business_name',
                'business_description', 
                'services',
                'products',
                'business_hours'
            ];
            
            foreach ($business_indicators as $field) {
                if (!empty($profile_data['meta'][$field] ?? '')) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get contact information with proper formatting
     * 
     * @param array $contact_data
     * @return array
     */
    public static function formatContactInfo($contact_data) {
        $contact_fields = [
            'phone' => [
                'label' => 'Phone',
                'icon' => 'fas fa-phone',
                'type' => 'tel'
            ],
            'secondary_phone' => [
                'label' => 'Secondary Phone', 
                'icon' => 'fas fa-phone-alt',
                'type' => 'tel'
            ],
            'whatsapp' => [
                'label' => 'WhatsApp',
                'icon' => 'fab fa-whatsapp', 
                'type' => 'whatsapp'
            ],
            'email' => [
                'label' => 'Email',
                'icon' => 'fas fa-envelope',
                'type' => 'email'
            ],
            'website' => [
                'label' => 'Website',
                'icon' => 'fas fa-globe',
                'type' => 'url'
            ]
        ];
        
        $formatted = [];
        
        foreach ($contact_fields as $field => $config) {
            $value = $contact_data[$field] ?? '';
            if (!empty($value)) {
                $formatted[] = [
                    'field' => $field,
                    'label' => $config['label'],
                    'value' => $value,
                    'icon' => $config['icon'],
                    'type' => $config['type'],
                    'link' => self::generateContactLink($config['type'], $value)
                ];
            }
        }
        
        return $formatted;
    }
    
    /**
     * Generate appropriate link for contact type
     * 
     * @param string $type
     * @param string $value
     * @return string
     */
    private static function generateContactLink($type, $value) {
        switch ($type) {
            case 'tel':
                return 'tel:' . $value;
            case 'email':
                return 'mailto:' . $value;
            case 'whatsapp':
                $clean_number = preg_replace('/[^\d+]/', '', $value);
                return 'https://wa.me/' . $clean_number;
            case 'url':
                return esc_url($value);
            default:
                return $value;
        }
    }
}
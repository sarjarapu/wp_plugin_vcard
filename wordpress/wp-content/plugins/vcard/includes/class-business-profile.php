<?php
/**
 * Business Profile Class
 * 
 * Handles business profile data validation, management, and backward compatibility
 * with existing personal vCard functionality.
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_Business_Profile {
    
    /**
     * Post ID for the vCard profile
     * @var int
     */
    private $post_id;
    
    /**
     * Profile data cache
     * @var array
     */
    private $profile_data = array();
    
    /**
     * Validation errors
     * @var array
     */
    private $validation_errors = array();
    
    /**
     * Constructor
     * 
     * @param int $post_id The post ID of the vCard profile
     */
    public function __construct($post_id = null) {
        if ($post_id) {
            $this->post_id = $post_id;
            $this->load_profile_data();
        }
    }
    
    /**
     * Load profile data from post meta
     */
    private function load_profile_data() {
        if (!$this->post_id) {
            return;
        }
        
        // Load all vCard meta fields
        $meta_fields = $this->get_all_meta_fields();
        
        foreach ($meta_fields as $field) {
            $value = get_post_meta($this->post_id, '_vcard_' . $field, true);
            
            // Handle JSON fields
            if (in_array($field, $this->get_json_fields())) {
                $decoded = json_decode($value, true);
                $this->profile_data[$field] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : array();
            } else {
                $this->profile_data[$field] = $value;
            }
        }
        
        // Load social media fields separately (they have different prefix)
        $social_fields = $this->get_social_media_fields();
        foreach ($social_fields as $field) {
            $value = get_post_meta($this->post_id, '_vcard_social_' . $field, true);
            $this->profile_data['social_' . $field] = $value;
        }
    }
    
    /**
     * Get all meta field names
     * 
     * @return array
     */
    private function get_all_meta_fields() {
        return array_merge(
            $this->get_personal_fields(),
            $this->get_business_fields(),
            $this->get_contact_fields(),
            $this->get_template_fields(),
            $this->get_system_fields(),
            $this->get_json_fields()
        );
    }
    
    /**
     * Get personal vCard fields (backward compatibility)
     * 
     * @return array
     */
    private function get_personal_fields() {
        return array(
            'first_name', 'last_name', 'company', 'job_title'
        );
    }
    
    /**
     * Get business-specific fields
     * 
     * @return array
     */
    private function get_business_fields() {
        return array(
            'business_name', 'business_tagline', 'business_description',
            'business_logo', 'cover_image', 'business_hours'
        );
    }
    
    /**
     * Get contact information fields
     * 
     * @return array
     */
    private function get_contact_fields() {
        return array(
            'phone', 'secondary_phone', 'whatsapp', 'email', 'website',
            'address', 'city', 'state', 'zip_code', 'country',
            'latitude', 'longitude'
        );
    }
    
    /**
     * Get template and customization fields
     * 
     * @return array
     */
    private function get_template_fields() {
        return array(
            'template_name', 'template_customizations', 'primary_color',
            'secondary_color', 'font_family', 'layout_options'
        );
    }
    
    /**
     * Get system fields (analytics, subscription)
     * 
     * @return array
     */
    private function get_system_fields() {
        return array(
            'profile_views', 'vcard_downloads', 'qr_scans', 'shares',
            'subscription_plan', 'subscription_status', 'subscription_expires'
        );
    }
    
    /**
     * Get JSON fields that need special handling
     * 
     * @return array
     */
    private function get_json_fields() {
        return array(
            'services', 'products', 'gallery', 'vcard_config', 'business_hours'
        );
    }
    
    /**
     * Get social media fields
     * 
     * @return array
     */
    private function get_social_media_fields() {
        return array(
            'facebook', 'instagram', 'linkedin', 'twitter', 'youtube', 'tiktok'
        );
    }
    
    /**
     * Get profile data
     * 
     * @param string $field Optional field name to get specific data
     * @return mixed
     */
    public function get_data($field = null) {
        if ($field) {
            return isset($this->profile_data[$field]) ? $this->profile_data[$field] : null;
        }
        
        return $this->profile_data;
    }
    
    /**
     * Set profile data
     * 
     * @param string|array $field Field name or array of field => value pairs
     * @param mixed $value Field value (if $field is string)
     * @return bool
     */
    public function set_data($field, $value = null) {
        if (is_array($field)) {
            foreach ($field as $key => $val) {
                $this->profile_data[$key] = $val;
            }
        } else {
            $this->profile_data[$field] = $value;
        }
        
        return true;
    }
    
    /**
     * Validate profile data
     * 
     * @param array $data Optional data to validate (uses current profile data if not provided)
     * @return bool
     */
    public function validate($data = null) {
        if ($data === null) {
            $data = $this->profile_data;
        }
        
        $this->validation_errors = array();
        
        // Validate required fields based on profile type
        $this->validate_required_fields($data);
        
        // Validate email format
        $this->validate_email($data);
        
        // Validate URLs
        $this->validate_urls($data);
        
        // Validate phone numbers
        $this->validate_phone_numbers($data);
        
        // Validate business hours format
        $this->validate_business_hours($data);
        
        // Validate services data
        $this->validate_services($data);
        
        // Validate products data
        $this->validate_products($data);
        
        // Validate template settings
        $this->validate_template_settings($data);
        
        return empty($this->validation_errors);
    }
    
    /**
     * Validate required fields
     * 
     * @param array $data
     */
    private function validate_required_fields($data) {
        // Determine if this is a business profile or personal vCard
        $is_business_profile = !empty($data['business_name']) || !empty($data['services']) || !empty($data['products']);
        
        if ($is_business_profile) {
            // Business profile requirements
            $required_fields = array(
                'business_name' => __('Business Name is required for business profiles', 'vcard'),
                'email' => __('Email is required', 'vcard'),
                'phone' => __('Phone number is required', 'vcard')
            );
        } else {
            // Personal vCard requirements (backward compatibility)
            $required_fields = array(
                'first_name' => __('First Name is required', 'vcard'),
                'last_name' => __('Last Name is required', 'vcard'),
                'email' => __('Email is required', 'vcard')
            );
        }
        
        foreach ($required_fields as $field => $message) {
            if (empty($data[$field])) {
                $this->validation_errors[$field] = $message;
            }
        }
    }
    
    /**
     * Validate email format
     * 
     * @param array $data
     */
    private function validate_email($data) {
        if (!empty($data['email']) && !is_email($data['email'])) {
            $this->validation_errors['email'] = __('Please enter a valid email address', 'vcard');
        }
    }
    
    /**
     * Validate URLs
     * 
     * @param array $data
     */
    private function validate_urls($data) {
        $url_fields = array_merge(
            array('website'),
            array_map(function($field) { return 'social_' . $field; }, $this->get_social_media_fields())
        );
        
        foreach ($url_fields as $field) {
            if (!empty($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_URL)) {
                $field_label = str_replace('social_', '', $field);
                $field_label = ucfirst(str_replace('_', ' ', $field_label));
                $this->validation_errors[$field] = sprintf(__('%s must be a valid URL', 'vcard'), $field_label);
            }
        }
    }
    
    /**
     * Validate phone numbers
     * 
     * @param array $data
     */
    private function validate_phone_numbers($data) {
        $phone_fields = array('phone', 'secondary_phone', 'whatsapp');
        
        foreach ($phone_fields as $field) {
            if (!empty($data[$field])) {
                // Basic phone number validation (allows various formats)
                $phone = preg_replace('/[^\d+\-\(\)\s]/', '', $data[$field]);
                if (strlen($phone) < 10) {
                    $field_label = ucfirst(str_replace('_', ' ', $field));
                    $this->validation_errors[$field] = sprintf(__('%s must be a valid phone number', 'vcard'), $field_label);
                }
            }
        }
    }
    
    /**
     * Validate business hours format
     * 
     * @param array $data
     */
    private function validate_business_hours($data) {
        if (!empty($data['business_hours'])) {
            $hours = is_string($data['business_hours']) ? json_decode($data['business_hours'], true) : $data['business_hours'];
            
            if (!is_array($hours)) {
                $this->validation_errors['business_hours'] = __('Business hours data is invalid', 'vcard');
                return;
            }
            
            $valid_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
            
            foreach ($hours as $day => $schedule) {
                if (!in_array($day, $valid_days)) {
                    $this->validation_errors['business_hours'] = __('Invalid day in business hours', 'vcard');
                    continue;
                }
                
                if (!is_array($schedule)) {
                    $this->validation_errors['business_hours'] = __('Invalid schedule format for ' . $day, 'vcard');
                    continue;
                }
                
                // Validate time format if not closed
                if (empty($schedule['closed'])) {
                    if (empty($schedule['open']) || empty($schedule['close'])) {
                        $this->validation_errors['business_hours'] = sprintf(__('Open and close times required for %s', 'vcard'), ucfirst($day));
                    } elseif (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $schedule['open']) || 
                             !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $schedule['close'])) {
                        $this->validation_errors['business_hours'] = sprintf(__('Invalid time format for %s', 'vcard'), ucfirst($day));
                    }
                }
            }
        }
    }
    
    /**
     * Validate services data
     * 
     * @param array $data
     */
    private function validate_services($data) {
        if (!empty($data['services'])) {
            $services = is_string($data['services']) ? json_decode($data['services'], true) : $data['services'];
            
            if (!is_array($services)) {
                $this->validation_errors['services'] = __('Services data is invalid', 'vcard');
                return;
            }
            
            foreach ($services as $index => $service) {
                if (!is_array($service)) {
                    $this->validation_errors['services'] = sprintf(__('Service #%d data is invalid', 'vcard'), $index + 1);
                    continue;
                }
                
                if (empty($service['name'])) {
                    $this->validation_errors['services'] = sprintf(__('Service #%d name is required', 'vcard'), $index + 1);
                }
                
                if (!empty($service['price']) && !is_numeric(str_replace(array('$', ',', ' '), '', $service['price']))) {
                    $this->validation_errors['services'] = sprintf(__('Service #%d price must be numeric', 'vcard'), $index + 1);
                }
            }
        }
    }
    
    /**
     * Validate products data
     * 
     * @param array $data
     */
    private function validate_products($data) {
        if (!empty($data['products'])) {
            $products = is_string($data['products']) ? json_decode($data['products'], true) : $data['products'];
            
            if (!is_array($products)) {
                $this->validation_errors['products'] = __('Products data is invalid', 'vcard');
                return;
            }
            
            foreach ($products as $index => $product) {
                if (!is_array($product)) {
                    $this->validation_errors['products'] = sprintf(__('Product #%d data is invalid', 'vcard'), $index + 1);
                    continue;
                }
                
                if (empty($product['name'])) {
                    $this->validation_errors['products'] = sprintf(__('Product #%d name is required', 'vcard'), $index + 1);
                }
                
                if (!empty($product['price']) && !is_numeric(str_replace(array('$', ',', ' '), '', $product['price']))) {
                    $this->validation_errors['products'] = sprintf(__('Product #%d price must be numeric', 'vcard'), $index + 1);
                }
            }
        }
    }
    
    /**
     * Validate template settings
     * 
     * @param array $data
     */
    private function validate_template_settings($data) {
        // Validate template name
        if (!empty($data['template_name'])) {
            $valid_templates = array(
                'ceo', 'freelancer', 'restaurant', 'construction', 'education',
                'fitness', 'coffeebar', 'handyman', 'healthcare', 'immigration',
                'lawyer', 'makeup-artist', 'ngo', 'saloon', 'tour'
            );
            
            if (!in_array($data['template_name'], $valid_templates)) {
                $this->validation_errors['template_name'] = __('Invalid template selected', 'vcard');
            }
        }
        
        // Validate color formats
        $color_fields = array('primary_color', 'secondary_color');
        foreach ($color_fields as $field) {
            if (!empty($data[$field]) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data[$field])) {
                $field_label = ucfirst(str_replace('_', ' ', $field));
                $this->validation_errors[$field] = sprintf(__('%s must be a valid hex color', 'vcard'), $field_label);
            }
        }
    }
    
    /**
     * Get validation errors
     * 
     * @return array
     */
    public function get_validation_errors() {
        return $this->validation_errors;
    }
    
    /**
     * Save profile data to database
     * 
     * @return bool
     */
    public function save() {
        if (!$this->post_id) {
            return false;
        }
        
        // Validate data before saving
        if (!$this->validate()) {
            return false;
        }
        
        // Save regular meta fields
        $regular_fields = array_merge(
            $this->get_personal_fields(),
            $this->get_business_fields(),
            $this->get_contact_fields(),
            $this->get_template_fields(),
            $this->get_system_fields()
        );
        
        foreach ($regular_fields as $field) {
            if (isset($this->profile_data[$field])) {
                $value = $this->profile_data[$field];
                
                // Handle JSON fields
                if (in_array($field, $this->get_json_fields())) {
                    $value = is_array($value) ? wp_json_encode($value) : $value;
                }
                
                update_post_meta($this->post_id, '_vcard_' . $field, $value);
            }
        }
        
        // Save social media fields
        foreach ($this->get_social_media_fields() as $field) {
            $social_key = 'social_' . $field;
            if (isset($this->profile_data[$social_key])) {
                update_post_meta($this->post_id, '_vcard_social_' . $field, $this->profile_data[$social_key]);
            }
        }
        
        return true;
    }
    
    /**
     * Check if profile is a business profile
     * 
     * @return bool
     */
    public function is_business_profile() {
        return !empty($this->profile_data['business_name']) || 
               !empty($this->profile_data['services']) || 
               !empty($this->profile_data['products']);
    }
    
    /**
     * Check if profile is a personal vCard (backward compatibility)
     * 
     * @return bool
     */
    public function is_personal_vcard() {
        return !$this->is_business_profile() && 
               (!empty($this->profile_data['first_name']) || !empty($this->profile_data['last_name']));
    }
    
    /**
     * Get formatted business hours
     * 
     * @return array
     */
    public function get_formatted_business_hours() {
        $hours = $this->get_data('business_hours');
        if (empty($hours)) {
            return array();
        }
        
        if (is_string($hours)) {
            $hours = json_decode($hours, true);
        }
        
        if (!is_array($hours)) {
            return array();
        }
        
        $formatted = array();
        $days = array(
            'monday' => __('Monday', 'vcard'),
            'tuesday' => __('Tuesday', 'vcard'),
            'wednesday' => __('Wednesday', 'vcard'),
            'thursday' => __('Thursday', 'vcard'),
            'friday' => __('Friday', 'vcard'),
            'saturday' => __('Saturday', 'vcard'),
            'sunday' => __('Sunday', 'vcard')
        );
        
        foreach ($days as $day => $label) {
            if (isset($hours[$day])) {
                $schedule = $hours[$day];
                if (!empty($schedule['closed'])) {
                    $formatted[$day] = array(
                        'label' => $label,
                        'status' => __('Closed', 'vcard'),
                        'closed' => true
                    );
                } else {
                    $formatted[$day] = array(
                        'label' => $label,
                        'open' => $schedule['open'] ?? '09:00',
                        'close' => $schedule['close'] ?? '17:00',
                        'status' => sprintf('%s - %s', $schedule['open'] ?? '09:00', $schedule['close'] ?? '17:00'),
                        'closed' => false
                    );
                }
            }
        }
        
        return $formatted;
    }
    
    /**
     * Get services data
     * 
     * @return array
     */
    public function get_services() {
        $services = $this->get_data('services');
        if (empty($services)) {
            return array();
        }
        
        if (is_string($services)) {
            $services = json_decode($services, true);
        }
        
        return is_array($services) ? $services : array();
    }
    
    /**
     * Get products data
     * 
     * @return array
     */
    public function get_products() {
        $products = $this->get_data('products');
        if (empty($products)) {
            return array();
        }
        
        if (is_string($products)) {
            $products = json_decode($products, true);
        }
        
        return is_array($products) ? $products : array();
    }
    
    /**
     * Get social media links
     * 
     * @return array
     */
    public function get_social_media_links() {
        $links = array();
        
        foreach ($this->get_social_media_fields() as $platform) {
            $url = $this->get_data('social_' . $platform);
            if (!empty($url)) {
                $links[$platform] = $url;
            }
        }
        
        return $links;
    }
    
    /**
     * Generate vCard data for export
     * 
     * @return array
     */
    public function get_vcard_export_data() {
        $data = array();
        
        // Basic information
        if ($this->is_business_profile()) {
            $data['fn'] = $this->get_data('business_name');
            $data['org'] = $this->get_data('business_name');
            $data['title'] = $this->get_data('job_title') ?: 'Business Owner';
        } else {
            $data['fn'] = trim($this->get_data('first_name') . ' ' . $this->get_data('last_name'));
            $data['org'] = $this->get_data('company');
            $data['title'] = $this->get_data('job_title');
        }
        
        // Contact information
        $data['tel_work'] = $this->get_data('phone');
        $data['tel_cell'] = $this->get_data('whatsapp') ?: $this->get_data('secondary_phone');
        $data['email'] = $this->get_data('email');
        $data['url'] = $this->get_data('website');
        
        // Address
        $address_parts = array(
            $this->get_data('address'),
            $this->get_data('city'),
            $this->get_data('state'),
            $this->get_data('zip_code'),
            $this->get_data('country')
        );
        $data['adr'] = array_filter($address_parts);
        
        // Notes
        $notes = array();
        if ($this->get_data('business_description')) {
            $notes[] = $this->get_data('business_description');
        }
        if ($this->get_data('business_tagline')) {
            $notes[] = $this->get_data('business_tagline');
        }
        $data['note'] = implode("\n\n", $notes);
        
        // Social media
        $data['social_media'] = $this->get_social_media_links();
        
        return $data;
    }
    
    /**
     * Create a new business profile instance
     * 
     * @param array $data Profile data
     * @return VCard_Business_Profile|false
     */
    public static function create($data) {
        // Create new post
        $post_data = array(
            'post_type' => 'vcard_profile',
            'post_status' => 'publish',
            'post_title' => $data['business_name'] ?? ($data['first_name'] . ' ' . $data['last_name']),
            'post_author' => get_current_user_id()
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return false;
        }
        
        // Create profile instance and set data
        $profile = new self($post_id);
        $profile->set_data($data);
        
        if ($profile->save()) {
            return $profile;
        }
        
        // Clean up if save failed
        wp_delete_post($post_id, true);
        return false;
    }
}
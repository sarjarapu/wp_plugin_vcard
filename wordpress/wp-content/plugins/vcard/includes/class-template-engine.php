<?php
/**
 * Template Engine Class
 * 
 * Handles template loading, parsing, and data binding for vCard business profiles.
 * Supports curated color schemes and industry-specific templates.
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_Template_Engine {
    
    /**
     * Available templates
     * @var array
     */
    private $available_templates = array();
    
    /**
     * Available color schemes
     * @var array
     */
    private $color_schemes = array();
    
    /**
     * Template cache
     * @var array
     */
    private $template_cache = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_templates();
        $this->init_color_schemes();
    }
    
    /**
     * Initialize available templates
     */
    private function init_templates() {
        $this->available_templates = array(
            'ceo' => array(
                'name' => __('Executive', 'vcard'),
                'description' => __('Professional layout for executives and business leaders', 'vcard'),
                'recommended_schemes' => array('professional', 'finance', 'corporate', 'luxury'),
                'layout' => 'header-focused',
                'features' => array('large_header', 'services_grid', 'contact_prominent'),
                'industries' => array('business', 'finance', 'consulting', 'legal')
            ),
            'freelancer' => array(
                'name' => __('Creative Professional', 'vcard'),
                'description' => __('Modern layout for freelancers and creative professionals', 'vcard'),
                'recommended_schemes' => array('creative', 'modern', 'artistic', 'vibrant'),
                'layout' => 'portfolio-focused',
                'features' => array('gallery_prominent', 'skills_showcase', 'portfolio_grid'),
                'industries' => array('design', 'photography', 'marketing', 'creative')
            ),
            'restaurant' => array(
                'name' => __('Restaurant & Food', 'vcard'),
                'description' => __('Appetizing layout for restaurants and food businesses', 'vcard'),
                'recommended_schemes' => array('warm', 'food', 'hospitality', 'organic'),
                'layout' => 'menu-focused',
                'features' => array('menu_display', 'gallery_food', 'hours_prominent'),
                'industries' => array('restaurant', 'food', 'catering', 'hospitality')
            ),
            'healthcare' => array(
                'name' => __('Healthcare & Medical', 'vcard'),
                'description' => __('Clean, trustworthy layout for healthcare professionals', 'vcard'),
                'recommended_schemes' => array('healthcare', 'clean', 'trust', 'medical'),
                'layout' => 'service-focused',
                'features' => array('services_detailed', 'credentials', 'appointment_booking'),
                'industries' => array('healthcare', 'medical', 'dental', 'therapy')
            ),
            'construction' => array(
                'name' => __('Construction & Trade', 'vcard'),
                'description' => __('Strong, reliable layout for construction and trade businesses', 'vcard'),
                'recommended_schemes' => array('industrial', 'strong', 'reliable', 'earth'),
                'layout' => 'project-focused',
                'features' => array('project_gallery', 'services_list', 'contact_direct'),
                'industries' => array('construction', 'contracting', 'trades', 'engineering')
            ),
            'education' => array(
                'name' => __('Education & Training', 'vcard'),
                'description' => __('Professional layout for educators and training providers', 'vcard'),
                'recommended_schemes' => array('academic', 'professional', 'trust', 'knowledge'),
                'layout' => 'content-focused',
                'features' => array('courses_list', 'credentials', 'testimonials'),
                'industries' => array('education', 'training', 'coaching', 'consulting')
            ),
            'fitness' => array(
                'name' => __('Fitness & Wellness', 'vcard'),
                'description' => __('Energetic layout for fitness and wellness professionals', 'vcard'),
                'recommended_schemes' => array('energetic', 'health', 'vibrant', 'active'),
                'layout' => 'action-focused',
                'features' => array('programs_showcase', 'before_after', 'booking_prominent'),
                'industries' => array('fitness', 'wellness', 'sports', 'health')
            )
        );
    }
    
    /**
     * Initialize curated color schemes
     */
    private function init_color_schemes() {
        $this->color_schemes = array(
            'professional' => array(
                'name' => __('Professional Blue', 'vcard'),
                'description' => __('Classic professional look with blue accents', 'vcard'),
                'primary' => '#2563eb',
                'secondary' => '#64748b',
                'accent' => '#f8fafc',
                'text' => '#1e293b',
                'text_light' => '#64748b',
                'background' => '#ffffff',
                'card_bg' => '#f8fafc',
                'border' => '#e2e8f0',
                'success' => '#059669',
                'warning' => '#d97706',
                'error' => '#dc2626'
            ),
            'healthcare' => array(
                'name' => __('Medical Green', 'vcard'),
                'description' => __('Clean and trustworthy healthcare colors', 'vcard'),
                'primary' => '#059669',
                'secondary' => '#6b7280',
                'accent' => '#f0fdf4',
                'text' => '#111827',
                'text_light' => '#6b7280',
                'background' => '#ffffff',
                'card_bg' => '#f9fafb',
                'border' => '#d1d5db',
                'success' => '#10b981',
                'warning' => '#f59e0b',
                'error' => '#ef4444'
            ),
            'creative' => array(
                'name' => __('Creative Purple', 'vcard'),
                'description' => __('Modern and creative with purple highlights', 'vcard'),
                'primary' => '#7c3aed',
                'secondary' => '#a855f7',
                'accent' => '#faf5ff',
                'text' => '#1f2937',
                'text_light' => '#6b7280',
                'background' => '#ffffff',
                'card_bg' => '#f9fafb',
                'border' => '#e5e7eb',
                'success' => '#10b981',
                'warning' => '#f59e0b',
                'error' => '#ef4444'
            ),
            'finance' => array(
                'name' => __('Finance Navy', 'vcard'),
                'description' => __('Trustworthy navy blue for financial services', 'vcard'),
                'primary' => '#1e40af',
                'secondary' => '#374151',
                'accent' => '#f9fafb',
                'text' => '#111827',
                'text_light' => '#6b7280',
                'background' => '#ffffff',
                'card_bg' => '#f3f4f6',
                'border' => '#d1d5db',
                'success' => '#059669',
                'warning' => '#d97706',
                'error' => '#dc2626'
            ),
            'warm' => array(
                'name' => __('Warm Orange', 'vcard'),
                'description' => __('Welcoming warm colors for hospitality', 'vcard'),
                'primary' => '#ea580c',
                'secondary' => '#92400e',
                'accent' => '#fff7ed',
                'text' => '#1c1917',
                'text_light' => '#78716c',
                'background' => '#ffffff',
                'card_bg' => '#fefcfb',
                'border' => '#e7e5e4',
                'success' => '#16a34a',
                'warning' => '#ca8a04',
                'error' => '#dc2626'
            ),
            'industrial' => array(
                'name' => __('Industrial Gray', 'vcard'),
                'description' => __('Strong and reliable colors for construction', 'vcard'),
                'primary' => '#374151',
                'secondary' => '#6b7280',
                'accent' => '#f9fafb',
                'text' => '#111827',
                'text_light' => '#6b7280',
                'background' => '#ffffff',
                'card_bg' => '#f3f4f6',
                'border' => '#d1d5db',
                'success' => '#059669',
                'warning' => '#d97706',
                'error' => '#dc2626'
            ),
            'energetic' => array(
                'name' => __('Energetic Red', 'vcard'),
                'description' => __('Dynamic colors for fitness and sports', 'vcard'),
                'primary' => '#dc2626',
                'secondary' => '#991b1b',
                'accent' => '#fef2f2',
                'text' => '#1f2937',
                'text_light' => '#6b7280',
                'background' => '#ffffff',
                'card_bg' => '#fefcfc',
                'border' => '#f3f4f6',
                'success' => '#16a34a',
                'warning' => '#d97706',
                'error' => '#b91c1c'
            ),
            'luxury' => array(
                'name' => __('Luxury Gold', 'vcard'),
                'description' => __('Premium gold accents for luxury services', 'vcard'),
                'primary' => '#d97706',
                'secondary' => '#92400e',
                'accent' => '#fffbeb',
                'text' => '#1c1917',
                'text_light' => '#78716c',
                'background' => '#ffffff',
                'card_bg' => '#fefdfb',
                'border' => '#f3f4f6',
                'success' => '#059669',
                'warning' => '#ca8a04',
                'error' => '#dc2626'
            )
        );
    }
    
    /**
     * Get available templates
     * 
     * @param string $industry Optional industry filter
     * @return array
     */
    public function get_available_templates($industry = null) {
        if (!$industry) {
            return $this->available_templates;
        }
        
        $filtered = array();
        foreach ($this->available_templates as $key => $template) {
            if (in_array($industry, $template['industries'])) {
                $filtered[$key] = $template;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get available color schemes
     * 
     * @param string $template_key Optional template key to get recommended schemes
     * @return array
     */
    public function get_available_color_schemes($template_key = null) {
        if (!$template_key || !isset($this->available_templates[$template_key])) {
            return $this->color_schemes;
        }
        
        $template = $this->available_templates[$template_key];
        $recommended = array();
        
        foreach ($template['recommended_schemes'] as $scheme_key) {
            if (isset($this->color_schemes[$scheme_key])) {
                $recommended[$scheme_key] = $this->color_schemes[$scheme_key];
            }
        }
        
        return $recommended;
    }
    
    /**
     * Get template data
     * 
     * @param string $template_key
     * @return array|null
     */
    public function get_template($template_key) {
        return isset($this->available_templates[$template_key]) ? $this->available_templates[$template_key] : null;
    }
    
    /**
     * Get color scheme data
     * 
     * @param string $scheme_key
     * @return array|null
     */
    public function get_color_scheme($scheme_key) {
        return isset($this->color_schemes[$scheme_key]) ? $this->color_schemes[$scheme_key] : null;
    }
    
    /**
     * Load and parse template
     * 
     * @param string $template_key
     * @param array $data Profile data
     * @param string $color_scheme_key
     * @return string Parsed template HTML
     */
    public function render_template($template_key, $data, $color_scheme_key = 'professional') {
        // Get template and color scheme
        $template = $this->get_template($template_key);
        $color_scheme = $this->get_color_scheme($color_scheme_key);
        
        if (!$template || !$color_scheme) {
            return $this->render_fallback_template($data);
        }
        
        // Load template file
        $template_html = $this->load_template_file($template_key);
        if (!$template_html) {
            return $this->render_fallback_template($data);
        }
        
        // Generate CSS for color scheme
        $custom_css = $this->generate_template_css($template_key, $color_scheme);
        
        // Parse template with data
        $parsed_html = $this->parse_template_data($template_html, $data, $template, $color_scheme);
        
        // Combine CSS and HTML
        return $this->wrap_template_output($parsed_html, $custom_css, $template_key, $color_scheme_key);
    }
    
    /**
     * Load template file
     * 
     * @param string $template_key
     * @return string|false Template HTML or false on failure
     */
    private function load_template_file($template_key) {
        // Check cache first
        if (isset($this->template_cache[$template_key])) {
            return $this->template_cache[$template_key];
        }
        
        // Look for template file in templates directory
        $template_file = VCARD_TEMPLATES_PATH . 'vcard-' . $template_key . '.php';
        
        if (!file_exists($template_file)) {
            // Try fallback location
            $template_file = VCARD_TEMPLATES_PATH . 'single-vcard_profile.php';
            if (!file_exists($template_file)) {
                // Return a basic template structure for preview
                return $this->get_basic_template_structure();
            }
        }
        
        // Load template content
        ob_start();
        include $template_file;
        $template_html = ob_get_clean();
        
        // Cache the template
        $this->template_cache[$template_key] = $template_html;
        
        return $template_html;
    }
    
    /**
     * Parse template data placeholders
     * 
     * @param string $template_html
     * @param array $data
     * @param array $template
     * @param array $color_scheme
     * @return string
     */
    private function parse_template_data($template_html, $data, $template, $color_scheme) {
        // Create BusinessProfile instance for data access
        $profile = new VCard_Business_Profile();
        $profile->set_data($data);
        
        // Define template variables
        $template_vars = array(
            // Basic Info
            '{{business_name}}' => $profile->get_data('business_name') ?: ($profile->get_data('first_name') . ' ' . $profile->get_data('last_name')),
            '{{business_tagline}}' => $profile->get_data('business_tagline') ?: '',
            '{{business_description}}' => $profile->get_data('business_description') ?: '',
            '{{first_name}}' => $profile->get_data('first_name') ?: '',
            '{{last_name}}' => $profile->get_data('last_name') ?: '',
            '{{job_title}}' => $profile->get_data('job_title') ?: '',
            '{{company}}' => $profile->get_data('company') ?: '',
            
            // Contact Info
            '{{email}}' => $profile->get_data('email') ?: '',
            '{{phone}}' => $profile->get_data('phone') ?: '',
            '{{secondary_phone}}' => $profile->get_data('secondary_phone') ?: '',
            '{{whatsapp}}' => $profile->get_data('whatsapp') ?: '',
            '{{website}}' => $profile->get_data('website') ?: '',
            '{{address}}' => $this->format_address($profile),
            
            // Business Hours
            '{{business_hours}}' => $this->format_business_hours($profile),
            
            // Services and Products
            '{{services}}' => $this->format_services($profile, $template),
            '{{products}}' => $this->format_products($profile, $template),
            
            // Gallery
            '{{gallery}}' => $this->format_gallery($profile, $template),
            
            // Social Media
            '{{social_media}}' => $this->format_social_media($profile),
            
            // Images
            '{{business_logo}}' => $this->format_business_logo($profile),
            '{{cover_image}}' => $this->format_cover_image($profile),
            
            // Template specific
            '{{template_class}}' => 'vcard-template-' . $template['layout'],
            '{{color_scheme_class}}' => 'color-scheme-' . key($this->color_schemes), // This will be fixed in actual implementation
        );
        
        // Replace placeholders
        $parsed_html = str_replace(array_keys($template_vars), array_values($template_vars), $template_html);
        
        // Handle conditional sections
        $parsed_html = $this->parse_conditional_sections($parsed_html, $profile, $template);
        
        return $parsed_html;
    }
    
    /**
     * Format address for display
     * 
     * @param VCard_Business_Profile $profile
     * @return string
     */
    private function format_address($profile) {
        $address_parts = array_filter(array(
            $profile->get_data('address'),
            $profile->get_data('city'),
            $profile->get_data('state'),
            $profile->get_data('zip_code'),
            $profile->get_data('country')
        ));
        
        return implode(', ', $address_parts);
    }
    
    /**
     * Format business hours for display
     * 
     * @param VCard_Business_Profile $profile
     * @return string
     */
    private function format_business_hours($profile) {
        $hours = $profile->get_formatted_business_hours();
        if (empty($hours)) {
            return '';
        }
        
        $html = '<div class="business-hours">';
        foreach ($hours as $day => $schedule) {
            $html .= '<div class="hours-day">';
            $html .= '<span class="day">' . esc_html($schedule['label']) . '</span>';
            $html .= '<span class="time">' . esc_html($schedule['status']) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Format services for display
     * 
     * @param VCard_Business_Profile $profile
     * @param array $template
     * @return string
     */
    private function format_services($profile, $template) {
        $services = $profile->get_services();
        if (empty($services)) {
            return '';
        }
        
        $layout = $template['layout'];
        $html = '<div class="services-section layout-' . esc_attr($layout) . '">';
        
        foreach ($services as $service) {
            $html .= '<div class="service-item">';
            $html .= '<h4 class="service-name">' . esc_html($service['name']) . '</h4>';
            
            if (!empty($service['price'])) {
                $html .= '<span class="service-price">' . esc_html($service['price']) . '</span>';
            }
            
            if (!empty($service['description'])) {
                $html .= '<p class="service-description">' . esc_html($service['description']) . '</p>';
            }
            
            if (!empty($service['duration'])) {
                $html .= '<span class="service-duration">' . esc_html($service['duration']) . '</span>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Format products for display
     * 
     * @param VCard_Business_Profile $profile
     * @param array $template
     * @return string
     */
    private function format_products($profile, $template) {
        $products = $profile->get_products();
        if (empty($products)) {
            return '';
        }
        
        $layout = $template['layout'];
        $html = '<div class="products-section layout-' . esc_attr($layout) . '">';
        
        foreach ($products as $product) {
            $html .= '<div class="product-item">';
            
            // Product image
            if (!empty($product['image_id'])) {
                $image_url = wp_get_attachment_image_url($product['image_id'], 'medium');
                if ($image_url) {
                    $html .= '<div class="product-image"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($product['name']) . '"></div>';
                }
            }
            
            $html .= '<div class="product-content">';
            $html .= '<h4 class="product-name">' . esc_html($product['name']) . '</h4>';
            
            if (!empty($product['price'])) {
                $html .= '<span class="product-price">' . esc_html($product['price']) . '</span>';
            }
            
            if (!empty($product['description'])) {
                $html .= '<p class="product-description">' . esc_html($product['description']) . '</p>';
            }
            
            // Stock status
            if (isset($product['in_stock'])) {
                $stock_class = $product['in_stock'] ? 'in-stock' : 'out-of-stock';
                $stock_text = $product['in_stock'] ? __('In Stock', 'vcard') : __('Out of Stock', 'vcard');
                $html .= '<span class="stock-status ' . $stock_class . '">' . $stock_text . '</span>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Format gallery for display
     * 
     * @param VCard_Business_Profile $profile
     * @param array $template
     * @return string
     */
    private function format_gallery($profile, $template) {
        $gallery = $profile->get_data('gallery');
        if (empty($gallery)) {
            return '';
        }
        
        $gallery_ids = explode(',', $gallery);
        if (empty($gallery_ids)) {
            return '';
        }
        
        $html = '<div class="gallery-section">';
        $html .= '<div class="gallery-grid">';
        
        foreach ($gallery_ids as $image_id) {
            $image_id = intval($image_id);
            if ($image_id > 0) {
                $image_url = wp_get_attachment_image_url($image_id, 'medium');
                $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                
                if ($image_url) {
                    $html .= '<div class="gallery-item">';
                    $html .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '">';
                    $html .= '</div>';
                }
            }
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Format social media links
     * 
     * @param VCard_Business_Profile $profile
     * @return string
     */
    private function format_social_media($profile) {
        $social_links = $profile->get_social_media_links();
        if (empty($social_links)) {
            return '';
        }
        
        $html = '<div class="social-media-section">';
        
        foreach ($social_links as $platform => $url) {
            $html .= '<a href="' . esc_url($url) . '" class="social-link social-' . esc_attr($platform) . '" target="_blank" rel="noopener">';
            $html .= '<span class="social-icon">' . ucfirst($platform) . '</span>';
            $html .= '</a>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Format business logo
     * 
     * @param VCard_Business_Profile $profile
     * @return string
     */
    private function format_business_logo($profile) {
        $logo_id = $profile->get_data('business_logo');
        if (empty($logo_id)) {
            return '';
        }
        
        $logo_url = wp_get_attachment_image_url($logo_id, 'medium');
        if (!$logo_url) {
            return '';
        }
        
        $business_name = $profile->get_data('business_name') ?: ($profile->get_data('first_name') . ' ' . $profile->get_data('last_name'));
        
        return '<div class="business-logo"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($business_name) . ' Logo"></div>';
    }
    
    /**
     * Format cover image
     * 
     * @param VCard_Business_Profile $profile
     * @return string
     */
    private function format_cover_image($profile) {
        $cover_id = $profile->get_data('cover_image');
        if (empty($cover_id)) {
            return '';
        }
        
        $cover_url = wp_get_attachment_image_url($cover_id, 'large');
        if (!$cover_url) {
            return '';
        }
        
        return '<div class="cover-image"><img src="' . esc_url($cover_url) . '" alt="Cover Image"></div>';
    }
    
    /**
     * Parse conditional sections in template
     * 
     * @param string $html
     * @param VCard_Business_Profile $profile
     * @param array $template
     * @return string
     */
    private function parse_conditional_sections($html, $profile, $template) {
        // Handle {{#if_services}} ... {{/if_services}}
        $services = $profile->get_services();
        if (empty($services)) {
            $html = preg_replace('/\{\{#if_services\}\}.*?\{\{\/if_services\}\}/s', '', $html);
        } else {
            $html = str_replace(array('{{#if_services}}', '{{/if_services}}'), '', $html);
        }
        
        // Handle {{#if_products}} ... {{/if_products}}
        $products = $profile->get_products();
        if (empty($products)) {
            $html = preg_replace('/\{\{#if_products\}\}.*?\{\{\/if_products\}\}/s', '', $html);
        } else {
            $html = str_replace(array('{{#if_products}}', '{{/if_products}}'), '', $html);
        }
        
        // Handle {{#if_gallery}} ... {{/if_gallery}}
        $gallery = $profile->get_data('gallery');
        if (empty($gallery)) {
            $html = preg_replace('/\{\{#if_gallery\}\}.*?\{\{\/if_gallery\}\}/s', '', $html);
        } else {
            $html = str_replace(array('{{#if_gallery}}', '{{/if_gallery}}'), '', $html);
        }
        
        // Handle {{#if_business_profile}} ... {{/if_business_profile}}
        if (!$profile->is_business_profile()) {
            $html = preg_replace('/\{\{#if_business_profile\}\}.*?\{\{\/if_business_profile\}\}/s', '', $html);
        } else {
            $html = str_replace(array('{{#if_business_profile}}', '{{/if_business_profile}}'), '', $html);
        }
        
        return $html;
    }
    
    /**
     * Generate CSS for template and color scheme
     * 
     * @param string $template_key
     * @param array $color_scheme
     * @return string
     */
    private function generate_template_css($template_key, $color_scheme) {
        $css = "
        .vcard-template.template-{$template_key} {
            --primary-color: {$color_scheme['primary']};
            --secondary-color: {$color_scheme['secondary']};
            --accent-color: {$color_scheme['accent']};
            --text-color: {$color_scheme['text']};
            --text-light-color: {$color_scheme['text_light']};
            --background-color: {$color_scheme['background']};
            --card-bg-color: {$color_scheme['card_bg']};
            --border-color: {$color_scheme['border']};
            --success-color: {$color_scheme['success']};
            --warning-color: {$color_scheme['warning']};
            --error-color: {$color_scheme['error']};
        }
        
        .vcard-template.template-{$template_key} .primary-bg { background-color: var(--primary-color); }
        .vcard-template.template-{$template_key} .secondary-bg { background-color: var(--secondary-color); }
        .vcard-template.template-{$template_key} .accent-bg { background-color: var(--accent-color); }
        .vcard-template.template-{$template_key} .primary-text { color: var(--primary-color); }
        .vcard-template.template-{$template_key} .secondary-text { color: var(--secondary-color); }
        .vcard-template.template-{$template_key} .text-color { color: var(--text-color); }
        .vcard-template.template-{$template_key} .text-light { color: var(--text-light-color); }
        .vcard-template.template-{$template_key} .border-color { border-color: var(--border-color); }
        ";
        
        return $css;
    }
    
    /**
     * Wrap template output with CSS and container
     * 
     * @param string $html
     * @param string $css
     * @param string $template_key
     * @param string $color_scheme_key
     * @return string
     */
    private function wrap_template_output($html, $css, $template_key, $color_scheme_key) {
        $output = '<style>' . $css . '</style>';
        $output .= '<div class="vcard-template template-' . esc_attr($template_key) . ' color-scheme-' . esc_attr($color_scheme_key) . '">';
        $output .= $html;
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render fallback template when main template fails
     * 
     * @param array $data
     * @return string
     */
    private function render_fallback_template($data) {
        $profile = new VCard_Business_Profile();
        $profile->set_data($data);
        
        $name = $profile->get_data('business_name') ?: ($profile->get_data('first_name') . ' ' . $profile->get_data('last_name'));
        $email = $profile->get_data('email');
        $phone = $profile->get_data('phone');
        
        $html = '<div class="vcard-fallback">';
        $html .= '<h1>' . esc_html($name) . '</h1>';
        if ($email) $html .= '<p>Email: <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></p>';
        if ($phone) $html .= '<p>Phone: <a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a></p>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Validate template data
     * 
     * @param array $data
     * @return array Validation errors
     */
    public function validate_template_data($data) {
        $profile = new VCard_Business_Profile();
        $profile->set_data($data);
        
        if (!$profile->validate()) {
            return $profile->get_validation_errors();
        }
        
        return array();
    }
    
    /**
     * Get basic template structure for preview when template files don't exist
     */
    private function get_basic_template_structure() {
        return '
        <div class="vcard-profile-header">
            {{business_logo}}
            <div class="profile-info">
                <h1 class="business-name primary-text">{{business_name}}</h1>
                <p class="business-tagline secondary-text">{{business_tagline}}</p>
                <p class="job-title text-light">{{job_title}}</p>
            </div>
        </div>
        
        <div class="vcard-profile-content">
            {{#if_business_profile}}
            <div class="business-description">
                <p>{{business_description}}</p>
            </div>
            {{/if_business_profile}}
            
            <div class="contact-info card-bg">
                <h3 class="primary-text">Contact Information</h3>
                <div class="contact-details">
                    <p><strong>Email:</strong> <a href="mailto:{{email}}">{{email}}</a></p>
                    <p><strong>Phone:</strong> <a href="tel:{{phone}}">{{phone}}</a></p>
                    <p><strong>Website:</strong> <a href="{{website}}" target="_blank">{{website}}</a></p>
                    <p><strong>Address:</strong> {{address}}</p>
                </div>
            </div>
            
            {{#if_services}}
            <div class="services-section">
                <h3 class="primary-text">Services</h3>
                {{services}}
            </div>
            {{/if_services}}
            
            {{#if_products}}
            <div class="products-section">
                <h3 class="primary-text">Products</h3>
                {{products}}
            </div>
            {{/if_products}}
            
            {{#if_gallery}}
            <div class="gallery-section">
                <h3 class="primary-text">Gallery</h3>
                {{gallery}}
            </div>
            {{/if_gallery}}
            
            <div class="business-hours">
                <h3 class="primary-text">Business Hours</h3>
                {{business_hours}}
            </div>
            
            <div class="social-media">
                {{social_media}}
            </div>
        </div>
        ';
    }
}
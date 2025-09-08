<?php
/**
 * VCard Template Renderer Class
 * 
 * Handles template rendering for vCard profiles using simple PHP templates
 * 
 * @package VCard
 * @version 1.0.0
 */

namespace VCard;

class VCardTemplateRenderer {
    
    private $twig;
    private $template_path;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->template_path = VCARD_PLUGIN_PATH . 'templates/twig/';
        $this->initializeTwig();
    }
    
    /**
     * Initialize Twig template engine
     */
    private function initializeTwig() {
        $loader = new \Twig\Loader\FilesystemLoader($this->template_path);
        $this->twig = new \Twig\Environment($loader, [
            'cache' => false, // Disabled for development
            'debug' => true,
            'auto_reload' => true
        ]);
        
        // Add WordPress-specific filters
        $this->addWordPressFilters();
    }
    
    /**
     * Add WordPress-specific Twig filters
     */
    private function addWordPressFilters() {
        // Add esc_html filter
        $this->twig->addFilter(new \Twig\TwigFilter('esc_html', function ($string) {
            return esc_html($string);
        }));
        
        // Add esc_attr filter
        $this->twig->addFilter(new \Twig\TwigFilter('esc_attr', function ($string) {
            return esc_attr($string);
        }));
        
        // Add esc_url filter
        $this->twig->addFilter(new \Twig\TwigFilter('esc_url', function ($string) {
            return esc_url($string);
        }));
        
        // Add WordPress __ function
        $this->twig->addFunction(new \Twig\TwigFunction('__', function ($text, $domain = 'vcard') {
            return __($text, $domain);
        }));
        
        // Add sprintf-like format function
        $this->twig->addFilter(new \Twig\TwigFilter('format', function ($string, ...$args) {
            return sprintf($string, ...$args);
        }));
        
        // Add wpautop filter
        $this->twig->addFilter(new \Twig\TwigFilter('wpautop', function ($string) {
            return wpautop($string);
        }));
        
        // Add wp_kses_post filter
        $this->twig->addFilter(new \Twig\TwigFilter('wp_kses_post', function ($string) {
            return wp_kses_post($string);
        }));
    }
    
    /**
     * Render vCard profile template
     * 
     * @param array $profile_data
     * @return string
     */
    public function renderProfile($profile_data) {
        $template_name = $profile_data['template_settings']['template_name'] ?? 'default';
        
        // Debug: Log the raw profile data
        error_log('VCardTemplateRenderer - Raw Profile Data: ' . print_r($profile_data, true));
        
        // Prepare template variables
        $template_vars = $this->prepareTemplateVariables($profile_data);
        
        // Debug: Log the prepared template variables
        error_log('VCardTemplateRenderer - Template Variables: ' . print_r($template_vars, true));
        
        // Try to load specific template, fallback to default
        $template_file = "profile-{$template_name}.twig";
        
        try {
            $template = $this->twig->load($template_file);
            return $template->render($template_vars);
        } catch (\Twig\Error\LoaderError $e) {
            // Fallback to default template
            $template = $this->twig->load('profile-default.twig');
            return $template->render($template_vars);
        }
    }
    
    /**
     * Prepare variables for template rendering
     * 
     * @param array $profile_data
     * @return array
     */
    private function prepareTemplateVariables($profile_data) {
        $is_business = VCardDatabaseHelper::isBusinessProfile($profile_data);
        
        return [
            'profile' => $profile_data,
            'is_business' => $is_business,
            'basic_info' => $profile_data['basic_info'],
            'contact_info' => $profile_data['contact_info'], // Already formatted by getBusinessProfileData
            'raw_contact_info' => $profile_data['raw_contact_info'], // Raw contact data for data attributes
            'address' => $profile_data['address'],
            'social_media' => VCardDatabaseHelper::formatSocialMediaLinks($profile_data['social_media']),
            'business_data' => $profile_data['business_data'],
            'business_hours' => VCardDatabaseHelper::formatBusinessHours($profile_data['business_data']['business_hours']),
            'template_settings' => $profile_data['template_settings'],
            'analytics' => $profile_data['analytics'],
            'settings' => $profile_data['settings'],
            
            // Helper variables
            'has_address' => !empty(array_filter($profile_data['address'])),
            'has_social_media' => !empty(array_filter($profile_data['social_media'])),
            'has_services' => !empty($profile_data['business_data']['services']),
            'has_products' => !empty($profile_data['business_data']['products']),
            'has_gallery' => !empty($profile_data['business_data']['gallery']),
            'has_business_hours' => !empty($profile_data['business_data']['business_hours']),
            
            // WordPress context
            'wp_debug' => WP_DEBUG,
            'plugin_url' => VCARD_PLUGIN_URL,
            'assets_url' => VCARD_ASSETS_URL,
            
            // Current page context
            'profile_id' => $profile_data['raw_data']['id'],
            'profile_url' => get_permalink($profile_data['raw_data']['id']),
            'thumbnail_url' => $profile_data['raw_data']['thumbnail_url'] ?? '',
            
            // Edit permissions and URL
            'can_edit_profile' => $this->canEditProfile($profile_data['raw_data']['id']),
            'edit_profile_url' => get_edit_post_link($profile_data['raw_data']['id']),
            
            // WordPress nonces and functions
            'contact_form_nonce' => wp_create_nonce('vcard_contact_form'),
        ];
    }
    
    /**
     * Render fallback template when Twig fails
     * 
     * @param array $profile_data
     * @return string
     */
    private function renderFallbackTemplate($profile_data) {
        $basic_info = $profile_data['basic_info'];
        $is_business = VCardDatabaseHelper::isBusinessProfile($profile_data);
        
        $name = $is_business ? 
            ($basic_info['business_name'] ?: 'Business Profile') :
            (trim($basic_info['first_name'] . ' ' . $basic_info['last_name']) ?: 'Profile');
        
        return sprintf(
            '<div class="vcard-fallback"><h1>%s</h1><p>Template rendering error. Please check your template files.</p></div>',
            esc_html($name)
        );
    }
    
    /**
     * Get available templates
     * 
     * @return array
     */
    public function getAvailableTemplates() {
        $templates = [];
        $template_path = VCARD_PLUGIN_PATH . 'templates/simple/';
        $files = glob($template_path . 'profile-*.php');
        
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            $template_name = str_replace('profile-', '', $filename);
            $templates[$template_name] = ucfirst(str_replace('-', ' ', $template_name));
        }
        
        return $templates;
    }
    
    /**
     * Check if current user can edit the profile
     * 
     * @param int $profile_id
     * @return bool
     */
    private function canEditProfile($profile_id) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Administrators can edit all profiles
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Editors can edit all profiles
        if (current_user_can('edit_others_posts')) {
            return true;
        }
        
        // Check for specific vCard capabilities
        if (current_user_can('edit_others_vcard_profiles')) {
            return true;
        }
        
        // Users can edit their own profiles
        $profile = get_post($profile_id);
        if ($profile && current_user_can('edit_posts')) {
            return $profile->post_author == get_current_user_id();
        }
        
        return false;
    }
}
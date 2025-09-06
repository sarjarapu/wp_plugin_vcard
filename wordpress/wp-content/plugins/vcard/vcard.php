<?php
/**
 * Plugin Name: vCard Business Directory
 * Plugin URI: https://example.com/vcard-plugin
 * Description: A comprehensive multi-tenant business directory platform that enables virtual business card exchange with template customization, contact management, and subscription billing.
 * Version: 1.0.0
 * Author: vCard Team
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vcard
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VCARD_VERSION', '1.0.0');
define('VCARD_PLUGIN_FILE', __FILE__);
define('VCARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VCARD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('VCARD_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('VCARD_INCLUDES_PATH', VCARD_PLUGIN_PATH . 'includes/');
define('VCARD_ADMIN_PATH', VCARD_PLUGIN_PATH . 'admin/');
define('VCARD_PUBLIC_PATH', VCARD_PLUGIN_PATH . 'public/');
define('VCARD_TEMPLATES_PATH', VCARD_PLUGIN_PATH . 'templates/');
define('VCARD_ASSETS_URL', VCARD_PLUGIN_URL . 'assets/');

// Define database table names
global $wpdb;
define('VCARD_ANALYTICS_TABLE', $wpdb->prefix . 'vcard_analytics');
define('VCARD_SUBSCRIPTIONS_TABLE', $wpdb->prefix . 'vcard_subscriptions');
define('VCARD_SAVED_CONTACTS_TABLE', $wpdb->prefix . 'vcard_saved_contacts');

class VCardPlugin {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->define_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Define WordPress hooks
     */
    private function define_hooks() {
        // Core hooks
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Plugin lifecycle hooks
        register_activation_hook(VCARD_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(VCARD_PLUGIN_FILE, array($this, 'deactivate'));
        register_uninstall_hook(VCARD_PLUGIN_FILE, array('VCardPlugin', 'uninstall'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        
        // Access control hooks for multi-tenant functionality
        add_action('load-post.php', array($this, 'restrict_vcard_profile_editing'));
        add_action('load-post-new.php', array($this, 'restrict_vcard_profile_editing'));
        add_action('pre_get_posts', array($this, 'filter_vcard_profiles_by_user'));
        
        // Load text domain for internationalization
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // AJAX hooks for frontend interactions
        add_action('wp_ajax_vcard_track_download', array($this, 'handle_track_download'));
        add_action('wp_ajax_nopriv_vcard_track_download', array($this, 'handle_track_download'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load BusinessProfile class for enhanced profile management
        require_once VCARD_INCLUDES_PATH . 'class-business-profile.php';
        
        // Core includes will be loaded in future tasks
        // require_once VCARD_INCLUDES_PATH . 'class-vcard-post-type.php';
        // require_once VCARD_INCLUDES_PATH . 'class-vcard-meta-fields.php';
        // require_once VCARD_INCLUDES_PATH . 'class-vcard-template-engine.php';
    }
    
    public function init() {
        $this->register_post_type();
        $this->register_meta_fields();
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_fields'));
        add_filter('template_include', array($this, 'load_templates'));
    }
    
    public function register_post_type() {
        $args = array(
            'labels' => array(
                'name' => __('vCards', 'vcard'),
                'singular_name' => __('vCard', 'vcard'),
                'add_new' => __('Add New vCard', 'vcard'),
                'add_new_item' => __('Add New vCard', 'vcard'),
                'edit_item' => __('Edit vCard', 'vcard'),
                'new_item' => __('New vCard', 'vcard'),
                'view_item' => __('View vCard', 'vcard'),
                'search_items' => __('Search vCards', 'vcard'),
                'not_found' => __('No vCards found', 'vcard'),
                'not_found_in_trash' => __('No vCards found in trash', 'vcard'),
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'vcard'),
            'supports' => array('title', 'editor', 'thumbnail'),
            'menu_icon' => 'dashicons-id-alt',
            'show_in_rest' => true,
        );
        
        register_post_type('vcard_profile', $args);
    }    

    public function register_meta_fields() {
        // Existing personal vCard fields
        $personal_fields = array(
            'first_name', 'last_name', 'company', 'job_title',
            'phone', 'email', 'website', 'address', 'city',
            'state', 'zip_code', 'country'
        );
        
        // New business-focused fields
        $business_fields = array(
            'business_name', 'business_tagline', 'business_description',
            'business_logo', 'cover_image', 'secondary_phone', 'whatsapp',
            'latitude', 'longitude'
        );
        
        // Template and customization fields
        $template_fields = array(
            'template_name', 'template_customizations', 'primary_color',
            'secondary_color', 'font_family', 'layout_options'
        );
        
        // Business hours (stored as JSON)
        $schedule_fields = array(
            'business_hours'
        );
        
        // Social media fields
        $social_fields = array(
            'facebook', 'instagram', 'linkedin', 'twitter',
            'youtube', 'tiktok'
        );
        
        // Analytics and subscription fields
        $system_fields = array(
            'profile_views', 'vcard_downloads', 'qr_scans', 'shares',
            'subscription_plan', 'subscription_status', 'subscription_expires'
        );
        
        // Complex data fields (stored as JSON)
        $json_fields = array(
            'services', 'products', 'gallery', 'vcard_config'
        );
        
        // Register all personal fields as strings
        foreach ($personal_fields as $field) {
            register_post_meta('vcard_profile', '_vcard_' . $field, array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ));
        }
        
        // Register business fields as strings
        foreach ($business_fields as $field) {
            register_post_meta('vcard_profile', '_vcard_' . $field, array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'sanitize_callback' => ($field === 'business_description') ? 'sanitize_textarea_field' : 'sanitize_text_field',
            ));
        }
        
        // Register template fields as strings
        foreach ($template_fields as $field) {
            register_post_meta('vcard_profile', '_vcard_' . $field, array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ));
        }
        
        // Register schedule fields as strings (JSON)
        foreach ($schedule_fields as $field) {
            register_post_meta('vcard_profile', '_vcard_' . $field, array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_json_field'),
            ));
        }
        
        // Register social media fields as strings
        foreach ($social_fields as $field) {
            register_post_meta('vcard_profile', '_vcard_social_' . $field, array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ));
        }
        
        // Register system fields with appropriate types
        register_post_meta('vcard_profile', '_vcard_profile_views', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'default' => 0,
        ));
        
        register_post_meta('vcard_profile', '_vcard_vcard_downloads', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'default' => 0,
        ));
        
        register_post_meta('vcard_profile', '_vcard_qr_scans', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'default' => 0,
        ));
        
        register_post_meta('vcard_profile', '_vcard_shares', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'default' => 0,
        ));
        
        register_post_meta('vcard_profile', '_vcard_subscription_plan', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => 'free',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_post_meta('vcard_profile', '_vcard_subscription_status', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => 'active',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_post_meta('vcard_profile', '_vcard_subscription_expires', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        // Register JSON fields
        foreach ($json_fields as $field) {
            register_post_meta('vcard_profile', '_vcard_' . $field, array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_json_field'),
            ));
        }
    }
    
    /**
     * Sanitize JSON field data
     */
    public function sanitize_json_field($value) {
        if (empty($value)) {
            return '';
        }
        
        // If it's already a string, try to decode and re-encode to validate
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return wp_json_encode($decoded);
            }
            return '';
        }
        
        // If it's an array or object, encode it
        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value);
        }
        
        return '';
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'vcard_details',
            __('vCard Details', 'vcard'),
            array($this, 'meta_box_callback'),
            'vcard_profile',
            'normal',
            'high'
        );
    }
    
    public function meta_box_callback($post) {
        wp_nonce_field('vcard_meta_box', 'vcard_meta_box_nonce');
        
        // Create tabbed interface for better organization
        echo '<div class="vcard-meta-tabs">';
        echo '<nav class="nav-tab-wrapper">';
        echo '<a href="#basic-info" class="nav-tab nav-tab-active">' . __('Basic Info', 'vcard') . '</a>';
        echo '<a href="#business-info" class="nav-tab">' . __('Business Info', 'vcard') . '</a>';
        echo '<a href="#contact-info" class="nav-tab">' . __('Contact Info', 'vcard') . '</a>';
        echo '<a href="#social-media" class="nav-tab">' . __('Social Media', 'vcard') . '</a>';
        echo '<a href="#template-settings" class="nav-tab">' . __('Template Settings', 'vcard') . '</a>';
        echo '<a href="#services-products" class="nav-tab">' . __('Services & Products', 'vcard') . '</a>';
        echo '</nav>';
        
        // Basic Info Tab
        echo '<div id="basic-info" class="tab-content active">';
        echo '<h3>' . __('Personal Information', 'vcard') . '</h3>';
        echo '<table class="form-table">';
        
        $basic_fields = array(
            'first_name' => __('First Name', 'vcard'),
            'last_name' => __('Last Name', 'vcard'),
            'company' => __('Company', 'vcard'),
            'job_title' => __('Job Title', 'vcard'),
        );
        
        foreach ($basic_fields as $key => $label) {
            $value = get_post_meta($post->ID, '_vcard_' . $key, true);
            echo '<tr>';
            echo '<th><label for="vcard_' . $key . '">' . $label . '</label></th>';
            echo '<td><input type="text" id="vcard_' . $key . '" name="vcard_' . $key . '" value="' . esc_attr($value) . '" class="regular-text" /></td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Business Info Tab
        echo '<div id="business-info" class="tab-content">';
        echo '<h3>' . __('Business Information', 'vcard') . '</h3>';
        echo '<table class="form-table">';
        
        $business_fields = array(
            'business_name' => __('Business Name', 'vcard'),
            'business_tagline' => __('Business Tagline', 'vcard'),
        );
        
        foreach ($business_fields as $key => $label) {
            $value = get_post_meta($post->ID, '_vcard_' . $key, true);
            echo '<tr>';
            echo '<th><label for="vcard_' . $key . '">' . $label . '</label></th>';
            echo '<td><input type="text" id="vcard_' . $key . '" name="vcard_' . $key . '" value="' . esc_attr($value) . '" class="regular-text" /></td>';
            echo '</tr>';
        }
        
        // Business Description (textarea)
        $business_description = get_post_meta($post->ID, '_vcard_business_description', true);
        echo '<tr>';
        echo '<th><label for="vcard_business_description">' . __('Business Description', 'vcard') . '</label></th>';
        echo '<td><textarea id="vcard_business_description" name="vcard_business_description" rows="4" class="large-text">' . esc_textarea($business_description) . '</textarea></td>';
        echo '</tr>';
        
        // Business Logo
        $business_logo = get_post_meta($post->ID, '_vcard_business_logo', true);
        echo '<tr>';
        echo '<th><label>' . __('Business Logo', 'vcard') . '</label></th>';
        echo '<td>';
        echo '<div class="image-selection-container">';
        echo '<input type="hidden" id="vcard_business_logo" name="vcard_business_logo" value="' . esc_attr($business_logo) . '">';
        echo '<div class="business-logo-preview">';
        if (!empty($business_logo)) {
            $logo_url = wp_get_attachment_image_url($business_logo, 'thumbnail');
            if ($logo_url) {
                echo '<img src="' . esc_url($logo_url) . '" alt="">';
            }
        }
        echo '</div>';
        echo '<button type="button" class="button select-business-logo"' . (empty($business_logo) ? '' : ' style="display:none;"') . '>' . __('Select Logo', 'vcard') . '</button>';
        echo '<button type="button" class="button remove-business-logo"' . (empty($business_logo) ? ' style="display:none;"' : '') . '>' . __('Remove Logo', 'vcard') . '</button>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        
        // Cover Image
        $cover_image = get_post_meta($post->ID, '_vcard_cover_image', true);
        echo '<tr>';
        echo '<th><label>' . __('Cover Image', 'vcard') . '</label></th>';
        echo '<td>';
        echo '<div class="image-selection-container">';
        echo '<input type="hidden" id="vcard_cover_image" name="vcard_cover_image" value="' . esc_attr($cover_image) . '">';
        echo '<div class="cover-image-preview">';
        if (!empty($cover_image)) {
            $cover_url = wp_get_attachment_image_url($cover_image, 'medium');
            if ($cover_url) {
                echo '<img src="' . esc_url($cover_url) . '" alt="">';
            }
        }
        echo '</div>';
        echo '<button type="button" class="button select-cover-image"' . (empty($cover_image) ? '' : ' style="display:none;"') . '>' . __('Select Cover Image', 'vcard') . '</button>';
        echo '<button type="button" class="button remove-cover-image"' . (empty($cover_image) ? ' style="display:none;"' : '') . '>' . __('Remove Cover Image', 'vcard') . '</button>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        
        // Business Hours (JSON field with UI)
        $business_hours = get_post_meta($post->ID, '_vcard_business_hours', true);
        $hours_data = !empty($business_hours) ? json_decode($business_hours, true) : array();
        
        echo '<tr>';
        echo '<th><label>' . __('Business Hours', 'vcard') . '</label></th>';
        echo '<td>';
        echo '<div class="business-hours-container">';
        
        $days = array(
            'monday' => __('Monday', 'vcard'),
            'tuesday' => __('Tuesday', 'vcard'),
            'wednesday' => __('Wednesday', 'vcard'),
            'thursday' => __('Thursday', 'vcard'),
            'friday' => __('Friday', 'vcard'),
            'saturday' => __('Saturday', 'vcard'),
            'sunday' => __('Sunday', 'vcard'),
        );
        
        foreach ($days as $day => $label) {
            $day_data = isset($hours_data[$day]) ? $hours_data[$day] : array('open' => '09:00', 'close' => '17:00', 'closed' => false);
            echo '<div class="business-hours-day">';
            echo '<label class="day-label">' . $label . '</label>';
            echo '<input type="checkbox" name="vcard_business_hours[' . $day . '][closed]" value="1" ' . checked(!empty($day_data['closed']), true, false) . '> ' . __('Closed', 'vcard');
            echo '<input type="time" name="vcard_business_hours[' . $day . '][open]" value="' . esc_attr($day_data['open']) . '" class="time-input">';
            echo '<span> - </span>';
            echo '<input type="time" name="vcard_business_hours[' . $day . '][close]" value="' . esc_attr($day_data['close']) . '" class="time-input">';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</div>';
        
        // Contact Info Tab
        echo '<div id="contact-info" class="tab-content">';
        echo '<h3>' . __('Contact Information', 'vcard') . '</h3>';
        echo '<table class="form-table">';
        
        $contact_fields = array(
            'phone' => __('Primary Phone', 'vcard'),
            'secondary_phone' => __('Secondary Phone', 'vcard'),
            'whatsapp' => __('WhatsApp Number', 'vcard'),
            'email' => __('Email', 'vcard'),
            'website' => __('Website', 'vcard'),
            'address' => __('Address', 'vcard'),
            'city' => __('City', 'vcard'),
            'state' => __('State', 'vcard'),
            'zip_code' => __('Zip Code', 'vcard'),
            'country' => __('Country', 'vcard'),
            'latitude' => __('Latitude', 'vcard'),
            'longitude' => __('Longitude', 'vcard'),
        );
        
        foreach ($contact_fields as $key => $label) {
            $value = get_post_meta($post->ID, '_vcard_' . $key, true);
            echo '<tr>';
            echo '<th><label for="vcard_' . $key . '">' . $label . '</label></th>';
            echo '<td><input type="text" id="vcard_' . $key . '" name="vcard_' . $key . '" value="' . esc_attr($value) . '" class="regular-text" /></td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Social Media Tab
        echo '<div id="social-media" class="tab-content">';
        echo '<h3>' . __('Social Media Links', 'vcard') . '</h3>';
        echo '<table class="form-table">';
        
        $social_fields = array(
            'facebook' => __('Facebook URL', 'vcard'),
            'instagram' => __('Instagram URL', 'vcard'),
            'linkedin' => __('LinkedIn URL', 'vcard'),
            'twitter' => __('Twitter URL', 'vcard'),
            'youtube' => __('YouTube URL', 'vcard'),
            'tiktok' => __('TikTok URL', 'vcard'),
        );
        
        foreach ($social_fields as $key => $label) {
            $value = get_post_meta($post->ID, '_vcard_social_' . $key, true);
            echo '<tr>';
            echo '<th><label for="vcard_social_' . $key . '">' . $label . '</label></th>';
            echo '<td><input type="url" id="vcard_social_' . $key . '" name="vcard_social_' . $key . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://" /></td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Template Settings Tab
        echo '<div id="template-settings" class="tab-content">';
        echo '<h3>' . __('Template & Customization', 'vcard') . '</h3>';
        echo '<table class="form-table">';
        
        // Template selection
        $template_name = get_post_meta($post->ID, '_vcard_template_name', true);
        if (empty($template_name)) {
            $template_name = 'ceo'; // Default template
        }
        
        $available_templates = array(
            'ceo' => __('CEO Template', 'vcard'),
            'freelancer' => __('Freelancer Template', 'vcard'),
            'restaurant' => __('Restaurant Template', 'vcard'),
            'construction' => __('Construction Template', 'vcard'),
            'education' => __('Education Template', 'vcard'),
            'fitness' => __('Fitness Template', 'vcard'),
            'coffeebar' => __('Coffee Bar Template', 'vcard'),
            'handyman' => __('Handyman Template', 'vcard'),
            'healthcare' => __('Healthcare Template', 'vcard'),
            'immigration' => __('Immigration Template', 'vcard'),
            'lawyer' => __('Lawyer Template', 'vcard'),
            'makeup-artist' => __('Makeup Artist Template', 'vcard'),
            'ngo' => __('NGO Template', 'vcard'),
            'saloon' => __('Saloon Template', 'vcard'),
            'tour' => __('Tour Template', 'vcard'),
        );
        
        echo '<tr>';
        echo '<th><label for="vcard_template_name">' . __('Template', 'vcard') . '</label></th>';
        echo '<td>';
        echo '<select id="vcard_template_name" name="vcard_template_name" class="regular-text">';
        foreach ($available_templates as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($template_name, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        // Color customization
        $primary_color = get_post_meta($post->ID, '_vcard_primary_color', true);
        $secondary_color = get_post_meta($post->ID, '_vcard_secondary_color', true);
        
        echo '<tr>';
        echo '<th><label for="vcard_primary_color">' . __('Primary Color', 'vcard') . '</label></th>';
        echo '<td><input type="color" id="vcard_primary_color" name="vcard_primary_color" value="' . esc_attr($primary_color ?: '#007cba') . '" /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="vcard_secondary_color">' . __('Secondary Color', 'vcard') . '</label></th>';
        echo '<td><input type="color" id="vcard_secondary_color" name="vcard_secondary_color" value="' . esc_attr($secondary_color ?: '#666666') . '" /></td>';
        echo '</tr>';
        
        // Font family
        $font_family = get_post_meta($post->ID, '_vcard_font_family', true);
        echo '<tr>';
        echo '<th><label for="vcard_font_family">' . __('Font Family', 'vcard') . '</label></th>';
        echo '<td>';
        echo '<select id="vcard_font_family" name="vcard_font_family" class="regular-text">';
        $fonts = array(
            'Arial, sans-serif' => 'Arial',
            'Helvetica, sans-serif' => 'Helvetica',
            'Georgia, serif' => 'Georgia',
            'Times New Roman, serif' => 'Times New Roman',
            'Roboto, sans-serif' => 'Roboto',
            'Open Sans, sans-serif' => 'Open Sans',
        );
        foreach ($fonts as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($font_family, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</div>';
        
        // Services & Products Tab
        echo '<div id="services-products" class="tab-content">';
        echo '<h3>' . __('Services & Products', 'vcard') . '</h3>';
        
        // Gallery section
        echo '<h4>' . __('Business Gallery', 'vcard') . '</h4>';
        $gallery = get_post_meta($post->ID, '_vcard_gallery', true);
        $gallery_ids = !empty($gallery) ? explode(',', $gallery) : array();
        
        echo '<div class="gallery-container">';
        echo '<div class="gallery-images">';
        echo '<input type="hidden" name="vcard_gallery" class="gallery-ids" value="' . esc_attr($gallery) . '">';
        echo '<div class="gallery-grid">';
        
        if (!empty($gallery_ids)) {
            foreach ($gallery_ids as $image_id) {
                if (!empty($image_id)) {
                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                    if ($image_url) {
                        echo '<div class="gallery-image" data-id="' . esc_attr($image_id) . '">';
                        echo '<img src="' . esc_url($image_url) . '" alt="">';
                        echo '<div class="gallery-image-actions">';
                        echo '<button type="button" class="remove-gallery-image">&times;</button>';
                        echo '</div>';
                        echo '</div>';
                    }
                }
            }
        }
        
        echo '</div>';
        echo '<button type="button" class="button add-gallery-image">' . __('Add Gallery Images', 'vcard') . '</button>';
        echo '</div>';
        echo '</div>';
        
        // Services section
        echo '<h4>' . __('Services', 'vcard') . '</h4>';
        $services = get_post_meta($post->ID, '_vcard_services', true);
        $services_data = !empty($services) ? json_decode($services, true) : array();
        
        echo '<div id="services-container">';
        echo '<div class="services-list">';
        if (!empty($services_data) && is_array($services_data)) {
            foreach ($services_data as $index => $service) {
                $this->render_enhanced_service_item($index, $service);
            }
        }
        echo '</div>';
        echo '<button type="button" class="button add-service">' . __('Add Service', 'vcard') . '</button>';
        echo '</div>';
        
        // Products section
        echo '<h4>' . __('Products', 'vcard') . '</h4>';
        $products = get_post_meta($post->ID, '_vcard_products', true);
        $products_data = !empty($products) ? json_decode($products, true) : array();
        
        echo '<div id="products-container">';
        echo '<div class="products-list">';
        if (!empty($products_data) && is_array($products_data)) {
            foreach ($products_data as $index => $product) {
                $this->render_enhanced_product_item($index, $product);
            }
        }
        echo '</div>';
        echo '<button type="button" class="button add-product">' . __('Add Product', 'vcard') . '</button>';
        echo '</div>';
        
        echo '</div>';
        
        echo '</div>'; // Close tabs container
    }
    
    /**
     * Render service item in meta box
     */
    private function render_service_item($index, $service) {
        echo '<div class="service-item">';
        echo '<a href="#" class="remove-service remove-item">' . __('Remove', 'vcard') . '</a>';
        echo '<h5>' . __('Service', 'vcard') . ' #' . ($index + 1) . '</h5>';
        echo '<p>';
        echo '<label>' . __('Name:', 'vcard') . '</label><br>';
        echo '<input type="text" name="vcard_services[' . $index . '][name]" value="' . esc_attr($service['name'] ?? '') . '" class="regular-text">';
        echo '</p>';
        echo '<p>';
        echo '<label>' . __('Description:', 'vcard') . '</label><br>';
        echo '<textarea name="vcard_services[' . $index . '][description]" rows="3" class="large-text">' . esc_textarea($service['description'] ?? '') . '</textarea>';
        echo '</p>';
        echo '<p>';
        echo '<label>' . __('Price:', 'vcard') . '</label><br>';
        echo '<input type="text" name="vcard_services[' . $index . '][price]" value="' . esc_attr($service['price'] ?? '') . '" class="regular-text">';
        echo '</p>';
        echo '<p>';
        echo '<label>' . __('Category:', 'vcard') . '</label><br>';
        echo '<input type="text" name="vcard_services[' . $index . '][category]" value="' . esc_attr($service['category'] ?? '') . '" class="regular-text">';
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Render product item in meta box
     */
    private function render_product_item($index, $product) {
        echo '<div class="product-item">';
        echo '<a href="#" class="remove-product remove-item">' . __('Remove', 'vcard') . '</a>';
        echo '<h5>' . __('Product', 'vcard') . ' #' . ($index + 1) . '</h5>';
        echo '<p>';
        echo '<label>' . __('Name:', 'vcard') . '</label><br>';
        echo '<input type="text" name="vcard_products[' . $index . '][name]" value="' . esc_attr($product['name'] ?? '') . '" class="regular-text">';
        echo '</p>';
        echo '<p>';
        echo '<label>' . __('Description:', 'vcard') . '</label><br>';
        echo '<textarea name="vcard_products[' . $index . '][description]" rows="3" class="large-text">' . esc_textarea($product['description'] ?? '') . '</textarea>';
        echo '</p>';
        echo '<p>';
        echo '<label>' . __('Price:', 'vcard') . '</label><br>';
        echo '<input type="text" name="vcard_products[' . $index . '][price]" value="' . esc_attr($product['price'] ?? '') . '" class="regular-text">';
        echo '</p>';
        echo '<p>';
        echo '<label>' . __('Category:', 'vcard') . '</label><br>';
        echo '<input type="text" name="vcard_products[' . $index . '][category]" value="' . esc_attr($product['category'] ?? '') . '" class="regular-text">';
        echo '</p>';
        echo '<p>';
        echo '<label>';
        echo '<input type="checkbox" name="vcard_products[' . $index . '][in_stock]" value="1" ' . checked(!empty($product['in_stock']), true, false) . '>';
        echo ' ' . __('In Stock', 'vcard');
        echo '</label>';
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Render enhanced service item in meta box
     */
    private function render_enhanced_service_item($index, $service) {
        echo '<div class="service-item">';
        echo '<div class="service-header">';
        echo '<h5>' . __('Service', 'vcard') . ' #' . ($index + 1) . '</h5>';
        echo '<a href="#" class="remove-service remove-item">' . __('Remove', 'vcard') . '</a>';
        echo '</div>';
        echo '<div class="service-fields">';
        
        echo '<div class="field-row">';
        echo '<div class="field-col">';
        echo '<label>' . __('Service Name', 'vcard') . ':</label>';
        echo '<input type="text" name="vcard_services[' . $index . '][name]" value="' . esc_attr($service['name'] ?? '') . '" class="regular-text" required>';
        echo '</div>';
        echo '<div class="field-col">';
        echo '<label>' . __('Price', 'vcard') . ':</label>';
        echo '<input type="text" name="vcard_services[' . $index . '][price]" value="' . esc_attr($service['price'] ?? '') . '" class="regular-text" placeholder="$0.00">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="field-row">';
        echo '<div class="field-col">';
        echo '<label>' . __('Category', 'vcard') . ':</label>';
        echo '<input type="text" name="vcard_services[' . $index . '][category]" value="' . esc_attr($service['category'] ?? '') . '" class="regular-text">';
        echo '</div>';
        echo '<div class="field-col">';
        echo '<label>' . __('Duration', 'vcard') . ':</label>';
        echo '<input type="text" name="vcard_services[' . $index . '][duration]" value="' . esc_attr($service['duration'] ?? '') . '" class="regular-text" placeholder="60 min">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="field-row">';
        echo '<div class="field-col full-width">';
        echo '<label>' . __('Description', 'vcard') . ':</label>';
        echo '<textarea name="vcard_services[' . $index . '][description]" rows="3" class="large-text">' . esc_textarea($service['description'] ?? '') . '</textarea>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render enhanced product item in meta box
     */
    private function render_enhanced_product_item($index, $product) {
        echo '<div class="product-item">';
        echo '<div class="product-header">';
        echo '<h5>' . __('Product', 'vcard') . ' #' . ($index + 1) . '</h5>';
        echo '<a href="#" class="remove-product remove-item">' . __('Remove', 'vcard') . '</a>';
        echo '</div>';
        echo '<div class="product-fields">';
        
        echo '<div class="field-row">';
        echo '<div class="field-col">';
        echo '<label>' . __('Product Name', 'vcard') . ':</label>';
        echo '<input type="text" name="vcard_products[' . $index . '][name]" value="' . esc_attr($product['name'] ?? '') . '" class="regular-text" required>';
        echo '</div>';
        echo '<div class="field-col">';
        echo '<label>' . __('Price', 'vcard') . ':</label>';
        echo '<input type="text" name="vcard_products[' . $index . '][price]" value="' . esc_attr($product['price'] ?? '') . '" class="regular-text" placeholder="$0.00">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="field-row">';
        echo '<div class="field-col">';
        echo '<label>' . __('Category', 'vcard') . ':</label>';
        echo '<input type="text" name="vcard_products[' . $index . '][category]" value="' . esc_attr($product['category'] ?? '') . '" class="regular-text">';
        echo '</div>';
        echo '<div class="field-col">';
        echo '<label>' . __('SKU', 'vcard') . ':</label>';
        echo '<input type="text" name="vcard_products[' . $index . '][sku]" value="' . esc_attr($product['sku'] ?? '') . '" class="regular-text">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="field-row">';
        echo '<div class="field-col">';
        echo '<label>';
        echo '<input type="checkbox" name="vcard_products[' . $index . '][in_stock]" value="1" ' . checked(!empty($product['in_stock']), true, false) . '>';
        echo ' ' . __('In Stock', 'vcard');
        echo '</label>';
        echo '</div>';
        echo '<div class="field-col">';
        echo '<label>';
        echo '<input type="checkbox" name="vcard_products[' . $index . '][featured]" value="1" ' . checked(!empty($product['featured']), true, false) . '>';
        echo ' ' . __('Featured', 'vcard');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="field-row">';
        echo '<div class="field-col full-width">';
        echo '<label>' . __('Description', 'vcard') . ':</label>';
        echo '<textarea name="vcard_products[' . $index . '][description]" rows="3" class="large-text">' . esc_textarea($product['description'] ?? '') . '</textarea>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="field-row">';
        echo '<div class="field-col full-width">';
        echo '<label>' . __('Product Image', 'vcard') . ':</label>';
        echo '<div class="product-image-container">';
        echo '<input type="hidden" name="vcard_products[' . $index . '][image_id]" class="product-image-id" value="' . esc_attr($product['image_id'] ?? '') . '">';
        echo '<div class="product-image-preview">';
        if (!empty($product['image_id'])) {
            $image_url = wp_get_attachment_image_url($product['image_id'], 'thumbnail');
            if ($image_url) {
                echo '<img src="' . esc_url($image_url) . '" alt="">';
            }
        }
        echo '</div>';
        echo '<button type="button" class="button select-product-image"' . (empty($product['image_id']) ? '' : ' style="display:none;"') . '>' . __('Select Image', 'vcard') . '</button>';
        echo '<button type="button" class="button remove-product-image"' . (empty($product['image_id']) ? ' style="display:none;"' : '') . '>' . __('Remove Image', 'vcard') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    public function save_meta_fields($post_id) {
        if (!isset($_POST['vcard_meta_box_nonce']) || !wp_verify_nonce($_POST['vcard_meta_box_nonce'], 'vcard_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Personal fields
        $personal_fields = array('first_name', 'last_name', 'company', 'job_title');
        foreach ($personal_fields as $field) {
            if (isset($_POST['vcard_' . $field])) {
                update_post_meta($post_id, '_vcard_' . $field, sanitize_text_field($_POST['vcard_' . $field]));
            }
        }
        
        // Business fields
        $business_fields = array('business_name', 'business_tagline', 'business_logo', 'cover_image');
        foreach ($business_fields as $field) {
            if (isset($_POST['vcard_' . $field])) {
                update_post_meta($post_id, '_vcard_' . $field, sanitize_text_field($_POST['vcard_' . $field]));
            }
        }
        
        // Business description (textarea)
        if (isset($_POST['vcard_business_description'])) {
            update_post_meta($post_id, '_vcard_business_description', sanitize_textarea_field($_POST['vcard_business_description']));
        }
        
        // Contact fields
        $contact_fields = array('phone', 'secondary_phone', 'whatsapp', 'email', 'website', 'address', 'city', 'state', 'zip_code', 'country', 'latitude', 'longitude');
        foreach ($contact_fields as $field) {
            if (isset($_POST['vcard_' . $field])) {
                $value = $_POST['vcard_' . $field];
                if ($field === 'email') {
                    $value = sanitize_email($value);
                } elseif ($field === 'website') {
                    $value = esc_url_raw($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                update_post_meta($post_id, '_vcard_' . $field, $value);
            }
        }
        
        // Social media fields
        $social_fields = array('facebook', 'instagram', 'linkedin', 'twitter', 'youtube', 'tiktok');
        foreach ($social_fields as $field) {
            if (isset($_POST['vcard_social_' . $field])) {
                update_post_meta($post_id, '_vcard_social_' . $field, esc_url_raw($_POST['vcard_social_' . $field]));
            }
        }
        
        // Template settings
        $template_fields = array('template_name', 'primary_color', 'secondary_color', 'font_family');
        foreach ($template_fields as $field) {
            if (isset($_POST['vcard_' . $field])) {
                update_post_meta($post_id, '_vcard_' . $field, sanitize_text_field($_POST['vcard_' . $field]));
            }
        }
        
        // Business hours (JSON)
        if (isset($_POST['vcard_business_hours']) && is_array($_POST['vcard_business_hours'])) {
            $business_hours = array();
            foreach ($_POST['vcard_business_hours'] as $day => $hours) {
                $business_hours[sanitize_key($day)] = array(
                    'open' => sanitize_text_field($hours['open'] ?? '09:00'),
                    'close' => sanitize_text_field($hours['close'] ?? '17:00'),
                    'closed' => !empty($hours['closed'])
                );
            }
            update_post_meta($post_id, '_vcard_business_hours', wp_json_encode($business_hours));
        }
        
        // Gallery
        if (isset($_POST['vcard_gallery'])) {
            $gallery_ids = sanitize_text_field($_POST['vcard_gallery']);
            // Validate that all IDs are valid attachment IDs
            if (!empty($gallery_ids)) {
                $ids = explode(',', $gallery_ids);
                $valid_ids = array();
                foreach ($ids as $id) {
                    $id = intval($id);
                    if ($id > 0 && wp_attachment_is_image($id)) {
                        $valid_ids[] = $id;
                    }
                }
                update_post_meta($post_id, '_vcard_gallery', implode(',', $valid_ids));
            } else {
                update_post_meta($post_id, '_vcard_gallery', '');
            }
        }
        
        // Services (JSON)
        if (isset($_POST['vcard_services']) && is_array($_POST['vcard_services'])) {
            $services = array();
            foreach ($_POST['vcard_services'] as $service) {
                if (!empty($service['name'])) {
                    $services[] = array(
                        'id' => uniqid(),
                        'name' => sanitize_text_field($service['name']),
                        'description' => sanitize_textarea_field($service['description'] ?? ''),
                        'price' => sanitize_text_field($service['price'] ?? ''),
                        'category' => sanitize_text_field($service['category'] ?? ''),
                        'duration' => sanitize_text_field($service['duration'] ?? ''),
                    );
                }
            }
            update_post_meta($post_id, '_vcard_services', wp_json_encode($services));
        }
        
        // Products (JSON)
        if (isset($_POST['vcard_products']) && is_array($_POST['vcard_products'])) {
            $products = array();
            foreach ($_POST['vcard_products'] as $product) {
                if (!empty($product['name'])) {
                    $image_id = !empty($product['image_id']) ? intval($product['image_id']) : 0;
                    // Validate image ID
                    if ($image_id > 0 && !wp_attachment_is_image($image_id)) {
                        $image_id = 0;
                    }
                    
                    $products[] = array(
                        'id' => uniqid(),
                        'name' => sanitize_text_field($product['name']),
                        'description' => sanitize_textarea_field($product['description'] ?? ''),
                        'price' => sanitize_text_field($product['price'] ?? ''),
                        'category' => sanitize_text_field($product['category'] ?? ''),
                        'sku' => sanitize_text_field($product['sku'] ?? ''),
                        'in_stock' => !empty($product['in_stock']),
                        'featured' => !empty($product['featured']),
                        'image_id' => $image_id,
                    );
                }
            }
            update_post_meta($post_id, '_vcard_products', wp_json_encode($products));
        }
        
        // Initialize analytics fields if they don't exist
        $analytics_fields = array('profile_views', 'vcard_downloads', 'qr_scans', 'shares');
        foreach ($analytics_fields as $field) {
            if (get_post_meta($post_id, '_vcard_' . $field, true) === '') {
                update_post_meta($post_id, '_vcard_' . $field, 0);
            }
        }
        
        // Initialize subscription fields if they don't exist
        if (get_post_meta($post_id, '_vcard_subscription_plan', true) === '') {
            update_post_meta($post_id, '_vcard_subscription_plan', 'free');
        }
        if (get_post_meta($post_id, '_vcard_subscription_status', true) === '') {
            update_post_meta($post_id, '_vcard_subscription_status', 'active');
        }
    }
    
    public function load_templates($template) {
        if (is_post_type_archive('vcard_profile')) {
            $plugin_template = VCARD_PLUGIN_PATH . 'templates/archive-vcard_profile.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        if (is_singular('vcard_profile')) {
            $plugin_template = VCARD_PLUGIN_PATH . 'templates/single-vcard_profile.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Enqueue public scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on vCard pages
        if (is_post_type_archive('vcard_profile') || is_singular('vcard_profile')) {
            // Enqueue CSS with cache busting
            wp_enqueue_style('vcard-public-style', VCARD_ASSETS_URL . 'css/public.css', array(), VCARD_VERSION . '-' . time());
            
            // Fallback: also enqueue the main style.css if public.css fails
            if (file_exists(VCARD_PLUGIN_PATH . 'assets/style.css')) {
                wp_enqueue_style('vcard-fallback-style', VCARD_ASSETS_URL . 'style.css', array(), VCARD_VERSION . '-' . time());
            }
            
            wp_enqueue_script('vcard-public-script', VCARD_ASSETS_URL . 'js/public.js', array('jquery'), VCARD_VERSION, true);
            
            // Localize script for AJAX
            wp_localize_script('vcard-public-script', 'vcard_public', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vcard_public_nonce'),
                'strings' => array(
                    'download_vcard' => __('Download vCard', 'vcard'),
                    'share_profile' => __('Share Profile', 'vcard'),
                    'save_contact' => __('Save Contact', 'vcard'),
                )
            ));
            
            // Debug: Log the CSS URL for troubleshooting
            error_log('vCard CSS URL: ' . VCARD_ASSETS_URL . 'css/public.css');
            
            // Add critical inline CSS as fallback
            $inline_css = "
                .vcard-single-container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .vcard-single { background: #fff; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; }
                .vcard-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; text-align: center; }
                .vcard-name { font-size: 2.2em; margin: 0 0 10px 0; font-weight: 300; }
                .vcard-title { font-size: 1.1em; opacity: 0.9; margin: 0; font-weight: 300; }
                .vcard-content { padding: 30px; }
                .vcard-contact-info h3, .vcard-address h3 { color: #333; font-size: 1.3em; margin: 0 0 15px 0; padding-bottom: 8px; border-bottom: 2px solid #667eea; }
                .vcard-contact-item { margin: 8px 0; display: flex; align-items: center; }
                .vcard-contact-item strong { min-width: 80px; color: #555; }
                .vcard-contact-item a { color: #667eea; text-decoration: none; }
                .vcard-contact-item a:hover { text-decoration: underline; }
                .vcard-address-details { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #667eea; }
                .vcard-actions { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
                .vcard-download-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 30px; border-radius: 25px; font-size: 1em; cursor: pointer; transition: transform 0.2s ease; }
                .vcard-download-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
            ";
            wp_add_inline_style('vcard-public-style', $inline_css);
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Register post type and flush rewrite rules
        $this->register_post_type();
        $this->register_meta_fields();
        flush_rewrite_rules();
        
        // Create custom database tables
        $this->create_custom_tables();
        
        // Create custom user roles
        $this->create_user_roles();
        
        // Set default options
        $this->set_default_options();
        
        // Run data migration for existing vCard profiles
        $this->migrate_existing_vcard_data();
        
        // Update version
        update_option('vcard_version', VCARD_VERSION);
        update_option('vcard_activation_time', current_time('timestamp'));
    }
    
    /**
     * Migrate existing vCard data to new business-focused structure
     */
    private function migrate_existing_vcard_data() {
        // Get all existing vCard profiles
        $existing_profiles = get_posts(array(
            'post_type' => 'vcard_profile',
            'numberposts' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_vcard_migrated',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        foreach ($existing_profiles as $profile) {
            $this->migrate_single_profile($profile->ID);
        }
        
        // Log migration completion
        error_log('vCard Plugin: Migrated ' . count($existing_profiles) . ' existing profiles to new business structure');
    }
    
    /**
     * Migrate a single vCard profile to new structure
     */
    private function migrate_single_profile($post_id) {
        // Check if already migrated
        if (get_post_meta($post_id, '_vcard_migrated', true)) {
            return;
        }
        
        // Preserve existing personal data (already in correct format)
        // No changes needed for: first_name, last_name, company, job_title, phone, email, website, address, city, state, zip_code, country
        
        // Set default business fields if not present
        $business_defaults = array(
            'business_name' => get_post_meta($post_id, '_vcard_company', true) ?: get_the_title($post_id),
            'business_tagline' => '',
            'business_description' => get_post_meta($post_id, '_vcard_job_title', true) ?: '',
            'business_logo' => '',
            'cover_image' => '',
            'secondary_phone' => '',
            'whatsapp' => get_post_meta($post_id, '_vcard_phone', true) ?: '',
            'latitude' => '',
            'longitude' => '',
        );
        
        foreach ($business_defaults as $key => $default_value) {
            if (!get_post_meta($post_id, '_vcard_' . $key, true)) {
                update_post_meta($post_id, '_vcard_' . $key, $default_value);
            }
        }
        
        // Set default template settings
        $template_defaults = array(
            'template_name' => 'ceo',
            'primary_color' => '#007cba',
            'secondary_color' => '#666666',
            'font_family' => 'Arial, sans-serif',
        );
        
        foreach ($template_defaults as $key => $default_value) {
            if (!get_post_meta($post_id, '_vcard_' . $key, true)) {
                update_post_meta($post_id, '_vcard_' . $key, $default_value);
            }
        }
        
        // Initialize empty social media fields
        $social_fields = array('facebook', 'instagram', 'linkedin', 'twitter', 'youtube', 'tiktok');
        foreach ($social_fields as $field) {
            if (!get_post_meta($post_id, '_vcard_social_' . $field, true)) {
                update_post_meta($post_id, '_vcard_social_' . $field, '');
            }
        }
        
        // Initialize default business hours (9 AM to 5 PM, Monday to Friday)
        if (!get_post_meta($post_id, '_vcard_business_hours', true)) {
            $default_hours = array(
                'monday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'tuesday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'wednesday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'thursday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'friday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'saturday' => array('open' => '09:00', 'close' => '17:00', 'closed' => true),
                'sunday' => array('open' => '09:00', 'close' => '17:00', 'closed' => true),
            );
            update_post_meta($post_id, '_vcard_business_hours', wp_json_encode($default_hours));
        }
        
        // Initialize empty services and products arrays
        if (!get_post_meta($post_id, '_vcard_services', true)) {
            update_post_meta($post_id, '_vcard_services', wp_json_encode(array()));
        }
        
        if (!get_post_meta($post_id, '_vcard_products', true)) {
            update_post_meta($post_id, '_vcard_products', wp_json_encode(array()));
        }
        
        if (!get_post_meta($post_id, '_vcard_gallery', true)) {
            update_post_meta($post_id, '_vcard_gallery', wp_json_encode(array()));
        }
        
        // Initialize analytics fields
        $analytics_fields = array('profile_views', 'vcard_downloads', 'qr_scans', 'shares');
        foreach ($analytics_fields as $field) {
            if (!get_post_meta($post_id, '_vcard_' . $field, true)) {
                update_post_meta($post_id, '_vcard_' . $field, 0);
            }
        }
        
        // Initialize subscription fields
        if (!get_post_meta($post_id, '_vcard_subscription_plan', true)) {
            update_post_meta($post_id, '_vcard_subscription_plan', 'free');
        }
        
        if (!get_post_meta($post_id, '_vcard_subscription_status', true)) {
            update_post_meta($post_id, '_vcard_subscription_status', 'active');
        }
        
        // Initialize vCard config
        if (!get_post_meta($post_id, '_vcard_vcard_config', true)) {
            $default_config = array(
                'include_logo' => true,
                'include_social_media' => true,
                'include_services' => false,
                'export_format' => 'vcf',
                'custom_fields' => array()
            );
            update_post_meta($post_id, '_vcard_vcard_config', wp_json_encode($default_config));
        }
        
        // Mark as migrated
        update_post_meta($post_id, '_vcard_migrated', true);
        update_post_meta($post_id, '_vcard_migration_date', current_time('mysql'));
        
        // Log individual migration
        error_log('vCard Plugin: Migrated profile ID ' . $post_id . ' (' . get_the_title($post_id) . ')');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear scheduled events
        wp_clear_scheduled_hook('vcard_cleanup_expired_subscriptions');
        wp_clear_scheduled_hook('vcard_analytics_cleanup');
    }
    
    /**
     * Plugin uninstall (static method)
     */
    public static function uninstall() {
        // Remove custom database tables
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS " . VCARD_ANALYTICS_TABLE);
        $wpdb->query("DROP TABLE IF EXISTS " . VCARD_SUBSCRIPTIONS_TABLE);
        $wpdb->query("DROP TABLE IF EXISTS " . VCARD_SAVED_CONTACTS_TABLE);
        
        // Remove custom user roles
        remove_role('vcard_client');
        remove_role('vcard_user');
        
        // Remove plugin options
        delete_option('vcard_version');
        delete_option('vcard_activation_time');
        delete_option('vcard_settings');
        
        // Remove all vCard posts and meta
        $posts = get_posts(array(
            'post_type' => 'vcard_profile',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($posts as $post) {
            wp_delete_post($post->ID, true);
        }
    }
    
    /**
     * Create custom database tables for specialized data only
     * Core profile data remains in WordPress post meta for better integration
     */
    private function create_custom_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Analytics table - for tracking profile views, downloads, and shares
        // This data is performance-critical and needs efficient querying
        $analytics_table = "CREATE TABLE " . VCARD_ANALYTICS_TABLE . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            event_type varchar(50) NOT NULL COMMENT 'view, download, qr_scan, share',
            event_data longtext COMMENT 'JSON data for additional event information',
            ip_address varchar(45) COMMENT 'Visitor IP address for analytics',
            user_agent text COMMENT 'Browser user agent string',
            referrer varchar(255) COMMENT 'Referrer URL',
            session_id varchar(64) COMMENT 'Session identifier for unique visitor tracking',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY session_id (session_id),
            KEY profile_event (profile_id, event_type)
        ) $charset_collate COMMENT='Analytics tracking for vCard profiles';";
        
        // Subscriptions table - for billing management
        // Separate from WordPress users for billing-specific data
        $subscriptions_table = "CREATE TABLE " . VCARD_SUBSCRIPTIONS_TABLE . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL COMMENT 'WordPress user ID',
            plan varchar(50) NOT NULL COMMENT 'free, basic, professional',
            status varchar(20) NOT NULL COMMENT 'active, expired, cancelled, suspended',
            payment_method varchar(50) COMMENT 'Payment gateway method',
            payment_id varchar(100) COMMENT 'External payment system ID',
            amount decimal(10,2) COMMENT 'Subscription amount',
            currency varchar(3) DEFAULT 'USD' COMMENT 'Currency code',
            billing_cycle varchar(20) COMMENT 'monthly, yearly',
            next_billing_date datetime COMMENT 'Next billing date',
            expires_at datetime COMMENT 'Subscription expiration date',
            cancelled_at datetime COMMENT 'Cancellation date',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY expires_at (expires_at),
            KEY next_billing_date (next_billing_date),
            UNIQUE KEY user_active_subscription (user_id, status)
        ) $charset_collate COMMENT='Subscription and billing management';";
        
        // Saved contacts table - for end user contact management
        // Allows registered users to save business contacts with personal notes
        $saved_contacts_table = "CREATE TABLE " . VCARD_SAVED_CONTACTS_TABLE . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL COMMENT 'WordPress user ID (end user)',
            profile_id bigint(20) NOT NULL COMMENT 'vCard profile ID being saved',
            notes text COMMENT 'Personal notes about this business contact',
            tags varchar(255) COMMENT 'Comma-separated tags for organization',
            is_favorite tinyint(1) DEFAULT 0 COMMENT 'Favorite contact flag',
            contact_frequency varchar(20) COMMENT 'never, rarely, sometimes, often',
            last_contacted datetime COMMENT 'Last contact date',
            reminder_date datetime COMMENT 'Follow-up reminder date',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY profile_id (profile_id),
            KEY is_favorite (is_favorite),
            KEY reminder_date (reminder_date),
            UNIQUE KEY user_profile (user_id, profile_id)
        ) $charset_collate COMMENT='End user saved business contacts';";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables and log results
        $analytics_result = dbDelta($analytics_table);
        $subscriptions_result = dbDelta($subscriptions_table);
        $saved_contacts_result = dbDelta($saved_contacts_table);
        
        // Log table creation for debugging
        error_log('vCard Plugin: Custom tables created');
        error_log('Analytics table: ' . print_r($analytics_result, true));
        error_log('Subscriptions table: ' . print_r($subscriptions_result, true));
        error_log('Saved contacts table: ' . print_r($saved_contacts_result, true));
        
        // Create indexes for better performance
        $this->create_custom_table_indexes();
    }
    
    /**
     * Create additional indexes for better performance
     */
    private function create_custom_table_indexes() {
        global $wpdb;
        
        // Additional indexes for analytics table
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_analytics_profile_date ON " . VCARD_ANALYTICS_TABLE . " (profile_id, created_at)");
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_analytics_event_date ON " . VCARD_ANALYTICS_TABLE . " (event_type, created_at)");
        
        // Additional indexes for subscriptions table
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_subscriptions_plan_status ON " . VCARD_SUBSCRIPTIONS_TABLE . " (plan, status)");
        
        // Additional indexes for saved contacts table
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_saved_contacts_user_created ON " . VCARD_SAVED_CONTACTS_TABLE . " (user_id, created_at)");
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_saved_contacts_profile_created ON " . VCARD_SAVED_CONTACTS_TABLE . " (profile_id, created_at)");
    }
    
    /**
     * Create custom user roles and capabilities for multi-tenant access control
     */
    private function create_user_roles() {
        // vCard Client role - Business owners who create and manage their business profiles
        add_role('vcard_client', __('vCard Business Client', 'vcard'), array(
            // Basic WordPress capabilities
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            
            // vCard profile capabilities (own profiles only)
            'edit_vcard_profiles' => true,
            'edit_own_vcard_profiles' => true,
            'delete_own_vcard_profiles' => true,
            'publish_vcard_profiles' => true,
            'read_vcard_profiles' => true,
            
            // Media and file capabilities
            'upload_files' => true,
            'edit_files' => false,
            'delete_files' => false,
            
            // vCard-specific business capabilities
            'manage_own_vcard_analytics' => true,
            'export_own_vcard_data' => true,
            'customize_own_vcard_template' => true,
            'manage_own_vcard_subscription' => true,
            'access_vcard_client_dashboard' => true,
            
            // Contact form and interaction capabilities
            'receive_vcard_inquiries' => true,
            'manage_vcard_contact_forms' => true,
        ));
        
        // vCard User role - End users who discover and save business contacts
        add_role('vcard_user', __('vCard User', 'vcard'), array(
            // Basic WordPress capabilities
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            
            // vCard viewing capabilities
            'read_vcard_profiles' => true,
            
            // Contact management capabilities
            'save_vcard_contacts' => true,
            'manage_own_saved_contacts' => true,
            'export_own_saved_contacts' => true,
            'download_vcard_files' => true,
            
            // Interaction capabilities
            'submit_vcard_inquiries' => true,
            'share_vcard_profiles' => true,
            'access_vcard_user_dashboard' => true,
        ));
        
        // Add comprehensive capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            // vCard profile management
            $admin_role->add_cap('edit_vcard_profiles');
            $admin_role->add_cap('edit_others_vcard_profiles');
            $admin_role->add_cap('delete_vcard_profiles');
            $admin_role->add_cap('delete_others_vcard_profiles');
            $admin_role->add_cap('publish_vcard_profiles');
            $admin_role->add_cap('read_private_vcard_profiles');
            
            // System administration
            $admin_role->add_cap('manage_vcard_settings');
            $admin_role->add_cap('manage_vcard_subscriptions');
            $admin_role->add_cap('view_all_vcard_analytics');
            $admin_role->add_cap('moderate_vcard_profiles');
            $admin_role->add_cap('suspend_vcard_clients');
            
            // Data management
            $admin_role->add_cap('export_all_vcard_data');
            $admin_role->add_cap('import_vcard_data');
            $admin_role->add_cap('cleanup_vcard_data');
            
            // Support and maintenance
            $admin_role->add_cap('access_vcard_support_tools');
            $admin_role->add_cap('manage_vcard_templates');
            $admin_role->add_cap('configure_vcard_billing');
        }
        
        // Add limited capabilities to editor role for content management
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->add_cap('edit_vcard_profiles');
            $editor_role->add_cap('edit_others_vcard_profiles');
            $editor_role->add_cap('publish_vcard_profiles');
            $editor_role->add_cap('read_private_vcard_profiles');
            $editor_role->add_cap('moderate_vcard_profiles');
        }
        
        // Ensure existing users get appropriate roles
        $this->migrate_existing_user_roles();
        
        // Log role creation
        error_log('vCard Plugin: Custom user roles and capabilities created');
    }
    
    /**
     * Migrate existing users to appropriate vCard roles
     */
    private function migrate_existing_user_roles() {
        // Find users who have created vCard profiles and assign them vcard_client role
        $profile_authors = get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'vcard_profile_count',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        // Also check for users who are authors of vcard_profile posts
        $vcard_posts = get_posts(array(
            'post_type' => 'vcard_profile',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        $profile_author_ids = array();
        foreach ($vcard_posts as $post) {
            $profile_author_ids[] = $post->post_author;
        }
        
        $profile_author_ids = array_unique($profile_author_ids);
        
        foreach ($profile_author_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            if ($user && !in_array('administrator', $user->roles) && !in_array('vcard_client', $user->roles)) {
                $user->add_role('vcard_client');
                error_log('vCard Plugin: Added vcard_client role to user ID ' . $user_id);
            }
        }
    }
    
    /**
     * Check if current user can edit specific vCard profile
     */
    public function current_user_can_edit_vcard_profile($profile_id) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $current_user = wp_get_current_user();
        $profile = get_post($profile_id);
        
        if (!$profile) {
            return false;
        }
        
        // Administrators and editors can edit all profiles
        if (current_user_can('manage_options') || current_user_can('edit_others_posts')) {
            return true;
        }
        
        // Check for specific vCard capabilities
        if (current_user_can('edit_others_vcard_profiles')) {
            return true;
        }
        
        // Users can edit their own profiles if they have the capability
        if (current_user_can('edit_own_vcard_profiles') || current_user_can('edit_posts')) {
            return $profile->post_author == $current_user->ID;
        }
        
        // Fallback: if user can edit posts and is the author
        if (current_user_can('edit_posts') && $profile->post_author == $current_user->ID) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Restrict vCard profile editing to owners only
     */
    public function restrict_vcard_profile_editing() {
        global $pagenow;
        
        // Only apply restrictions in admin area
        if (!is_admin()) {
            return;
        }
        
        // Only apply to post editing pages
        if (!in_array($pagenow, array('post.php', 'post-new.php'))) {
            return;
        }
        
        // Only apply to vcard_profile post type
        $post_id = 0;
        if (isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
        } elseif (isset($_POST['post_ID'])) {
            $post_id = intval($_POST['post_ID']);
        } elseif (isset($_GET['post_type']) && $_GET['post_type'] === 'vcard_profile') {
            // This is a new post creation, allow it for now
            return;
        }
        
        if (!$post_id) {
            return;
        }
        
        $post_obj = get_post($post_id);
        
        if (!$post_obj || $post_obj->post_type !== 'vcard_profile') {
            return;
        }
        
        // Skip restriction for administrators
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Check if current user can edit this profile
        if (!$this->current_user_can_edit_vcard_profile($post_id)) {
            wp_die(__('You do not have permission to edit this vCard profile.', 'vcard'));
        }
    }
    
    /**
     * Filter vCard profiles in admin list to show only user's own profiles
     */
    public function filter_vcard_profiles_by_user($query) {
        global $pagenow;
        
        // Only apply in admin area for vcard_profile post type
        if (!is_admin() || $pagenow !== 'edit.php') {
            return;
        }
        
        if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'vcard_profile') {
            return;
        }
        
        // Don't filter for administrators
        if (current_user_can('edit_others_vcard_profiles')) {
            return;
        }
        
        // Filter to show only current user's profiles
        if (current_user_can('edit_own_vcard_profiles')) {
            $query->set('author', get_current_user_id());
        }
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_settings = array(
            'enable_registration' => true,
            'default_template' => 'ceo',
            'enable_analytics' => true,
            'enable_subscriptions' => false,
            'free_plan_profiles_limit' => 1,
            'enable_qr_codes' => true,
            'enable_social_sharing' => true,
            'contact_form_enabled' => true,
            'analytics_retention_days' => 365,
        );
        
        add_option('vcard_settings', $default_settings);
    }
    
    /**
     * Load text domain for internationalization
     */
    public function load_textdomain() {
        load_plugin_textdomain('vcard', false, dirname(VCARD_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Add admin menu with role-based access
     */
    public function add_admin_menu() {
        // Main admin menu (for administrators only)
        if (current_user_can('manage_vcard_settings')) {
            add_menu_page(
                __('vCard Directory', 'vcard'),
                __('vCard Directory', 'vcard'),
                'manage_vcard_settings',
                'vcard-admin',
                array($this, 'admin_page'),
                'dashicons-id-alt',
                30
            );
            
            add_submenu_page(
                'vcard-admin',
                __('Settings', 'vcard'),
                __('Settings', 'vcard'),
                'manage_vcard_settings',
                'vcard-settings',
                array($this, 'settings_page')
            );
            
            add_submenu_page(
                'vcard-admin',
                __('Analytics', 'vcard'),
                __('Analytics', 'vcard'),
                'view_all_vcard_analytics',
                'vcard-analytics',
                array($this, 'analytics_page')
            );
            
            add_submenu_page(
                'vcard-admin',
                __('Subscriptions', 'vcard'),
                __('Subscriptions', 'vcard'),
                'manage_vcard_subscriptions',
                'vcard-subscriptions',
                array($this, 'subscriptions_page')
            );
        }
        
        // Business Client Dashboard (for vcard_client role)
        if (current_user_can('access_vcard_client_dashboard')) {
            add_menu_page(
                __('My Business Profile', 'vcard'),
                __('My vCard', 'vcard'),
                'access_vcard_client_dashboard',
                'vcard-client-dashboard',
                array($this, 'client_dashboard_page'),
                'dashicons-businessman',
                25
            );
            
            add_submenu_page(
                'vcard-client-dashboard',
                __('Profile Analytics', 'vcard'),
                __('Analytics', 'vcard'),
                'manage_own_vcard_analytics',
                'vcard-client-analytics',
                array($this, 'client_analytics_page')
            );
            
            add_submenu_page(
                'vcard-client-dashboard',
                __('Subscription', 'vcard'),
                __('Subscription', 'vcard'),
                'manage_own_vcard_subscription',
                'vcard-client-subscription',
                array($this, 'client_subscription_page')
            );
        }
        
        // End User Dashboard (for vcard_user role)
        if (current_user_can('access_vcard_user_dashboard')) {
            add_menu_page(
                __('My Saved Contacts', 'vcard'),
                __('Saved Contacts', 'vcard'),
                'access_vcard_user_dashboard',
                'vcard-user-dashboard',
                array($this, 'user_dashboard_page'),
                'dashicons-groups',
                25
            );
        }
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('vCard Directory Dashboard', 'vcard') . '</h1>';
        echo '<p>' . __('Welcome to the vCard Business Directory plugin dashboard.', 'vcard') . '</p>';
        echo '</div>';
    }
    
    /**
     * Settings page callback
     */
    public function settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('vCard Settings', 'vcard') . '</h1>';
        echo '<p>' . __('Plugin settings will be implemented in future tasks.', 'vcard') . '</p>';
        echo '</div>';
    }
    
    /**
     * Analytics page callback (admin)
     */
    public function analytics_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('vCard Analytics', 'vcard') . '</h1>';
        echo '<p>' . __('Platform-wide analytics will be implemented in future tasks.', 'vcard') . '</p>';
        echo '</div>';
    }
    
    /**
     * Subscriptions page callback (admin)
     */
    public function subscriptions_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Subscription Management', 'vcard') . '</h1>';
        echo '<p>' . __('Subscription management will be implemented in future tasks.', 'vcard') . '</p>';
        echo '</div>';
    }
    
    /**
     * Client dashboard page callback
     */
    public function client_dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('My Business Profile Dashboard', 'vcard') . '</h1>';
        echo '<p>' . __('Business client dashboard will be implemented in future tasks.', 'vcard') . '</p>';
        
        // Show user's vCard profiles
        $user_profiles = get_posts(array(
            'post_type' => 'vcard_profile',
            'author' => get_current_user_id(),
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        if ($user_profiles) {
            echo '<h2>' . __('Your Business Profiles', 'vcard') . '</h2>';
            echo '<ul>';
            foreach ($user_profiles as $profile) {
                echo '<li>';
                echo '<a href="' . get_edit_post_link($profile->ID) . '">' . esc_html($profile->post_title) . '</a>';
                echo ' - ' . ucfirst($profile->post_status);
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p><a href="' . admin_url('post-new.php?post_type=vcard_profile') . '" class="button button-primary">' . __('Create Your First Business Profile', 'vcard') . '</a></p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Client analytics page callback
     */
    public function client_analytics_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('My Profile Analytics', 'vcard') . '</h1>';
        echo '<p>' . __('Client analytics dashboard will be implemented in future tasks.', 'vcard') . '</p>';
        echo '</div>';
    }
    
    /**
     * Client subscription page callback
     */
    public function client_subscription_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('My Subscription', 'vcard') . '</h1>';
        echo '<p>' . __('Subscription management will be implemented in future tasks.', 'vcard') . '</p>';
        echo '</div>';
    }
    
    /**
     * User dashboard page callback
     */
    public function user_dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('My Saved Contacts', 'vcard') . '</h1>';
        echo '<p>' . __('Saved contacts management will be implemented in future tasks.', 'vcard') . '</p>';
        echo '</div>';
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        // Register settings will be implemented in future tasks
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        global $post_type, $post;
        
        // Only enqueue on vCard profile edit pages
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'vcard_profile') {
            // Enqueue enhanced admin styles and scripts
            wp_enqueue_style('vcard-admin-meta-box', VCARD_ASSETS_URL . 'css/admin-meta-box.css', array(), VCARD_VERSION);
            wp_enqueue_script('vcard-admin-meta-box', VCARD_ASSETS_URL . 'js/admin-meta-box.js', array('jquery', 'jquery-ui-sortable'), VCARD_VERSION, true);
            
            // Enqueue WordPress media library
            wp_enqueue_media();
            
            // Enqueue WordPress color picker
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            
            // Localize script for AJAX and translations
            wp_localize_script('vcard-admin-meta-box', 'vcardAdmin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vcard_admin_nonce'),
                'strings' => array(
                    'service' => __('Service', 'vcard'),
                    'product' => __('Product', 'vcard'),
                    'remove' => __('Remove', 'vcard'),
                    'serviceName' => __('Service Name', 'vcard'),
                    'servicePrice' => __('Price', 'vcard'),
                    'serviceCategory' => __('Category', 'vcard'),
                    'serviceDuration' => __('Duration', 'vcard'),
                    'serviceDescription' => __('Description', 'vcard'),
                    'productName' => __('Product Name', 'vcard'),
                    'productPrice' => __('Price', 'vcard'),
                    'productCategory' => __('Category', 'vcard'),
                    'productSKU' => __('SKU', 'vcard'),
                    'productDescription' => __('Description', 'vcard'),
                    'productImage' => __('Product Image', 'vcard'),
                    'inStock' => __('In Stock', 'vcard'),
                    'featured' => __('Featured', 'vcard'),
                    'selectImage' => __('Select Image', 'vcard'),
                    'removeImage' => __('Remove Image', 'vcard'),
                    'selectGalleryImages' => __('Select Gallery Images', 'vcard'),
                    'addToGallery' => __('Add to Gallery', 'vcard'),
                    'selectProductImage' => __('Select Product Image', 'vcard'),
                    'selectBusinessLogo' => __('Select Business Logo', 'vcard'),
                    'selectCoverImage' => __('Select Cover Image', 'vcard'),
                    'businessNameRequired' => __('Business name is required for business profiles.', 'vcard'),
                    'nameRequired' => __('First name and last name are required.', 'vcard'),
                    'emailRequired' => __('Email address is required.', 'vcard'),
                    'emailInvalid' => __('Please enter a valid email address.', 'vcard'),
                    'serviceNameRequired' => __('Service #%d name is required.', 'vcard'),
                    'productNameRequired' => __('Product #%d name is required.', 'vcard'),
                    'validationErrors' => __('Please fix the following errors:', 'vcard')
                )
            ));
        }
        
        // Enqueue on vCard admin pages
        if (strpos($hook, 'vcard') !== false) {
            wp_enqueue_style('vcard-admin-style', VCARD_ASSETS_URL . 'css/admin.css', array(), VCARD_VERSION);
            wp_enqueue_script('vcard-admin-script', VCARD_ASSETS_URL . 'js/admin.js', array('jquery'), VCARD_VERSION, true);
        }
    }
    
    // ========================================
    // Custom Table Helper Methods
    // ========================================
    
    /**
     * Record analytics event
     */
    public function record_analytics_event($profile_id, $event_type, $event_data = array()) {
        global $wpdb;
        
        $data = array(
            'profile_id' => intval($profile_id),
            'event_type' => sanitize_text_field($event_type),
            'event_data' => wp_json_encode($event_data),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referrer' => sanitize_text_field($_SERVER['HTTP_REFERER'] ?? ''),
            'session_id' => $this->get_session_id(),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(VCARD_ANALYTICS_TABLE, $data);
        
        if ($result === false) {
            error_log('vCard Plugin: Failed to record analytics event - ' . $wpdb->last_error);
        }
        
        return $result !== false;
    }
    
    /**
     * Get analytics data for a profile
     */
    public function get_profile_analytics($profile_id, $date_from = null, $date_to = null) {
        global $wpdb;
        
        $where_clause = $wpdb->prepare("WHERE profile_id = %d", $profile_id);
        
        if ($date_from) {
            $where_clause .= $wpdb->prepare(" AND created_at >= %s", $date_from);
        }
        
        if ($date_to) {
            $where_clause .= $wpdb->prepare(" AND created_at <= %s", $date_to);
        }
        
        $query = "SELECT event_type, COUNT(*) as count, DATE(created_at) as date 
                  FROM " . VCARD_ANALYTICS_TABLE . " 
                  {$where_clause} 
                  GROUP BY event_type, DATE(created_at) 
                  ORDER BY created_at DESC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Create or update subscription
     */
    public function create_subscription($user_id, $plan, $data = array()) {
        global $wpdb;
        
        $subscription_data = array_merge(array(
            'user_id' => intval($user_id),
            'plan' => sanitize_text_field($plan),
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ), $data);
        
        $result = $wpdb->insert(VCARD_SUBSCRIPTIONS_TABLE, $subscription_data);
        
        if ($result === false) {
            error_log('vCard Plugin: Failed to create subscription - ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get user subscription
     */
    public function get_user_subscription($user_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . VCARD_SUBSCRIPTIONS_TABLE . " 
             WHERE user_id = %d AND status = 'active' 
             ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
    }
    
    /**
     * Update subscription status
     */
    public function update_subscription_status($subscription_id, $status, $data = array()) {
        global $wpdb;
        
        $update_data = array_merge(array(
            'status' => sanitize_text_field($status),
            'updated_at' => current_time('mysql')
        ), $data);
        
        if ($status === 'cancelled') {
            $update_data['cancelled_at'] = current_time('mysql');
        }
        
        return $wpdb->update(
            VCARD_SUBSCRIPTIONS_TABLE,
            $update_data,
            array('id' => intval($subscription_id))
        );
    }
    
    /**
     * Save contact for user
     */
    public function save_contact($user_id, $profile_id, $notes = '', $tags = '') {
        global $wpdb;
        
        $data = array(
            'user_id' => intval($user_id),
            'profile_id' => intval($profile_id),
            'notes' => sanitize_textarea_field($notes),
            'tags' => sanitize_text_field($tags),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for upsert behavior
        $query = $wpdb->prepare(
            "INSERT INTO " . VCARD_SAVED_CONTACTS_TABLE . " 
             (user_id, profile_id, notes, tags, created_at, updated_at) 
             VALUES (%d, %d, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE 
             notes = VALUES(notes), 
             tags = VALUES(tags), 
             updated_at = VALUES(updated_at)",
            $data['user_id'],
            $data['profile_id'],
            $data['notes'],
            $data['tags'],
            $data['created_at'],
            $data['updated_at']
        );
        
        $result = $wpdb->query($query);
        
        if ($result === false) {
            error_log('vCard Plugin: Failed to save contact - ' . $wpdb->last_error);
        }
        
        return $result !== false;
    }
    
    /**
     * Get saved contacts for user
     */
    public function get_user_saved_contacts($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT sc.*, p.post_title as business_name, p.post_status
             FROM " . VCARD_SAVED_CONTACTS_TABLE . " sc
             LEFT JOIN {$wpdb->posts} p ON sc.profile_id = p.ID
             WHERE sc.user_id = %d AND p.post_type = 'vcard_profile'
             ORDER BY sc.updated_at DESC
             LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));
    }
    
    /**
     * Remove saved contact
     */
    public function remove_saved_contact($user_id, $profile_id) {
        global $wpdb;
        
        return $wpdb->delete(
            VCARD_SAVED_CONTACTS_TABLE,
            array(
                'user_id' => intval($user_id),
                'profile_id' => intval($profile_id)
            )
        );
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
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
     * Get or create session ID
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['vcard_session_id'])) {
            $_SESSION['vcard_session_id'] = wp_generate_uuid4();
        }
        
        return $_SESSION['vcard_session_id'];
    }
    
    /**
     * Handle vCard download tracking via AJAX
     */
    public function handle_track_download() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vcard_track_download')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id'] ?? 0);
        
        if ($profile_id && get_post_type($profile_id) === 'vcard_profile') {
            // Record analytics event
            $this->record_analytics_event($profile_id, 'download', array(
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql')
            ));
            
            // Update download counter
            $current_downloads = get_post_meta($profile_id, '_vcard_vcard_downloads', true) ?: 0;
            update_post_meta($profile_id, '_vcard_vcard_downloads', $current_downloads + 1);
            
            wp_send_json_success(array('message' => 'Download tracked successfully'));
        } else {
            wp_send_json_error(array('message' => 'Invalid profile ID'));
        }
    }
}

// Initialize the plugin
VCardPlugin::get_instance();
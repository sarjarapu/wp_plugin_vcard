<?php
/**
 * Plugin Name: vCard Business Directory
 * Plugin URI: https://example.com/vcard-plugin
 * Description: A comprehensive multi-tenant business directory platform that enables virtual business card exchange with template customization, contact management, and subscription billing.
 * Version: 1.0.1
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
define('VCARD_VERSION', '1.0.1');
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
        add_action('wp_ajax_track_vcard_download', array($this, 'handle_track_vcard_download'));
        add_action('wp_ajax_nopriv_track_vcard_download', array($this, 'handle_track_vcard_download'));
        add_action('wp_ajax_track_qr_generation', array($this, 'handle_track_qr_generation'));
        add_action('wp_ajax_nopriv_track_qr_generation', array($this, 'handle_track_qr_generation'));
        add_action('wp_ajax_submit_vcard_contact_form', array($this, 'handle_contact_form_submission'));
        add_action('wp_ajax_nopriv_submit_vcard_contact_form', array($this, 'handle_contact_form_submission'));
        
        // Enhanced vCard export AJAX hooks
        add_action('wp_ajax_get_vcard_export_data', array($this, 'handle_get_vcard_export_data'));
        add_action('wp_ajax_nopriv_get_vcard_export_data', array($this, 'handle_get_vcard_export_data'));
        add_action('wp_ajax_bulk_vcard_export', array($this, 'handle_bulk_vcard_export'));
        add_action('wp_ajax_nopriv_bulk_vcard_export', array($this, 'handle_bulk_vcard_export'));
        
        // vCard sharing and QR code AJAX hooks
        add_action('wp_ajax_generate_vcard_qr', array($this, 'handle_generate_qr_code'));
        add_action('wp_ajax_nopriv_generate_vcard_qr', array($this, 'handle_generate_qr_code'));
        add_action('wp_ajax_get_vcard_sharing_links', array($this, 'handle_get_sharing_links'));
        add_action('wp_ajax_nopriv_get_vcard_sharing_links', array($this, 'handle_get_sharing_links'));
        add_action('wp_ajax_generate_short_url', array($this, 'handle_generate_short_url'));
        add_action('wp_ajax_nopriv_generate_short_url', array($this, 'handle_generate_short_url'));
        add_action('wp_ajax_track_vcard_share', array($this, 'handle_track_share'));
        add_action('wp_ajax_nopriv_track_vcard_share', array($this, 'handle_track_share'));
        add_action('wp_ajax_get_embed_code', array($this, 'handle_get_embed_code'));
        add_action('wp_ajax_nopriv_get_embed_code', array($this, 'handle_get_embed_code'));
        add_action('wp_ajax_download_qr_code', array($this, 'handle_download_qr_code'));
        add_action('wp_ajax_nopriv_download_qr_code', array($this, 'handle_download_qr_code'));
        add_action('wp_ajax_track_vcard_event', array($this, 'handle_track_event'));
        add_action('wp_ajax_nopriv_track_vcard_event', array($this, 'handle_track_event'));
        add_action('wp_ajax_save_vcard_contact', array($this, 'handle_save_vcard_contact'));
        add_action('wp_ajax_nopriv_save_vcard_contact', array($this, 'handle_save_vcard_contact'));
        
        // Contact management AJAX hooks
        add_action('wp_ajax_vcard_track_event', array($this, 'handle_track_event'));
        add_action('wp_ajax_nopriv_vcard_track_event', array($this, 'handle_track_event'));
        add_action('wp_ajax_vcard_contact_form', array($this, 'handle_contact_form_submission'));
        add_action('wp_ajax_nopriv_vcard_contact_form', array($this, 'handle_contact_form_submission'));
        
        // Modern UX enhancements AJAX hooks
        add_action('wp_ajax_vcard_modern_ux_track_event', array($this, 'handle_modern_ux_track_event'));
        add_action('wp_ajax_nopriv_vcard_modern_ux_track_event', array($this, 'handle_modern_ux_track_event'));
        add_action('wp_ajax_vcard_track_section_view', array($this, 'handle_track_section_view'));
        add_action('wp_ajax_nopriv_vcard_track_section_view', array($this, 'handle_track_section_view'));
        
        // Short URL redirect handling
        add_action('init', array($this, 'handle_short_url_redirects'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on vCard profile pages
        if (is_singular('vcard_profile')) {
            // Always load the base business profile CSS for compatibility
            wp_enqueue_style(
                'vcard-business-profile',
                VCARD_ASSETS_URL . 'css/business-profile.css',
                array(),
                VCARD_VERSION
            );
            
            // Load compatibility bridge CSS for better styling
            wp_enqueue_style(
                'vcard-compatibility-bridge',
                VCARD_ASSETS_URL . 'css/compatibility-bridge.css',
                array('vcard-business-profile'),
                VCARD_VERSION
            );
            
            // Load modern UX enhancements CSS
            wp_enqueue_style(
                'vcard-modern-ux',
                VCARD_ASSETS_URL . 'css/modern-ux-enhancements.css',
                array('vcard-compatibility-bridge'),
                VCARD_VERSION
            );
            
            // Check if user wants optimized version (can be controlled via option)
            $use_optimized_css = get_option('vcard_use_optimized_css', true); // Default to true now
            
            if ($use_optimized_css) {
                // Load Tailwind utilities and components
                wp_enqueue_style(
                    'vcard-tailwind-utilities',
                    VCARD_ASSETS_URL . 'css/tailwind-utilities.css',
                    array(),
                    VCARD_VERSION
                );
                
                wp_enqueue_style(
                    'vcard-complete-migration',
                    VCARD_ASSETS_URL . 'css/complete-migration-optimized.css',
                    array('vcard-tailwind-utilities'),
                    VCARD_VERSION
                );
            } else {
                // Component-based loading for development/debugging
                
                // Enqueue Tailwind utilities (base utilities)
                wp_enqueue_style(
                    'vcard-tailwind-utilities',
                    VCARD_ASSETS_URL . 'css/tailwind-utilities.css',
                    array(),
                    VCARD_VERSION
                );
                
                // Enqueue action bar Tailwind components (Phase 2)
                wp_enqueue_style(
                    'vcard-action-bar-tailwind',
                    VCARD_ASSETS_URL . 'css/tailwind-action-bar.css',
                    array('vcard-tailwind-utilities'),
                    VCARD_VERSION
                );
                
                // Enqueue navigation and forms Tailwind components
                wp_enqueue_style(
                    'vcard-navigation-forms-tailwind',
                    VCARD_ASSETS_URL . 'css/navigation-forms-tailwind.css',
                    array('vcard-tailwind-utilities'),
                    VCARD_VERSION
                );
                
                // Complete migration and optimization
                wp_enqueue_style(
                    'vcard-complete-migration',
                    VCARD_ASSETS_URL . 'css/complete-migration-optimized.css',
                    array('vcard-tailwind-utilities'),
                    VCARD_VERSION
                );
            }
            
            // Essential external dependencies (loaded asynchronously for performance)
            wp_enqueue_style(
                'font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
                array(),
                '6.0.0'
            );
            
            // Legacy support - only load if Bootstrap components are still needed
            $legacy_support = get_option('vcard_legacy_bootstrap_support', false);
            if ($legacy_support) {
                wp_enqueue_style(
                    'vcard-business-profile',
                    VCARD_ASSETS_URL . 'css/business-profile.css',
                    array(),
                    VCARD_VERSION
                );
                
                wp_enqueue_style(
                    'vcard-sharing',
                    VCARD_ASSETS_URL . 'css/vcard-sharing.css',
                    array(),
                    VCARD_VERSION
                );
            }
            
            // Enqueue enhanced vCard export script
            wp_enqueue_script(
                'vcard-export',
                VCARD_ASSETS_URL . 'js/vcard-export.js',
                array('jquery'),
                VCARD_VERSION,
                true
            );
            
            // Enqueue vCard sharing script
            wp_enqueue_script(
                'vcard-sharing',
                VCARD_ASSETS_URL . 'js/vcard-sharing.js',
                array('jquery'),
                VCARD_VERSION,
                true
            );
            
            // Localize scripts for AJAX
            wp_localize_script('vcard-export', 'vcard_export', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vcard_export_nonce'),
                'strings' => array(
                    'downloading' => __('Downloading...', 'vcard'),
                    'export_failed' => __('Export failed', 'vcard'),
                    'network_error' => __('Network error', 'vcard'),
                    'no_profiles_selected' => __('No profiles selected', 'vcard'),
                )
            ));
            
            wp_localize_script('vcard-sharing', 'vcard_sharing', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vcard_sharing_nonce'),
                'strings' => array(
                    'qr_code_title' => __('QR Code for Profile', 'vcard'),
                    'qr_code_description' => __('Scan this QR code to quickly access this profile', 'vcard'),
                    'customize_qr' => __('Customize QR Code', 'vcard'),
                    'size' => __('Size', 'vcard'),
                    'foreground_color' => __('Foreground Color', 'vcard'),
                    'background_color' => __('Background Color', 'vcard'),
                    'error_correction' => __('Error Correction', 'vcard'),
                    'regenerate_qr' => __('Regenerate QR Code', 'vcard'),
                    'download_qr' => __('Download QR Code', 'vcard'),
                    'share_qr' => __('Share QR Code', 'vcard'),
                    'share_profile' => __('Share Profile', 'vcard'),
                    'profile_url' => __('Profile URL', 'vcard'),
                    'short_url' => __('Short URL', 'vcard'),
                    'copy' => __('Copy', 'vcard'),
                    'generate_short_url' => __('Generate Short URL', 'vcard'),
                    'sharing_stats' => __('Sharing Statistics', 'vcard'),
                    'total_shares' => __('Total Shares', 'vcard'),
                    'qr_scans' => __('QR Scans', 'vcard'),
                    'link_clicks' => __('Link Clicks', 'vcard'),
                    'copied_to_clipboard' => __('Copied to clipboard!', 'vcard'),
                    'copy_failed' => __('Failed to copy to clipboard', 'vcard'),
                    'short_url_generated' => __('Short URL generated successfully', 'vcard'),
                    'loading' => __('Loading...', 'vcard'),
                )
            ));
            
            // Enqueue public script for contact saving functionality
            wp_enqueue_script(
                'vcard-public',
                VCARD_ASSETS_URL . 'js/public.js',
                array('jquery'),
                VCARD_VERSION,
                true
            );
            
            // Enqueue contact manager script and styles
            wp_enqueue_script(
                'vcard-contact-manager',
                VCARD_ASSETS_URL . 'js/contact-manager.js',
                array('jquery'),
                VCARD_VERSION,
                true
            );
            
            wp_enqueue_style(
                'vcard-contact-manager',
                VCARD_ASSETS_URL . 'css/contact-manager.css',
                array(),
                VCARD_VERSION
            );
            
            // Localize contact manager script
            wp_localize_script('vcard-contact-manager', 'vcard_contact_manager', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vcard_public_nonce'),
                'is_logged_in' => is_user_logged_in(),
                'user_id' => get_current_user_id(),
                'strings' => array(
                    'save_to_cloud' => __('Save to Account', 'vcard'),
                    'sync_with_cloud' => __('Sync with Account', 'vcard'),
                    'cloud_sync_success' => __('Synced with your account!', 'vcard'),
                    'cloud_sync_failed' => __('Failed to sync with account', 'vcard'),
                    'login_required' => __('Please login to sync contacts', 'vcard'),
                    'register_prompt' => __('Create an account to save contacts across devices', 'vcard'),
                )
            ));
            
            wp_localize_script('vcard-public', 'vcard_public', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vcard_public_nonce'),
                'strings' => array(
                    'save_contact' => __('Save Contact', 'vcard'),
                    'contact_saved' => __('Contact saved successfully!', 'vcard'),
                    'save_failed' => __('Failed to save contact', 'vcard'),
                )
            ));
            
            // Enqueue modern UX enhancements script (Phase 1 - Bootstrap)
            wp_enqueue_script(
                'vcard-modern-ux',
                VCARD_ASSETS_URL . 'js/modern-ux-enhancements.js',
                array('jquery', 'vcard-public'),
                VCARD_VERSION,
                true
            );
            
            // Localize modern UX script
            wp_localize_script('vcard-modern-ux', 'vcard_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vcard_public_nonce'),
            ));
            
            // Enqueue Tailwind modern UX script (Phase 2 - Tailwind Migration)
            // Disabled: Tailwind version causes duplicate action bars
            // wp_enqueue_script(
            //     'vcard-modern-ux-tailwind',
            //     VCARD_ASSETS_URL . 'js/modern-ux-tailwind.js',
            //     array('jquery', 'vcard-public'),
            //     VCARD_VERSION,
            //     true
            // );
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only enqueue on vCard profile edit pages
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            global $post_type;
            if ($post_type === 'vcard_profile') {
                wp_enqueue_media();
                wp_enqueue_style(
                    'vcard-admin',
                    VCARD_ASSETS_URL . 'css/admin.css',
                    array(),
                    VCARD_VERSION
                );
                wp_enqueue_script(
                    'vcard-admin',
                    VCARD_ASSETS_URL . 'js/admin.js',
                    array('jquery', 'media-upload'),
                    VCARD_VERSION,
                    true
                );
            }
        }
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load Composer autoloader for Twig
        if (file_exists(VCARD_PLUGIN_PATH . 'vendor/autoload.php')) {
            require_once VCARD_PLUGIN_PATH . 'vendor/autoload.php';
        }
        
        // Load new architecture classes
        require_once VCARD_INCLUDES_PATH . 'VCardDatabaseHelper.php';
        require_once VCARD_INCLUDES_PATH . 'VCardTemplateRenderer.php';
        require_once VCARD_INCLUDES_PATH . 'VCardProfileController.php';
        
        // Load BusinessProfile class for enhanced profile management
        require_once VCARD_INCLUDES_PATH . 'class-business-profile.php';
        
        // Load vCard Export class for enhanced export functionality
        require_once VCARD_INCLUDES_PATH . 'class-vcard-export.php';
        
        // Load vCard Sharing class for QR codes and social sharing
        require_once VCARD_INCLUDES_PATH . 'class-vcard-sharing.php';
        
        // Load TemplateCustomizer class for template customization
        require_once VCARD_INCLUDES_PATH . 'class-template-customizer.php';
        
        // Load Dashboard Authentication class
        require_once VCARD_INCLUDES_PATH . 'class-dashboard-auth.php';
        
        // Load User Roles Management class
        require_once VCARD_INCLUDES_PATH . 'class-user-roles.php';
        
        // Load Profile Manager class
        require_once VCARD_INCLUDES_PATH . 'class-profile-manager.php';
        
        // Load Contact Manager class
        require_once VCARD_INCLUDES_PATH . 'class-contact-manager.php';
        
        // Load User Registration class
        require_once VCARD_INCLUDES_PATH . 'class-user-registration.php';
    }
    
    public function init() {
        $this->register_post_type();
        $this->register_meta_fields();
        $this->assign_vcard_capabilities();
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_fields'));
        add_filter('template_include', array($this, 'load_templates'));
        
        // Initialize template customizer
        new VCard_Template_Customizer();
        
        // Initialize dashboard authentication
        new VCard_Dashboard_Auth();
        
        // Initialize user roles management
        new VCard_User_Roles();
        
        // Initialize profile manager
        new VCard_Profile_Manager();
    }
    
    /**
     * Assign vCard capabilities to administrator and editor roles
     */
    private function assign_vcard_capabilities() {
        $admin_role = get_role('administrator');
        $editor_role = get_role('editor');
        
        $vcard_capabilities = array(
            'edit_vcard_profile',
            'read_vcard_profile', 
            'delete_vcard_profile',
            'edit_vcard_profiles',
            'edit_others_vcard_profiles',
            'publish_vcard_profiles',
            'read_private_vcard_profiles',
            'delete_vcard_profiles',
            'delete_private_vcard_profiles',
            'delete_published_vcard_profiles',
            'delete_others_vcard_profiles',
            'edit_private_vcard_profiles',
            'edit_published_vcard_profiles',
            'create_vcard_profiles'
        );
        
        // Add capabilities to administrator
        if ($admin_role) {
            foreach ($vcard_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Add capabilities to editor
        if ($editor_role) {
            foreach ($vcard_capabilities as $cap) {
                $editor_role->add_cap($cap);
            }
        }
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
            'capability_type' => 'vcard_profile',
            'map_meta_cap' => true,
            'capabilities' => array(
                'edit_post' => 'edit_vcard_profile',
                'read_post' => 'read_vcard_profile',
                'delete_post' => 'delete_vcard_profile',
                'edit_posts' => 'edit_vcard_profiles',
                'edit_others_posts' => 'edit_others_vcard_profiles',
                'publish_posts' => 'publish_vcard_profiles',
                'read_private_posts' => 'read_private_vcard_profiles',
                'delete_posts' => 'delete_vcard_profiles',
                'delete_private_posts' => 'delete_private_vcard_profiles',
                'delete_published_posts' => 'delete_published_vcard_profiles',
                'delete_others_posts' => 'delete_others_vcard_profiles',
                'edit_private_posts' => 'edit_private_vcard_profiles',
                'edit_published_posts' => 'edit_published_vcard_profiles',
                'create_posts' => 'create_vcard_profiles',
            ),
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
        
        register_post_meta('vcard_profile', '_vcard_contact_saves', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'default' => 0,
        ));
        
        register_post_meta('vcard_profile', '_vcard_contact_inquiries', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'default' => 0,
        ));
        
        register_post_meta('vcard_profile', '_vcard_contact_form_enabled', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '1',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_post_meta('vcard_profile', '_vcard_contact_form_title', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
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
    
    /**
     * Handle AJAX request for vCard export data
     */
    public function handle_get_vcard_export_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_export_nonce')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id']);
        $format = sanitize_text_field($_POST['format']) ?: 'vcf';
        
        if (!$profile_id) {
            wp_send_json_error('Invalid profile ID');
        }
        
        // Check if post exists and is vcard_profile type
        $post = get_post($profile_id);
        if (!$post || $post->post_type !== 'vcard_profile') {
            wp_send_json_error('Profile not found');
        }
        
        try {
            // Use new architecture
            $controller = new VCard\VCardProfileController();
            $vcard_data = $controller->generateVCardExportData($profile_id);
            
            if (!$vcard_data) {
                wp_send_json_error('Failed to generate vCard data');
            }
            
            // Create export instance with new data
            $business_profile = new VCard_Business_Profile($profile_id);
            $exporter = new VCard_Export($business_profile);
            $exporter->set_format($format);
            
            // Generate export data
            $export_data = $exporter->generate();
            
            // Prepare response
            $response = array(
                'content' => $export_data,
                'filename' => $exporter->get_filename(),
                'mime_type' => $exporter->get_mime_type(),
                'format' => $format
            );
            
            // Validate vCard if VCF format
            if ($format === 'vcf') {
                $validation = $exporter->validate_vcard($export_data);
                $response['validation'] = $validation;
                
                if (!$validation['valid']) {
                    error_log('vCard validation failed for profile ' . $profile_id . ': ' . implode(', ', $validation['errors']));
                }
            }
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            error_log('vCard export error: ' . $e->getMessage());
            wp_send_json_error('Export generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle AJAX request for bulk vCard export
     */
    public function handle_bulk_vcard_export() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_export_nonce')) {
            wp_die('Security check failed');
        }
        
        $profile_ids = array_map('intval', $_POST['profile_ids']);
        $format = sanitize_text_field($_POST['format']) ?: 'vcf';
        
        if (empty($profile_ids)) {
            wp_send_json_error('No profile IDs provided');
        }
        
        // Limit bulk export to prevent server overload
        if (count($profile_ids) > 50) {
            wp_send_json_error('Too many profiles selected. Maximum 50 profiles allowed.');
        }
        
        try {
            $export_files = array();
            
            foreach ($profile_ids as $profile_id) {
                // Check if post exists and is vcard_profile type
                $post = get_post($profile_id);
                if (!$post || $post->post_type !== 'vcard_profile') {
                    continue;
                }
                
                // Create business profile instance
                $business_profile = new VCard_Business_Profile($profile_id);
                
                // Create export instance
                $exporter = new VCard_Export($business_profile);
                $exporter->set_format($format);
                
                // Generate export data
                $export_data = $exporter->generate();
                
                $export_files[] = array(
                    'filename' => $exporter->get_filename(),
                    'content' => $export_data
                );
            }
            
            if (empty($export_files)) {
                wp_send_json_error('No valid profiles found');
            }
            
            // Create ZIP file for bulk export
            $zip_content = $this->create_zip_from_files($export_files);
            
            $response = array(
                'content' => base64_encode($zip_content),
                'filename' => 'vcard_bulk_export_' . date('Y-m-d_H-i-s') . '.zip',
                'mime_type' => 'application/zip',
                'count' => count($export_files)
            );
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            error_log('Bulk vCard export error: ' . $e->getMessage());
            wp_send_json_error('Bulk export failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Create ZIP file from array of files
     */
    private function create_zip_from_files($files) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not available');
        }
        
        $zip = new ZipArchive();
        $temp_file = tempnam(sys_get_temp_dir(), 'vcard_bulk_');
        
        if ($zip->open($temp_file, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Cannot create ZIP file');
        }
        
        foreach ($files as $file) {
            $zip->addFromString($file['filename'], $file['content']);
        }
        
        $zip->close();
        
        $zip_content = file_get_contents($temp_file);
        unlink($temp_file);
        
        return $zip_content;
    }
    
    /**
     * Handle AJAX request for QR code generation
     */
    public function handle_generate_qr_code() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_sharing_nonce')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id']);
        $options = json_decode(stripslashes($_POST['options']), true) ?: array();
        
        if (!$profile_id) {
            wp_send_json_error('Invalid profile ID');
        }
        
        // Check if post exists and is vcard_profile type
        $post = get_post($profile_id);
        if (!$post || $post->post_type !== 'vcard_profile') {
            wp_send_json_error('Profile not found');
        }
        
        try {
            // Create business profile and sharing instances
            $business_profile = new VCard_Business_Profile($profile_id);
            $sharing = new VCard_Sharing($business_profile);
            
            // Generate QR code
            $qr_data = $sharing->generate_qr_code($options);
            
            wp_send_json_success($qr_data);
            
        } catch (Exception $e) {
            error_log('QR code generation error: ' . $e->getMessage());
            wp_send_json_error('QR code generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle AJAX request for sharing links
     */
    public function handle_get_sharing_links() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_sharing_nonce')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id']);
        
        if (!$profile_id) {
            wp_send_json_error('Invalid profile ID');
        }
        
        // Check if post exists and is vcard_profile type
        $post = get_post($profile_id);
        if (!$post || $post->post_type !== 'vcard_profile') {
            wp_send_json_error('Profile not found');
        }
        
        try {
            // Create business profile and sharing instances
            $business_profile = new VCard_Business_Profile($profile_id);
            $sharing = new VCard_Sharing($business_profile);
            
            // Get sharing data
            $sharing_data = array(
                'links' => $sharing->get_social_sharing_links(),
                'profile_url' => get_permalink($profile_id),
                'short_url' => get_post_meta($profile_id, '_vcard_short_url', true),
                'analytics' => $sharing->get_sharing_analytics()
            );
            
            wp_send_json_success($sharing_data);
            
        } catch (Exception $e) {
            error_log('Sharing links error: ' . $e->getMessage());
            wp_send_json_error('Failed to get sharing links: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle AJAX request for short URL generation
     */
    public function handle_generate_short_url() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_sharing_nonce')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id']);
        
        if (!$profile_id) {
            wp_send_json_error('Invalid profile ID');
        }
        
        // Check if post exists and is vcard_profile type
        $post = get_post($profile_id);
        if (!$post || $post->post_type !== 'vcard_profile') {
            wp_send_json_error('Profile not found');
        }
        
        try {
            // Create business profile and sharing instances
            $business_profile = new VCard_Business_Profile($profile_id);
            $sharing = new VCard_Sharing($business_profile);
            
            // Generate short URL
            $short_url = $sharing->generate_short_url();
            
            wp_send_json_success(array(
                'short_url' => $short_url,
                'profile_id' => $profile_id
            ));
            
        } catch (Exception $e) {
            error_log('Short URL generation error: ' . $e->getMessage());
            wp_send_json_error('Short URL generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle AJAX request for tracking shares
     */
    public function handle_track_share() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_sharing_nonce')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id']);
        $platform = sanitize_text_field($_POST['platform']);
        
        if (!$profile_id || !$platform) {
            wp_send_json_error('Invalid parameters');
        }
        
        try {
            // Create business profile and sharing instances
            $business_profile = new VCard_Business_Profile($profile_id);
            $sharing = new VCard_Sharing($business_profile);
            
            // Track share
            $sharing->track_share($platform);
            
            wp_send_json_success('Share tracked');
            
        } catch (Exception $e) {
            error_log('Share tracking error: ' . $e->getMessage());
            wp_send_json_error('Share tracking failed');
        }
    }
    
    /**
     * Handle AJAX request for embed code generation
     */
    public function handle_get_embed_code() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_sharing_nonce')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id']);
        $options = json_decode(stripslashes($_POST['options']), true) ?: array();
        
        if (!$profile_id) {
            wp_send_json_error('Invalid profile ID');
        }
        
        try {
            // Create business profile and sharing instances
            $business_profile = new VCard_Business_Profile($profile_id);
            $sharing = new VCard_Sharing($business_profile);
            
            // Generate embed code
            $embed_code = $sharing->get_embed_code($options);
            
            wp_send_json_success(array(
                'embed_code' => $embed_code,
                'options' => $options
            ));
            
        } catch (Exception $e) {
            error_log('Embed code generation error: ' . $e->getMessage());
            wp_send_json_error('Embed code generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle AJAX request for QR code download
     */
    public function handle_download_qr_code() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'vcard_qr_download')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_GET['profile_id']);
        $size = intval($_GET['size']) ?: 300;
        $format = sanitize_text_field($_GET['format']) ?: 'png';
        
        if (!$profile_id) {
            wp_die('Invalid profile ID');
        }
        
        try {
            // Create business profile and sharing instances
            $business_profile = new VCard_Business_Profile($profile_id);
            $sharing = new VCard_Sharing($business_profile);
            
            // Generate QR code
            $qr_data = $sharing->generate_qr_code(array(
                'size' => $size,
                'format' => $format
            ));
            
            // Get QR code image data
            $image_data = file_get_contents($qr_data['url']);
            
            if ($image_data === false) {
                wp_die('Failed to generate QR code image');
            }
            
            // Set headers for download
            $filename = 'qr-code-' . $profile_id . '.' . $format;
            header('Content-Type: image/' . $format);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($image_data));
            
            echo $image_data;
            exit;
            
        } catch (Exception $e) {
            error_log('QR code download error: ' . $e->getMessage());
            wp_die('QR code download failed');
        }
    }
    

    
    /**
     * Handle AJAX request for saving vCard contact
     */
    public function handle_save_vcard_contact() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id']);
        $user_id = get_current_user_id();
        
        if (!$profile_id || !$user_id) {
            wp_send_json_error('Invalid profile ID or user not logged in');
        }
        
        // Check if user has permission to save contacts
        if (!current_user_can('save_vcard_contacts')) {
            wp_send_json_error('You do not have permission to save contacts');
        }
        
        // Check if post exists and is vcard_profile type
        $post = get_post($profile_id);
        if (!$post || $post->post_type !== 'vcard_profile') {
            wp_send_json_error('Profile not found');
        }
        
        try {
            global $wpdb;
            $saved_contacts_table = $wpdb->prefix . 'vcard_saved_contacts';
            
            // Check if already saved
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $saved_contacts_table WHERE user_id = %d AND profile_id = %d",
                $user_id,
                $profile_id
            ));
            
            if ($existing) {
                wp_send_json_error('Contact already saved');
            }
            
            // Save the contact
            $result = $wpdb->insert(
                $saved_contacts_table,
                array(
                    'user_id' => $user_id,
                    'profile_id' => $profile_id,
                    'saved_at' => current_time('mysql'),
                    'notes' => ''
                ),
                array('%d', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                wp_send_json_error('Failed to save contact');
            }
            
            wp_send_json_success('Contact saved successfully');
            
        } catch (Exception $e) {
            error_log('Save contact error: ' . $e->getMessage());
            wp_send_json_error('Failed to save contact');
        }
    }
    
    /**
     * Handle short URL redirects
     */
    public function handle_short_url_redirects() {
        // Check if this is a short URL request
        $request_uri = $_SERVER['REQUEST_URI'];
        
        if (preg_match('/^\/vc\/([a-zA-Z0-9\-_]+)\/?$/', $request_uri, $matches)) {
            $short_code = $matches[1];
            
            // Get mapping
            $mappings = get_option('vcard_short_url_mappings', array());
            
            if (isset($mappings[$short_code])) {
                $post_id = $mappings[$short_code];
                
                // Verify post still exists
                $post = get_post($post_id);
                if ($post && $post->post_type === 'vcard_profile' && $post->post_status === 'publish') {
                    // Track click
                    VCard_Sharing::track_short_url_click($short_code);
                    
                    // Redirect to full URL
                    wp_redirect(get_permalink($post_id), 301);
                    exit;
                }
            }
            
            // Short URL not found, redirect to 404
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
        }
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
        
        // Contact Form Settings
        echo '<tr>';
        echo '<th><label>' . __('Contact Form Settings', 'vcard') . '</label></th>';
        echo '<td>';
        
        $contact_form_enabled = get_post_meta($post->ID, '_vcard_contact_form_enabled', true);
        $contact_form_title = get_post_meta($post->ID, '_vcard_contact_form_title', true);
        
        echo '<div class="contact-form-settings">';
        echo '<label>';
        echo '<input type="checkbox" name="vcard_contact_form_enabled" value="1" ' . checked($contact_form_enabled, '1', false) . '>';
        echo ' ' . __('Enable contact form on profile', 'vcard');
        echo '</label><br><br>';
        
        echo '<label for="vcard_contact_form_title">' . __('Contact Form Title', 'vcard') . '</label><br>';
        echo '<input type="text" id="vcard_contact_form_title" name="vcard_contact_form_title" value="' . esc_attr($contact_form_title ?: __('Leave a Message', 'vcard')) . '" class="regular-text">';
        echo '</div>';
        
        echo '</td>';
        echo '</tr>';
        
        // Hook for streamlined template customization features
        do_action('vcard_template_settings_streamlined', $post);
        
        echo '</table>';
        echo '</div>';
        

        
        echo '</div>'; // Close tabs container
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
        
        // Contact form settings
        $contact_form_enabled = isset($_POST['vcard_contact_form_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_vcard_contact_form_enabled', $contact_form_enabled);
        
        if (isset($_POST['vcard_contact_form_title'])) {
            update_post_meta($post_id, '_vcard_contact_form_title', sanitize_text_field($_POST['vcard_contact_form_title']));
        }
        
        // Streamlined template settings - template_name is now handled by radio buttons
        if (isset($_POST['vcard_template_name'])) {
            update_post_meta($post_id, '_vcard_template_name', sanitize_text_field($_POST['vcard_template_name']));
        }
        
        // Template customization fields
        if (isset($_POST['vcard_template_customization_nonce']) && wp_verify_nonce($_POST['vcard_template_customization_nonce'], 'vcard_template_customization')) {
            // Color scheme selection
            if (isset($_POST['vcard_color_scheme'])) {
                update_post_meta($post_id, '_vcard_color_scheme', sanitize_text_field($_POST['vcard_color_scheme']));
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
            // Use new Twig-based template
            $plugin_template = VCARD_PLUGIN_PATH . 'templates/single-vcard_profile-twig.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
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
        
        // Run database upgrades if needed
        $this->upgrade_database();
        
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
        // Allows registered users to save business contacts with contact data
        $saved_contacts_table = "CREATE TABLE " . VCARD_SAVED_CONTACTS_TABLE . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL COMMENT 'WordPress user ID (end user)',
            profile_id bigint(20) NOT NULL COMMENT 'vCard profile ID being saved',
            contact_data longtext NOT NULL COMMENT 'JSON data containing contact information',
            notes text COMMENT 'Personal notes about this business contact',
            tags varchar(255) COMMENT 'Comma-separated tags for organization',
            is_favorite tinyint(1) DEFAULT 0 COMMENT 'Favorite contact flag',
            contact_frequency varchar(20) COMMENT 'never, rarely, sometimes, often',
            last_contacted datetime COMMENT 'Last contact date',
            reminder_date datetime COMMENT 'Follow-up reminder date',
            saved_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY profile_id (profile_id),
            KEY is_favorite (is_favorite),
            KEY reminder_date (reminder_date),
            KEY saved_at (saved_at),
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
     * Upgrade database schema when plugin is updated
     */
    private function upgrade_database() {
        $current_db_version = get_option('vcard_db_version', '1.0.0');
        
        // Only run upgrades if needed
        if (version_compare($current_db_version, VCARD_VERSION, '<')) {
            global $wpdb;
            
            // Upgrade to version 1.0.1 - Add missing columns to saved contacts table
            if (version_compare($current_db_version, '1.0.1', '<')) {
                $table_name = VCARD_SAVED_CONTACTS_TABLE;
                
                // Check if contact_data column exists
                $columns = $wpdb->get_results("DESCRIBE $table_name");
                $has_contact_data = false;
                $has_updated_at = false;
                
                foreach ($columns as $column) {
                    if ($column->Field === 'contact_data') {
                        $has_contact_data = true;
                    }
                    if ($column->Field === 'updated_at') {
                        $has_updated_at = true;
                    }
                }
                
                // Add missing contact_data column
                if (!$has_contact_data) {
                    $wpdb->query("ALTER TABLE $table_name ADD COLUMN contact_data longtext NOT NULL COMMENT 'JSON data containing contact information' AFTER profile_id");
                    error_log('vCard Plugin: Added contact_data column to saved contacts table');
                }
                
                // Add missing updated_at column
                if (!$has_updated_at) {
                    $wpdb->query("ALTER TABLE $table_name ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp'");
                    error_log('vCard Plugin: Added updated_at column to saved contacts table');
                }
            }
            
            // Update database version
            update_option('vcard_db_version', VCARD_VERSION);
            error_log('vCard Plugin: Database upgraded to version ' . VCARD_VERSION);
        }
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
        $current_user_id = get_current_user_id();
        
        // Handle contact removal
        if (isset($_POST['remove_contact']) && wp_verify_nonce($_POST['_wpnonce'], 'remove_contact')) {
            $profile_id = intval($_POST['profile_id']);
            $this->remove_saved_contact($current_user_id, $profile_id);
            echo '<div class="notice notice-success"><p>' . __('Contact removed successfully.', 'vcard') . '</p></div>';
        }
        
        // Get saved contacts
        $saved_contacts = $this->get_user_saved_contacts($current_user_id);
        
        echo '<div class="wrap">';
        echo '<h1>' . __('My Saved Contacts', 'vcard') . '</h1>';
        
        if (empty($saved_contacts)) {
            echo '<div class="notice notice-info">';
            echo '<p>' . __('You haven\'t saved any contacts yet. Visit business profiles and click "Save Contact" to add them here.', 'vcard') . '</p>';
            echo '</div>';
        } else {
            echo '<div class="saved-contacts-grid">';
            
            foreach ($saved_contacts as $contact) {
                $profile_url = get_permalink($contact->profile_id);
                $business_profile = new VCard_Business_Profile($contact->profile_id);
                
                // Get contact info
                $business_name = $business_profile->get_data('business_name') ?: $contact->business_name;
                $tagline = $business_profile->get_data('business_tagline');
                $phone = $business_profile->get_data('phone');
                $email = $business_profile->get_data('email');
                $website = $business_profile->get_data('website');
                
                echo '<div class="contact-card">';
                echo '<div class="contact-header">';
                echo '<h3><a href="' . esc_url($profile_url) . '">' . esc_html($business_name) . '</a></h3>';
                if ($tagline) {
                    echo '<p class="contact-tagline">' . esc_html($tagline) . '</p>';
                }
                echo '</div>';
                
                echo '<div class="contact-details">';
                if ($phone) {
                    echo '<div class="contact-item"><i class="fas fa-phone"></i> <a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a></div>';
                }
                if ($email) {
                    echo '<div class="contact-item"><i class="fas fa-envelope"></i> <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></div>';
                }
                if ($website) {
                    echo '<div class="contact-item"><i class="fas fa-globe"></i> <a href="' . esc_url($website) . '" target="_blank">' . esc_html($website) . '</a></div>';
                }
                echo '<div class="contact-item"><i class="fas fa-calendar"></i> Saved: ' . date('M j, Y', strtotime($contact->saved_at)) . '</div>';
                echo '</div>';
                
                echo '<div class="contact-actions">';
                echo '<a href="' . esc_url($profile_url) . '" class="button button-primary">View Profile</a>';
                
                // Remove contact form
                echo '<form method="post" style="display: inline-block; margin-left: 10px;">';
                wp_nonce_field('remove_contact');
                echo '<input type="hidden" name="profile_id" value="' . esc_attr($contact->profile_id) . '">';
                echo '<button type="submit" name="remove_contact" class="button button-secondary" onclick="return confirm(\'Are you sure you want to remove this contact?\')">Remove</button>';
                echo '</form>';
                echo '</div>';
                
                echo '</div>'; // contact-card
            }
            
            echo '</div>'; // saved-contacts-grid
        }
        
        echo '</div>'; // wrap
        
        // Add CSS for the contact cards
        echo '<style>
        .saved-contacts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .contact-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .contact-header h3 {
            margin: 0 0 5px 0;
        }
        
        .contact-header h3 a {
            text-decoration: none;
            color: #0073aa;
        }
        
        .contact-tagline {
            color: #666;
            font-style: italic;
            margin: 0 0 15px 0;
        }
        
        .contact-details {
            margin-bottom: 15px;
        }
        
        .contact-item {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .contact-item i {
            width: 16px;
            color: #666;
        }
        
        .contact-item a {
            text-decoration: none;
        }
        
        .contact-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        </style>';
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        // Register settings will be implemented in future tasks
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
             WHERE sc.user_id = %d AND p.post_type = 'vcard_profile' AND p.post_status = 'publish'
             ORDER BY sc.saved_at DESC
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
    
    /**
     * Handle vCard download tracking
     */
    public function handle_track_vcard_download() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_tracking')) {
            wp_die('Security check failed');
        }
        
        $post_id = intval($_POST['post_id']);
        
        if ($post_id > 0 && get_post_type($post_id) === 'vcard_profile') {
            // Increment download counter
            $current_downloads = (int) get_post_meta($post_id, '_vcard_vcard_downloads', true);
            update_post_meta($post_id, '_vcard_vcard_downloads', $current_downloads + 1);
            
            // Log to analytics table if it exists
            global $wpdb;
            $analytics_table = VCARD_ANALYTICS_TABLE;
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") === $analytics_table) {
                $wpdb->insert(
                    $analytics_table,
                    array(
                        'profile_id' => $post_id,
                        'event_type' => 'vcard_download',
                        'event_date' => current_time('mysql'),
                        'user_ip' => $this->get_client_ip(),
                        'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                    ),
                    array('%d', '%s', '%s', '%s', '%s')
                );
            }
            
            wp_send_json_success(array('downloads' => $current_downloads + 1));
        }
        
        wp_send_json_error('Invalid post ID');
    }
    
    /**
     * Handle QR code generation tracking
     */
    public function handle_track_qr_generation() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_tracking')) {
            wp_die('Security check failed');
        }
        
        $post_id = intval($_POST['post_id']);
        
        if ($post_id > 0 && get_post_type($post_id) === 'vcard_profile') {
            // Increment QR scan counter
            $current_qr_scans = (int) get_post_meta($post_id, '_vcard_qr_scans', true);
            update_post_meta($post_id, '_vcard_qr_scans', $current_qr_scans + 1);
            
            // Log to analytics table if it exists
            global $wpdb;
            $analytics_table = VCARD_ANALYTICS_TABLE;
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") === $analytics_table) {
                $wpdb->insert(
                    $analytics_table,
                    array(
                        'profile_id' => $post_id,
                        'event_type' => 'qr_generation',
                        'event_date' => current_time('mysql'),
                        'user_ip' => $this->get_client_ip(),
                        'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                    ),
                    array('%d', '%s', '%s', '%s', '%s')
                );
            }
            
            wp_send_json_success(array('qr_scans' => $current_qr_scans + 1));
        }
        
        wp_send_json_error('Invalid post ID');
    }
    
    /**
     * Handle contact form submission
     */
    public function handle_contact_form_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vcard_contact_nonce'], 'vcard_contact_form')) {
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'vcard')));
        }
        
        // Honeypot spam protection
        if (!empty($_POST['website_url'])) {
            wp_send_json_error(array('message' => __('Spam detected.', 'vcard')));
        }
        
        // Rate limiting - prevent too many submissions from same IP
        $user_ip = $this->get_client_ip();
        $rate_limit_key = 'vcard_contact_rate_limit_' . md5($user_ip);
        $submissions_count = get_transient($rate_limit_key);
        
        if ($submissions_count && $submissions_count >= 5) {
            wp_send_json_error(array('message' => __('Too many submissions. Please wait before sending another message.', 'vcard')));
        }
        
        // Validate required fields
        $required_fields = array(
            'contact_name' => __('Name is required.', 'vcard'),
            'contact_email' => __('Email is required.', 'vcard'),
            'contact_message' => __('Message is required.', 'vcard'),
            'profile_id' => __('Invalid profile.', 'vcard')
        );
        
        foreach ($required_fields as $field => $error_message) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => $error_message));
            }
        }
        
        // Sanitize and validate data
        $profile_id = intval($_POST['profile_id']);
        $contact_name = sanitize_text_field($_POST['contact_name']);
        $contact_email = sanitize_email($_POST['contact_email']);
        $contact_phone = sanitize_text_field($_POST['contact_phone']);
        $contact_subject = sanitize_text_field($_POST['contact_subject']);
        $contact_message = sanitize_textarea_field($_POST['contact_message']);
        
        // Validate email
        if (!is_email($contact_email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'vcard')));
        }
        
        // Validate profile exists and is published
        $profile_post = get_post($profile_id);
        if (!$profile_post || $profile_post->post_type !== 'vcard_profile' || $profile_post->post_status !== 'publish') {
            wp_send_json_error(array('message' => __('Invalid profile.', 'vcard')));
        }
        
        // Get business profile data
        $business_profile = new VCard_Business_Profile($profile_id);
        $business_email = $business_profile->get_data('email');
        $business_name = $business_profile->get_data('business_name') ?: get_the_title($profile_id);
        
        if (empty($business_email)) {
            wp_send_json_error(array('message' => __('Business contact information is not available.', 'vcard')));
        }
        
        // Prepare email content
        $subject = !empty($contact_subject) ? $contact_subject : sprintf(__('New inquiry from %s', 'vcard'), get_bloginfo('name'));
        
        $message = sprintf(
            __("You have received a new message through your vCard profile.\n\n" .
               "Profile: %s\n" .
               "From: %s\n" .
               "Email: %s\n" .
               "Phone: %s\n" .
               "Subject: %s\n\n" .
               "Message:\n%s\n\n" .
               "---\n" .
               "This message was sent through your vCard profile: %s", 'vcard'),
            $business_name,
            $contact_name,
            $contact_email,
            $contact_phone ?: __('Not provided', 'vcard'),
            $contact_subject ?: __('No subject', 'vcard'),
            $contact_message,
            get_permalink($profile_id)
        );
        
        // Set email headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'Reply-To: ' . $contact_name . ' <' . $contact_email . '>'
        );
        
        // Send email
        $email_sent = wp_mail($business_email, $subject, $message, $headers);
        
        if ($email_sent) {
            // Log the contact form submission
            $this->log_contact_form_submission($profile_id, $contact_name, $contact_email, $contact_subject, $contact_message);
            
            // Update rate limiting
            $new_count = $submissions_count ? $submissions_count + 1 : 1;
            set_transient($rate_limit_key, $new_count, HOUR_IN_SECONDS);
            
            wp_send_json_success(array(
                'message' => __('Thank you for your message! We will get back to you soon.', 'vcard')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to send message. Please try again or contact us directly.', 'vcard')
            ));
        }
    }
    
    /**
     * Log contact form submission for analytics
     */
    private function log_contact_form_submission($profile_id, $contact_name, $contact_email, $subject, $message) {
        global $wpdb;
        $analytics_table = VCARD_ANALYTICS_TABLE;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") === $analytics_table) {
            $wpdb->insert(
                $analytics_table,
                array(
                    'profile_id' => $profile_id,
                    'event_type' => 'contact_form_submission',
                    'event_date' => current_time('mysql'),
                    'user_ip' => $this->get_client_ip(),
                    'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                    'event_data' => wp_json_encode(array(
                        'contact_name' => $contact_name,
                        'contact_email' => $contact_email,
                        'subject' => $subject,
                        'message_length' => strlen($message)
                    ))
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        // Also increment a simple counter in post meta
        $current_inquiries = (int) get_post_meta($profile_id, '_vcard_contact_inquiries', true);
        update_post_meta($profile_id, '_vcard_contact_inquiries', $current_inquiries + 1);
    }
    
    /**
     * Handle AJAX request for tracking events
     */
    public function handle_track_event() {
        // Verify nonce - accept both sharing and public nonces
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce') || 
                      wp_verify_nonce($_POST['nonce'], 'vcard_sharing_nonce');
        
        if (!$nonce_valid) {
            wp_die('Security check failed');
        }
        
        $event_type = sanitize_text_field($_POST['event_type']);
        $event_data = json_decode(stripslashes($_POST['event_data']), true);
        
        if (empty($event_type)) {
            wp_send_json_error('Invalid event type');
        }
        
        // Log the event
        $this->log_analytics_event($event_type, $event_data);
        
        wp_send_json_success();
    }
    
    /**
     * Log analytics event
     */
    private function log_analytics_event($event_type, $event_data = array()) {
        global $wpdb;
        $analytics_table = VCARD_ANALYTICS_TABLE;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") === $analytics_table) {
            $profile_id = isset($event_data['profile_id']) ? intval($event_data['profile_id']) : 0;
            
            $wpdb->insert(
                $analytics_table,
                array(
                    'profile_id' => $profile_id,
                    'event_type' => $event_type,
                    'event_data' => wp_json_encode($event_data),
                    'ip_address' => $this->get_client_ip(),
                    'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                    'referrer' => sanitize_text_field($_SERVER['HTTP_REFERER'] ?? ''),
                    'session_id' => session_id() ?: md5($this->get_client_ip() . $_SERVER['HTTP_USER_AGENT'])
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            // Update post meta counters for quick access
            if ($profile_id > 0) {
                switch ($event_type) {
                    case 'profile_view':
                        $current_views = (int) get_post_meta($profile_id, '_vcard_profile_views', true);
                        update_post_meta($profile_id, '_vcard_profile_views', $current_views + 1);
                        break;
                        
                    case 'vcard_download':
                        $current_downloads = (int) get_post_meta($profile_id, '_vcard_vcard_downloads', true);
                        update_post_meta($profile_id, '_vcard_vcard_downloads', $current_downloads + 1);
                        break;
                        
                    case 'qr_scan':
                        $current_scans = (int) get_post_meta($profile_id, '_vcard_qr_scans', true);
                        update_post_meta($profile_id, '_vcard_qr_scans', $current_scans + 1);
                        break;
                        
                    case 'profile_share':
                        $current_shares = (int) get_post_meta($profile_id, '_vcard_shares', true);
                        update_post_meta($profile_id, '_vcard_shares', $current_shares + 1);
                        break;
                }
            }
        }
    }
    
    /**
     * Handle AJAX request for event tracking from modern UX enhancements
     */
    public function handle_modern_ux_track_event() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $event_name = sanitize_text_field($_POST['event_name']);
        $event_data = isset($_POST['event_data']) ? $_POST['event_data'] : array();
        
        // Log the event for analytics
        error_log("vCard Event: {$event_name} - " . json_encode($event_data));
        
        // If profile_id is provided, update the relevant meta field
        if (isset($event_data['profile_id'])) {
            $profile_id = intval($event_data['profile_id']);
            
            // Map modern UX events to existing tracking
            switch ($event_name) {
                case 'quick_action_call':
                case 'quick_action_message':
                case 'quick_action_whatsapp':
                case 'quick_action_directions':
                case 'quick_action_share_native':
                case 'quick_action_share_clipboard':
                    // Track as general interaction
                    $current_views = (int) get_post_meta($profile_id, '_vcard_profile_views', true);
                    update_post_meta($profile_id, '_vcard_profile_views', $current_views + 1);
                    break;
                    
                case 'contact_save_toggle':
                    // Track contact saves
                    if (isset($event_data['saved']) && $event_data['saved']) {
                        $current_saves = (int) get_post_meta($profile_id, '_vcard_contact_saves', true);
                        update_post_meta($profile_id, '_vcard_contact_saves', $current_saves + 1);
                    }
                    break;
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Event tracked successfully',
            'event' => $event_name
        ));
    }
    
    /**
     * Handle AJAX request for section view tracking
     */
    public function handle_track_section_view() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id']);
        $section = sanitize_text_field($_POST['section']);
        
        if (!$profile_id || !$section) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Track the section view
        $this->track_analytics_event('section_view', array(
            'profile_id' => $profile_id,
            'section' => $section
        ));
        
        wp_send_json_success(array(
            'message' => 'Section view tracked',
            'profile_id' => $profile_id,
            'section' => $section
        ));
    }
    
}

// Initialize the plugin
VCardPlugin::get_instance();

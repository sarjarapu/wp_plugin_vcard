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
        
        // Load text domain for internationalization
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core includes will be loaded here in future tasks
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
        $meta_fields = array(
            'first_name', 'last_name', 'company', 'job_title',
            'phone', 'email', 'website', 'address', 'city',
            'state', 'zip_code', 'country'
        );
        
        foreach ($meta_fields as $field) {
            register_post_meta('vcard_profile', '_vcard_' . $field, array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
            ));
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
        
        $fields = array(
            'first_name' => __('First Name', 'vcard'),
            'last_name' => __('Last Name', 'vcard'),
            'company' => __('Company', 'vcard'),
            'job_title' => __('Job Title', 'vcard'),
            'phone' => __('Phone', 'vcard'),
            'email' => __('Email', 'vcard'),
            'website' => __('Website', 'vcard'),
            'address' => __('Address', 'vcard'),
            'city' => __('City', 'vcard'),
            'state' => __('State', 'vcard'),
            'zip_code' => __('Zip Code', 'vcard'),
            'country' => __('Country', 'vcard'),
        );
        
        echo '<table class="form-table">';
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, '_vcard_' . $key, true);
            echo '<tr>';
            echo '<th><label for="vcard_' . $key . '">' . $label . '</label></th>';
            echo '<td><input type="text" id="vcard_' . $key . '" name="vcard_' . $key . '" value="' . esc_attr($value) . '" class="regular-text" /></td>';
            echo '</tr>';
        }
        echo '</table>';
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
        
        $fields = array('first_name', 'last_name', 'company', 'job_title', 'phone', 'email', 'website', 'address', 'city', 'state', 'zip_code', 'country');
        
        foreach ($fields as $field) {
            if (isset($_POST['vcard_' . $field])) {
                update_post_meta($post_id, '_vcard_' . $field, sanitize_text_field($_POST['vcard_' . $field]));
            }
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
            wp_enqueue_style('vcard-public-style', VCARD_ASSETS_URL . 'css/public.css', array(), VCARD_VERSION);
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
        
        // Update version
        update_option('vcard_version', VCARD_VERSION);
        update_option('vcard_activation_time', current_time('timestamp'));
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
     * Create custom database tables
     */
    private function create_custom_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Analytics table
        $analytics_table = "CREATE TABLE " . VCARD_ANALYTICS_TABLE . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Subscriptions table
        $subscriptions_table = "CREATE TABLE " . VCARD_SUBSCRIPTIONS_TABLE . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            plan varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            expires_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Saved contacts table
        $saved_contacts_table = "CREATE TABLE " . VCARD_SAVED_CONTACTS_TABLE . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            profile_id bigint(20) NOT NULL,
            notes text,
            tags varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY profile_id (profile_id),
            UNIQUE KEY user_profile (user_id, profile_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($analytics_table);
        dbDelta($subscriptions_table);
        dbDelta($saved_contacts_table);
    }
    
    /**
     * Create custom user roles
     */
    private function create_user_roles() {
        // vCard Client role - can create and manage their own business profiles
        add_role('vcard_client', __('vCard Client', 'vcard'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'edit_vcard_profiles' => true,
            'edit_own_vcard_profiles' => true,
            'delete_own_vcard_profiles' => true,
            'publish_vcard_profiles' => true,
            'upload_files' => true,
        ));
        
        // vCard User role - can save and manage contacts
        add_role('vcard_user', __('vCard User', 'vcard'), array(
            'read' => true,
            'save_vcard_contacts' => true,
        ));
        
        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('edit_vcard_profiles');
            $admin_role->add_cap('edit_others_vcard_profiles');
            $admin_role->add_cap('delete_vcard_profiles');
            $admin_role->add_cap('delete_others_vcard_profiles');
            $admin_role->add_cap('publish_vcard_profiles');
            $admin_role->add_cap('read_private_vcard_profiles');
            $admin_role->add_cap('manage_vcard_settings');
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
     * Add admin menu
     */
    public function add_admin_menu() {
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
     * Admin init
     */
    public function admin_init() {
        // Register settings will be implemented in future tasks
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Admin assets will be enqueued here in future tasks
        wp_enqueue_style('vcard-admin-style', VCARD_ASSETS_URL . 'css/admin.css', array(), VCARD_VERSION);
        wp_enqueue_script('vcard-admin-script', VCARD_ASSETS_URL . 'js/admin.js', array('jquery'), VCARD_VERSION, true);
    }
}

// Initialize the plugin
VCardPlugin::get_instance();
<?php
/**
 * Plugin Name: BizCard Pro
 * Plugin URI: https://your-website.com
 * Description: Comprehensive multi-tenant business directory platform for virtual business card exchange with subscription billing and template customization.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: bizcard-pro
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BIZCARD_PRO_VERSION', '1.0.0');
define('BIZCARD_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BIZCARD_PRO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BIZCARD_PRO_PLUGIN_FILE', __FILE__);

/**
 * Main BizCard Pro Plugin Class
 */
class BizCardPro {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Get single instance
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(BIZCARD_PRO_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(BIZCARD_PRO_PLUGIN_FILE, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once BIZCARD_PRO_PLUGIN_PATH . 'includes/class-database.php';
        require_once BIZCARD_PRO_PLUGIN_PATH . 'includes/class-business-profile.php';
        require_once BIZCARD_PRO_PLUGIN_PATH . 'includes/class-template-engine.php';
        require_once BIZCARD_PRO_PLUGIN_PATH . 'includes/class-subscription-manager.php';
        require_once BIZCARD_PRO_PLUGIN_PATH . 'includes/class-analytics.php';
        
        // Admin classes
        if (is_admin()) {
            require_once BIZCARD_PRO_PLUGIN_PATH . 'admin/class-admin.php';
        }
        
        // Public classes
        require_once BIZCARD_PRO_PLUGIN_PATH . 'public/class-public.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize database
        BizCard_Pro_Database::get_instance();
        
        // Initialize business profiles
        BizCard_Pro_Business_Profile::get_instance();
        
        // Initialize admin area
        if (is_admin()) {
            BizCard_Pro_Admin::get_instance();
        }
        
        // Initialize public area
        BizCard_Pro_Public::get_instance();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'bizcard-pro',
            false,
            dirname(plugin_basename(BIZCARD_PRO_PLUGIN_FILE)) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        BizCard_Pro_Database::create_tables();
        
        // Create default user roles
        $this->create_user_roles();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        $this->set_default_options();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create custom user roles
     */
    private function create_user_roles() {
        // Business Client role
        add_role('bizcard_client', __('Business Client', 'bizcard-pro'), array(
            'read' => true,
            'edit_bizcard_profiles' => true,
            'publish_bizcard_profiles' => true,
        ));
        
        // End User role (optional - they can also be anonymous)
        add_role('bizcard_user', __('BizCard User', 'bizcard-pro'), array(
            'read' => true,
            'save_bizcard_contacts' => true,
        ));
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'bizcard_pro_version' => BIZCARD_PRO_VERSION,
            'bizcard_pro_db_version' => '1.0.0',
            'bizcard_pro_subscription_required' => true,
            'bizcard_pro_trial_days' => 30,
            'bizcard_pro_default_theme' => 'professional',
        );
        
        foreach ($default_options as $option => $value) {
            if (!get_option($option)) {
                add_option($option, $value);
            }
        }
    }
}

// Initialize the plugin
BizCardPro::get_instance();
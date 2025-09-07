<?php
/**
 * Lean VCard Plugin Class
 * 
 * Focused only on profile viewing functionality
 * 
 * @package VCard
 * @version 1.0.0
 */

class VCardPluginLean {
    
    private static $instance = null;
    private $profile_loader;
    
    /**
     * Get plugin instance
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->loadDependencies();
        $this->initHooks();
        $this->initComponents();
    }
    
    /**
     * Load dependencies
     */
    private function loadDependencies() {
        // Load autoloader
        require_once VCARD_INCLUDES_PATH . 'autoloader.php';
        
        // Ensure cache directory exists
        $cache_dir = VCARD_PLUGIN_PATH . 'cache/';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        // Core hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('init', [$this, 'init']);
        
        // Load text domain
        add_action('plugins_loaded', [$this, 'loadTextdomain']);
    }
    
    /**
     * Initialize components
     */
    private function initComponents() {
        // Initialize profile loader
        $this->profile_loader = new \VCard\VCardProfileLoader();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Register post type (minimal version)
        $this->registerPostType();
    }
    
    /**
     * Register vCard post type
     */
    private function registerPostType() {
        $args = [
            'labels' => [
                'name' => __('vCards', 'vcard'),
                'singular_name' => __('vCard', 'vcard'),
            ],
            'public' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'vcard'],
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-id-alt',
            'show_in_rest' => true,
        ];
        
        register_post_type('vcard_profile', $args);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueueScripts() {
        // Only enqueue on vCard profile pages
        if (!is_singular('vcard_profile')) {
            return;
        }
        
        // Core styles
        wp_enqueue_style(
            'vcard-business-profile',
            VCARD_ASSETS_URL . 'css/business-profile.css',
            [],
            VCARD_VERSION
        );
        
        // Compatibility bridge
        wp_enqueue_style(
            'vcard-compatibility-bridge',
            VCARD_ASSETS_URL . 'css/compatibility-bridge.css',
            ['vcard-business-profile'],
            VCARD_VERSION
        );
        
        // Tailwind utilities
        wp_enqueue_style(
            'vcard-tailwind-utilities',
            VCARD_ASSETS_URL . 'css/tailwind-utilities.css',
            [],
            VCARD_VERSION
        );
        
        // Font Awesome
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
            [],
            '6.0.0'
        );
        
        // Modern UX JavaScript
        wp_enqueue_script(
            'vcard-modern-ux',
            VCARD_ASSETS_URL . 'js/modern-ux-enhancements.js',
            ['jquery'],
            VCARD_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('vcard-modern-ux', 'vcard_public', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vcard_public_nonce'),
            'strings' => [
                'save_contact' => __('Save Contact', 'vcard'),
                'contact_saved' => __('Contact saved successfully!', 'vcard'),
                'save_failed' => __('Failed to save contact', 'vcard'),
            ]
        ]);
    }
    
    /**
     * Load text domain
     */
    public function loadTextdomain() {
        load_plugin_textdomain('vcard', false, dirname(VCARD_PLUGIN_BASENAME) . '/languages');
    }
}
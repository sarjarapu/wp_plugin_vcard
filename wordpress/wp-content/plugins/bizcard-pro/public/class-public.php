<?php
/**
 * Public class for BizCard Pro
 *
 * @package BizCard_Pro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BizCard Pro Public Class
 */
class BizCard_Pro_Public {
    
    /**
     * Single instance
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
        // Public functionality will be implemented here
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue public scripts and styles
     */
    public function enqueue_scripts() {
        // Enqueue styles and scripts for public pages
        wp_enqueue_style(
            'bizcard-pro-public',
            BIZCARD_PRO_PLUGIN_URL . 'assets/css/public.css',
            array(),
            BIZCARD_PRO_VERSION
        );
        
        wp_enqueue_script(
            'bizcard-pro-public',
            BIZCARD_PRO_PLUGIN_URL . 'assets/js/public.js',
            array('jquery'),
            BIZCARD_PRO_VERSION,
            true
        );
    }
}
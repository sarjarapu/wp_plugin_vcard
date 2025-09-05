<?php
/**
 * Plugin Name: vCard
 * Description: A WordPress plugin for managing vCard profiles using custom post types
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: vcard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VCARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VCARD_PLUGIN_PATH', plugin_dir_path(__FILE__));

class VCardPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
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
    
    public function enqueue_scripts() {
        wp_enqueue_style('vcard-style', VCARD_PLUGIN_URL . 'assets/style.css', array(), '1.0.0');
    }
    
    public function activate() {
        $this->register_post_type();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new VCardPlugin();
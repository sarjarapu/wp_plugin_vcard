<?php
/**
 * Plugin Name: Digi vCard
 * Plugin URI: https://your-website.com
 * Description: Create professional digital business cards with multiple templates and custom fields.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: digi-vcard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DIGI_VCARD_VERSION', '1.0.0');
define('DIGI_VCARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DIGI_VCARD_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main Plugin Class
 */
class DigiVCard {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        $this->register_post_type();
        $this->register_taxonomies();
        $this->add_admin_hooks();
        $this->add_template_hooks();
    }
    
    /**
     * Register vCard Custom Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('vCards', 'Post type general name', 'digi-vcard'),
            'singular_name'         => _x('vCard', 'Post type singular name', 'digi-vcard'),
            'menu_name'             => _x('vCards', 'Admin Menu text', 'digi-vcard'),
            'name_admin_bar'        => _x('vCard', 'Add New on Toolbar', 'digi-vcard'),
            'add_new'               => __('Add New', 'digi-vcard'),
            'add_new_item'          => __('Add New vCard', 'digi-vcard'),
            'new_item'              => __('New vCard', 'digi-vcard'),
            'edit_item'             => __('Edit vCard', 'digi-vcard'),
            'view_item'             => __('View vCard', 'digi-vcard'),
            'all_items'             => __('All vCards', 'digi-vcard'),
            'search_items'          => __('Search vCards', 'digi-vcard'),
            'not_found'             => __('No vCards found.', 'digi-vcard'),
            'not_found_in_trash'    => __('No vCards found in Trash.', 'digi-vcard'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'vcard'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-id-alt',
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
        );

        register_post_type('vcard', $args);
    }
    
    /**
     * Register Taxonomies
     */
    public function register_taxonomies() {
        // vCard Industries
        register_taxonomy('vcard_industry', array('vcard'), array(
            'hierarchical'      => true,
            'labels'            => array(
                'name'              => _x('Industries', 'taxonomy general name', 'digi-vcard'),
                'singular_name'     => _x('Industry', 'taxonomy singular name', 'digi-vcard'),
                'menu_name'         => __('Industries', 'digi-vcard'),
                'all_items'         => __('All Industries', 'digi-vcard'),
                'edit_item'         => __('Edit Industry', 'digi-vcard'),
                'update_item'       => __('Update Industry', 'digi-vcard'),
                'add_new_item'      => __('Add New Industry', 'digi-vcard'),
                'new_item_name'     => __('New Industry Name', 'digi-vcard'),
            ),
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'vcard-industry'),
        ));
    }
    
    /**
     * Add Admin Hooks
     */
    public function add_admin_hooks() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_filter('manage_vcard_posts_columns', array($this, 'admin_columns'));
        add_action('manage_vcard_posts_custom_column', array($this, 'admin_column_content'), 10, 2);
    }
    
    /**
     * Add Template Hooks
     */
    public function add_template_hooks() {
        add_filter('single_template', array($this, 'load_single_template'));
        add_filter('archive_template', array($this, 'load_archive_template'));
        add_filter('the_content', array($this, 'display_vcard_content'));
    }
    
    /**
     * Load Single vCard Template
     */
    public function load_single_template($template) {
        if (is_singular('vcard')) {
            $plugin_template = DIGI_VCARD_PLUGIN_PATH . 'templates/single-vcard-page.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
    
    /**
     * Load Archive vCard Template
     */
    public function load_archive_template($template) {
        if (is_post_type_archive('vcard')) {
            $plugin_template = DIGI_VCARD_PLUGIN_PATH . 'templates/archive-vcard.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
    
    /**
     * Display vCard Content
     */
    public function display_vcard_content($content) {
        if (is_singular('vcard') && is_main_query() && !is_admin()) {
            ob_start();
            include DIGI_VCARD_PLUGIN_PATH . 'templates/single-vcard.php';
            $vcard_content = ob_get_clean();
            return $vcard_content;
        }
        return $content;
    }
    
    /**
     * Add Meta Boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'vcard_basic_info',
            __('Basic Information', 'digi-vcard'),
            array($this, 'basic_info_meta_box'),
            'vcard',
            'normal',
            'high'
        );
        
        add_meta_box(
            'vcard_contact_info',
            __('Contact Information', 'digi-vcard'),
            array($this, 'contact_info_meta_box'),
            'vcard',
            'normal',
            'high'
        );
    }
    
    /**
     * Basic Info Meta Box
     */
    public function basic_info_meta_box($post) {
        wp_nonce_field('vcard_meta_box', 'vcard_meta_box_nonce');
        
        $job_title = get_post_meta($post->ID, '_vcard_job_title', true);
        $company = get_post_meta($post->ID, '_vcard_company', true);
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="vcard_job_title">' . __('Job Title', 'digi-vcard') . '</label></th>';
        echo '<td><input type="text" id="vcard_job_title" name="vcard_job_title" value="' . esc_attr($job_title) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="vcard_company">' . __('Company/Organization', 'digi-vcard') . '</label></th>';
        echo '<td><input type="text" id="vcard_company" name="vcard_company" value="' . esc_attr($company) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';
    }
    
    /**
     * Contact Info Meta Box
     */
    public function contact_info_meta_box($post) {
        $email = get_post_meta($post->ID, '_vcard_email', true);
        $phone = get_post_meta($post->ID, '_vcard_phone', true);
        $office_phone = get_post_meta($post->ID, '_vcard_office_phone', true);
        $address = get_post_meta($post->ID, '_vcard_address', true);
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="vcard_email">' . __('Email Address', 'digi-vcard') . '</label></th>';
        echo '<td><input type="email" id="vcard_email" name="vcard_email" value="' . esc_attr($email) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="vcard_phone">' . __('Phone Number', 'digi-vcard') . '</label></th>';
        echo '<td><input type="text" id="vcard_phone" name="vcard_phone" value="' . esc_attr($phone) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="vcard_office_phone">' . __('Office Phone', 'digi-vcard') . '</label></th>';
        echo '<td><input type="text" id="vcard_office_phone" name="vcard_office_phone" value="' . esc_attr($office_phone) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="vcard_address">' . __('Address', 'digi-vcard') . '</label></th>';
        echo '<td><textarea id="vcard_address" name="vcard_address" rows="3" class="large-text">' . esc_textarea($address) . '</textarea></td>';
        echo '</tr>';
        echo '</table>';
    }
    
    /**
     * Save Meta Boxes
     */
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['vcard_meta_box_nonce']) || !wp_verify_nonce($_POST['vcard_meta_box_nonce'], 'vcard_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save basic info
        if (isset($_POST['vcard_job_title'])) {
            update_post_meta($post_id, '_vcard_job_title', sanitize_text_field($_POST['vcard_job_title']));
        }
        
        if (isset($_POST['vcard_company'])) {
            update_post_meta($post_id, '_vcard_company', sanitize_text_field($_POST['vcard_company']));
        }
        
        // Save contact info
        if (isset($_POST['vcard_email'])) {
            update_post_meta($post_id, '_vcard_email', sanitize_email($_POST['vcard_email']));
        }
        
        if (isset($_POST['vcard_phone'])) {
            update_post_meta($post_id, '_vcard_phone', sanitize_text_field($_POST['vcard_phone']));
        }
        
        if (isset($_POST['vcard_office_phone'])) {
            update_post_meta($post_id, '_vcard_office_phone', sanitize_text_field($_POST['vcard_office_phone']));
        }
        
        if (isset($_POST['vcard_address'])) {
            update_post_meta($post_id, '_vcard_address', sanitize_textarea_field($_POST['vcard_address']));
        }
    }
    
    /**
     * Admin Columns
     */
    public function admin_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['job_title'] = __('Job Title', 'digi-vcard');
        $new_columns['email'] = __('Email', 'digi-vcard');
        $new_columns['phone'] = __('Phone', 'digi-vcard');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Admin Column Content
     */
    public function admin_column_content($column, $post_id) {
        switch ($column) {
            case 'job_title':
                echo esc_html(get_post_meta($post_id, '_vcard_job_title', true));
                break;
            case 'email':
                echo esc_html(get_post_meta($post_id, '_vcard_email', true));
                break;
            case 'phone':
                echo esc_html(get_post_meta($post_id, '_vcard_phone', true));
                break;
        }
    }
    
    /**
     * Enqueue Scripts and Styles
     */
    public function enqueue_scripts() {
        if (is_singular('vcard') || is_post_type_archive('vcard')) {
            // Enqueue Font Awesome
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
            wp_enqueue_style('digi-vcard-style', DIGI_VCARD_PLUGIN_URL . 'assets/style.css', array('font-awesome'), DIGI_VCARD_VERSION);
            wp_enqueue_script('digi-vcard-script', DIGI_VCARD_PLUGIN_URL . 'assets/vcard.js', array(), DIGI_VCARD_VERSION, true);
        }
    }
    
    /**
     * Plugin Activation
     */
    public function activate() {
        $this->register_post_type();
        $this->register_taxonomies();
        flush_rewrite_rules();
        
        // Add default categories
        $categories = array(
            'CEO' => 'Chief Executive Officer and Business Leaders',
            'Construction' => 'Construction and Building Industry',
            'Coffeebar' => 'Coffee Shop and Restaurant Industry',
            'Healthcare' => 'Medical and Healthcare Professionals',
            'Education' => 'Teachers and Educational Professionals',
            'Freelancer' => 'Freelancers and Independent Contractors',
        );
        
        foreach ($categories as $name => $description) {
            if (!term_exists($name, 'vcard_industry')) {
                wp_insert_term($name, 'vcard_industry', array('description' => $description));
            }
        }
    }
    
    /**
     * Plugin Deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new DigiVCard();

/**
 * Helper Functions
 */
function get_vcard_meta($post_id, $key) {
    return get_post_meta($post_id, '_vcard_' . $key, true);
}

function display_vcard($post_id = null) {
    if (!$post_id) {
        global $post;
        $post_id = $post->ID;
    }
    
    include DIGI_VCARD_PLUGIN_PATH . 'templates/single-vcard.php';
}

/**
 * Shortcode for displaying vCard
 */
function vcard_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => get_the_ID(),
    ), $atts);
    
    ob_start();
    display_vcard($atts['id']);
    return ob_get_clean();
}
add_shortcode('vcard', 'vcard_shortcode');
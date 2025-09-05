<?php
/**
 * Business Profile management class for BizCard Pro
 *
 * @package BizCard_Pro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BizCard Pro Business Profile Class
 */
class BizCard_Pro_Business_Profile {
    
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
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Register custom post type
        $this->register_post_type();
        
        // Add rewrite rules for custom URLs
        $this->add_rewrite_rules();
        
        // Handle profile display
        add_action('template_redirect', array($this, 'handle_profile_display'));
    }
    
    /**
     * Register custom post type for business profiles
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Business Profiles', 'Post type general name', 'bizcard-pro'),
            'singular_name'         => _x('Business Profile', 'Post type singular name', 'bizcard-pro'),
            'menu_name'             => _x('BizCard Profiles', 'Admin Menu text', 'bizcard-pro'),
            'name_admin_bar'        => _x('Business Profile', 'Add New on Toolbar', 'bizcard-pro'),
            'add_new'               => __('Add New', 'bizcard-pro'),
            'add_new_item'          => __('Add New Business Profile', 'bizcard-pro'),
            'new_item'              => __('New Business Profile', 'bizcard-pro'),
            'edit_item'             => __('Edit Business Profile', 'bizcard-pro'),
            'view_item'             => __('View Business Profile', 'bizcard-pro'),
            'all_items'             => __('All Profiles', 'bizcard-pro'),
            'search_items'          => __('Search Profiles', 'bizcard-pro'),
            'not_found'             => __('No profiles found.', 'bizcard-pro'),
            'not_found_in_trash'    => __('No profiles found in Trash.', 'bizcard-pro'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'bizcard'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-businessperson',
            'supports'           => array('title', 'editor', 'thumbnail'),
        );

        register_post_type('bizcard_profile', $args);
    }
    
    /**
     * Add custom rewrite rules for pretty URLs
     */
    public function add_rewrite_rules() {
        // Custom URL structure: /profile/business-name
        add_rewrite_rule(
            '^profile/([^/]+)/?$',
            'index.php?bizcard_profile_slug=$matches[1]',
            'top'
        );
        
        // Add query var
        add_filter('query_vars', array($this, 'add_query_vars'));
    }
    
    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'bizcard_profile_slug';
        return $vars;
    }
    
    /**
     * Handle profile display
     */
    public function handle_profile_display() {
        $profile_slug = get_query_var('bizcard_profile_slug');
        
        if ($profile_slug) {
            $this->display_profile($profile_slug);
            exit;
        }
    }
    
    /**
     * Display business profile
     */
    private function display_profile($slug) {
        global $wpdb;
        
        // Convert URL slug back to business name
        $business_name = str_replace('-', ' ', $slug);
        $business_name = ucwords($business_name);
        
        // Get profile from database
        $table_name = BizCard_Pro_Database::get_table_name('profiles');
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE business_name = %s AND is_public = 1",
            $business_name
        ));
        
        if (!$profile) {
            // Try with original slug
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE business_name = %s AND is_public = 1",
                $slug
            ));
        }
        
        if (!$profile) {
            // Profile not found
            wp_die(__('Business profile not found.', 'bizcard-pro'), 404);
        }
        
        // Load profile template
        $this->load_profile_template($profile);
    }
    
    /**
     * Load profile template
     */
    private function load_profile_template($profile) {
        // Set global profile data
        global $bizcard_profile;
        $bizcard_profile = $profile;
        
        // Get styling
        global $wpdb;
        $styling_table = BizCard_Pro_Database::get_table_name('styling');
        $styling = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$styling_table} WHERE profile_id = %d",
            $profile->id
        ));
        
        global $bizcard_styling;
        $bizcard_styling = $styling;
        
        // Load template file
        $template_path = BIZCARD_PRO_PLUGIN_PATH . 'templates/profile-display.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback template
            $this->display_basic_profile($profile);
        }
    }
    
    /**
     * Basic profile display fallback
     */
    private function display_basic_profile($profile) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($profile->business_name); ?> - Business Profile</title>
            <?php wp_head(); ?>
        </head>
        <body>
            <div class="bizcard-profile-container">
                <h1><?php echo esc_html($profile->business_name); ?></h1>
                <?php if ($profile->business_tagline): ?>
                    <p class="tagline"><?php echo esc_html($profile->business_tagline); ?></p>
                <?php endif; ?>
                
                <?php if ($profile->business_description): ?>
                    <div class="description">
                        <?php echo wp_kses_post(wpautop($profile->business_description)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="contact-info">
                    <h2>Contact Information</h2>
                    <?php
                    $contact_info = json_decode($profile->contact_info, true);
                    if ($contact_info && is_array($contact_info)) {
                        foreach ($contact_info as $key => $value) {
                            if ($value) {
                                echo '<p><strong>' . ucfirst($key) . ':</strong> ' . esc_html($value) . '</p>';
                            }
                        }
                    }
                    ?>
                </div>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Create new business profile
     */
    public static function create_profile($data) {
        global $wpdb;
        
        $table_name = BizCard_Pro_Database::get_table_name('profiles');
        
        // Prepare data
        $profile_data = array(
            'user_id' => get_current_user_id(),
            'business_name' => sanitize_text_field($data['business_name']),
            'business_tagline' => sanitize_text_field($data['business_tagline'] ?? ''),
            'owner_name' => sanitize_text_field($data['owner_name'] ?? ''),
            'business_description' => wp_kses_post($data['business_description'] ?? ''),
            'contact_info' => json_encode($data['contact_info'] ?? array()),
            'profile_status' => 'draft'
        );
        
        // Insert profile
        $result = $wpdb->insert($table_name, $profile_data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create business profile.', 'bizcard-pro'));
        }
        
        $profile_id = $wpdb->insert_id;
        
        // Create default styling
        self::create_default_styling($profile_id);
        
        return $profile_id;
    }
    
    /**
     * Create default styling for new profile
     */
    private static function create_default_styling($profile_id) {
        global $wpdb;
        
        $styling_table = BizCard_Pro_Database::get_table_name('styling');
        
        $styling_data = array(
            'profile_id' => $profile_id,
            'style_theme' => 'professional',
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2'
        );
        
        $wpdb->insert($styling_table, $styling_data);
    }
    
    /**
     * Get profile by ID
     */
    public static function get_profile($profile_id) {
        global $wpdb;
        
        $table_name = BizCard_Pro_Database::get_table_name('profiles');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $profile_id
        ));
    }
    
    /**
     * Update profile
     */
    public static function update_profile($profile_id, $data) {
        global $wpdb;
        
        $table_name = BizCard_Pro_Database::get_table_name('profiles');
        
        // Prepare data
        $profile_data = array();
        
        if (isset($data['business_name'])) {
            $profile_data['business_name'] = sanitize_text_field($data['business_name']);
        }
        
        if (isset($data['business_tagline'])) {
            $profile_data['business_tagline'] = sanitize_text_field($data['business_tagline']);
        }
        
        if (isset($data['business_description'])) {
            $profile_data['business_description'] = wp_kses_post($data['business_description']);
        }
        
        if (isset($data['contact_info'])) {
            $profile_data['contact_info'] = json_encode($data['contact_info']);
        }
        
        if (empty($profile_data)) {
            return false;
        }
        
        // Update profile
        $result = $wpdb->update(
            $table_name,
            $profile_data,
            array('id' => $profile_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
}
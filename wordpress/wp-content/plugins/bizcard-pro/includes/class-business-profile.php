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
        
        // Add activation/deactivation hooks like movies plugin
        register_activation_hook(BIZCARD_PRO_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(BIZCARD_PRO_PLUGIN_FILE, array($this, 'deactivate'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->register_post_type();
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Register custom post type
        $this->register_post_type();
        
        // Hook into WordPress template system (simplified like movies plugin)
        add_filter('template_include', array($this, 'load_templates'));
        
        // Add shortcode for embedding profiles
        add_shortcode('bizcard_profile', array($this, 'profile_shortcode'));
        
        // Add meta box for linking profiles to posts
        add_action('add_meta_boxes', array($this, 'add_profile_meta_box'));
        add_action('save_post', array($this, 'save_profile_meta_box'));
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
        
        // Force flush rewrite rules on init (temporary for testing)
        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 20);
    }
    
    /**
     * Maybe flush rewrite rules
     */
    public function maybe_flush_rewrite_rules() {
        if (get_option('bizcard_pro_flush_rewrite_rules', false)) {
            flush_rewrite_rules();
            delete_option('bizcard_pro_flush_rewrite_rules');
        }
    }
    
    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'bizcard_profile_slug';
        return $vars;
    }
    
    /**
     * Modify main query to detect profile requests
     */
    public function modify_main_query($query) {
        // Only modify main query on frontend
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Check for profile parameters
        if (isset($_GET['bizcard_profile']) || isset($_GET['bizcard_id'])) {
            // Set a flag that we're viewing a profile
            $query->set('is_bizcard_profile', true);
            
            if (isset($_GET['bizcard_profile'])) {
                $query->set('bizcard_profile_name', sanitize_text_field($_GET['bizcard_profile']));
            }
            
            if (isset($_GET['bizcard_id'])) {
                $query->set('bizcard_profile_id', intval($_GET['bizcard_id']));
            }
        }
    }
    
    /**
     * Load templates (simplified like movies plugin)
     */
    public function load_templates($template) {
        // Handle bizcard_profile archive
        if (is_post_type_archive('bizcard_profile')) {
            $theme_template = locate_template(['archive-bizcard_profile.php']);
            if (!$theme_template) {
                return BIZCARD_PRO_PLUGIN_PATH . 'templates/archive-bizcard_profile.php';
            }
        }
        
        // Handle single bizcard_profile posts
        if (is_singular('bizcard_profile')) {
            $theme_template = locate_template(['single-bizcard_profile.php']);
            if (!$theme_template) {
                return BIZCARD_PRO_PLUGIN_PATH . 'templates/single-bizcard_profile.php';
            }
        }
        
        return $template;
    }
    
    /**
     * Load profile template when needed (for direct URL access)
     */
    public function load_profile_template_filter($template) {
        global $wp_query;
        
        // Check if this is a profile request
        if ($wp_query->get('is_bizcard_profile')) {
            // Load our custom template
            $plugin_template = BIZCARD_PRO_PLUGIN_PATH . 'templates/profile-display.php';
            
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Add meta box for linking profiles to posts
     */
    public function add_profile_meta_box() {
        add_meta_box(
            'bizcard_profile_link',
            __('BizCard Profile', 'bizcard-pro'),
            array($this, 'profile_meta_box_callback'),
            'bizcard_profile',
            'side',
            'default'
        );
    }
    
    /**
     * Meta box callback
     */
    public function profile_meta_box_callback($post) {
        // Add nonce field
        wp_nonce_field('bizcard_profile_meta_box', 'bizcard_profile_meta_box_nonce');
        
        // Get current value
        $profile_id = get_post_meta($post->ID, '_bizcard_profile_id', true);
        
        // Get available profiles
        global $wpdb;
        $table_name = BizCard_Pro_Database::get_table_name('profiles');
        $profiles = $wpdb->get_results("SELECT id, business_name FROM {$table_name} ORDER BY business_name");
        
        echo '<label for="bizcard_profile_id">' . __('Select Profile:', 'bizcard-pro') . '</label>';
        echo '<select name="bizcard_profile_id" id="bizcard_profile_id" style="width: 100%;">';
        echo '<option value="">' . __('Select a profile...', 'bizcard-pro') . '</option>';
        
        foreach ($profiles as $profile) {
            $selected = selected($profile_id, $profile->id, false);
            echo '<option value="' . esc_attr($profile->id) . '" ' . $selected . '>';
            echo esc_html($profile->business_name);
            echo '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">' . __('Link this post to a business profile from the database.', 'bizcard-pro') . '</p>';
    }
    
    /**
     * Save meta box data
     */
    public function save_profile_meta_box($post_id) {
        // Check if nonce is valid
        if (!isset($_POST['bizcard_profile_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['bizcard_profile_meta_box_nonce'], 'bizcard_profile_meta_box')) {
            return;
        }
        
        // Check if user has permission
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save the profile ID
        if (isset($_POST['bizcard_profile_id'])) {
            update_post_meta($post_id, '_bizcard_profile_id', sanitize_text_field($_POST['bizcard_profile_id']));
        }
    }
    
    /**
     * Display business profile
     */
    private function display_profile($slug) {
        global $wpdb;
        
        // Debug: Log the request
        error_log("BizCard Pro: Attempting to display profile for slug: " . $slug);
        
        // Convert URL slug back to business name
        $business_name = str_replace('-', ' ', $slug);
        $business_name = ucwords($business_name);
        
        // Get profile from database
        $table_name = BizCard_Pro_Database::get_table_name('profiles');
        
        // Try multiple variations
        $search_terms = array($business_name, $slug, 'Test Business');
        $profile = null;
        
        foreach ($search_terms as $term) {
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE business_name = %s AND is_public = 1",
                $term
            ));
            if ($profile) {
                error_log("BizCard Pro: Found profile with term: " . $term);
                break;
            }
        }
        
        if (!$profile) {
            // Debug: Show what profiles exist
            $all_profiles = $wpdb->get_results("SELECT business_name FROM {$table_name}");
            error_log("BizCard Pro: Available profiles: " . print_r($all_profiles, true));
            
            // Show debug page instead of 404
            $this->show_debug_page($slug, $all_profiles);
            exit;
        }
        
        // Load profile template
        $this->load_profile_template($profile);
    }
    
    /**
     * Show debug page when profile not found
     */
    private function show_debug_page($slug, $profiles) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>BizCard Pro Debug</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .debug-info { background: #f0f0f0; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .profile-list { background: #e8f4f8; padding: 15px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <h1>BizCard Pro Debug Page</h1>
            
            <div class="debug-info">
                <h2>Request Information</h2>
                <p><strong>Requested Slug:</strong> <?php echo esc_html($slug); ?></p>
                <p><strong>Request URI:</strong> <?php echo esc_html($_SERVER['REQUEST_URI'] ?? 'Not set'); ?></p>
                <p><strong>Converted Name:</strong> <?php echo esc_html(ucwords(str_replace('-', ' ', $slug))); ?></p>
            </div>
            
            <div class="profile-list">
                <h2>Available Profiles</h2>
                <?php if (empty($profiles)): ?>
                    <p>No profiles found in database.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($profiles as $profile): ?>
                            <li><?php echo esc_html($profile->business_name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <p><a href="<?php echo admin_url('admin.php?page=bizcard-pro'); ?>">← Back to BizCard Pro Admin</a></p>
        </body>
        </html>
        <?php
    }
    
    /**
     * Display profile by ID
     */
    private function display_profile_by_id($profile_id) {
        global $wpdb;
        
        $table_name = BizCard_Pro_Database::get_table_name('profiles');
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND is_public = 1",
            $profile_id
        ));
        
        if (!$profile) {
            wp_die(__('Business profile not found.', 'bizcard-pro'), 404);
        }
        
        $this->load_profile_template($profile);
    }
    
    /**
     * Profile shortcode
     */
    public function profile_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'name' => ''
        ), $atts);
        
        if ($atts['id']) {
            $profile = self::get_profile($atts['id']);
        } elseif ($atts['name']) {
            global $wpdb;
            $table_name = BizCard_Pro_Database::get_table_name('profiles');
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE business_name = %s AND is_public = 1",
                $atts['name']
            ));
        } else {
            return '<p>Please specify profile ID or name.</p>';
        }
        
        if (!$profile) {
            return '<p>Profile not found.</p>';
        }
        
        // Return embedded profile HTML
        ob_start();
        $this->render_embedded_profile($profile);
        return ob_get_clean();
    }
    
    /**
     * Render embedded profile (for shortcode)
     */
    private function render_embedded_profile($profile) {
        // Simple embedded version
        $contact_info = json_decode($profile->contact_info, true) ?: array();
        ?>
        <div class="bizcard-embedded-profile" style="border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3><?php echo esc_html($profile->business_name); ?></h3>
            <?php if ($profile->business_tagline): ?>
                <p><em><?php echo esc_html($profile->business_tagline); ?></em></p>
            <?php endif; ?>
            
            <?php if (!empty($contact_info)): ?>
                <div class="contact-info">
                    <?php foreach ($contact_info as $key => $value): ?>
                        <?php if ($value): ?>
                            <p><strong><?php echo ucfirst($key); ?>:</strong> <?php echo esc_html($value); ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <p><a href="<?php echo esc_url(home_url('?bizcard_id=' . $profile->id)); ?>" target="_blank">View Full Profile</a></p>
        </div>
        <?php
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
        
        // Create WordPress post for this profile
        self::create_profile_post($profile_id, $data);
        
        return $profile_id;
    }
    
    /**
     * Create WordPress post for profile
     */
    private static function create_profile_post($profile_id, $data) {
        $post_data = array(
            'post_title'    => sanitize_text_field($data['business_name']),
            'post_content'  => wp_kses_post($data['business_description'] ?? ''),
            'post_status'   => 'publish',
            'post_type'     => 'bizcard_profile',
            'post_author'   => get_current_user_id(),
            'meta_input'    => array(
                '_bizcard_profile_id' => $profile_id
            )
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Update the profile with the post ID
            global $wpdb;
            $table_name = BizCard_Pro_Database::get_table_name('profiles');
            $wpdb->update(
                $table_name,
                array('post_id' => $post_id),
                array('id' => $profile_id),
                array('%d'),
                array('%d')
            );
        }
        
        return $post_id;
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
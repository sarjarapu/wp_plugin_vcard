<?php
/**
 * Dashboard Authentication and Access Control Class
 * 
 * Handles user authentication, session management, and access control
 * for business client dashboard functionality.
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_Dashboard_Auth {
    
    /**
     * Current user ID
     * @var int
     */
    private $current_user_id;
    
    /**
     * User capabilities cache
     * @var array
     */
    private $user_capabilities = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->current_user_id = get_current_user_id();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Authentication hooks
        add_action('wp_login', array($this, 'handle_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'handle_user_logout'));
        
        // Access control hooks
        add_action('init', array($this, 'check_dashboard_access'));
        add_filter('user_has_cap', array($this, 'filter_user_capabilities'), 10, 4);
        
        // Profile editing restrictions
        add_action('load-post.php', array($this, 'restrict_profile_editing'));
        add_action('load-post-new.php', array($this, 'restrict_profile_creation'));
        add_filter('pre_get_posts', array($this, 'filter_profile_queries'));
        
        // Dashboard menu restrictions
        add_action('admin_menu', array($this, 'modify_admin_menu'), 999);
        add_action('admin_bar_menu', array($this, 'modify_admin_bar'), 999);
    }
    
    /**
     * Handle user login
     * 
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function handle_user_login($user_login, $user) {
        // Set session data for vCard clients
        if ($this->is_vcard_client($user->ID)) {
            $this->set_client_session_data($user->ID);
        }
        
        // Log login activity
        $this->log_user_activity($user->ID, 'login');
    }
    
    /**
     * Handle user logout
     */
    public function handle_user_logout() {
        if ($this->current_user_id) {
            $this->log_user_activity($this->current_user_id, 'logout');
            $this->clear_client_session_data();
        }
    }
    
    /**
     * Check dashboard access permissions
     */
    public function check_dashboard_access() {
        // Skip if not in admin area
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // Skip for super admins
        if (is_super_admin()) {
            return;
        }
        
        $current_user = wp_get_current_user();
        
        // Redirect non-vCard users away from vCard admin pages
        if ($this->is_vcard_admin_page() && !$this->can_access_vcard_dashboard($current_user->ID)) {
            wp_redirect(home_url());
            exit;
        }
        
        // Restrict vCard clients to only vCard-related pages
        if ($this->is_vcard_client($current_user->ID) && !$this->is_allowed_admin_page()) {
            wp_redirect($this->get_client_dashboard_url());
            exit;
        }
    }
    
    /**
     * Filter user capabilities for vCard-specific permissions
     * 
     * @param array $allcaps All capabilities
     * @param array $caps Required capabilities
     * @param array $args Arguments
     * @param WP_User $user User object
     * @return array
     */
    public function filter_user_capabilities($allcaps, $caps, $args, $user) {
        // Handle vCard profile editing capabilities
        if (in_array('edit_vcard_profile', $caps) || in_array('delete_vcard_profile', $caps)) {
            $post_id = isset($args[2]) ? $args[2] : 0;
            
            if ($post_id && get_post_type($post_id) === 'vcard_profile') {
                $post_author = get_post_field('post_author', $post_id);
                
                // Allow editing only own profiles for vCard clients
                if ($this->is_vcard_client($user->ID) && $post_author == $user->ID) {
                    $allcaps['edit_vcard_profile'] = true;
                    $allcaps['delete_vcard_profile'] = true;
                }
                
                // Allow admins to edit all profiles
                if (user_can($user->ID, 'manage_options')) {
                    $allcaps['edit_vcard_profile'] = true;
                    $allcaps['delete_vcard_profile'] = true;
                }
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Restrict profile editing to owners only
     */
    public function restrict_profile_editing() {
        global $post;
        
        if (!$post || $post->post_type !== 'vcard_profile') {
            return;
        }
        
        $current_user_id = get_current_user_id();
        
        // Allow admins full access
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Check if user can edit this specific profile
        if (!$this->can_edit_profile($current_user_id, $post->ID)) {
            wp_die(__('You do not have permission to edit this profile.', 'vcard'));
        }
    }
    
    /**
     * Restrict profile creation
     */
    public function restrict_profile_creation() {
        global $typenow;
        
        if ($typenow !== 'vcard_profile') {
            return;
        }
        
        $current_user_id = get_current_user_id();
        
        // Allow admins to create profiles
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Check if user can create profiles
        if (!$this->can_create_profile($current_user_id)) {
            wp_die(__('You do not have permission to create profiles.', 'vcard'));
        }
        
        // Check subscription limits
        if (!$this->check_profile_creation_limits($current_user_id)) {
            wp_die(__('You have reached your profile creation limit. Please upgrade your subscription.', 'vcard'));
        }
    }
    
    /**
     * Filter profile queries to show only user's own profiles
     * 
     * @param WP_Query $query
     */
    public function filter_profile_queries($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('post_type') !== 'vcard_profile') {
            return;
        }
        
        $current_user_id = get_current_user_id();
        
        // Skip filtering for admins
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Show only user's own profiles for vCard clients
        if ($this->is_vcard_client($current_user_id)) {
            $query->set('author', $current_user_id);
        }
    }
    
    /**
     * Modify admin menu for vCard clients
     */
    public function modify_admin_menu() {
        $current_user_id = get_current_user_id();
        
        if (!$this->is_vcard_client($current_user_id)) {
            return;
        }
        
        // Remove unnecessary menu items for vCard clients
        $menu_items_to_remove = array(
            'index.php',           // Dashboard
            'edit.php',            // Posts
            'upload.php',          // Media (keep for gallery management)
            'edit.php?post_type=page', // Pages
            'edit-comments.php',   // Comments
            'themes.php',          // Appearance
            'plugins.php',         // Plugins
            'users.php',           // Users
            'tools.php',           // Tools
            'options-general.php', // Settings
        );
        
        foreach ($menu_items_to_remove as $menu_item) {
            if ($menu_item !== 'upload.php') { // Keep media for gallery management
                remove_menu_page($menu_item);
            }
        }
        
        // Add custom vCard dashboard menu
        add_menu_page(
            __('My vCard Dashboard', 'vcard'),
            __('Dashboard', 'vcard'),
            'read',
            'vcard-dashboard',
            array($this, 'render_client_dashboard'),
            'dashicons-id-alt',
            2
        );
        
        // Modify vCard profiles menu
        global $submenu;
        if (isset($submenu['edit.php?post_type=vcard_profile'])) {
            // Rename menu items for better UX
            foreach ($submenu['edit.php?post_type=vcard_profile'] as $key => $item) {
                if ($item[2] === 'edit.php?post_type=vcard_profile') {
                    $submenu['edit.php?post_type=vcard_profile'][$key][0] = __('My Profiles', 'vcard');
                } elseif ($item[2] === 'post-new.php?post_type=vcard_profile') {
                    $submenu['edit.php?post_type=vcard_profile'][$key][0] = __('Create New Profile', 'vcard');
                }
            }
        }
    }
    
    /**
     * Modify admin bar for vCard clients
     * 
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function modify_admin_bar($wp_admin_bar) {
        $current_user_id = get_current_user_id();
        
        if (!$this->is_vcard_client($current_user_id)) {
            return;
        }
        
        // Remove unnecessary admin bar items
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('about');
        $wp_admin_bar->remove_node('wporg');
        $wp_admin_bar->remove_node('documentation');
        $wp_admin_bar->remove_node('support-forums');
        $wp_admin_bar->remove_node('feedback');
        $wp_admin_bar->remove_node('new-content');
        $wp_admin_bar->remove_node('comments');
        
        // Add vCard-specific admin bar items
        $wp_admin_bar->add_node(array(
            'id'    => 'vcard-dashboard',
            'title' => __('My Dashboard', 'vcard'),
            'href'  => admin_url('admin.php?page=vcard-dashboard'),
        ));
        
        $wp_admin_bar->add_node(array(
            'id'    => 'vcard-new-profile',
            'title' => __('New Profile', 'vcard'),
            'href'  => admin_url('post-new.php?post_type=vcard_profile'),
        ));
    }
    
    /**
     * Check if user is a vCard client
     * 
     * @param int $user_id
     * @return bool
     */
    public function is_vcard_client($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return false;
        }
        
        return in_array('vcard_client', $user->roles) || 
               in_array('vcard_user', $user->roles);
    }
    
    /**
     * Check if user can access vCard dashboard
     * 
     * @param int $user_id
     * @return bool
     */
    public function can_access_vcard_dashboard($user_id) {
        return $this->is_vcard_client($user_id) || 
               user_can($user_id, 'manage_options');
    }
    
    /**
     * Check if user can edit specific profile
     * 
     * @param int $user_id
     * @param int $profile_id
     * @return bool
     */
    public function can_edit_profile($user_id, $profile_id) {
        // Admins can edit all profiles
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Users can edit only their own profiles
        $profile_author = get_post_field('post_author', $profile_id);
        return $profile_author == $user_id && $this->is_vcard_client($user_id);
    }
    
    /**
     * Check if user can create profiles
     * 
     * @param int $user_id
     * @return bool
     */
    public function can_create_profile($user_id) {
        return $this->is_vcard_client($user_id) || 
               user_can($user_id, 'manage_options');
    }
    
    /**
     * Check profile creation limits based on subscription
     * 
     * @param int $user_id
     * @return bool
     */
    public function check_profile_creation_limits($user_id) {
        // Get user's subscription plan
        $subscription_plan = get_user_meta($user_id, '_vcard_subscription_plan', true);
        if (empty($subscription_plan)) {
            $subscription_plan = 'free';
        }
        
        // Define limits per plan
        $limits = array(
            'free' => 1,
            'basic' => 3,
            'professional' => -1, // Unlimited
        );
        
        $limit = isset($limits[$subscription_plan]) ? $limits[$subscription_plan] : 1;
        
        // Unlimited profiles
        if ($limit === -1) {
            return true;
        }
        
        // Count existing profiles
        $existing_profiles = get_posts(array(
            'post_type' => 'vcard_profile',
            'author' => $user_id,
            'post_status' => array('publish', 'draft', 'private'),
            'numberposts' => -1,
            'fields' => 'ids',
        ));
        
        return count($existing_profiles) < $limit;
    }
    
    /**
     * Check if current page is a vCard admin page
     * 
     * @return bool
     */
    private function is_vcard_admin_page() {
        global $pagenow, $typenow;
        
        // vCard post type pages
        if ($typenow === 'vcard_profile') {
            return true;
        }
        
        // vCard dashboard page
        if ($pagenow === 'admin.php' && isset($_GET['page']) && 
            strpos($_GET['page'], 'vcard') === 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if current page is allowed for vCard clients
     * 
     * @return bool
     */
    private function is_allowed_admin_page() {
        global $pagenow, $typenow;
        
        $allowed_pages = array(
            'admin.php',
            'post.php',
            'post-new.php',
            'edit.php',
            'upload.php', // For gallery management
            'media-upload.php',
            'async-upload.php',
            'admin-ajax.php',
        );
        
        // Allow vCard-related pages
        if (in_array($pagenow, $allowed_pages)) {
            // For post-related pages, check if it's vCard profile
            if (in_array($pagenow, array('post.php', 'post-new.php', 'edit.php'))) {
                return $typenow === 'vcard_profile' || 
                       (isset($_GET['post_type']) && $_GET['post_type'] === 'vcard_profile');
            }
            
            // For admin.php, check if it's vCard dashboard
            if ($pagenow === 'admin.php') {
                return isset($_GET['page']) && strpos($_GET['page'], 'vcard') === 0;
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get client dashboard URL
     * 
     * @return string
     */
    public function get_client_dashboard_url() {
        return admin_url('admin.php?page=vcard-dashboard');
    }
    
    /**
     * Set client session data
     * 
     * @param int $user_id
     */
    private function set_client_session_data($user_id) {
        // Store last login time
        update_user_meta($user_id, '_vcard_last_login', current_time('mysql'));
        
        // Update login count
        $login_count = get_user_meta($user_id, '_vcard_login_count', true);
        update_user_meta($user_id, '_vcard_login_count', intval($login_count) + 1);
    }
    
    /**
     * Clear client session data
     */
    private function clear_client_session_data() {
        // Clear any temporary session data if needed
        // This can be extended for more complex session management
    }
    
    /**
     * Log user activity
     * 
     * @param int $user_id
     * @param string $action
     */
    private function log_user_activity($user_id, $action) {
        // Simple activity logging
        $activity_log = get_user_meta($user_id, '_vcard_activity_log', true);
        if (!is_array($activity_log)) {
            $activity_log = array();
        }
        
        $activity_log[] = array(
            'action' => $action,
            'timestamp' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        );
        
        // Keep only last 50 activities
        if (count($activity_log) > 50) {
            $activity_log = array_slice($activity_log, -50);
        }
        
        update_user_meta($user_id, '_vcard_activity_log', $activity_log);
    }
    
    /**
     * Render client dashboard page
     */
    public function render_client_dashboard() {
        $current_user_id = get_current_user_id();
        
        // Get user's profiles
        $profiles = get_posts(array(
            'post_type' => 'vcard_profile',
            'author' => $current_user_id,
            'post_status' => array('publish', 'draft', 'private'),
            'numberposts' => -1,
        ));
        
        // Get subscription info
        $subscription_plan = get_user_meta($current_user_id, '_vcard_subscription_plan', true);
        if (empty($subscription_plan)) {
            $subscription_plan = 'free';
        }
        
        include VCARD_ADMIN_PATH . 'dashboard-client.php';
    }
    
    /**
     * Get user role verification
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_role_info($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return array(
                'is_vcard_client' => false,
                'roles' => array(),
                'capabilities' => array(),
            );
        }
        
        return array(
            'is_vcard_client' => $this->is_vcard_client($user_id),
            'roles' => $user->roles,
            'capabilities' => array_keys($user->allcaps),
        );
    }
    
    /**
     * Verify user permissions for specific action
     * 
     * @param int $user_id
     * @param string $action
     * @param mixed $context
     * @return bool
     */
    public function verify_user_permission($user_id, $action, $context = null) {
        switch ($action) {
            case 'edit_profile':
                return $this->can_edit_profile($user_id, $context);
                
            case 'create_profile':
                return $this->can_create_profile($user_id) && 
                       $this->check_profile_creation_limits($user_id);
                
            case 'access_dashboard':
                return $this->can_access_vcard_dashboard($user_id);
                
            case 'manage_gallery':
                return $this->is_vcard_client($user_id) || 
                       user_can($user_id, 'manage_options');
                
            default:
                return false;
        }
    }
}
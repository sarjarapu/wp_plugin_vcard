<?php
/**
 * Admin class for BizCard Pro
 *
 * @package BizCard_Pro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BizCard Pro Admin Class
 */
class BizCard_Pro_Admin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('BizCard Pro', 'bizcard-pro'),
            __('BizCard Pro', 'bizcard-pro'),
            'manage_options',
            'bizcard-pro',
            array($this, 'admin_page'),
            'dashicons-businessperson',
            30
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php _e('Welcome to BizCard Pro! The plugin has been successfully activated.', 'bizcard-pro'); ?></p>
            
            <div class="card">
                <h2><?php _e('Database Status', 'bizcard-pro'); ?></h2>
                <?php
                if (BizCard_Pro_Database::tables_exist()) {
                    echo '<p style="color: green;">✅ ' . __('Database tables created successfully!', 'bizcard-pro') . '</p>';
                } else {
                    echo '<p style="color: red;">❌ ' . __('Database tables not found. Please deactivate and reactivate the plugin.', 'bizcard-pro') . '</p>';
                }
                ?>
            </div>
            
            <div class="card">
                <h2><?php _e('Database Tables', 'bizcard-pro'); ?></h2>
                <?php
                global $wpdb;
                $tables = array(
                    'profiles', 'services', 'products', 'gallery', 'styling',
                    'subscriptions', 'analytics', 'saved_contacts', 'reviews', 'certifications'
                );
                
                echo '<ul>';
                foreach ($tables as $table) {
                    $table_name = $wpdb->prefix . 'bizcard_' . $table;
                    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
                    $status = $exists ? '✅' : '❌';
                    echo '<li>' . $status . ' ' . $table_name . '</li>';
                }
                echo '</ul>';
                ?>
            </div>
            
            <div class="card">
                <h2><?php _e('Test Profile Creation', 'bizcard-pro'); ?></h2>
                <p><?php _e('Click the button below to create a test business profile:', 'bizcard-pro'); ?></p>
                <form method="post" action="" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field('create_test_profile', 'test_profile_nonce'); ?>
                    <input type="submit" name="create_test_profile" class="button button-primary" value="<?php _e('Create Test Profile', 'bizcard-pro'); ?>">
                </form>
                
                <form method="post" action="" style="display: inline-block;">
                    <?php wp_nonce_field('flush_rewrite_rules', 'flush_rules_nonce'); ?>
                    <input type="submit" name="flush_rewrite_rules" class="button button-secondary" value="<?php _e('Fix Profile URLs', 'bizcard-pro'); ?>">
                </form>
                
                <?php
                if (isset($_POST['create_test_profile']) && wp_verify_nonce($_POST['test_profile_nonce'], 'create_test_profile')) {
                    $this->create_test_profile();
                }
                
                if (isset($_POST['flush_rewrite_rules']) && wp_verify_nonce($_POST['flush_rules_nonce'], 'flush_rewrite_rules')) {
                    flush_rewrite_rules();
                    update_option('bizcard_pro_flush_rewrite_rules', true);
                    echo '<div class="notice notice-success"><p>' . __('Profile URLs have been refreshed! Try viewing your profile again.', 'bizcard-pro') . '</p></div>';
                }
                ?>
            </div>
            
            <div class="card">
                <h2><?php _e('URL Rewrite Debug', 'bizcard-pro'); ?></h2>
                <?php $this->debug_rewrite_rules(); ?>
            </div>
            
            <div class="card">
                <h2><?php _e('Existing Profiles', 'bizcard-pro'); ?></h2>
                <?php $this->list_profiles(); ?>
            </div>
            
            <div class="card">
                <h2><?php _e('Next Steps', 'bizcard-pro'); ?></h2>
                <ul>
                    <li><?php _e('Create your first business profile', 'bizcard-pro'); ?></li>
                    <li><?php _e('Configure plugin settings', 'bizcard-pro'); ?></li>
                    <li><?php _e('Set up payment integration', 'bizcard-pro'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Create test profile
     */
    private function create_test_profile() {
        $test_data = array(
            'business_name' => 'Test Business',
            'business_tagline' => 'Your trusted partner for quality services',
            'owner_name' => 'John Doe',
            'business_description' => 'This is a test business profile created to demonstrate the BizCard Pro functionality.',
            'contact_info' => array(
                'email' => 'test@example.com',
                'phone' => '+1 (555) 123-4567',
                'website' => 'https://example.com'
            )
        );
        
        $profile_id = BizCard_Pro_Business_Profile::create_profile($test_data);
        
        if (is_wp_error($profile_id)) {
            echo '<div class="notice notice-error"><p>' . $profile_id->get_error_message() . '</p></div>';
        } else {
            // Publish the profile so it's publicly visible
            global $wpdb;
            $table_name = BizCard_Pro_Database::get_table_name('profiles');
            $wpdb->update(
                $table_name,
                array('profile_status' => 'published'),
                array('id' => $profile_id),
                array('%s'),
                array('%d')
            );
            
            // Flush rewrite rules to ensure URLs work
            flush_rewrite_rules();
            
            // Get the created post
            $profile = BizCard_Pro_Business_Profile::get_profile($profile_id);
            if ($profile && $profile->post_id) {
                $post_url = get_permalink($profile->post_id);
                echo '<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;">';
                echo '<h4>' . __('WordPress Post Created:', 'bizcard-pro') . '</h4>';
                echo '<p><strong>Post URL:</strong> <a href="' . esc_url($post_url) . '" target="_blank">' . esc_html($post_url) . '</a></p>';
                echo '<p><em>' . __('This uses the WordPress template system with single-business_profile.php', 'bizcard-pro') . '</em></p>';
                echo '</div>';
            }
            
            echo '<div class="notice notice-success"><p>' . sprintf(__('Test profile created successfully! Profile ID: %d', 'bizcard-pro'), $profile_id) . '</p></div>';
            
            // Show working URL options
            $profile_urls = array(
                'By ID: ' . home_url('?bizcard_id=' . $profile_id),
                'By Name: ' . home_url('?bizcard_profile=Test%20Business')
            );
            
            echo '<div style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin: 10px 0;">';
            echo '<h4>' . __('View your test profile:', 'bizcard-pro') . '</h4>';
            foreach ($profile_urls as $url_desc) {
                list($desc, $url) = explode(': ', $url_desc, 2);
                echo '<p><strong>' . $desc . ':</strong> <a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a></p>';
            }
            echo '<p><em>' . __('Note: These URLs use query parameters which work reliably with any WordPress setup.', 'bizcard-pro') . '</em></p>';
            echo '</div>';
        }
    }
    
    /**
     * List existing profiles
     */
    private function list_profiles() {
        global $wpdb;
        $table_name = BizCard_Pro_Database::get_table_name('profiles');
        
        $profiles = $wpdb->get_results("SELECT id, business_name, profile_status, created_at FROM {$table_name} ORDER BY created_at DESC LIMIT 10");
        
        if (empty($profiles)) {
            echo '<p>' . __('No profiles found. Create a test profile to get started!', 'bizcard-pro') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('ID', 'bizcard-pro') . '</th>';
        echo '<th>' . __('Business Name', 'bizcard-pro') . '</th>';
        echo '<th>' . __('Status', 'bizcard-pro') . '</th>';
        echo '<th>' . __('Created', 'bizcard-pro') . '</th>';
        echo '<th>' . __('Actions', 'bizcard-pro') . '</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($profiles as $profile) {
            // Use query parameter approach (more reliable)
            $profile_url = home_url('?bizcard_id=' . $profile->id);
            $profile_url_name = home_url('?bizcard_profile=' . urlencode($profile->business_name));
            
            echo '<tr>';
            echo '<td>' . esc_html($profile->id) . '</td>';
            echo '<td><strong>' . esc_html($profile->business_name) . '</strong></td>';
            echo '<td>' . esc_html($profile->profile_status) . '</td>';
            echo '<td>' . esc_html($profile->created_at) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($profile_url) . '" target="_blank">' . __('View by ID', 'bizcard-pro') . '</a> | ';
            echo '<a href="' . esc_url($profile_url_name) . '" target="_blank">' . __('View by Name', 'bizcard-pro') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * Debug rewrite rules
     */
    private function debug_rewrite_rules() {
        global $wp_rewrite;
        
        echo '<p><strong>' . __('Current Permalink Structure:', 'bizcard-pro') . '</strong> ' . get_option('permalink_structure') . '</p>';
        
        // Check if our rewrite rule exists
        $rules = get_option('rewrite_rules');
        $our_rule_exists = false;
        
        if (is_array($rules)) {
            foreach ($rules as $pattern => $replacement) {
                if (strpos($pattern, 'profile') !== false) {
                    echo '<p style="color: green;">✅ ' . __('Profile rewrite rule found:', 'bizcard-pro') . ' <code>' . $pattern . ' → ' . $replacement . '</code></p>';
                    $our_rule_exists = true;
                    break;
                }
            }
        }
        
        if (!$our_rule_exists) {
            echo '<p style="color: red;">❌ ' . __('Profile rewrite rule not found. Click "Fix Profile URLs" button above.', 'bizcard-pro') . '</p>';
        }
        
        // Test URL
        echo '<p><strong>' . __('Test Profile URL:', 'bizcard-pro') . '</strong> <a href="' . home_url('/profile/test-business') . '" target="_blank">' . home_url('/profile/test-business') . '</a></p>';
    }
}
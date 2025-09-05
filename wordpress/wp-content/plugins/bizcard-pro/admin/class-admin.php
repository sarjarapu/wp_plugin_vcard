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
                <form method="post" action="">
                    <?php wp_nonce_field('create_test_profile', 'test_profile_nonce'); ?>
                    <input type="submit" name="create_test_profile" class="button button-primary" value="<?php _e('Create Test Profile', 'bizcard-pro'); ?>">
                </form>
                
                <?php
                if (isset($_POST['create_test_profile']) && wp_verify_nonce($_POST['test_profile_nonce'], 'create_test_profile')) {
                    $this->create_test_profile();
                }
                ?>
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
            
            echo '<div class="notice notice-success"><p>' . sprintf(__('Test profile created successfully! Profile ID: %d', 'bizcard-pro'), $profile_id) . '</p></div>';
            
            // Show multiple URL options
            $profile_urls = array(
                home_url('/profile/test-business'),
                home_url('/profile/Test-Business'),
                home_url('/profile/Test%20Business')
            );
            
            echo '<div style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin: 10px 0;">';
            echo '<h4>' . __('Try these URLs to view your test profile:', 'bizcard-pro') . '</h4>';
            foreach ($profile_urls as $url) {
                echo '<p><a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a></p>';
            }
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
            $profile_slug = strtolower(str_replace(' ', '-', $profile->business_name));
            $profile_url = home_url('/profile/' . $profile_slug);
            
            echo '<tr>';
            echo '<td>' . esc_html($profile->id) . '</td>';
            echo '<td><strong>' . esc_html($profile->business_name) . '</strong></td>';
            echo '<td>' . esc_html($profile->profile_status) . '</td>';
            echo '<td>' . esc_html($profile->created_at) . '</td>';
            echo '<td><a href="' . esc_url($profile_url) . '" target="_blank">' . __('View Profile', 'bizcard-pro') . '</a></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
}
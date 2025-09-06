<?php
/**
 * User Roles Management Class
 * 
 * Handles custom user roles and capabilities for the vCard system
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_User_Roles {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Plugin activation/deactivation hooks for role management
        register_activation_hook(VCARD_PLUGIN_FILE, array($this, 'create_roles'));
        register_deactivation_hook(VCARD_PLUGIN_FILE, array($this, 'remove_roles'));
        
        // User registration hooks
        add_action('user_register', array($this, 'assign_default_role'));
        add_action('wp_login', array($this, 'update_user_capabilities'), 10, 2);
    }
    
    /**
     * Create custom user roles
     */
    public function create_roles() {
        // Remove existing roles first to ensure clean setup
        $this->remove_roles();
        
        // Create vCard Client role
        add_role(
            'vcard_client',
            __('vCard Business Client', 'vcard'),
            array(
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'publish_posts' => false,
                'upload_files' => true, // For gallery management
                
                // vCard-specific capabilities
                'create_vcard_profiles' => true,
                'edit_vcard_profiles' => true,
                'edit_own_vcard_profiles' => true,
                'delete_own_vcard_profiles' => true,
                'read_vcard_profiles' => true,
                'manage_vcard_gallery' => true,
                'export_vcard_data' => true,
                'view_vcard_analytics' => true,
                
                // Dashboard access
                'access_vcard_dashboard' => true,
            )
        );
        
        // Create vCard User role (for end users who save contacts)
        add_role(
            'vcard_user',
            __('vCard User', 'vcard'),
            array(
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'publish_posts' => false,
                
                // vCard user-specific capabilities
                'save_vcard_contacts' => true,
                'export_saved_contacts' => true,
                'manage_contact_lists' => true,
            )
        );
        
        // Add vCard capabilities to administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = array(
                'create_vcard_profiles',
                'edit_vcard_profiles',
                'edit_others_vcard_profiles',
                'delete_vcard_profiles',
                'delete_others_vcard_profiles',
                'read_vcard_profiles',
                'manage_vcard_gallery',
                'export_vcard_data',
                'view_vcard_analytics',
                'access_vcard_dashboard',
                'manage_vcard_subscriptions',
                'moderate_vcard_profiles',
                'view_all_vcard_analytics',
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Add limited vCard capabilities to editor role
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_capabilities = array(
                'edit_vcard_profiles',
                'edit_others_vcard_profiles',
                'read_vcard_profiles',
                'moderate_vcard_profiles',
            );
            
            foreach ($editor_capabilities as $cap) {
                $editor_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Remove custom user roles
     */
    public function remove_roles() {
        // Remove custom roles
        remove_role('vcard_client');
        remove_role('vcard_user');
        
        // Remove vCard capabilities from existing roles
        $roles_to_clean = array('administrator', 'editor');
        $vcard_capabilities = array(
            'create_vcard_profiles',
            'edit_vcard_profiles',
            'edit_others_vcard_profiles',
            'edit_own_vcard_profiles',
            'delete_vcard_profiles',
            'delete_others_vcard_profiles',
            'delete_own_vcard_profiles',
            'read_vcard_profiles',
            'manage_vcard_gallery',
            'export_vcard_data',
            'view_vcard_analytics',
            'access_vcard_dashboard',
            'manage_vcard_subscriptions',
            'moderate_vcard_profiles',
            'view_all_vcard_analytics',
            'save_vcard_contacts',
            'export_saved_contacts',
            'manage_contact_lists',
        );
        
        foreach ($roles_to_clean as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($vcard_capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Assign default role to new users
     * 
     * @param int $user_id
     */
    public function assign_default_role($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return;
        }
        
        // Check if user was registered through vCard system
        $registration_source = get_user_meta($user_id, '_vcard_registration_source', true);
        
        if ($registration_source === 'business_client') {
            // Assign vCard client role
            $user->set_role('vcard_client');
            
            // Set default subscription plan
            update_user_meta($user_id, '_vcard_subscription_plan', 'free');
            update_user_meta($user_id, '_vcard_subscription_status', 'active');
            
        } elseif ($registration_source === 'end_user') {
            // Assign vCard user role
            $user->set_role('vcard_user');
        }
        
        // Set registration timestamp
        update_user_meta($user_id, '_vcard_registration_date', current_time('mysql'));
    }
    
    /**
     * Update user capabilities on login
     * 
     * @param string $user_login
     * @param WP_User $user
     */
    public function update_user_capabilities($user_login, $user) {
        // Refresh user capabilities if needed
        if (in_array('vcard_client', $user->roles) || in_array('vcard_user', $user->roles)) {
            // Update last login
            update_user_meta($user->ID, '_vcard_last_login', current_time('mysql'));
            
            // Check subscription status for vCard clients
            if (in_array('vcard_client', $user->roles)) {
                $this->check_subscription_status($user->ID);
            }
        }
    }
    
    /**
     * Check and update subscription status
     * 
     * @param int $user_id
     */
    private function check_subscription_status($user_id) {
        $subscription_status = get_user_meta($user_id, '_vcard_subscription_status', true);
        $subscription_expires = get_user_meta($user_id, '_vcard_subscription_expires', true);
        
        // Check if subscription has expired
        if ($subscription_expires && strtotime($subscription_expires) < current_time('timestamp')) {
            if ($subscription_status !== 'expired') {
                update_user_meta($user_id, '_vcard_subscription_status', 'expired');
                
                // Optionally downgrade to free plan
                update_user_meta($user_id, '_vcard_subscription_plan', 'free');
                
                // Log subscription expiry
                $this->log_subscription_event($user_id, 'expired');
            }
        }
    }
    
    /**
     * Log subscription events
     * 
     * @param int $user_id
     * @param string $event
     */
    private function log_subscription_event($user_id, $event) {
        $log = get_user_meta($user_id, '_vcard_subscription_log', true);
        if (!is_array($log)) {
            $log = array();
        }
        
        $log[] = array(
            'event' => $event,
            'timestamp' => current_time('mysql'),
            'details' => array(
                'plan' => get_user_meta($user_id, '_vcard_subscription_plan', true),
                'status' => get_user_meta($user_id, '_vcard_subscription_status', true),
            ),
        );
        
        // Keep only last 20 events
        if (count($log) > 20) {
            $log = array_slice($log, -20);
        }
        
        update_user_meta($user_id, '_vcard_subscription_log', $log);
    }
    
    /**
     * Get user role information
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_role_info($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return array(
                'roles' => array(),
                'is_vcard_client' => false,
                'is_vcard_user' => false,
                'subscription_plan' => null,
                'subscription_status' => null,
            );
        }
        
        $subscription_plan = get_user_meta($user_id, '_vcard_subscription_plan', true);
        $subscription_status = get_user_meta($user_id, '_vcard_subscription_status', true);
        
        return array(
            'roles' => $user->roles,
            'is_vcard_client' => in_array('vcard_client', $user->roles),
            'is_vcard_user' => in_array('vcard_user', $user->roles),
            'subscription_plan' => $subscription_plan ?: 'free',
            'subscription_status' => $subscription_status ?: 'active',
            'capabilities' => array_keys(array_filter($user->allcaps)),
        );
    }
    
    /**
     * Change user role
     * 
     * @param int $user_id
     * @param string $new_role
     * @return bool
     */
    public function change_user_role($user_id, $new_role) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return false;
        }
        
        $valid_roles = array('vcard_client', 'vcard_user', 'subscriber');
        
        if (!in_array($new_role, $valid_roles)) {
            return false;
        }
        
        // Log role change
        $old_roles = $user->roles;
        
        // Set new role
        $user->set_role($new_role);
        
        // Log the change
        $this->log_role_change($user_id, $old_roles, array($new_role));
        
        return true;
    }
    
    /**
     * Log role changes
     * 
     * @param int $user_id
     * @param array $old_roles
     * @param array $new_roles
     */
    private function log_role_change($user_id, $old_roles, $new_roles) {
        $log = get_user_meta($user_id, '_vcard_role_change_log', true);
        if (!is_array($log)) {
            $log = array();
        }
        
        $log[] = array(
            'timestamp' => current_time('mysql'),
            'old_roles' => $old_roles,
            'new_roles' => $new_roles,
            'changed_by' => get_current_user_id(),
        );
        
        // Keep only last 10 changes
        if (count($log) > 10) {
            $log = array_slice($log, -10);
        }
        
        update_user_meta($user_id, '_vcard_role_change_log', $log);
    }
    
    /**
     * Get subscription plan limits
     * 
     * @param string $plan
     * @return array
     */
    public function get_plan_limits($plan = 'free') {
        $limits = array(
            'free' => array(
                'profiles' => 1,
                'templates' => 3,
                'gallery_images' => 5,
                'services' => 3,
                'products' => 0,
                'analytics_retention' => 30, // days
                'custom_colors' => false,
                'qr_customization' => false,
                'priority_support' => false,
            ),
            'basic' => array(
                'profiles' => 3,
                'templates' => 8,
                'gallery_images' => 20,
                'services' => 10,
                'products' => 5,
                'analytics_retention' => 90,
                'custom_colors' => true,
                'qr_customization' => false,
                'priority_support' => false,
            ),
            'professional' => array(
                'profiles' => -1, // unlimited
                'templates' => -1,
                'gallery_images' => -1,
                'services' => -1,
                'products' => -1,
                'analytics_retention' => 365,
                'custom_colors' => true,
                'qr_customization' => true,
                'priority_support' => true,
            ),
        );
        
        return isset($limits[$plan]) ? $limits[$plan] : $limits['free'];
    }
    
    /**
     * Check if user can perform action based on subscription
     * 
     * @param int $user_id
     * @param string $action
     * @param mixed $context
     * @return bool
     */
    public function check_subscription_limit($user_id, $action, $context = null) {
        $user_info = $this->get_user_role_info($user_id);
        $limits = $this->get_plan_limits($user_info['subscription_plan']);
        
        switch ($action) {
            case 'create_profile':
                if ($limits['profiles'] === -1) {
                    return true;
                }
                
                $profile_count = count(get_posts(array(
                    'post_type' => 'vcard_profile',
                    'author' => $user_id,
                    'post_status' => array('publish', 'draft', 'private'),
                    'numberposts' => -1,
                    'fields' => 'ids',
                )));
                
                return $profile_count < $limits['profiles'];
                
            case 'use_template':
                if ($limits['templates'] === -1) {
                    return true;
                }
                
                // For now, allow all templates but this could be restricted
                return true;
                
            case 'add_gallery_image':
                if ($limits['gallery_images'] === -1) {
                    return true;
                }
                
                $profile_id = $context;
                if (!$profile_id) {
                    return false;
                }
                
                $gallery = get_post_meta($profile_id, '_vcard_gallery', true);
                $image_count = empty($gallery) ? 0 : count(explode(',', $gallery));
                
                return $image_count < $limits['gallery_images'];
                
            case 'add_service':
                if ($limits['services'] === -1) {
                    return true;
                }
                
                $profile_id = $context;
                if (!$profile_id) {
                    return false;
                }
                
                $services = get_post_meta($profile_id, '_vcard_services', true);
                $services_data = !empty($services) ? json_decode($services, true) : array();
                $service_count = is_array($services_data) ? count($services_data) : 0;
                
                return $service_count < $limits['services'];
                
            case 'add_product':
                if ($limits['products'] === -1) {
                    return true;
                }
                
                $profile_id = $context;
                if (!$profile_id) {
                    return false;
                }
                
                $products = get_post_meta($profile_id, '_vcard_products', true);
                $products_data = !empty($products) ? json_decode($products, true) : array();
                $product_count = is_array($products_data) ? count($products_data) : 0;
                
                return $product_count < $limits['products'];
                
            case 'custom_colors':
                return $limits['custom_colors'];
                
            case 'qr_customization':
                return $limits['qr_customization'];
                
            default:
                return false;
        }
    }
    
    /**
     * Get user's remaining limits
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_remaining_limits($user_id) {
        $user_info = $this->get_user_role_info($user_id);
        $limits = $this->get_plan_limits($user_info['subscription_plan']);
        
        // Count current usage
        $profiles = get_posts(array(
            'post_type' => 'vcard_profile',
            'author' => $user_id,
            'post_status' => array('publish', 'draft', 'private'),
            'numberposts' => -1,
            'fields' => 'ids',
        ));
        
        $profile_count = count($profiles);
        
        // Calculate remaining limits
        $remaining = array(
            'profiles' => $limits['profiles'] === -1 ? -1 : max(0, $limits['profiles'] - $profile_count),
            'templates' => $limits['templates'],
            'gallery_images' => $limits['gallery_images'],
            'services' => $limits['services'],
            'products' => $limits['products'],
            'features' => array(
                'custom_colors' => $limits['custom_colors'],
                'qr_customization' => $limits['qr_customization'],
                'priority_support' => $limits['priority_support'],
            ),
        );
        
        return $remaining;
    }
}
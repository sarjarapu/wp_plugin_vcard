<?php
/**
 * vCard Contact Manager Class
 * Handles contact saving, retrieval, and management for end users
 * 
 * @package vCard
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_Contact_Manager {
    
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
        // AJAX hooks for contact management
        add_action('wp_ajax_vcard_save_contact_cloud', array($this, 'handle_save_contact_cloud'));
        add_action('wp_ajax_vcard_get_saved_contacts', array($this, 'handle_get_saved_contacts'));
        add_action('wp_ajax_vcard_remove_saved_contact', array($this, 'handle_remove_saved_contact'));
        add_action('wp_ajax_vcard_sync_contacts', array($this, 'handle_sync_contacts'));
        add_action('wp_ajax_vcard_full_sync_contacts', array($this, 'handle_full_sync_contacts'));
        add_action('wp_ajax_vcard_push_to_cloud', array($this, 'handle_push_to_cloud'));
        add_action('wp_ajax_vcard_migrate_to_cloud', array($this, 'handle_migrate_to_cloud'));
        
        // Add contact management meta to profile pages
        add_action('wp_head', array($this, 'add_profile_meta_tags'));
        
        // Add contact management buttons to profile templates
        add_filter('the_content', array($this, 'add_contact_buttons_to_profile'));
    }
    
    /**
     * Add meta tags to profile pages for contact extraction
     */
    public function add_profile_meta_tags() {
        if (is_singular('vcard_profile')) {
            global $post;
            
            $business_profile = new VCard_Business_Profile($post->ID);
            
            // Add meta tags for JavaScript extraction
            echo '<meta name="vcard:business_name" content="' . esc_attr($business_profile->get_data('business_name')) . '">' . "\n";
            echo '<meta name="vcard:owner_name" content="' . esc_attr($business_profile->get_data('first_name') . ' ' . $business_profile->get_data('last_name')) . '">' . "\n";
            echo '<meta name="vcard:job_title" content="' . esc_attr($business_profile->get_data('job_title')) . '">' . "\n";
            echo '<meta name="vcard:phone" content="' . esc_attr($business_profile->get_data('phone')) . '">' . "\n";
            echo '<meta name="vcard:email" content="' . esc_attr($business_profile->get_data('email')) . '">' . "\n";
            echo '<meta name="vcard:website" content="' . esc_attr($business_profile->get_data('website')) . '">' . "\n";
            
            $address = $this->format_address($business_profile);
            if ($address) {
                echo '<meta name="vcard:address" content="' . esc_attr($address) . '">' . "\n";
            }
        }
    }
    
    /**
     * Format address from business profile data
     */
    private function format_address($business_profile) {
        $address_parts = array();
        
        $street = $business_profile->get_data('address');
        $city = $business_profile->get_data('city');
        $state = $business_profile->get_data('state');
        $zip = $business_profile->get_data('zip_code');
        $country = $business_profile->get_data('country');
        
        if ($street) $address_parts[] = $street;
        if ($city) $address_parts[] = $city;
        if ($state) $address_parts[] = $state;
        if ($zip) $address_parts[] = $zip;
        if ($country) $address_parts[] = $country;
        
        return implode(', ', $address_parts);
    }
    
    /**
     * Add contact management buttons to profile content
     */
    public function add_contact_buttons_to_profile($content) {
        if (is_singular('vcard_profile')) {
            global $post;
            
            $buttons_html = $this->get_contact_buttons_html($post->ID);
            
            // Add buttons after the content
            $content .= $buttons_html;
        }
        
        return $content;
    }
    
    /**
     * Get contact management buttons HTML
     */
    public function get_contact_buttons_html($profile_id) {
        $saved_count = $this->get_user_saved_contacts_count();
        
        $html = '<div class="vcard-contact-management">';
        
        // Save contact button
        $html .= '<button class="vcard-save-contact-btn" data-profile-id="' . esc_attr($profile_id) . '">';
        $html .= '<i class="fas fa-bookmark"></i> ' . __('Save Contact', 'vcard');
        $html .= '</button>';
        
        // View saved contacts button (only show if user has saved contacts)
        if ($saved_count > 0 || !is_user_logged_in()) {
            $html .= '<button class="vcard-view-contacts-btn">';
            $html .= '<i class="fas fa-address-book"></i> ' . __('My Contacts', 'vcard');
            if ($saved_count > 0) {
                $html .= '<span class="vcard-contact-count">' . $saved_count . '</span>';
            }
            $html .= '</button>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Handle AJAX request to save contact to cloud (for registered users)
     */
    public function handle_save_contact_cloud() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id']);
        $contact_data = json_decode(stripslashes($_POST['contact_data']), true);
        
        if (!$profile_id || !$contact_data) {
            wp_send_json_error('Invalid data provided');
        }
        
        // Validate profile exists
        $profile_post = get_post($profile_id);
        if (!$profile_post || $profile_post->post_type !== 'vcard_profile') {
            wp_send_json_error('Profile not found');
        }
        
        $user_id = get_current_user_id();
        
        // Save to database
        $saved = $this->save_contact_to_database($user_id, $profile_id, $contact_data);
        
        if ($saved) {
            wp_send_json_success(array(
                'message' => __('Contact saved to your account', 'vcard')
            ));
        } else {
            wp_send_json_error('Failed to save contact');
        }
    }
    
    /**
     * Handle AJAX request to get saved contacts for logged-in user
     */
    public function handle_get_saved_contacts() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce')) {
            wp_die('Security check failed');
        }
        
        $user_id = get_current_user_id();
        $contacts = $this->get_user_saved_contacts($user_id);
        
        wp_send_json_success($contacts);
    }
    
    /**
     * Handle AJAX request to remove saved contact
     */
    public function handle_remove_saved_contact() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce')) {
            wp_die('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id']);
        
        if (!$profile_id) {
            wp_send_json_error('Invalid profile ID');
        }
        
        $user_id = get_current_user_id();
        
        // Remove from database
        $removed = $this->remove_contact_from_database($user_id, $profile_id);
        
        if ($removed) {
            wp_send_json_success(array(
                'message' => __('Contact removed from your account', 'vcard')
            ));
        } else {
            wp_send_json_error('Failed to remove contact');
        }
    }
    
    /**
     * Handle AJAX request to sync local storage contacts with cloud
     */
    public function handle_sync_contacts() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce')) {
            wp_die('Security check failed');
        }
        
        $local_contacts = json_decode(stripslashes($_POST['local_contacts']), true);
        
        if (!is_array($local_contacts)) {
            wp_send_json_error('Invalid local contacts data');
        }
        
        $user_id = get_current_user_id();
        $synced_count = 0;
        $already_synced_count = 0;
        $total_local_contacts = count($local_contacts);
        
        foreach ($local_contacts as $profile_id => $contact_data) {
            $profile_id = intval($profile_id);
            
            // Check if contact already exists in cloud
            if (!$this->contact_exists_in_database($user_id, $profile_id)) {
                if ($this->save_contact_to_database($user_id, $profile_id, $contact_data)) {
                    $synced_count++;
                }
            } else {
                $already_synced_count++;
            }
        }
        
        // Create a clear message based on the results
        if ($total_local_contacts === 0) {
            $message = __('No local contacts found to sync', 'vcard');
        } elseif ($synced_count === 0 && $already_synced_count > 0) {
            $message = sprintf(__('All %d contacts are already synced to your account', 'vcard'), $already_synced_count);
        } elseif ($synced_count > 0 && $already_synced_count === 0) {
            $message = sprintf(__('%d new contacts synced to your account', 'vcard'), $synced_count);
        } elseif ($synced_count > 0 && $already_synced_count > 0) {
            $message = sprintf(__('%d new contacts synced, %d were already in your account', 'vcard'), $synced_count, $already_synced_count);
        } else {
            $message = __('Sync completed successfully', 'vcard');
        }
        
        wp_send_json_success(array(
            'synced_count' => $synced_count,
            'already_synced_count' => $already_synced_count,
            'total_contacts' => $total_local_contacts,
            'message' => $message
        ));
    }
    
    /**
     * Handle full bidirectional sync between local and cloud
     */
    public function handle_full_sync_contacts() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce')) {
            wp_die('Security check failed');
        }
        
        $local_contacts = json_decode(stripslashes($_POST['local_contacts']), true);
        $local_contact_ids = json_decode(stripslashes($_POST['local_contact_ids']), true);
        
        if (!is_array($local_contacts)) {
            $local_contacts = array();
        }
        
        if (!is_array($local_contact_ids)) {
            $local_contact_ids = array();
        }
        
        $user_id = get_current_user_id();
        
        // Get current cloud contacts
        $cloud_contacts = $this->get_user_saved_contacts($user_id);
        $cloud_contact_ids = array_keys($cloud_contacts);
        
        // Find contacts to add to cloud (in local but not in cloud)
        $contacts_to_add = array();
        foreach ($local_contact_ids as $profile_id) {
            if (!in_array($profile_id, $cloud_contact_ids) && isset($local_contacts[$profile_id])) {
                $contacts_to_add[$profile_id] = $local_contacts[$profile_id];
            }
        }
        
        // Find contacts to remove from cloud (in cloud but not in local)
        $contacts_to_remove = array();
        foreach ($cloud_contact_ids as $profile_id) {
            if (!in_array($profile_id, $local_contact_ids)) {
                $contacts_to_remove[] = $profile_id;
            }
        }
        
        $added_count = 0;
        $removed_count = 0;
        
        // Add new contacts to cloud
        foreach ($contacts_to_add as $profile_id => $contact_data) {
            if ($this->save_contact_to_database($user_id, intval($profile_id), $contact_data)) {
                $added_count++;
            }
        }
        
        // Remove contacts from cloud
        foreach ($contacts_to_remove as $profile_id) {
            if ($this->remove_contact_from_database($user_id, intval($profile_id))) {
                $removed_count++;
            }
        }
        
        // Get final cloud state
        $final_cloud_contacts = $this->get_user_saved_contacts($user_id);
        
        // Create sync message
        $message_parts = array();
        if ($added_count > 0) {
            $message_parts[] = sprintf(__('%d contacts added to cloud', 'vcard'), $added_count);
        }
        if ($removed_count > 0) {
            $message_parts[] = sprintf(__('%d contacts removed from cloud', 'vcard'), $removed_count);
        }
        
        if (empty($message_parts)) {
            $message = __('Contacts are already in sync', 'vcard');
        } else {
            $message = implode(', ', $message_parts);
        }
        
        wp_send_json_success(array(
            'added_count' => $added_count,
            'removed_count' => $removed_count,
            'total_contacts' => count($final_cloud_contacts),
            'cloud_contacts' => $final_cloud_contacts,
            'message' => $message
        ));
    }
    
    /**
     * Push local contacts to cloud (local is authoritative)
     */
    public function handle_push_to_cloud() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce')) {
            wp_die('Security check failed');
        }
        
        $local_contacts = json_decode(stripslashes($_POST['local_contacts']), true);
        $local_contact_ids = json_decode(stripslashes($_POST['local_contact_ids']), true);
        
        if (!is_array($local_contacts)) {
            $local_contacts = array();
        }
        
        if (!is_array($local_contact_ids)) {
            $local_contact_ids = array();
        }
        
        $user_id = get_current_user_id();
        
        // Clear all existing cloud contacts for this user
        global $wpdb;
        $table_name = VCARD_SAVED_CONTACTS_TABLE;
        $wpdb->delete($table_name, array('user_id' => $user_id), array('%d'));
        
        // Add all local contacts to cloud
        $added_count = 0;
        foreach ($local_contact_ids as $profile_id) {
            if (isset($local_contacts[$profile_id])) {
                if ($this->save_contact_to_database($user_id, intval($profile_id), $local_contacts[$profile_id])) {
                    $added_count++;
                }
            }
        }
        
        $message = sprintf(__('Cloud updated: %d contacts synced', 'vcard'), $added_count);
        
        wp_send_json_success(array(
            'synced_count' => $added_count,
            'total_contacts' => count($local_contact_ids),
            'message' => $message
        ));
    }
    
    /**
     * One-time migration from local storage to cloud
     */
    public function handle_migrate_to_cloud() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_public_nonce')) {
            wp_die('Security check failed');
        }
        
        $local_contacts = json_decode(stripslashes($_POST['local_contacts']), true);
        $local_contact_ids = json_decode(stripslashes($_POST['local_contact_ids']), true);
        $deleted_contacts = json_decode(stripslashes($_POST['deleted_contacts']), true);
        
        if (!is_array($local_contacts)) {
            $local_contacts = array();
        }
        
        if (!is_array($local_contact_ids)) {
            $local_contact_ids = array();
        }
        
        if (!is_array($deleted_contacts)) {
            $deleted_contacts = array();
        }
        
        $user_id = get_current_user_id();
        
        $migrated_count = 0;
        $deleted_count = 0;
        
        // Add local contacts to cloud (only if they don't exist)
        foreach ($local_contact_ids as $profile_id) {
            if (isset($local_contacts[$profile_id]) && !$this->contact_exists_in_database($user_id, intval($profile_id))) {
                if ($this->save_contact_to_database($user_id, intval($profile_id), $local_contacts[$profile_id])) {
                    $migrated_count++;
                }
            }
        }
        
        // Remove deleted contacts from cloud
        foreach ($deleted_contacts as $profile_id) {
            if ($this->remove_contact_from_database($user_id, intval($profile_id))) {
                $deleted_count++;
            }
        }
        
        // Create migration message
        $message_parts = array();
        if ($migrated_count > 0) {
            $message_parts[] = sprintf(__('%d contacts migrated to your account', 'vcard'), $migrated_count);
        }
        if ($deleted_count > 0) {
            $message_parts[] = sprintf(__('%d deleted contacts removed', 'vcard'), $deleted_count);
        }
        
        $message = empty($message_parts) ? 
            __('Migration completed', 'vcard') : 
            implode(', ', $message_parts);
        
        wp_send_json_success(array(
            'migrated_count' => $migrated_count,
            'deleted_count' => $deleted_count,
            'message' => $message
        ));
    }
    
    /**
     * Save contact to database
     */
    private function save_contact_to_database($user_id, $profile_id, $contact_data) {
        global $wpdb;
        $table_name = VCARD_SAVED_CONTACTS_TABLE;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return false;
        }
        
        // Check if contact already exists
        if ($this->contact_exists_in_database($user_id, $profile_id)) {
            // Update existing contact
            return $wpdb->update(
                $table_name,
                array(
                    'contact_data' => wp_json_encode($contact_data),
                    'updated_at' => current_time('mysql')
                ),
                array(
                    'user_id' => $user_id,
                    'profile_id' => $profile_id
                ),
                array('%s', '%s'),
                array('%d', '%d')
            ) !== false;
        } else {
            // Insert new contact
            return $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'profile_id' => $profile_id,
                    'contact_data' => wp_json_encode($contact_data),
                    'saved_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%s')
            ) !== false;
        }
    }
    
    /**
     * Remove contact from database
     */
    private function remove_contact_from_database($user_id, $profile_id) {
        global $wpdb;
        $table_name = VCARD_SAVED_CONTACTS_TABLE;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return false;
        }
        
        return $wpdb->delete(
            $table_name,
            array(
                'user_id' => $user_id,
                'profile_id' => $profile_id
            ),
            array('%d', '%d')
        ) !== false;
    }
    
    /**
     * Check if contact exists in database
     */
    private function contact_exists_in_database($user_id, $profile_id) {
        global $wpdb;
        $table_name = VCARD_SAVED_CONTACTS_TABLE;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return false;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND profile_id = %d",
            $user_id,
            $profile_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Get user's saved contacts from database
     */
    private function get_user_saved_contacts($user_id) {
        global $wpdb;
        $table_name = VCARD_SAVED_CONTACTS_TABLE;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return array();
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT profile_id, contact_data, saved_at, updated_at FROM $table_name WHERE user_id = %d ORDER BY updated_at DESC",
            $user_id
        ));
        
        $contacts = array();
        
        foreach ($results as $result) {
            $contact_data = json_decode($result->contact_data, true);
            if ($contact_data) {
                $contact_data['saved_at'] = $result->saved_at;
                $contact_data['updated_at'] = $result->updated_at;
                $contacts[$result->profile_id] = $contact_data;
            }
        }
        
        return $contacts;
    }
    
    /**
     * Get count of user's saved contacts
     */
    private function get_user_saved_contacts_count($user_id = null) {
        if (!$user_id) {
            if (!is_user_logged_in()) {
                return 0;
            }
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        $table_name = VCARD_SAVED_CONTACTS_TABLE;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return 0;
        }
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get contact management statistics for admin
     */
    public function get_contact_statistics() {
        global $wpdb;
        $table_name = VCARD_SAVED_CONTACTS_TABLE;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return array(
                'total_contacts' => 0,
                'total_users' => 0,
                'avg_contacts_per_user' => 0,
                'most_saved_profiles' => array()
            );
        }
        
        // Total saved contacts
        $total_contacts = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Total users with saved contacts
        $total_users = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name");
        
        // Average contacts per user
        $avg_contacts_per_user = $total_users > 0 ? round($total_contacts / $total_users, 2) : 0;
        
        // Most saved profiles
        $most_saved_profiles = $wpdb->get_results(
            "SELECT profile_id, COUNT(*) as save_count 
             FROM $table_name 
             GROUP BY profile_id 
             ORDER BY save_count DESC 
             LIMIT 10"
        );
        
        return array(
            'total_contacts' => $total_contacts,
            'total_users' => $total_users,
            'avg_contacts_per_user' => $avg_contacts_per_user,
            'most_saved_profiles' => $most_saved_profiles
        );
    }
}

// Initialize the contact manager
new VCard_Contact_Manager();
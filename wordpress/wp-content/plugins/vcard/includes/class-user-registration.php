<?php
/**
 * vCard User Registration System
 * Handles user registration with social media login and SMS verification
 * 
 * @package vCard
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_User_Registration {
    
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
        // AJAX hooks for user registration
        add_action('wp_ajax_nopriv_vcard_register_user', array($this, 'handle_user_registration'));
        add_action('wp_ajax_nopriv_vcard_verify_sms', array($this, 'handle_sms_verification'));
        add_action('wp_ajax_nopriv_vcard_social_login', array($this, 'handle_social_login'));
        add_action('wp_ajax_nopriv_vcard_resend_verification', array($this, 'handle_resend_verification'));
        
        // Login/logout hooks
        add_action('wp_login', array($this, 'handle_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'handle_user_logout'));
        
        // User profile hooks
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));
        
        // Enqueue scripts for registration
        add_action('wp_enqueue_scripts', array($this, 'enqueue_registration_scripts'));
        
        // Add registration modal to footer
        add_action('wp_footer', array($this, 'add_registration_modal'));
    }
    
    /**
     * Enqueue registration scripts
     */
    public function enqueue_registration_scripts() {
        if (is_singular('vcard_profile') || is_page()) {
            wp_enqueue_script(
                'vcard-user-registration',
                VCARD_ASSETS_URL . 'js/user-registration.js',
                array('jquery'),
                VCARD_VERSION,
                true
            );
            
            wp_enqueue_style(
                'vcard-user-registration',
                VCARD_ASSETS_URL . 'css/user-registration.css',
                array(),
                VCARD_VERSION
            );
            
            wp_localize_script('vcard-user-registration', 'vcard_registration', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vcard_registration_nonce'),
                'strings' => array(
                    'register' => __('Register', 'vcard'),
                    'login' => __('Login', 'vcard'),
                    'verify_phone' => __('Verify Phone', 'vcard'),
                    'verification_sent' => __('Verification code sent!', 'vcard'),
                    'verification_failed' => __('Verification failed', 'vcard'),
                    'registration_success' => __('Registration successful!', 'vcard'),
                    'registration_failed' => __('Registration failed', 'vcard'),
                    'login_success' => __('Login successful!', 'vcard'),
                    'login_failed' => __('Login failed', 'vcard'),
                    'invalid_phone' => __('Please enter a valid phone number', 'vcard'),
                    'invalid_email' => __('Please enter a valid email address', 'vcard'),
                    'password_mismatch' => __('Passwords do not match', 'vcard'),
                    'weak_password' => __('Password is too weak', 'vcard'),
                    'phone_exists' => __('Phone number already registered', 'vcard'),
                    'email_exists' => __('Email already registered', 'vcard'),
                    'sync_contacts' => __('Sync Local Contacts', 'vcard'),
                    'sync_success' => __('Contacts synced successfully!', 'vcard'),
                    'sync_failed' => __('Contact sync failed', 'vcard'),
                )
            ));
        }
    }
    
    /**
     * Handle user registration AJAX request
     */
    public function handle_user_registration() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_registration_nonce')) {
            wp_die('Security check failed');
        }
        
        $registration_type = sanitize_text_field($_POST['registration_type']);
        
        switch ($registration_type) {
            case 'email':
                $this->register_with_email();
                break;
                
            case 'phone':
                $this->register_with_phone();
                break;
                
            default:
                wp_send_json_error('Invalid registration type');
        }
    }
    
    /**
     * Register user with email
     */
    private function register_with_email() {
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        
        // Validate input
        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }
        
        if (empty($password) || strlen($password) < 8) {
            wp_send_json_error('Password must be at least 8 characters long');
        }
        
        if ($password !== $confirm_password) {
            wp_send_json_error('Passwords do not match');
        }
        
        // Check if email already exists
        if (email_exists($email)) {
            wp_send_json_error('Email already registered');
        }
        
        // Create user
        $username = $this->generate_username($email, $first_name, $last_name);
        
        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name),
            'role' => 'vcard_user'
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }
        
        // Set user meta
        update_user_meta($user_id, 'vcard_registration_type', 'email');
        update_user_meta($user_id, 'vcard_registration_date', current_time('mysql'));
        update_user_meta($user_id, 'vcard_email_verified', true);
        
        // Auto-login user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        wp_send_json_success(array(
            'message' => 'Registration successful!',
            'user_id' => $user_id,
            'redirect_url' => $this->get_redirect_url()
        ));
    }
    
    /**
     * Register user with phone number
     */
    private function register_with_phone() {
        $phone = sanitize_text_field($_POST['phone']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        
        // Validate phone number
        $phone = $this->normalize_phone_number($phone);
        
        if (!$this->is_valid_phone_number($phone)) {
            wp_send_json_error('Invalid phone number');
        }
        
        // Check if phone already exists
        if ($this->phone_exists($phone)) {
            wp_send_json_error('Phone number already registered');
        }
        
        // Generate verification code
        $verification_code = $this->generate_verification_code();
        
        // Store verification data temporarily
        $verification_data = array(
            'phone' => $phone,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'code' => $verification_code,
            'expires' => time() + (10 * MINUTE_IN_SECONDS), // 10 minutes
            'attempts' => 0
        );
        
        set_transient('vcard_phone_verification_' . md5($phone), $verification_data, 10 * MINUTE_IN_SECONDS);
        
        // Send SMS verification code
        $sms_sent = $this->send_sms_verification($phone, $verification_code);
        
        if ($sms_sent) {
            wp_send_json_success(array(
                'message' => 'Verification code sent to your phone',
                'phone_hash' => md5($phone),
                'step' => 'verify_phone'
            ));
        } else {
            wp_send_json_error('Failed to send verification code');
        }
    }
    
    /**
     * Handle SMS verification
     */
    public function handle_sms_verification() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_registration_nonce')) {
            wp_die('Security check failed');
        }
        
        $phone_hash = sanitize_text_field($_POST['phone_hash']);
        $verification_code = sanitize_text_field($_POST['verification_code']);
        
        // Get verification data
        $verification_data = get_transient('vcard_phone_verification_' . $phone_hash);
        
        if (!$verification_data) {
            wp_send_json_error('Verification code expired. Please request a new one.');
        }
        
        // Check attempts
        if ($verification_data['attempts'] >= 3) {
            delete_transient('vcard_phone_verification_' . $phone_hash);
            wp_send_json_error('Too many failed attempts. Please request a new code.');
        }
        
        // Verify code
        if ($verification_code !== $verification_data['code']) {
            $verification_data['attempts']++;
            set_transient('vcard_phone_verification_' . $phone_hash, $verification_data, 10 * MINUTE_IN_SECONDS);
            wp_send_json_error('Invalid verification code');
        }
        
        // Create user account
        $username = $this->generate_username($verification_data['phone'], $verification_data['first_name'], $verification_data['last_name']);
        $password = wp_generate_password(12, false);
        
        $user_data = array(
            'user_login' => $username,
            'user_email' => '', // No email for phone registration
            'user_pass' => $password,
            'first_name' => $verification_data['first_name'],
            'last_name' => $verification_data['last_name'],
            'display_name' => trim($verification_data['first_name'] . ' ' . $verification_data['last_name']),
            'role' => 'vcard_user'
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }
        
        // Set user meta
        update_user_meta($user_id, 'vcard_phone', $verification_data['phone']);
        update_user_meta($user_id, 'vcard_registration_type', 'phone');
        update_user_meta($user_id, 'vcard_registration_date', current_time('mysql'));
        update_user_meta($user_id, 'vcard_phone_verified', true);
        
        // Clean up verification data
        delete_transient('vcard_phone_verification_' . $phone_hash);
        
        // Auto-login user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        wp_send_json_success(array(
            'message' => 'Phone verified and account created!',
            'user_id' => $user_id,
            'redirect_url' => $this->get_redirect_url()
        ));
    }
    
    /**
     * Handle social media login
     */
    public function handle_social_login() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_registration_nonce')) {
            wp_die('Security check failed');
        }
        
        $provider = sanitize_text_field($_POST['provider']);
        $social_data = json_decode(stripslashes($_POST['social_data']), true);
        
        if (!in_array($provider, array('google', 'facebook', 'linkedin'))) {
            wp_send_json_error('Invalid social provider');
        }
        
        // Validate social data
        if (empty($social_data['id']) || empty($social_data['email'])) {
            wp_send_json_error('Invalid social media data');
        }
        
        // Check if user already exists by email
        $existing_user = get_user_by('email', $social_data['email']);
        
        if ($existing_user) {
            // Link social account to existing user
            update_user_meta($existing_user->ID, 'vcard_' . $provider . '_id', $social_data['id']);
            
            // Login existing user
            wp_set_current_user($existing_user->ID);
            wp_set_auth_cookie($existing_user->ID);
            
            wp_send_json_success(array(
                'message' => 'Logged in successfully!',
                'user_id' => $existing_user->ID,
                'redirect_url' => $this->get_redirect_url()
            ));
        } else {
            // Create new user
            $username = $this->generate_username($social_data['email'], $social_data['first_name'], $social_data['last_name']);
            $password = wp_generate_password(12, false);
            
            $user_data = array(
                'user_login' => $username,
                'user_email' => $social_data['email'],
                'user_pass' => $password,
                'first_name' => $social_data['first_name'] ?: '',
                'last_name' => $social_data['last_name'] ?: '',
                'display_name' => $social_data['name'] ?: trim($social_data['first_name'] . ' ' . $social_data['last_name']),
                'role' => 'vcard_user'
            );
            
            $user_id = wp_insert_user($user_data);
            
            if (is_wp_error($user_id)) {
                wp_send_json_error($user_id->get_error_message());
            }
            
            // Set user meta
            update_user_meta($user_id, 'vcard_' . $provider . '_id', $social_data['id']);
            update_user_meta($user_id, 'vcard_registration_type', 'social_' . $provider);
            update_user_meta($user_id, 'vcard_registration_date', current_time('mysql'));
            update_user_meta($user_id, 'vcard_email_verified', true);
            
            if (!empty($social_data['picture'])) {
                update_user_meta($user_id, 'vcard_profile_picture', $social_data['picture']);
            }
            
            // Auto-login user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            wp_send_json_success(array(
                'message' => 'Account created and logged in successfully!',
                'user_id' => $user_id,
                'redirect_url' => $this->get_redirect_url()
            ));
        }
    }
    
    /**
     * Handle resend verification code
     */
    public function handle_resend_verification() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vcard_registration_nonce')) {
            wp_die('Security check failed');
        }
        
        $phone_hash = sanitize_text_field($_POST['phone_hash']);
        
        // Get existing verification data
        $verification_data = get_transient('vcard_phone_verification_' . $phone_hash);
        
        if (!$verification_data) {
            wp_send_json_error('No pending verification found');
        }
        
        // Generate new verification code
        $verification_code = $this->generate_verification_code();
        $verification_data['code'] = $verification_code;
        $verification_data['expires'] = time() + (10 * MINUTE_IN_SECONDS);
        $verification_data['attempts'] = 0;
        
        // Update transient
        set_transient('vcard_phone_verification_' . $phone_hash, $verification_data, 10 * MINUTE_IN_SECONDS);
        
        // Send new SMS
        $sms_sent = $this->send_sms_verification($verification_data['phone'], $verification_code);
        
        if ($sms_sent) {
            wp_send_json_success(array(
                'message' => 'New verification code sent!'
            ));
        } else {
            wp_send_json_error('Failed to send verification code');
        }
    }
    
    /**
     * Handle user login
     */
    public function handle_user_login($user_login, $user) {
        // Update last login time
        update_user_meta($user->ID, 'vcard_last_login', current_time('mysql'));
        
        // Trigger contact sync for registered users
        do_action('vcard_user_logged_in', $user->ID);
    }
    
    /**
     * Handle user logout
     */
    public function handle_user_logout() {
        // Clear any temporary data
        do_action('vcard_user_logged_out');
    }
    
    /**
     * Add user profile fields
     */
    public function add_user_profile_fields($user) {
        ?>
        <h3><?php _e('vCard Contact Management', 'vcard'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="vcard_phone"><?php _e('Phone Number', 'vcard'); ?></label></th>
                <td>
                    <input type="tel" name="vcard_phone" id="vcard_phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'vcard_phone', true)); ?>" class="regular-text" />
                    <?php if (get_user_meta($user->ID, 'vcard_phone_verified', true)): ?>
                        <span style="color: green;">✓ <?php _e('Verified', 'vcard'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="vcard_registration_type"><?php _e('Registration Type', 'vcard'); ?></label></th>
                <td>
                    <?php 
                    $reg_type = get_user_meta($user->ID, 'vcard_registration_type', true);
                    echo esc_html(ucfirst(str_replace('_', ' ', $reg_type ?: 'email')));
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="vcard_saved_contacts_count"><?php _e('Saved Contacts', 'vcard'); ?></label></th>
                <td>
                    <?php
                    global $wpdb;
                    $count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM " . VCARD_SAVED_CONTACTS_TABLE . " WHERE user_id = %d",
                        $user->ID
                    ));
                    echo intval($count);
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save user profile fields
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (isset($_POST['vcard_phone'])) {
            $phone = sanitize_text_field($_POST['vcard_phone']);
            $phone = $this->normalize_phone_number($phone);
            
            if ($this->is_valid_phone_number($phone)) {
                update_user_meta($user_id, 'vcard_phone', $phone);
            }
        }
    }
    
    /**
     * Add registration modal to footer
     */
    public function add_registration_modal() {
        if (is_user_logged_in()) {
            return;
        }
        
        include VCARD_TEMPLATES_PATH . 'registration-modal.php';
    }
    
    /**
     * Generate unique username
     */
    private function generate_username($email_or_phone, $first_name = '', $last_name = '') {
        $base_username = '';
        
        if (is_email($email_or_phone)) {
            $base_username = sanitize_user(substr($email_or_phone, 0, strpos($email_or_phone, '@')));
        } else {
            $base_username = 'user_' . substr(preg_replace('/[^0-9]/', '', $email_or_phone), -6);
        }
        
        if (empty($base_username) && !empty($first_name)) {
            $base_username = sanitize_user(strtolower($first_name . $last_name));
        }
        
        if (empty($base_username)) {
            $base_username = 'vcard_user';
        }
        
        // Ensure username is unique
        $username = $base_username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base_username . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Normalize phone number
     */
    private function normalize_phone_number($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^+0-9]/', '', $phone);
        
        // Add country code if missing (assuming US +1)
        if (!str_starts_with($phone, '+')) {
            if (strlen($phone) === 10) {
                $phone = '+1' . $phone;
            } elseif (strlen($phone) === 11 && str_starts_with($phone, '1')) {
                $phone = '+' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Validate phone number
     */
    private function is_valid_phone_number($phone) {
        // Basic validation for international phone numbers
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone);
    }
    
    /**
     * Check if phone number exists
     */
    private function phone_exists($phone) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'vcard_phone' AND meta_value = %s",
            $phone
        ));
        
        return $count > 0;
    }
    
    /**
     * Generate verification code
     */
    private function generate_verification_code() {
        return sprintf('%06d', mt_rand(100000, 999999));
    }
    
    /**
     * Send SMS verification code
     */
    private function send_sms_verification($phone, $code) {
        // This is a placeholder for SMS integration
        // In a real implementation, you would integrate with services like:
        // - Twilio
        // - AWS SNS
        // - Nexmo/Vonage
        // - TextMagic
        
        $message = sprintf(
            __('Your vCard verification code is: %s. This code expires in 10 minutes.', 'vcard'),
            $code
        );
        
        // For development/testing, log the code
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SMS Verification Code for $phone: $code");
        }
        
        // Apply filter to allow custom SMS providers
        $sms_sent = apply_filters('vcard_send_sms', false, $phone, $message, $code);
        
        // If no custom SMS provider handled it, return true for testing
        // In production, this should return false unless properly configured
        return $sms_sent !== false ? $sms_sent : (defined('WP_DEBUG') && WP_DEBUG);
    }
    
    /**
     * Get redirect URL after registration/login
     */
    private function get_redirect_url() {
        // Check for redirect parameter
        if (!empty($_POST['redirect_to'])) {
            return esc_url_raw($_POST['redirect_to']);
        }
        
        // Default to current page or home
        return wp_get_referer() ?: home_url();
    }
}

// Initialize user registration
new VCard_User_Registration();
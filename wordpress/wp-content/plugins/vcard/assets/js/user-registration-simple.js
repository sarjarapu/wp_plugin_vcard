/**
 * Simplified vCard User Registration JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Simple User Registration object
    var VCardUserRegistration = {
        
        // Initialize registration system
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Registration modal triggers
            $(document).on('click', '.vcard-register-btn, .vcard-login-btn', this.showRegistrationModal.bind(this));
            
            // Modal close
            $(document).on('click', '.vcard-registration-modal .vcard-modal-close, .vcard-registration-modal .vcard-modal-overlay', this.hideRegistrationModal.bind(this));
            
            // Switch between login and register
            $(document).on('click', '.vcard-switch-to-login', this.switchToLogin.bind(this));
            $(document).on('click', '.vcard-switch-to-register', this.switchToRegister.bind(this));
            
            // Form submissions
            $(document).on('submit', '.vcard-simple-login-form', this.handleSimpleLogin.bind(this));
            $(document).on('submit', '.vcard-simple-register-form', this.handleSimpleRegister.bind(this));
        },

        /**
         * Show registration modal
         */
        showRegistrationModal: function(e) {
            e.preventDefault();
            
            var $trigger = $(e.currentTarget);
            var mode = $trigger.hasClass('vcard-login-btn') ? 'login' : 'register';
            
            // Create modal if it doesn't exist
            if (!$('.vcard-registration-modal').length) {
                this.createSimpleModal();
            }
            
            // Set mode
            this.setMode(mode);
            
            // Show modal
            $('.vcard-registration-modal').fadeIn();
            $('body').addClass('vcard-modal-open');
        },

        /**
         * Hide registration modal
         */
        hideRegistrationModal: function(e) {
            if (e && $(e.target).closest('.vcard-modal-content').length) {
                return;
            }
            
            $('.vcard-registration-modal').fadeOut();
            $('body').removeClass('vcard-modal-open');
        },

        /**
         * Create simple modal
         */
        createSimpleModal: function() {
            var modalHtml = `
                <div class="vcard-registration-modal">
                    <div class="vcard-modal-overlay"></div>
                    <div class="vcard-modal-content">
                        <div class="vcard-modal-header">
                            <h3 class="vcard-modal-title">
                                <span class="vcard-register-title"><i class="fas fa-user-plus"></i> Create Account</span>
                                <span class="vcard-login-title"><i class="fas fa-sign-in-alt"></i> Login</span>
                            </h3>
                            <button class="vcard-modal-close"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="vcard-modal-body">
                            ${this.getRegisterForm()}
                            ${this.getLoginForm()}
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
        },

        /**
         * Get register form
         */
        getRegisterForm: function() {
            return `
                <div class="vcard-register-section">
                    <form class="vcard-simple-register-form">
                        <div class="vcard-form-field">
                            <label>First Name *</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="vcard-form-field">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" required>
                        </div>
                        <div class="vcard-form-field">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="vcard-form-field">
                            <label>Password *</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="vcard-form-field">
                            <label>Confirm Password *</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        <div class="vcard-form-actions">
                            <button type="submit" class="vcard-btn-primary">Create Account</button>
                        </div>
                    </form>
                    <div class="vcard-modal-footer">
                        <p>Already have an account? <a href="#" class="vcard-switch-to-login">Login here</a></p>
                    </div>
                </div>
            `;
        },

        /**
         * Get login form
         */
        getLoginForm: function() {
            return `
                <div class="vcard-login-section" style="display: none;">
                    <form class="vcard-simple-login-form">
                        <div class="vcard-form-field">
                            <label>Email or Username *</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="vcard-form-field">
                            <label>Password *</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="vcard-form-field">
                            <label>
                                <input type="checkbox" name="remember_me"> Remember me
                            </label>
                        </div>
                        <div class="vcard-form-actions">
                            <button type="submit" class="vcard-btn-primary">Login</button>
                        </div>
                    </form>
                    <div class="vcard-modal-footer">
                        <p>Don't have an account? <a href="#" class="vcard-switch-to-register">Register here</a></p>
                    </div>
                </div>
            `;
        },

        /**
         * Set modal mode
         */
        setMode: function(mode) {
            var $modal = $('.vcard-registration-modal');
            
            if (mode === 'login') {
                $modal.addClass('vcard-login-mode').removeClass('vcard-register-mode');
                $('.vcard-register-section').hide();
                $('.vcard-login-section').show();
            } else {
                $modal.addClass('vcard-register-mode').removeClass('vcard-login-mode');
                $('.vcard-login-section').hide();
                $('.vcard-register-section').show();
            }
        },

        /**
         * Switch to login
         */
        switchToLogin: function(e) {
            e.preventDefault();
            this.setMode('login');
        },

        /**
         * Switch to register
         */
        switchToRegister: function(e) {
            e.preventDefault();
            this.setMode('register');
        },

        /**
         * Handle simple login
         */
        handleSimpleLogin: function(e) {
            e.preventDefault();
            
            var $form = $(e.currentTarget);
            var $submitBtn = $form.find('button[type="submit"]');
            
            var username = $form.find('[name="username"]').val();
            var password = $form.find('[name="password"]').val();
            var remember = $form.find('[name="remember_me"]').is(':checked');
            
            if (!username || !password) {
                alert('Please enter both username and password');
                return;
            }
            
            $submitBtn.prop('disabled', true).text('Logging in...');
            
            $.ajax({
                url: vcard_registration.ajax_url,
                type: 'POST',
                data: {
                    action: 'vcard_user_login',
                    username: username,
                    password: password,
                    remember: remember ? 1 : 0,
                    nonce: vcard_registration.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Close modal and reload page
                        $('.vcard-registration-modal').fadeOut();
                        $('body').removeClass('vcard-modal-open');
                        window.location.reload();
                    } else {
                        alert('Login failed: ' + (response.data || 'Unknown error'));
                        $submitBtn.prop('disabled', false).text('Login');
                    }
                },
                error: function() {
                    alert('Network error occurred');
                    $submitBtn.prop('disabled', false).text('Login');
                }
            });
        },

        /**
         * Handle simple register
         */
        handleSimpleRegister: function(e) {
            e.preventDefault();
            
            var $form = $(e.currentTarget);
            var $submitBtn = $form.find('button[type="submit"]');
            
            var firstName = $form.find('[name="first_name"]').val();
            var lastName = $form.find('[name="last_name"]').val();
            var email = $form.find('[name="email"]').val();
            var password = $form.find('[name="password"]').val();
            var confirmPassword = $form.find('[name="confirm_password"]').val();
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return;
            }
            
            $submitBtn.prop('disabled', true).text('Creating account...');
            
            $.ajax({
                url: vcard_registration.ajax_url,
                type: 'POST',
                data: {
                    action: 'vcard_register_user',
                    registration_type: 'email',
                    first_name: firstName,
                    last_name: lastName,
                    email: email,
                    password: password,
                    confirm_password: confirmPassword,
                    nonce: vcard_registration.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Close modal and reload page
                        $('.vcard-registration-modal').fadeOut();
                        $('body').removeClass('vcard-modal-open');
                        window.location.reload();
                    } else {
                        alert('Registration failed: ' + (response.data || 'Unknown error'));
                        $submitBtn.prop('disabled', false).text('Create Account');
                    }
                },
                error: function() {
                    alert('Network error occurred');
                    $submitBtn.prop('disabled', false).text('Create Account');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        VCardUserRegistration.init();
    });

    // Make globally available
    window.VCardUserRegistration = VCardUserRegistration;

})(jQuery);
/**
 * vCard User Registration JavaScript
 * Handles user registration, login, and contact synchronization
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // User Registration object
    var VCardUserRegistration = {
        
        // Current step in registration process
        currentStep: 'choose_method',
        
        // Registration data
        registrationData: {},
        
        // Initialize registration system
        init: function() {
            this.bindEvents();
            this.initSocialLogin();
            this.checkLoginStatus();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Registration modal triggers
            $(document).on('click', '.vcard-register-btn, .vcard-login-btn', this.showRegistrationModal.bind(this));
            
            // Modal close
            $(document).on('click', '.vcard-registration-modal .vcard-modal-close, .vcard-registration-modal .vcard-modal-overlay', this.hideRegistrationModal.bind(this));
            
            // Registration method selection
            $(document).on('click', '.vcard-registration-method', this.selectRegistrationMethod.bind(this));
            
            // Form submissions
            $(document).on('submit', '.vcard-email-registration-form', this.handleEmailRegistration.bind(this));
            $(document).on('submit', '.vcard-phone-registration-form', this.handlePhoneRegistration.bind(this));
            $(document).on('submit', '.vcard-verification-form', this.handlePhoneVerification.bind(this));
            $(document).on('submit', '.vcard-login-form', this.handleLogin.bind(this));
            
            // Resend verification
            $(document).on('click', '.vcard-resend-verification', this.resendVerification.bind(this));
            
            // Social login buttons
            $(document).on('click', '.vcard-social-login-btn', this.handleSocialLogin.bind(this));
            
            // Contact sync
            $(document).on('click', '.vcard-sync-contacts-btn', this.syncLocalContacts.bind(this));
            
            // Switch between login and register
            $(document).on('click', '.vcard-switch-to-login', this.switchToLogin.bind(this));
            $(document).on('click', '.vcard-switch-to-register', this.switchToRegister.bind(this));
            
            // Phone number formatting
            $(document).on('input', '.vcard-phone-input', this.formatPhoneNumber.bind(this));
            
            // Password strength indicator
            $(document).on('input', '.vcard-password-input', this.checkPasswordStrength.bind(this));
        },

        /**
         * Show registration modal
         */
        showRegistrationModal: function(e) {
            if (e) e.preventDefault();
            
            // Create modal if it doesn't exist
            if (!$('.vcard-registration-modal').length) {
                this.createRegistrationModal();
            }
            
            // Reset to initial state
            this.resetModal();
            
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
         * Create registration modal
         */
        createRegistrationModal: function() {
            var modalHtml = `
                <div class="vcard-registration-modal">
                    <div class="vcard-modal-overlay"></div>
                    <div class="vcard-modal-content">
                        <div class="vcard-modal-header">
                            <h3 class="vcard-modal-title">
                                <i class="fas fa-user-plus"></i> 
                                <span class="vcard-modal-title-text">${vcard_registration.strings.register}</span>
                            </h3>
                            <button class="vcard-modal-close"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="vcard-modal-body">
                            ${this.getModalContent()}
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
        },

        /**
         * Get modal content based on current step
         */
        getModalContent: function() {
            switch (this.currentStep) {
                case 'choose_method':
                    return this.getChooseMethodContent();
                    
                case 'email_register':
                    return this.getEmailRegistrationContent();
                    
                case 'phone_register':
                    return this.getPhoneRegistrationContent();
                    
                case 'verify_phone':
                    return this.getPhoneVerificationContent();
                    
                case 'login':
                    return this.getLoginContent();
                    
                case 'sync_contacts':
                    return this.getSyncContactsContent();
                    
                default:
                    return this.getChooseMethodContent();
            }
        },

        /**
         * Get choose method content
         */
        getChooseMethodContent: function() {
            return `
                <div class="vcard-registration-step vcard-choose-method">
                    <p class="vcard-step-description">Choose how you'd like to create your account:</p>
                    
                    <div class="vcard-registration-methods">
                        <button class="vcard-registration-method" data-method="email">
                            <i class="fas fa-envelope"></i>
                            <span>Register with Email</span>
                            <small>Traditional email registration</small>
                        </button>
                        
                        <button class="vcard-registration-method" data-method="phone">
                            <i class="fas fa-mobile-alt"></i>
                            <span>Register with Phone</span>
                            <small>Quick SMS verification</small>
                        </button>
                    </div>
                    
                    <div class="vcard-social-login">
                        <div class="vcard-divider">
                            <span>or continue with</span>
                        </div>
                        
                        <div class="vcard-social-buttons">
                            <button class="vcard-social-login-btn" data-provider="google">
                                <i class="fab fa-google"></i>
                                <span>Google</span>
                            </button>
                            
                            <button class="vcard-social-login-btn" data-provider="facebook">
                                <i class="fab fa-facebook-f"></i>
                                <span>Facebook</span>
                            </button>
                            
                            <button class="vcard-social-login-btn" data-provider="linkedin">
                                <i class="fab fa-linkedin-in"></i>
                                <span>LinkedIn</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="vcard-switch-mode">
                        <p>Already have an account? <a href="#" class="vcard-switch-to-login">Sign in</a></p>
                    </div>
                </div>
            `;
        },

        /**
         * Get email registration content
         */
        getEmailRegistrationContent: function() {
            return `
                <div class="vcard-registration-step vcard-email-registration">
                    <form class="vcard-email-registration-form">
                        <div class="vcard-form-row">
                            <div class="vcard-form-field">
                                <label for="reg_first_name">First Name *</label>
                                <input type="text" id="reg_first_name" name="first_name" required>
                            </div>
                            <div class="vcard-form-field">
                                <label for="reg_last_name">Last Name *</label>
                                <input type="text" id="reg_last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="vcard-form-field">
                            <label for="reg_email">Email Address *</label>
                            <input type="email" id="reg_email" name="email" required>
                        </div>
                        
                        <div class="vcard-form-field">
                            <label for="reg_password">Password *</label>
                            <input type="password" id="reg_password" name="password" class="vcard-password-input" required>
                            <div class="vcard-password-strength"></div>
                        </div>
                        
                        <div class="vcard-form-field">
                            <label for="reg_confirm_password">Confirm Password *</label>
                            <input type="password" id="reg_confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="vcard-form-actions">
                            <button type="button" class="vcard-btn vcard-btn-secondary vcard-back-btn">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="submit" class="vcard-btn vcard-btn-primary">
                                <i class="fas fa-user-plus"></i> Create Account
                            </button>
                        </div>
                    </form>
                </div>
            `;
        },

        /**
         * Get phone registration content
         */
        getPhoneRegistrationContent: function() {
            return `
                <div class="vcard-registration-step vcard-phone-registration">
                    <form class="vcard-phone-registration-form">
                        <div class="vcard-form-row">
                            <div class="vcard-form-field">
                                <label for="reg_phone_first_name">First Name *</label>
                                <input type="text" id="reg_phone_first_name" name="first_name" required>
                            </div>
                            <div class="vcard-form-field">
                                <label for="reg_phone_last_name">Last Name *</label>
                                <input type="text" id="reg_phone_last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="vcard-form-field">
                            <label for="reg_phone">Phone Number *</label>
                            <input type="tel" id="reg_phone" name="phone" class="vcard-phone-input" placeholder="+1 (555) 123-4567" required>
                            <small>We'll send you a verification code via SMS</small>
                        </div>
                        
                        <div class="vcard-form-actions">
                            <button type="button" class="vcard-btn vcard-btn-secondary vcard-back-btn">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="submit" class="vcard-btn vcard-btn-primary">
                                <i class="fas fa-sms"></i> Send Verification Code
                            </button>
                        </div>
                    </form>
                </div>
            `;
        },

        /**
         * Get phone verification content
         */
        getPhoneVerificationContent: function() {
            return `
                <div class="vcard-registration-step vcard-phone-verification">
                    <div class="vcard-verification-info">
                        <i class="fas fa-mobile-alt"></i>
                        <h4>Verify Your Phone Number</h4>
                        <p>We sent a 6-digit code to <strong>${this.registrationData.phone || 'your phone'}</strong></p>
                    </div>
                    
                    <form class="vcard-verification-form">
                        <div class="vcard-form-field">
                            <label for="verification_code">Verification Code *</label>
                            <input type="text" id="verification_code" name="verification_code" 
                                   maxlength="6" pattern="[0-9]{6}" placeholder="123456" required>
                        </div>
                        
                        <div class="vcard-verification-actions">
                            <button type="button" class="vcard-resend-verification">
                                <i class="fas fa-redo"></i> Resend Code
                            </button>
                        </div>
                        
                        <div class="vcard-form-actions">
                            <button type="button" class="vcard-btn vcard-btn-secondary vcard-back-btn">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="submit" class="vcard-btn vcard-btn-primary">
                                <i class="fas fa-check"></i> Verify & Create Account
                            </button>
                        </div>
                    </form>
                </div>
            `;
        },

        /**
         * Get login content
         */
        getLoginContent: function() {
            return `
                <div class="vcard-registration-step vcard-login">
                    <form class="vcard-login-form">
                        <div class="vcard-form-field">
                            <label for="login_username">Email or Username *</label>
                            <input type="text" id="login_username" name="username" required>
                        </div>
                        
                        <div class="vcard-form-field">
                            <label for="login_password">Password *</label>
                            <input type="password" id="login_password" name="password" required>
                        </div>
                        
                        <div class="vcard-form-options">
                            <label class="vcard-checkbox">
                                <input type="checkbox" name="remember_me" value="1">
                                <span class="vcard-checkmark"></span>
                                Remember me
                            </label>
                            
                            <a href="#" class="vcard-forgot-password">Forgot password?</a>
                        </div>
                        
                        <div class="vcard-form-actions">
                            <button type="submit" class="vcard-btn vcard-btn-primary vcard-btn-full">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </button>
                        </div>
                    </form>
                    
                    <div class="vcard-social-login">
                        <div class="vcard-divider">
                            <span>or sign in with</span>
                        </div>
                        
                        <div class="vcard-social-buttons">
                            <button class="vcard-social-login-btn" data-provider="google">
                                <i class="fab fa-google"></i>
                                <span>Google</span>
                            </button>
                            
                            <button class="vcard-social-login-btn" data-provider="facebook">
                                <i class="fab fa-facebook-f"></i>
                                <span>Facebook</span>
                            </button>
                            
                            <button class="vcard-social-login-btn" data-provider="linkedin">
                                <i class="fab fa-linkedin-in"></i>
                                <span>LinkedIn</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="vcard-switch-mode">
                        <p>Don't have an account? <a href="#" class="vcard-switch-to-register">Sign up</a></p>
                    </div>
                </div>
            `;
        },

        /**
         * Get sync contacts content
         */
        getSyncContactsContent: function() {
            var localContacts = this.getLocalContacts();
            var contactCount = Object.keys(localContacts).length;
            
            return `
                <div class="vcard-registration-step vcard-sync-contacts">
                    <div class="vcard-sync-info">
                        <i class="fas fa-sync-alt"></i>
                        <h4>Sync Your Saved Contacts</h4>
                        <p>You have <strong>${contactCount}</strong> contacts saved locally. Would you like to sync them to your account?</p>
                    </div>
                    
                    ${contactCount > 0 ? `
                        <div class="vcard-local-contacts-preview">
                            <h5>Contacts to sync:</h5>
                            <div class="vcard-contacts-list">
                                ${this.getLocalContactsPreview(localContacts)}
                            </div>
                        </div>
                        
                        <div class="vcard-form-actions">
                            <button type="button" class="vcard-btn vcard-btn-secondary vcard-skip-sync">
                                Skip for now
                            </button>
                            <button type="button" class="vcard-btn vcard-btn-primary vcard-sync-contacts-btn">
                                <i class="fas fa-cloud-upload-alt"></i> Sync Contacts
                            </button>
                        </div>
                    ` : `
                        <div class="vcard-no-local-contacts">
                            <p>No local contacts found to sync.</p>
                        </div>
                        
                        <div class="vcard-form-actions">
                            <button type="button" class="vcard-btn vcard-btn-primary vcard-continue-btn">
                                Continue
                            </button>
                        </div>
                    `}
                </div>
            `;
        },

        /**
         * Select registration method
         */
        selectRegistrationMethod: function(e) {
            e.preventDefault();
            
            var method = $(e.currentTarget).data('method');
            
            if (method === 'email') {
                this.currentStep = 'email_register';
            } else if (method === 'phone') {
                this.currentStep = 'phone_register';
            }
            
            this.updateModalContent();
        },

        /**
         * Handle email registration
         */
        handleEmailRegistration: function(e) {
            e.preventDefault();
            
            var $form = $(e.currentTarget);
            var $submitBtn = $form.find('button[type="submit"]');
            
            // Get form data
            var formData = {
                action: 'vcard_register_user',
                registration_type: 'email',
                first_name: $form.find('[name="first_name"]').val(),
                last_name: $form.find('[name="last_name"]').val(),
                email: $form.find('[name="email"]').val(),
                password: $form.find('[name="password"]').val(),
                confirm_password: $form.find('[name="confirm_password"]').val(),
                nonce: vcard_registration.nonce
            };
            
            // Validate form
            if (!this.validateEmailRegistration(formData)) {
                return;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating Account...');
            
            // Submit registration
            $.ajax({
                url: vcard_registration.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        
                        // Check for local contacts to sync
                        var localContacts = this.getLocalContacts();
                        if (Object.keys(localContacts).length > 0) {
                            this.currentStep = 'sync_contacts';
                            this.updateModalContent();
                        } else {
                            // Redirect or close modal
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        }
                    } else {
                        this.showMessage(response.data || 'Registration failed', 'error');
                        $submitBtn.prop('disabled', false).html('<i class="fas fa-user-plus"></i> Create Account');
                    }
                },
                error: () => {
                    this.showMessage('Network error occurred', 'error');
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-user-plus"></i> Create Account');
                }
            });
        },

        /**
         * Handle phone registration
         */
        handlePhoneRegistration: function(e) {
            e.preventDefault();
            
            var $form = $(e.currentTarget);
            var $submitBtn = $form.find('button[type="submit"]');
            
            // Get form data
            var formData = {
                action: 'vcard_register_user',
                registration_type: 'phone',
                first_name: $form.find('[name="first_name"]').val(),
                last_name: $form.find('[name="last_name"]').val(),
                phone: $form.find('[name="phone"]').val(),
                nonce: vcard_registration.nonce
            };
            
            // Validate form
            if (!this.validatePhoneRegistration(formData)) {
                return;
            }
            
            // Store registration data
            this.registrationData = formData;
            
            // Show loading state
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending Code...');
            
            // Submit registration
            $.ajax({
                url: vcard_registration.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        this.registrationData.phone_hash = response.data.phone_hash;
                        this.currentStep = 'verify_phone';
                        this.updateModalContent();
                    } else {
                        this.showMessage(response.data || 'Failed to send verification code', 'error');
                        $submitBtn.prop('disabled', false).html('<i class="fas fa-sms"></i> Send Verification Code');
                    }
                },
                error: () => {
                    this.showMessage('Network error occurred', 'error');
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-sms"></i> Send Verification Code');
                }
            });
        },

        /**
         * Handle phone verification
         */
        handlePhoneVerification: function(e) {
            e.preventDefault();
            
            var $form = $(e.currentTarget);
            var $submitBtn = $form.find('button[type="submit"]');
            
            var verificationCode = $form.find('[name="verification_code"]').val();
            
            if (!verificationCode || verificationCode.length !== 6) {
                this.showMessage('Please enter a valid 6-digit code', 'error');
                return;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Verifying...');
            
            // Submit verification
            $.ajax({
                url: vcard_registration.ajax_url,
                type: 'POST',
                data: {
                    action: 'vcard_verify_sms',
                    phone_hash: this.registrationData.phone_hash,
                    verification_code: verificationCode,
                    nonce: vcard_registration.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        
                        // Check for local contacts to sync
                        var localContacts = this.getLocalContacts();
                        if (Object.keys(localContacts).length > 0) {
                            this.currentStep = 'sync_contacts';
                            this.updateModalContent();
                        } else {
                            // Redirect or close modal
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        }
                    } else {
                        this.showMessage(response.data || 'Verification failed', 'error');
                        $submitBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Verify & Create Account');
                    }
                },
                error: () => {
                    this.showMessage('Network error occurred', 'error');
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Verify & Create Account');
                }
            });
        },

        /**
         * Handle login
         */
        handleLogin: function(e) {
            e.preventDefault();
            
            var $form = $(e.currentTarget);
            var $submitBtn = $form.find('button[type="submit"]');
            
            // Get form data
            var formData = {
                log: $form.find('[name="username"]').val(),
                pwd: $form.find('[name="password"]').val(),
                rememberme: $form.find('[name="remember_me"]').is(':checked') ? 1 : 0,
                redirect_to: window.location.href
            };
            
            // Show loading state
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Signing In...');
            
            // Submit login via AJAX
            $.ajax({
                url: vcard_registration.ajax_url,
                type: 'POST',
                data: {
                    action: 'vcard_user_login',
                    username: formData.log,
                    password: formData.pwd,
                    remember: formData.rememberme,
                    nonce: vcard_registration.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage(response.data.message || 'Login successful!', 'success');
                        this.handleRegistrationSuccess(response.data);
                    } else {
                        this.showMessage(response.data || 'Login failed', 'error');
                        $submitBtn.prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> Sign In');
                    }
                },
                error: () => {
                    this.showMessage('Network error occurred', 'error');
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> Sign In');
                }
            });
        },

        /**
         * Get login content
         */
        getLoginContent: function() {
            return `
                <div class="vcard-registration-step vcard-step-login" style="display: none;">
                    <form class="vcard-login-form">
                        <div class="vcard-form-field">
                            <label for="login_username">Email or Username *</label>
                            <input type="text" id="login_username" name="username" required>
                        </div>
                        
                        <div class="vcard-form-field">
                            <label for="login_password">Password *</label>
                            <input type="password" id="login_password" name="password" required>
                        </div>
                        
                        <div class="vcard-form-field">
                            <label class="vcard-checkbox-label">
                                <input type="checkbox" name="remember_me">
                                <span class="vcard-checkbox-custom"></span>
                                Remember me
                            </label>
                        </div>
                        
                        <div class="vcard-form-actions">
                            <button type="submit" class="vcard-btn-primary vcard-btn-full">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </button>
                        </div>
                        
                        <div class="vcard-login-help">
                            <p><a href="#" class="vcard-forgot-password">Forgot your password?</a></p>
                        </div>
                    </form>
                    
                    <div class="vcard-social-divider">
                        <span>or login with</span>
                    </div>
                    
                    <div class="vcard-social-login-buttons">
                        <button class="vcard-social-login-btn" data-provider="google">
                            <i class="fab fa-google"></i>
                            <span>Google</span>
                        </button>
                        
                        <button class="vcard-social-login-btn" data-provider="facebook">
                            <i class="fab fa-facebook-f"></i>
                            <span>Facebook</span>
                        </button>
                        
                        <button class="vcard-social-login-btn" data-provider="linkedin">
                            <i class="fab fa-linkedin-in"></i>
                            <span>LinkedIn</span>
                        </button>
                    </div>
                    
                    <div class="vcard-modal-footer">
                        <p>Don't have an account? <a href="#" class="vcard-switch-to-register">Register here</a></p>
                    </div>
                </div>
            `;
        },

        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $('<div class="vcard-message vcard-message-' + type + '">' + message + '</div>');
            $('body').append($message);
            
            $message.fadeIn().delay(4000).fadeOut(function() {
                $(this).remove();
            });
        }
            $.ajax({
                url: wp.ajax.settings.url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    // WordPress login doesn't return JSON by default
                    // If we get here, login was successful
                    this.showMessage('Login successful!', 'success');
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                },
                error: () => {
                    this.showMessage('Login failed. Please check your credentials.', 'error');
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> Sign In');
                }
            });
        },

        /**
         * Handle social login
         */
        handleSocialLogin: function(e) {
            e.preventDefault();
            
            var provider = $(e.currentTarget).data('provider');
            
            // This is a placeholder for social login integration
            // In a real implementation, you would integrate with:
            // - Google OAuth 2.0
            // - Facebook Login
            // - LinkedIn OAuth
            
            this.showMessage('Social login integration coming soon!', 'info');
        },

        /**
         * Resend verification code
         */
        resendVerification: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
            
            $.ajax({
                url: vcard_registration.ajax_url,
                type: 'POST',
                data: {
                    action: 'vcard_resend_verification',
                    phone_hash: this.registrationData.phone_hash,
                    nonce: vcard_registration.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                    } else {
                        this.showMessage(response.data || 'Failed to resend code', 'error');
                    }
                    
                    $btn.prop('disabled', false).html('<i class="fas fa-redo"></i> Resend Code');
                },
                error: () => {
                    this.showMessage('Network error occurred', 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-redo"></i> Resend Code');
                }
            });
        },

        /**
         * Sync local contacts to cloud
         */
        syncLocalContacts: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var localContacts = this.getLocalContacts();
            
            if (Object.keys(localContacts).length === 0) {
                this.showMessage('No local contacts to sync', 'info');
                return;
            }
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Syncing...');
            
            $.ajax({
                url: vcard_registration.ajax_url,
                type: 'POST',
                data: {
                    action: 'vcard_sync_contacts',
                    local_contacts: JSON.stringify(localContacts),
                    nonce: vcard_registration.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                        
                        // Clear local storage after successful sync
                        localStorage.removeItem('vcard_saved_contacts');
                        localStorage.removeItem('vcard_contact_data');
                        
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showMessage(response.data || 'Sync failed', 'error');
                        $btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt"></i> Sync Contacts');
                    }
                },
                error: () => {
                    this.showMessage('Network error occurred', 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt"></i> Sync Contacts');
                }
            });
        },

        /**
         * Switch to login mode
         */
        switchToLogin: function(e) {
            e.preventDefault();
            
            this.currentStep = 'login';
            this.updateModalTitle('Sign In');
            this.updateModalContent();
        },

        /**
         * Switch to register mode
         */
        switchToRegister: function(e) {
            e.preventDefault();
            
            this.currentStep = 'choose_method';
            this.updateModalTitle('Register');
            this.updateModalContent();
        },

        /**
         * Update modal content
         */
        updateModalContent: function() {
            $('.vcard-modal-body').html(this.getModalContent());
            this.bindBackButton();
        },

        /**
         * Update modal title
         */
        updateModalTitle: function(title) {
            $('.vcard-modal-title-text').text(title);
        },

        /**
         * Bind back button functionality
         */
        bindBackButton: function() {
            $(document).off('click', '.vcard-back-btn').on('click', '.vcard-back-btn', (e) => {
                e.preventDefault();
                
                if (this.currentStep === 'email_register' || this.currentStep === 'phone_register') {
                    this.currentStep = 'choose_method';
                } else if (this.currentStep === 'verify_phone') {
                    this.currentStep = 'phone_register';
                }
                
                this.updateModalContent();
            });
        },

        /**
         * Reset modal to initial state
         */
        resetModal: function() {
            this.currentStep = 'choose_method';
            this.registrationData = {};
            this.updateModalTitle('Register');
        },

        /**
         * Validate email registration
         */
        validateEmailRegistration: function(data) {
            if (!data.first_name.trim()) {
                this.showMessage('First name is required', 'error');
                return false;
            }
            
            if (!data.last_name.trim()) {
                this.showMessage('Last name is required', 'error');
                return false;
            }
            
            if (!this.isValidEmail(data.email)) {
                this.showMessage('Please enter a valid email address', 'error');
                return false;
            }
            
            if (data.password.length < 8) {
                this.showMessage('Password must be at least 8 characters long', 'error');
                return false;
            }
            
            if (data.password !== data.confirm_password) {
                this.showMessage('Passwords do not match', 'error');
                return false;
            }
            
            return true;
        },

        /**
         * Validate phone registration
         */
        validatePhoneRegistration: function(data) {
            if (!data.first_name.trim()) {
                this.showMessage('First name is required', 'error');
                return false;
            }
            
            if (!data.last_name.trim()) {
                this.showMessage('Last name is required', 'error');
                return false;
            }
            
            if (!this.isValidPhoneNumber(data.phone)) {
                this.showMessage('Please enter a valid phone number', 'error');
                return false;
            }
            
            return true;
        },

        /**
         * Check if email is valid
         */
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        /**
         * Check if phone number is valid
         */
        isValidPhoneNumber: function(phone) {
            var phoneRegex = /^\+?[1-9]\d{1,14}$/;
            var cleanPhone = phone.replace(/[^\d+]/g, '');
            return phoneRegex.test(cleanPhone) && cleanPhone.length >= 10;
        },

        /**
         * Format phone number as user types
         */
        formatPhoneNumber: function(e) {
            var input = e.target;
            var value = input.value.replace(/\D/g, '');
            
            if (value.length >= 10) {
                var formatted = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                if (value.length > 10) {
                    formatted = '+' + value.substring(0, value.length - 10) + ' ' + formatted;
                }
                input.value = formatted;
            }
        },

        /**
         * Check password strength
         */
        checkPasswordStrength: function(e) {
            var password = e.target.value;
            var $indicator = $(e.target).siblings('.vcard-password-strength');
            
            if (!$indicator.length) {
                return;
            }
            
            var strength = this.calculatePasswordStrength(password);
            var strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            var strengthClass = ['very-weak', 'weak', 'fair', 'good', 'strong'];
            
            $indicator.removeClass('very-weak weak fair good strong')
                     .addClass(strengthClass[strength])
                     .text(strengthText[strength]);
        },

        /**
         * Calculate password strength
         */
        calculatePasswordStrength: function(password) {
            var score = 0;
            
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            return Math.min(score, 4);
        },

        /**
         * Get local contacts from storage
         */
        getLocalContacts: function() {
            try {
                return JSON.parse(localStorage.getItem('vcard_contact_data') || '{}');
            } catch (e) {
                return {};
            }
        },

        /**
         * Get local contacts preview HTML
         */
        getLocalContactsPreview: function(contacts) {
            var html = '';
            var count = 0;
            
            for (var profileId in contacts) {
                if (count >= 5) break; // Show max 5 contacts
                
                var contact = contacts[profileId];
                html += `
                    <div class="vcard-contact-preview">
                        <div class="vcard-contact-info">
                            <strong>${contact.business_name || contact.owner_name || 'Unknown'}</strong>
                            ${contact.phone ? `<span>${contact.phone}</span>` : ''}
                        </div>
                    </div>
                `;
                count++;
            }
            
            var remaining = Object.keys(contacts).length - count;
            if (remaining > 0) {
                html += `<div class="vcard-contacts-more">...and ${remaining} more</div>`;
            }
            
            return html;
        },

        /**
         * Check current login status
         */
        checkLoginStatus: function() {
            // Update UI based on login status
            if ($('body').hasClass('logged-in')) {
                $('.vcard-register-btn, .vcard-login-btn').hide();
                $('.vcard-user-menu').show();
            }
        },

        /**
         * Initialize social login SDKs
         */
        initSocialLogin: function() {
            // This would initialize social login SDKs
            // Google, Facebook, LinkedIn, etc.
            // Implementation depends on the specific providers
        },

        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $('<div class="vcard-message vcard-message-' + type + '">' + message + '</div>');
            $('body').append($message);
            
            $message.fadeIn().delay(3000).fadeOut(function() {
                $(this).remove();
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
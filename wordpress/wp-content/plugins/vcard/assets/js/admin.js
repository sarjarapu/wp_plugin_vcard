/**
 * vCard Plugin Admin JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Initialize admin functionality when document is ready
    $(document).ready(function() {
        VCardAdmin.init();
    });

    // Main admin object
    var VCardAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Settings form handling
            $('.vcard-settings-form').on('submit', this.handleSettingsSubmit);
            
            // Tab navigation
            $('.vcard-nav-tab').on('click', this.handleTabClick);
            
            // Confirmation dialogs
            $('.vcard-confirm-action').on('click', this.handleConfirmAction);
        },

        /**
         * Initialize components
         */
        initComponents: function() {
            // Initialize tooltips if available
            if (typeof $.fn.tooltip === 'function') {
                $('.vcard-tooltip').tooltip();
            }
            
            // Initialize color pickers if available
            if (typeof $.fn.wpColorPicker === 'function') {
                $('.vcard-color-picker').wpColorPicker();
            }
        },

        /**
         * Handle settings form submission
         */
        handleSettingsSubmit: function(e) {
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            // Show loading state
            $submitButton.prop('disabled', true).val('Saving...');
            
            // Form will submit normally, this just provides user feedback
            setTimeout(function() {
                $submitButton.prop('disabled', false).val('Save Changes');
            }, 2000);
        },

        /**
         * Handle tab navigation
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var targetTab = $tab.data('tab');
            
            // Update active tab
            $('.vcard-nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show target content
            $('.vcard-tab-content').hide();
            $('#vcard-tab-' + targetTab).show();
        },

        /**
         * Handle confirmation dialogs
         */
        handleConfirmAction: function(e) {
            var message = $(this).data('confirm') || 'Are you sure?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'success';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },

        /**
         * AJAX helper function
         */
        ajaxRequest: function(action, data, callback) {
            data = data || {};
            data.action = 'vcard_' + action;
            data.nonce = vcard_admin.nonce;
            
            $.ajax({
                url: vcard_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    VCardAdmin.showNotice('An error occurred. Please try again.', 'error');
                }
            });
        }
    };

    // Make VCardAdmin globally available
    window.VCardAdmin = VCardAdmin;

})(jQuery);
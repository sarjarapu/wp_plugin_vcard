/**
 * vCard Plugin Public JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Initialize public functionality when document is ready
    $(document).ready(function() {
        VCardPublic.init();
    });

    // Main public object
    var VCardPublic = {
        
        /**
         * Initialize public functionality
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // vCard download
            $('.vcard-download-btn').on('click', this.handleVCardDownload);
            
            // Contact save
            $('.vcard-save-contact-btn').on('click', this.handleSaveContact);
            
            // Share buttons
            $('.vcard-share-btn').on('click', this.handleShare);
            
            // Contact form
            $('.vcard-contact-form').on('submit', this.handleContactForm);
        },

        /**
         * Initialize components
         */
        initComponents: function() {
            // Track profile view
            this.trackProfileView();
            
            // Initialize any third-party components
            this.initQRCode();
        },

        /**
         * Handle vCard download
         */
        handleVCardDownload: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var profileId = $button.data('profile-id');
            
            if (!profileId) {
                console.error('Profile ID not found');
                return;
            }
            
            // Track download event
            VCardPublic.trackEvent('vcard_download', {
                profile_id: profileId
            });
            
            // Generate and download vCard
            VCardPublic.generateVCard(profileId);
        },

        /**
         * Handle save contact
         */
        handleSaveContact: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var profileId = $button.data('profile-id');
            
            if (!profileId) {
                VCardPublic.showMessage('Profile ID not found', 'error');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            
            // Save contact via AJAX
            $.ajax({
                url: vcard_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_vcard_contact',
                    profile_id: profileId,
                    nonce: vcard_public.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.html('<i class="fas fa-check"></i> Saved!').addClass('saved');
                        VCardPublic.showMessage('Contact saved successfully!', 'success');
                        
                        setTimeout(function() {
                            $button.html('<i class="fas fa-bookmark"></i> Save Contact').removeClass('saved').prop('disabled', false);
                        }, 2000);
                    } else {
                        VCardPublic.showMessage(response.data || 'Failed to save contact', 'error');
                        $button.html('<i class="fas fa-bookmark"></i> Save Contact').prop('disabled', false);
                    }
                },
                error: function() {
                    VCardPublic.showMessage('Network error occurred', 'error');
                    $button.html('<i class="fas fa-bookmark"></i> Save Contact').prop('disabled', false);
                }
            });
        },

        /**
         * Handle social sharing
         */
        handleShare: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var platform = $button.data('platform');
            var url = window.location.href;
            var title = document.title;
            
            var shareUrls = {
                facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url),
                twitter: 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(title),
                linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(url),
                whatsapp: 'https://wa.me/?text=' + encodeURIComponent(title + ' ' + url)
            };
            
            if (shareUrls[platform]) {
                window.open(shareUrls[platform], '_blank', 'width=600,height=400');
                
                // Track share event
                VCardPublic.trackEvent('profile_share', {
                    platform: platform,
                    url: url
                });
            }
        },

        /**
         * Handle contact form submission
         */
        handleContactForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            // Show loading state
            $submitButton.prop('disabled', true).val('Sending...');
            
            // Submit form via AJAX
            $.ajax({
                url: vcard_public.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=vcard_contact_form&nonce=' + vcard_public.nonce,
                success: function(response) {
                    if (response.success) {
                        $form[0].reset();
                        VCardPublic.showMessage('Message sent successfully!', 'success');
                    } else {
                        VCardPublic.showMessage('Error sending message. Please try again.', 'error');
                    }
                },
                error: function() {
                    VCardPublic.showMessage('Error sending message. Please try again.', 'error');
                },
                complete: function() {
                    $submitButton.prop('disabled', false).val('Send Message');
                }
            });
        },

        /**
         * Generate and download vCard
         */
        generateVCard: function(profileId) {
            // This will be implemented in future tasks
            // For now, just trigger download action
            window.location.href = vcard_public.ajax_url + '?action=vcard_download&profile_id=' + profileId + '&nonce=' + vcard_public.nonce;
        },

        /**
         * Save contact to local storage
         */
        saveToLocalStorage: function(profileId) {
            var savedContacts = JSON.parse(localStorage.getItem('vcard_saved_contacts') || '[]');
            
            if (savedContacts.indexOf(profileId) === -1) {
                savedContacts.push(profileId);
                localStorage.setItem('vcard_saved_contacts', JSON.stringify(savedContacts));
            }
        },

        /**
         * Track profile view
         */
        trackProfileView: function() {
            if ($('body').hasClass('single-vcard_profile')) {
                var profileId = $('body').data('profile-id') || $('.vcard-profile').data('profile-id');
                
                if (profileId) {
                    this.trackEvent('profile_view', {
                        profile_id: profileId
                    });
                }
            }
        },

        /**
         * Track events
         */
        trackEvent: function(eventType, data) {
            $.ajax({
                url: vcard_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'vcard_track_event',
                    event_type: eventType,
                    event_data: JSON.stringify(data),
                    nonce: vcard_public.nonce
                }
            });
        },

        /**
         * Initialize QR code
         */
        initQRCode: function() {
            // QR code generation will be implemented in future tasks
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

    // Make VCardPublic globally available
    window.VCardPublic = VCardPublic;

})(jQuery);
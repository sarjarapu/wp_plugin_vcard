/**
 * vCard Sharing and QR Code JavaScript
 * Handles social media sharing, QR code generation, and sharing analytics
 * 
 * @package vCard
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // vCard Sharing object
    var VCardSharing = {
        
        /**
         * Initialize sharing functionality
         */
        init: function() {
            if (typeof vcard_sharing === 'undefined') {
                console.error('vcard_sharing object not available - sharing functionality will be limited');
                return;
            }
            
            this.bindEvents();
            this.initializeComponents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // QR code generation
            $(document).on('click', '.vcard-qr-btn', this.handleQRGeneration);
            
            // Social sharing buttons
            $(document).on('click', '.vcard-share-btn', this.handleSocialShare);
            $(document).on('click', '.social-share-btn', this.handlePlatformShare);
            
            // Copy link functionality
            $(document).on('click', '[data-action="copy-link"]', this.handleCopyLink);
            
            // Short URL generation
            $(document).on('click', '.generate-short-url', this.handleShortUrlGeneration);
            
            // QR code customization
            $(document).on('change', '.qr-customization input, .qr-customization select', this.handleQRCustomization);
            
            // Modal controls
            $(document).on('click', '.close-modal, .modal-overlay', this.closeModal);
            $(document).on('click', '.modal-content', function(e) { e.stopPropagation(); });
            
            // Download QR code
            $(document).on('click', '.download-qr-btn', this.handleQRDownload);
            
            // Embed code generation
            $(document).on('click', '.generate-embed-code', this.handleEmbedGeneration);
        },

        /**
         * Initialize components
         */
        initializeComponents: function() {
            // Initialize any third-party libraries
            this.initializeQRLibrary();
            
            // Set up sharing analytics
            this.setupSharingAnalytics();
        },

        /**
         * Handle QR code generation
         */
        handleQRGeneration: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var profileId = $button.data('profile-id') || VCardSharing.getCurrentProfileId();
            
            if (!profileId) {
                VCardSharing.showError('Profile ID not found');
                return;
            }
            
            VCardSharing.setButtonLoading($button, true);
            
            // Generate QR code
            VCardSharing.generateQRCode(profileId)
                .then(function(qrData) {
                    VCardSharing.showQRModal(qrData);
                })
                .catch(function(error) {
                    VCardSharing.showError('QR code generation failed: ' + error);
                })
                .finally(function() {
                    VCardSharing.setButtonLoading($button, false);
                });
        },

        /**
         * Generate QR code
         */
        generateQRCode: function(profileId, options) {
            options = options || {};
            
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: vcard_sharing.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'generate_vcard_qr',
                        profile_id: profileId,
                        options: JSON.stringify(options),
                        nonce: vcard_sharing.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            console.log('QR Data received:', response.data);
                            resolve(response.data);
                        } else {
                            console.error('QR generation failed:', response);
                            reject(response.data || 'QR generation failed');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('QR AJAX error:', xhr.responseText);
                        reject('Network error: ' + error);
                    }
                });
            });
        },

        /**
         * Show QR code modal
         */
        showQRModal: function(qrData) {
            var modalHtml = `
                <div id="qr-modal" class="vcard-modal">
                    <div class="modal-overlay"></div>
                    <div class="modal-content qr-modal-content">
                        <div class="modal-header">
                            <h3>${vcard_sharing.strings.qr_code_title}</h3>
                            <button class="close-modal" type="button">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="qr-code-display">
                                <div class="qr-code-container">
                                    <div class="qr-loading" style="padding:20px; text-align:center; color:#666;">
                                        <i class="fas fa-spinner fa-spin"></i> Loading QR Code...
                                    </div>
                                    <img src="${qrData.url}" alt="QR Code" class="qr-code-image" style="display:none;" 
                                         onload="this.style.display='block'; this.style.opacity=1; this.parentElement.querySelector('.qr-loading').style.display='none';" 
                                         onerror="this.style.display='none'; this.parentElement.querySelector('.qr-loading').innerHTML='QR Code failed to load. <a href=&quot;${qrData.url}&quot; target=&quot;_blank&quot;>Click here to view</a>';">
                                </div>
                                <p class="qr-code-description">${vcard_sharing.strings.qr_code_description}</p>
                            </div>
                            
                            <div class="qr-customization">
                                <h4>${vcard_sharing.strings.customize_qr}</h4>
                                <div class="qr-options">
                                    <div class="qr-option">
                                        <label for="qr-size">${vcard_sharing.strings.size}:</label>
                                        <select id="qr-size" name="size">
                                            <option value="200">200x200</option>
                                            <option value="300" selected>300x300</option>
                                            <option value="400">400x400</option>
                                            <option value="500">500x500</option>
                                        </select>
                                    </div>
                                    
                                    <div class="qr-option">
                                        <label for="qr-fg-color">${vcard_sharing.strings.foreground_color}:</label>
                                        <input type="color" id="qr-fg-color" name="foreground_color" value="#000000">
                                    </div>
                                    
                                    <div class="qr-option">
                                        <label for="qr-bg-color">${vcard_sharing.strings.background_color}:</label>
                                        <input type="color" id="qr-bg-color" name="background_color" value="#FFFFFF">
                                    </div>
                                    
                                    <div class="qr-option">
                                        <label for="qr-error-correction">${vcard_sharing.strings.error_correction}:</label>
                                        <select id="qr-error-correction" name="error_correction">
                                            <option value="L">Low</option>
                                            <option value="M" selected>Medium</option>
                                            <option value="Q">Quartile</option>
                                            <option value="H">High</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <button type="button" class="button regenerate-qr-btn">
                                    ${vcard_sharing.strings.regenerate_qr}
                                </button>
                            </div>
                            
                            <div class="qr-actions">
                                <button type="button" class="button button-primary download-qr-btn">
                                    <i class="fas fa-download"></i>
                                    ${vcard_sharing.strings.download_qr}
                                </button>
                                
                                <button type="button" class="button share-qr-btn">
                                    <i class="fas fa-share-alt"></i>
                                    ${vcard_sharing.strings.share_qr}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal
            $('#qr-modal').remove();
            
            // Add new modal
            $('body').append(modalHtml);
            console.log('QR Modal added to body');
            $('#qr-modal').fadeIn(function() {
                console.log('QR Modal fade in complete');
            });
            
            // Store QR data
            $('#qr-modal').data('qr-data', qrData);
            
            // Bind regenerate button
            $('.regenerate-qr-btn').on('click', function() {
                VCardSharing.regenerateQR();
            });
        },

        /**
         * Handle QR customization changes
         */
        handleQRCustomization: function() {
            // Debounce the regeneration
            clearTimeout(VCardSharing.qrRegenerateTimeout);
            VCardSharing.qrRegenerateTimeout = setTimeout(function() {
                VCardSharing.regenerateQR();
            }, 500);
        },

        /**
         * Regenerate QR code with new options
         */
        regenerateQR: function() {
            var profileId = VCardSharing.getCurrentProfileId();
            var options = VCardSharing.getQROptions();
            
            $('.qr-code-image').css('opacity', '0.5');
            
            VCardSharing.generateQRCode(profileId, options)
                .then(function(qrData) {
                    $('.qr-code-image').attr('src', qrData.url).css('opacity', '1');
                    $('#qr-modal').data('qr-data', qrData);
                })
                .catch(function(error) {
                    VCardSharing.showError('QR regeneration failed: ' + error);
                    $('.qr-code-image').css('opacity', '1');
                });
        },

        /**
         * Get QR customization options
         */
        getQROptions: function() {
            return {
                size: parseInt($('#qr-size').val()) || 300,
                foreground_color: $('#qr-fg-color').val().replace('#', ''),
                background_color: $('#qr-bg-color').val().replace('#', ''),
                error_correction: $('#qr-error-correction').val() || 'M'
            };
        },

        /**
         * Handle QR code download
         */
        handleQRDownload: function(e) {
            e.preventDefault();
            
            var qrData = $('#qr-modal').data('qr-data');
            if (!qrData || !qrData.download_url) {
                VCardSharing.showError('QR code data not available');
                return;
            }
            
            // Create download link
            var link = document.createElement('a');
            link.href = qrData.download_url;
            link.download = 'qr-code.png';
            link.style.display = 'none';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Track download
            VCardSharing.trackEvent('qr_download', {
                profile_id: VCardSharing.getCurrentProfileId(),
                options: qrData.options
            });
        },

        /**
         * Handle social sharing
         */
        handleSocialShare: function(e) {
            e.preventDefault();
            
            var profileId = VCardSharing.getCurrentProfileId();
            
            if (!profileId) {
                VCardSharing.showError('Profile ID not found');
                return;
            }
            
            // Get sharing links
            VCardSharing.getSharingLinks(profileId)
                .then(function(sharingData) {
                    VCardSharing.showSharingModal(sharingData);
                })
                .catch(function(error) {
                    VCardSharing.showError('Failed to load sharing options: ' + error);
                });
        },

        /**
         * Get sharing links
         */
        getSharingLinks: function(profileId) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: vcard_sharing.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_vcard_sharing_links',
                        profile_id: profileId,
                        nonce: vcard_sharing.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            resolve(response.data);
                        } else {
                            reject(response.data || 'Failed to get sharing links');
                        }
                    },
                    error: function(xhr, status, error) {
                        reject('Network error: ' + error);
                    }
                });
            });
        },

        /**
         * Show sharing modal
         */
        showSharingModal: function(sharingData) {
            var sharingButtons = '';
            
            for (var platform in sharingData.links) {
                var link = sharingData.links[platform];
                sharingButtons += `
                    <button type="button" class="social-share-btn" 
                            data-platform="${platform}" 
                            data-url="${link.url}" 
                            data-action="${link.action || 'share'}"
                            style="background-color: ${link.color}">
                        <i class="${link.icon}"></i>
                        ${link.label}
                    </button>
                `;
            }
            
            var modalHtml = `
                <div id="sharing-modal" class="vcard-modal">
                    <div class="modal-overlay"></div>
                    <div class="modal-content sharing-modal-content">
                        <div class="modal-header">
                            <h3>${vcard_sharing.strings.share_profile}</h3>
                            <button class="close-modal" type="button">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="sharing-buttons">
                                ${sharingButtons}
                            </div>
                            
                            <div class="sharing-url">
                                <label for="profile-url">${vcard_sharing.strings.profile_url}:</label>
                                <div class="url-input-group">
                                    <input type="text" id="profile-url" value="${sharingData.profile_url}" readonly>
                                    <button type="button" class="button copy-url-btn" data-action="copy-link">
                                        <i class="fas fa-copy"></i>
                                        ${vcard_sharing.strings.copy}
                                    </button>
                                </div>
                            </div>
                            
                            ${sharingData.short_url ? `
                                <div class="sharing-short-url">
                                    <label for="short-url">${vcard_sharing.strings.short_url}:</label>
                                    <div class="url-input-group">
                                        <input type="text" id="short-url" value="${sharingData.short_url}" readonly>
                                        <button type="button" class="button copy-short-url-btn" data-action="copy-link">
                                            <i class="fas fa-copy"></i>
                                            ${vcard_sharing.strings.copy}
                                        </button>
                                    </div>
                                </div>
                            ` : `
                                <div class="generate-short-url-section">
                                    <button type="button" class="button generate-short-url">
                                        <i class="fas fa-link"></i>
                                        ${vcard_sharing.strings.generate_short_url}
                                    </button>
                                </div>
                            `}
                            
                            <div class="sharing-analytics">
                                <h4>${vcard_sharing.strings.sharing_stats}</h4>
                                <div class="stats-grid">
                                    <div class="stat-item">
                                        <span class="stat-number">${sharingData.analytics.total_shares}</span>
                                        <span class="stat-label">${vcard_sharing.strings.total_shares}</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number">${sharingData.analytics.qr_scans}</span>
                                        <span class="stat-label">${vcard_sharing.strings.qr_scans}</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number">${sharingData.analytics.short_url_clicks}</span>
                                        <span class="stat-label">${vcard_sharing.strings.link_clicks}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal
            $('#sharing-modal').remove();
            
            // Add new modal
            $('body').append(modalHtml);
            $('#sharing-modal').fadeIn();
        },

        /**
         * Handle platform-specific sharing
         */
        handlePlatformShare: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var platform = $button.data('platform');
            var url = $button.data('url');
            var action = $button.data('action');
            
            if (action === 'copy-link') {
                VCardSharing.copyToClipboard(url);
                VCardSharing.trackShare(platform);
                return;
            }
            
            // Open sharing window
            var windowFeatures = 'width=600,height=400,scrollbars=yes,resizable=yes';
            window.open(url, 'share_' + platform, windowFeatures);
            
            // Track share
            VCardSharing.trackShare(platform);
            
            // Close modal after a delay
            setTimeout(function() {
                VCardSharing.closeModal();
            }, 1000);
        },

        /**
         * Handle copy link functionality
         */
        handleCopyLink: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $button.siblings('input');
            var url = $input.length ? $input.val() : window.location.href;
            
            VCardSharing.copyToClipboard(url);
            VCardSharing.trackShare('copy');
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    VCardSharing.showSuccess(vcard_sharing.strings.copied_to_clipboard);
                }).catch(function() {
                    VCardSharing.fallbackCopyToClipboard(text);
                });
            } else {
                VCardSharing.fallbackCopyToClipboard(text);
            }
        },

        /**
         * Fallback copy to clipboard
         */
        fallbackCopyToClipboard: function(text) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                VCardSharing.showSuccess(vcard_sharing.strings.copied_to_clipboard);
            } catch (err) {
                VCardSharing.showError(vcard_sharing.strings.copy_failed);
            }
            
            document.body.removeChild(textArea);
        },

        /**
         * Handle short URL generation
         */
        handleShortUrlGeneration: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var profileId = VCardSharing.getCurrentProfileId();
            
            VCardSharing.setButtonLoading($button, true);
            
            $.ajax({
                url: vcard_sharing.ajax_url,
                type: 'POST',
                data: {
                    action: 'generate_short_url',
                    profile_id: profileId,
                    nonce: vcard_sharing.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Update the modal with the new short URL
                        var shortUrlHtml = `
                            <div class="sharing-short-url">
                                <label for="short-url">${vcard_sharing.strings.short_url}:</label>
                                <div class="url-input-group">
                                    <input type="text" id="short-url" value="${response.data.short_url}" readonly>
                                    <button type="button" class="button copy-short-url-btn" data-action="copy-link">
                                        <i class="fas fa-copy"></i>
                                        ${vcard_sharing.strings.copy}
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        $('.generate-short-url-section').replaceWith(shortUrlHtml);
                        VCardSharing.showSuccess(vcard_sharing.strings.short_url_generated);
                    } else {
                        VCardSharing.showError(response.data || 'Short URL generation failed');
                    }
                },
                error: function() {
                    VCardSharing.showError('Network error');
                },
                complete: function() {
                    VCardSharing.setButtonLoading($button, false);
                }
            });
        },

        /**
         * Handle embed code generation
         */
        handleEmbedGeneration: function(e) {
            e.preventDefault();
            
            var profileId = VCardSharing.getCurrentProfileId();
            var options = {
                width: $('#embed-width').val() || 300,
                height: $('#embed-height').val() || 400,
                theme: $('#embed-theme').val() || 'light',
                show_qr: $('#embed-show-qr').is(':checked'),
                show_contact_form: $('#embed-show-contact').is(':checked')
            };
            
            $.ajax({
                url: vcard_sharing.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_embed_code',
                    profile_id: profileId,
                    options: JSON.stringify(options),
                    nonce: vcard_sharing.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $('#embed-code-output').val(response.data.embed_code).show();
                        VCardSharing.showSuccess('Embed code generated');
                    } else {
                        VCardSharing.showError(response.data || 'Embed code generation failed');
                    }
                },
                error: function() {
                    VCardSharing.showError('Network error');
                }
            });
        },

        /**
         * Track sharing event
         */
        trackShare: function(platform) {
            var profileId = VCardSharing.getCurrentProfileId();
            
            $.ajax({
                url: vcard_sharing.ajax_url,
                type: 'POST',
                data: {
                    action: 'track_vcard_share',
                    profile_id: profileId,
                    platform: platform,
                    nonce: vcard_sharing.nonce
                }
            });
        },

        /**
         * Track generic event
         */
        trackEvent: function(eventType, data) {
            // Check if vcard_sharing object exists
            if (typeof vcard_sharing === 'undefined') {
                return;
            }
            
            $.ajax({
                url: vcard_sharing.ajax_url || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'track_vcard_event',
                    event_type: eventType,
                    event_data: JSON.stringify(data),
                    nonce: vcard_sharing.nonce || ''
                }
            });
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            if (e && $(e.target).hasClass('modal-content')) {
                return;
            }
            
            $('.vcard-modal').fadeOut(function() {
                $(this).remove();
            });
        },

        /**
         * Get current profile ID
         */
        getCurrentProfileId: function() {
            // Try multiple methods to get profile ID
            var profileId = $('body').data('profile-id') || 
                           $('.vcard-profile').data('profile-id') ||
                           $('.vcard-single').data('profile-id') ||
                           $('.vcard-download-btn').data('profile-id') ||
                           $('input[name="profile_id"]').val();
            
            // If still not found, try to extract from URL
            if (!profileId) {
                profileId = VCardSharing.extractProfileIdFromUrl();
            }
            
            // If still not found and we're on a single vcard page, try to get from WordPress
            if (!profileId && $('body').hasClass('single-vcard_profile')) {
                // Try to get from WordPress global if available
                if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
                    try {
                        var post = wp.data.select('core/editor').getCurrentPost();
                        if (post && post.id) {
                            profileId = post.id;
                        }
                    } catch (e) {
                        // Ignore error, fallback methods will be used
                    }
                }
            }
            
            return profileId;
        },

        /**
         * Extract profile ID from URL
         */
        extractProfileIdFromUrl: function() {
            var matches = window.location.pathname.match(/\/vcard\/(\d+)/);
            return matches ? matches[1] : null;
        },

        /**
         * Set button loading state
         */
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true)
                       .data('original-html', $button.html())
                       .html('<i class="fas fa-spinner fa-spin"></i> ' + 
                             (vcard_sharing.strings.loading || 'Loading...'));
            } else {
                $button.prop('disabled', false)
                       .html($button.data('original-html') || $button.html());
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            VCardSharing.showMessage(message, 'error');
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            VCardSharing.showMessage(message, 'success');
        },

        /**
         * Show message
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $('<div class="vcard-message vcard-message-' + type + '">' + message + '</div>');
            $('body').append($message);
            
            $message.fadeIn().delay(3000).fadeOut(function() {
                $(this).remove();
            });
        },

        /**
         * Initialize QR library
         */
        initializeQRLibrary: function() {
            // Initialize any QR code libraries if needed
        },

        /**
         * Setup sharing analytics
         */
        setupSharingAnalytics: function() {
            // Track page views for sharing analytics
            var profileId = VCardSharing.getCurrentProfileId();
            
            if (profileId && $('body').hasClass('single-vcard_profile')) {
                VCardSharing.trackEvent('profile_view', {
                    profile_id: profileId,
                    referrer: document.referrer,
                    user_agent: navigator.userAgent
                });
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        VCardSharing.init();
    });

    // Make VCardSharing globally available
    window.VCardSharing = VCardSharing;

})(jQuery);
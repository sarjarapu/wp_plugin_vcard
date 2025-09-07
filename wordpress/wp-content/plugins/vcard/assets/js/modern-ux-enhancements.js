/**
 * Modern UX Enhancements - Phase 1: Bootstrap Foundation
 * Enhanced user experience JavaScript functionality
 */

(function($) {
    'use strict';

    // Main UX Enhancement Class
    class VCardModernUX {
        constructor() {
            this.profileId = this.getProfileId();
            this.isContactSaved = false;
            this.currentSection = '';
            
            this.init();
        }

        init() {
            this.setupActionBar();
            this.setupSectionNavigation();
            this.setupScrollToTop();
            this.setupContactSaveStatus();
            this.setupQuickActions();
            this.setupSmoothScrolling();
            this.setupVisualFeedback();
            
            // Initialize on DOM ready
            $(document).ready(() => {
                this.checkContactSaveStatus();
                this.updateSectionNavigation();
            });
        }

        getProfileId() {
            return document.body.getAttribute('data-profile-id') || 
                   $('.vcard-single').data('profile-id') || 
                   $('[data-profile-id]').first().data('profile-id');
        }

        setupActionBar() {
            // Create and inject the modern action bar
            const actionBarHTML = this.createActionBarHTML();
            $('.vcard-single-container').prepend(actionBarHTML);

            // Handle scroll effects
            $(window).on('scroll', () => {
                const scrolled = $(window).scrollTop() > 50;
                $('.vcard-modern-action-bar').toggleClass('scrolled', scrolled);
                this.updateActiveSection();
            });
        }

        createActionBarHTML() {
            return `
                <div class="vcard-modern-action-bar">
                    <div class="action-bar-container">
                        <!-- Contact Save Status -->
                        <div class="contact-save-status">
                            <button class="save-contact-btn" data-profile-id="${this.profileId}">
                                <svg class="save-icon w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                </svg>
                                <span class="save-text">Save Contact</span>
                            </button>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="quick-actions">
                            <a href="#" class="quick-action-btn call" title="Call" data-action="call">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                                </svg>
                            </a>
                            <a href="#" class="quick-action-btn message" title="Message" data-action="message">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                    <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                                </svg>
                            </a>
                            <a href="#" class="quick-action-btn whatsapp" title="WhatsApp" data-action="whatsapp">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.893 3.488"/>
                                </svg>
                            </a>
                            <button class="quick-action-btn share" title="Share" data-action="share">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                    <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/>
                                </svg>
                            </button>
                            <a href="#" class="quick-action-btn directions" title="Directions" data-action="directions">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Section Navigation -->
                    <div class="section-navigation">
                        <ul class="section-nav-list">
                            <li class="section-nav-item">
                                <a href="#about" class="section-nav-link" data-section="about">About</a>
                            </li>
                            <li class="section-nav-item">
                                <a href="#services" class="section-nav-link" data-section="services">Services</a>
                            </li>
                            <li class="section-nav-item">
                                <a href="#products" class="section-nav-link" data-section="products">Products</a>
                            </li>
                            <li class="section-nav-item">
                                <a href="#gallery" class="section-nav-link" data-section="gallery">Gallery</a>
                            </li>
                            <li class="section-nav-item">
                                <a href="#hours" class="section-nav-link" data-section="hours">Hours</a>
                            </li>
                            <li class="section-nav-item">
                                <a href="#contact" class="section-nav-link" data-section="contact">Contact</a>
                            </li>
                        </ul>
                    </div>
                </div>
            `;
        }

        setupSectionNavigation() {
            // Add section IDs to existing content
            this.addSectionIds();
            
            // Handle section navigation clicks
            $(document).on('click', '.section-nav-link', (e) => {
                e.preventDefault();
                const targetSection = $(e.target).data('section');
                this.scrollToSection(targetSection);
            });

            // Update active section on scroll
            $(window).on('scroll', () => {
                this.updateActiveSection();
            });
        }

        addSectionIds() {
            // Map sections to existing content
            const sectionMappings = {
                'about': '.vcard-description, .vcard-basic-info',
                'services': '.vcard-services',
                'products': '.vcard-products',
                'gallery': '.vcard-gallery',
                'hours': '.vcard-business-hours',
                'contact': '.vcard-contact-info, .vcard-address, .vcard-contact-form'
            };

            Object.entries(sectionMappings).forEach(([sectionId, selector]) => {
                const $element = $(selector).first();
                if ($element.length) {
                    // Add ID if it doesn't exist, or ensure it has the right class
                    if (!$element.attr('id')) {
                        $element.attr('id', sectionId);
                    }
                    $element.addClass('vcard-section');
                }
            });
        }

        scrollToSection(sectionId) {
            const $target = $(`#${sectionId}`);
            if ($target.length) {
                const offset = $('.vcard-modern-action-bar').outerHeight() + 20;
                $('html, body').animate({
                    scrollTop: $target.offset().top - offset
                }, 500);
            }
        }

        updateActiveSection() {
            const scrollTop = $(window).scrollTop();
            const offset = $('.vcard-modern-action-bar').outerHeight() + 50;
            
            $('.vcard-section').each((index, element) => {
                const $section = $(element);
                const sectionTop = $section.offset().top - offset;
                const sectionBottom = sectionTop + $section.outerHeight();
                
                if (scrollTop >= sectionTop && scrollTop < sectionBottom) {
                    const sectionId = $section.attr('id');
                    this.setActiveNavItem(sectionId);
                    return false; // Break the loop
                }
            });
        }

        setActiveNavItem(sectionId) {
            if (this.currentSection !== sectionId) {
                this.currentSection = sectionId;
                $('.section-nav-link').removeClass('active');
                $(`.section-nav-link[data-section="${sectionId}"]`).addClass('active');
            }
        }

        updateSectionNavigation() {
            // Hide navigation items for sections that don't exist
            $('.section-nav-link').each((index, element) => {
                const $link = $(element);
                const sectionId = $link.data('section');
                const $section = $(`#${sectionId}`);
                
                // Only hide if section doesn't exist at all
                if (!$section.length) {
                    $link.parent().hide();
                } else {
                    $link.parent().show();
                }
            });
        }

        setupScrollToTop() {
            // Create scroll to top button
            $('body').append(`
                <button class="scroll-to-top" title="Scroll to top">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                        <path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/>
                    </svg>
                    <span class="sr-only">Scroll to top</span>
                </button>
            `);

            // Handle scroll to top visibility
            $(window).on('scroll', () => {
                const scrolled = $(window).scrollTop() > 300;
                $('.scroll-to-top').toggleClass('visible', scrolled);
            });

            // Handle scroll to top click
            $(document).on('click', '.scroll-to-top', () => {
                $('html, body').animate({ scrollTop: 0 }, 500);
            });
        }

        setupContactSaveStatus() {
            // Handle save contact button click
            $(document).on('click', '.save-contact-btn', (e) => {
                e.preventDefault();
                this.toggleContactSave();
            });
        }

        checkContactSaveStatus() {
            // Check if contact is already saved in localStorage
            const savedContacts = JSON.parse(localStorage.getItem('vcard_saved_contacts') || '[]');
            this.isContactSaved = savedContacts.includes(this.profileId.toString());
            this.updateSaveButton();
        }

        toggleContactSave() {
            const savedContacts = JSON.parse(localStorage.getItem('vcard_saved_contacts') || '[]');
            const profileIdStr = this.profileId.toString();
            
            if (this.isContactSaved) {
                // Remove from saved contacts
                const index = savedContacts.indexOf(profileIdStr);
                if (index > -1) {
                    savedContacts.splice(index, 1);
                }
                this.isContactSaved = false;
                this.showFeedback('Contact removed from saved list', 'success');
            } else {
                // Add to saved contacts
                if (!savedContacts.includes(profileIdStr)) {
                    savedContacts.push(profileIdStr);
                }
                this.isContactSaved = true;
                this.showFeedback('Contact saved successfully!', 'success');
                
                // Trigger pulse animation
                $('.save-contact-btn').addClass('pulse');
                setTimeout(() => $('.save-contact-btn').removeClass('pulse'), 500);
            }
            
            localStorage.setItem('vcard_saved_contacts', JSON.stringify(savedContacts));
            this.updateSaveButton();
            
            // Track the save action
            this.trackEvent('contact_save_toggle', {
                profile_id: this.profileId,
                saved: this.isContactSaved
            });
        }

        updateSaveButton() {
            const $btn = $('.save-contact-btn');
            const $icon = $btn.find('.save-icon');
            const $text = $btn.find('.save-text');
            
            if (this.isContactSaved) {
                $btn.addClass('saved');
                $text.text('Saved');
                $icon.html(`
                    <svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                    </svg>
                `);
            } else {
                $btn.removeClass('saved');
                $text.text('Save Contact');
                $icon.html(`
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                    </svg>
                `);
            }
        }

        setupQuickActions() {
            // Get contact information from the page
            const contactInfo = this.extractContactInfo();
            
            // Handle quick action clicks
            $(document).on('click', '.quick-action-btn', (e) => {
                e.preventDefault();
                const action = $(e.currentTarget).data('action');
                this.handleQuickAction(action, contactInfo);
            });
        }

        extractContactInfo() {
            return {
                phone: this.extractFieldValue('phone'),
                whatsapp: this.extractFieldValue('whatsapp'),
                email: this.extractFieldValue('email'),
                address: this.extractAddress(),
                businessName: $('.vcard-name').text().trim()
            };
        }

        extractFieldValue(fieldType) {
            // Try multiple selectors to find the field
            const selectors = [
                `.vcard-contact-item:contains("${fieldType}") a`,
                `[href^="tel:"]`,
                `[href^="mailto:"]`,
                `[href*="wa.me"]`
            ];
            
            let value = '';
            selectors.forEach(selector => {
                if (!value) {
                    const $element = $(selector).first();
                    if ($element.length) {
                        value = $element.attr('href') || $element.text();
                    }
                }
            });
            
            return value;
        }

        extractAddress() {
            const $address = $('.vcard-address-details');
            return $address.length ? $address.text().trim().replace(/\s+/g, ' ') : '';
        }

        handleQuickAction(action, contactInfo) {
            switch (action) {
                case 'call':
                    if (contactInfo.phone) {
                        const phoneUrl = contactInfo.phone.startsWith('tel:') ? 
                            contactInfo.phone : `tel:${contactInfo.phone}`;
                        window.location.href = phoneUrl;
                        this.trackEvent('quick_action_call', { profile_id: this.profileId });
                    } else {
                        this.showFeedback('Phone number not available', 'error');
                    }
                    break;
                    
                case 'message':
                    if (contactInfo.phone) {
                        const smsUrl = `sms:${contactInfo.phone.replace('tel:', '')}`;
                        window.location.href = smsUrl;
                        this.trackEvent('quick_action_message', { profile_id: this.profileId });
                    } else {
                        this.showFeedback('Phone number not available', 'error');
                    }
                    break;
                    
                case 'whatsapp':
                    if (contactInfo.whatsapp || contactInfo.phone) {
                        const whatsappNumber = (contactInfo.whatsapp || contactInfo.phone)
                            .replace(/[^\d+]/g, '');
                        const message = `Hi, I found your business profile and would like to connect.`;
                        const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;
                        window.open(whatsappUrl, '_blank');
                        this.trackEvent('quick_action_whatsapp', { profile_id: this.profileId });
                    } else {
                        this.showFeedback('WhatsApp number not available', 'error');
                    }
                    break;
                    
                case 'share':
                    this.handleShare();
                    break;
                    
                case 'directions':
                    if (contactInfo.address) {
                        const mapsUrl = `https://maps.google.com/?q=${encodeURIComponent(contactInfo.address)}`;
                        window.open(mapsUrl, '_blank');
                        this.trackEvent('quick_action_directions', { profile_id: this.profileId });
                    } else {
                        this.showFeedback('Address not available', 'error');
                    }
                    break;
            }
        }

        handleShare() {
            const shareData = {
                title: document.title,
                text: `Check out this business profile`,
                url: window.location.href
            };
            
            if (navigator.share) {
                navigator.share(shareData).then(() => {
                    this.trackEvent('quick_action_share_native', { profile_id: this.profileId });
                }).catch(() => {
                    this.fallbackShare();
                });
            } else {
                this.fallbackShare();
            }
        }

        fallbackShare() {
            // Copy URL to clipboard
            if (navigator.clipboard) {
                navigator.clipboard.writeText(window.location.href).then(() => {
                    this.showFeedback('Profile URL copied to clipboard!', 'success');
                    this.trackEvent('quick_action_share_clipboard', { profile_id: this.profileId });
                }).catch(() => {
                    this.showFeedback('Unable to copy URL', 'error');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = window.location.href;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    this.showFeedback('Profile URL copied to clipboard!', 'success');
                    this.trackEvent('quick_action_share_clipboard', { profile_id: this.profileId });
                } catch (err) {
                    this.showFeedback('Unable to copy URL', 'error');
                }
                document.body.removeChild(textArea);
            }
        }

        setupSmoothScrolling() {
            // Enhanced smooth scrolling for anchor links
            $(document).on('click', 'a[href^="#"]', (e) => {
                const href = $(e.currentTarget).attr('href');
                if (href === '#') return;
                
                const $target = $(href);
                if ($target.length) {
                    e.preventDefault();
                    const offset = $('.vcard-modern-action-bar').outerHeight() + 20;
                    $('html, body').animate({
                        scrollTop: $target.offset().top - offset
                    }, 500);
                }
            });
        }

        setupVisualFeedback() {
            // Add visual feedback for interactions
            $(document).on('click', '.btn-modern, .quick-action-btn, .save-contact-btn', function() {
                $(this).addClass('pulse');
                setTimeout(() => $(this).removeClass('pulse'), 300);
            });
        }

        showFeedback(message, type = 'success') {
            // Remove existing feedback
            $('.contact-save-feedback').remove();
            
            // Create feedback element
            const icon = type === 'success' ? 
                '<svg class="feedback-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>' :
                '<svg class="feedback-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
            
            const $feedback = $(`
                <div class="contact-save-feedback ${type}">
                    ${icon}
                    ${message}
                </div>
            `);
            
            $('body').append($feedback);
            
            // Show feedback
            setTimeout(() => $feedback.addClass('show'), 100);
            
            // Hide feedback after 3 seconds
            setTimeout(() => {
                $feedback.removeClass('show');
                setTimeout(() => $feedback.remove(), 300);
            }, 3000);
        }

        trackEvent(eventName, data = {}) {
            // Track events for analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, data);
            }
            
            // Also send to WordPress if AJAX is available
            if (typeof vcard_public !== 'undefined') {
                $.ajax({
                    url: vcard_public.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vcard_modern_ux_track_event',
                        nonce: vcard_public.nonce,
                        event_name: eventName,
                        event_data: data
                    }
                });
            }
        }

        setupSectionNavigation() {
            // Handle section navigation clicks
            $(document).on('click', '.section-nav-link', (e) => {
                e.preventDefault();
                const targetSection = $(e.currentTarget).data('section');
                this.scrollToSection(targetSection);
            });

            // Update active section on scroll
            $(window).on('scroll', () => {
                this.updateActiveSection();
            });
        }

        scrollToSection(sectionId) {
            const target = $(`#${sectionId}`);
            if (target.length) {
                const offset = 120; // Account for sticky headers
                const targetPosition = target.offset().top - offset;
                
                $('html, body').animate({
                    scrollTop: targetPosition
                }, 600, 'easeInOutCubic');
                
                // Update active state immediately
                this.setActiveSection(sectionId);
            }
        }

        updateActiveSection() {
            const sections = $('.vcard-section');
            const scrollTop = $(window).scrollTop();
            
            let activeSection = '';
            
            sections.each((index, element) => {
                const $section = $(element);
                const sectionTop = $section.offset().top - 150;
                const sectionBottom = sectionTop + $section.outerHeight();
                
                if (scrollTop >= sectionTop && scrollTop < sectionBottom) {
                    activeSection = $section.attr('id');
                }
            });
            
            if (activeSection && activeSection !== this.currentSection) {
                this.setActiveSection(activeSection);
            }
        }

        setActiveSection(sectionId) {
            this.currentSection = sectionId;
            
            // Update navigation active state
            $('.section-nav-link').removeClass('active');
            $(`.section-nav-link[data-section="${sectionId}"]`).addClass('active');
            
            // Track section view for analytics
            this.trackSectionView(sectionId);
        }

        trackSectionView(sectionId) {
            // Track section views for analytics
            if (this.profileId && sectionId) {
                $.post(vcard_ajax.ajax_url, {
                    action: 'vcard_track_section_view',
                    profile_id: this.profileId,
                    section: sectionId,
                    nonce: vcard_ajax.nonce
                });
            }
        }

        // Enhanced smooth scrolling with easing
        setupSmoothScrolling() {
            // Add custom easing function
            $.easing.easeInOutCubic = function (x, t, b, c, d) {
                if ((t/=d/2) < 1) return c/2*t*t*t + b;
                return c/2*((t-=2)*t*t + 2) + b;
            };
            
            // Handle all anchor links
            $(document).on('click', 'a[href^="#"]', (e) => {
                const href = $(e.currentTarget).attr('href');
                if (href !== '#' && $(href).length) {
                    e.preventDefault();
                    const sectionId = href.substring(1);
                    this.scrollToSection(sectionId);
                }
            });
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only initialize on vCard profile pages
        if ($('.vcard-single-container').length) {
            new VCardModernUX();
        }
    });

})(jQuery);
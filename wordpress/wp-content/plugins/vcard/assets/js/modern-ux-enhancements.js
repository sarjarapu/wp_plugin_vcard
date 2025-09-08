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
            this.contactData = this.getContactData();
            
            this.init();
        }

        getContactData() {
            // Get contact data directly from data attributes set by Twig template
            const $actionBar = $('.vcard-modern-action-bar');
            
            const phone = $actionBar.data('phone') || '';
            const email = $actionBar.data('email') || '';
            const whatsapp = $actionBar.data('whatsapp') || phone; // Use phone as WhatsApp fallback
            const businessName = $actionBar.data('business-name') || 'this business';
            const address = $actionBar.data('address') || '';
            
            // Clean and format the data
            const contactData = {
                phone: phone.toString().replace(/[^\d+]/g, ''), // Clean phone number
                email: email.toString().trim(),
                whatsapp: whatsapp.toString().replace(/[^\d+]/g, ''), // Clean WhatsApp number
                businessName: businessName.toString().trim(),
                address: address.toString().trim()
            };
            
            // Debug: log contact data (remove in production)
            console.log('Raw Data Attributes:', {
                phone: $actionBar.data('phone'),
                email: $actionBar.data('email'),
                whatsapp: $actionBar.data('whatsapp'),
                businessName: $actionBar.data('business-name'),
                address: $actionBar.data('address')
            });
            console.log('Processed Contact Data:', contactData);
            
            // Debug: Check which buttons will show
            console.log('Buttons that will show:', {
                phone: !!contactData.phone,
                sms: !!contactData.phone,
                whatsapp: !!contactData.whatsapp,
                email: !!contactData.email,
                directions: !!contactData.address && contactData.address !== ', ,  '
            });
            
            // Debug: Email specific (can be removed in production)
            // console.log('Email debug:', contactData.email);
            
            return contactData;
        }

        getDirectionsUrl() {
            // Get address from contact data
            const address = this.contactData.address;
            
            if (address && address !== ', ,  ') { // Check if address has actual content
                return `https://maps.google.com/maps?q=${encodeURIComponent(address)}`;
            }
            return '';
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
            // Create and inject the action bar content into the existing container
            const actionBarHTML = this.createActionBarHTML();
            $('.action-bar-container-placeholder').html(actionBarHTML);

            // Handle scroll effects - only float navigation when scrolling past action bar
            $(window).on('scroll', () => {
                const $actionBar = $('.vcard-modern-action-bar');
                const actionBarBottom = $actionBar.offset().top + $actionBar.outerHeight();
                const scrollTop = $(window).scrollTop();
                
                // Only float navigation when scrolled past the action bar
                const shouldFloat = scrollTop > actionBarBottom;
                $actionBar.toggleClass('scrolled', shouldFloat);
                
                this.updateActiveSection();
            });
        }

        createActionBarHTML() {
            return `
                <!-- Quick Actions -->
                <div class="quick-actions">
                    ${this.contactData.phone ? `
                    <a href="tel:${this.contactData.phone}" class="quick-action-btn call" title="Call ${this.contactData.phone}">
                        <i class="fas fa-phone"></i>
                    </a>` : ''}
                    ${this.contactData.whatsapp ? `
                    <a href="https://wa.me/${this.contactData.whatsapp}?text=${encodeURIComponent('Hi, I would like to know more about ' + this.contactData.businessName)}" class="quick-action-btn whatsapp" title="WhatsApp" target="_blank">
                        <i class="fab fa-whatsapp"></i>
                    </a>` : ''}
                    <!-- DEBUG: Force show email button -->
                    <a href="mailto:${this.contactData.email || 'no-email@example.com'}?subject=${encodeURIComponent('Inquiry about ' + this.contactData.businessName)}" class="quick-action-btn email" title="Email: '${this.contactData.email}' (${typeof this.contactData.email})">
                        <i class="fas fa-envelope"></i>
                    </a>
                    
                    <button class="quick-action-btn share" title="Share" data-action="share">
                        <i class="fas fa-share-alt"></i>
                    </button>
                    ${this.getDirectionsUrl() ? `
                    <a href="${this.getDirectionsUrl()}" class="quick-action-btn directions" title="Get Directions" target="_blank">
                        <i class="fas fa-map-marker-alt"></i>
                    </a>` : ''}
                    ${this.contactData.phone ? `
                    <a href="sms:${this.contactData.phone}?body=${encodeURIComponent('Hi, I would like to know more about ' + this.contactData.businessName)}" class="quick-action-btn message" title="Send SMS">
                        <i class="fas fa-comment"></i>
                    </a>` : ''}
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
                // Calculate offset based on whether navigation is floating
                const $actionBar = $('.vcard-modern-action-bar');
                const isFloating = $actionBar.hasClass('scrolled');
                const offset = isFloating ? $('.section-navigation').outerHeight() + 20 : 20;
                
                $('html, body').animate({
                    scrollTop: $target.offset().top - offset
                }, 500);
            }
        }

        updateActiveSection() {
            const scrollTop = $(window).scrollTop();
            // Calculate offset based on whether navigation is floating
            const $actionBar = $('.vcard-modern-action-bar');
            const isFloating = $actionBar.hasClass('scrolled');
            const offset = isFloating ? $('.section-navigation').outerHeight() + 50 : 50;
            
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
            $(document).on('click', '.contact-action-btn.save-contact', (e) => {
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
                $('.contact-action-btn.save-contact').addClass('pulse');
                setTimeout(() => $('.contact-action-btn.save-contact').removeClass('pulse'), 500);
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
            const $btn = $('.contact-action-btn.save-contact');
            const $icon = $btn.find('i');
            
            if (this.isContactSaved) {
                $btn.addClass('saved');
                $btn.attr('title', 'Contact Saved ❤️');
                $icon.removeClass('far').addClass('fas'); // Solid heart when saved
            } else {
                $btn.removeClass('saved');
                $btn.attr('title', 'Save Contact');
                $icon.removeClass('fas').addClass('far'); // Outline heart when not saved
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
            $(document).on('click', '.btn-modern, .quick-action-btn, .contact-action-btn', function() {
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
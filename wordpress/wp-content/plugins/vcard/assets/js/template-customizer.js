/**
 * Template Customizer JavaScript
 * Handles real-time preview, color scheme selection, and template recommendations
 */

jQuery(document).ready(function($) {
    
    var customizer = {
        
        // Current selections - simplified approach
        currentTemplate: $('input[name="vcard_template_name"]:checked').val() || 'ceo',
        currentColorScheme: $('input[name="vcard_color_scheme"]:checked').val() || 'corporate_blue',
        
        // Preview elements - streamlined
        $previewFrame: $('#template-preview-frame'),
        $previewLoading: $('.preview-loading-streamlined'),
        
        // Debounce timer
        previewTimer: null,
        
        /**
         * Initialize customizer
         */
        init: function() {
            // Debug: Check if vcardCustomizer is loaded
            console.log('vcardCustomizer object:', vcardCustomizer);
            console.log('Post ID:', vcardCustomizer.postId);
            console.log('AJAX URL:', vcardCustomizer.ajaxUrl);
            console.log('Nonce:', vcardCustomizer.nonce);
            
            this.bindEvents();
            this.loadInitialPreview();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            

            
            // Template selection - streamlined radio buttons
            $('input[name="vcard_template_name"]').on('change', function() {
                self.currentTemplate = $(this).val();
                self.updateTemplateSelection();
                self.schedulePreviewUpdate();
            });
            
            // Color scheme selection
            $(document).on('change', 'input[name="vcard_color_scheme"]', function() {
                self.currentColorScheme = $(this).val();
                self.updateColorSchemeSelection();
                self.schedulePreviewUpdate();
            });
            
            // Industry tab navigation
            $('.scheme-tab').on('click', function(e) {
                e.preventDefault();
                var industry = $(this).data('industry');
                self.switchIndustryTab(industry);
                $('#vcard_industry').val(industry).trigger('change');
            });
            
            // Preview controls
            $('#refresh-preview').on('click', function(e) {
                e.preventDefault();
                console.log('Refresh preview clicked');
                self.showStaticPreview();
            });
            
            // Test AJAX button
            $('#test-ajax').on('click', function(e) {
                e.preventDefault();
                console.log('Test AJAX button clicked');
                self.testAjax();
            });
            
            // Device toggle
            $('.device-toggle').on('click', function(e) {
                e.preventDefault();
                var device = $(this).data('device');
                self.switchPreviewDevice(device);
            });
            

            
            // Template option clicks - streamlined
            $('.template-option-streamlined').on('click', function() {
                var templateKey = $(this).data('template');
                $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            });
            
            // Color scheme option clicks - streamlined
            $(document).on('click', '.color-scheme-option-streamlined', function() {
                var schemeKey = $(this).data('scheme');
                $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            });
        },
        

        

        

        
        /**
         * Update template selection UI
         */
        updateTemplateSelection: function() {
            $('.template-option-streamlined').removeClass('selected');
            $('.template-option-streamlined[data-template="' + this.currentTemplate + '"]').addClass('selected');
        },
        
        /**
         * Update color scheme selection UI
         */
        updateColorSchemeSelection: function() {
            $('.color-scheme-option-streamlined').removeClass('selected');
            $('.color-scheme-option-streamlined[data-scheme="' + this.currentColorScheme + '"]').addClass('selected');
        },
        

        
        /**
         * Load template recommendations
         */
        loadRecommendations: function() {
            var self = this;
            
            $.ajax({
                url: vcardCustomizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vcard_get_template_recommendations',
                    nonce: vcardCustomizer.nonce,
                    industry: this.currentIndustry,
                    current_template: this.currentTemplate
                },
                success: function(response) {
                    if (response.success) {
                        self.renderRecommendations(response.data);
                    }
                },
                error: function() {
                    console.log('Error loading recommendations');
                }
            });
        },
        
        /**
         * Render recommendations
         */
        renderRecommendations: function(recommendations) {
            var $container = $('#template-recommendations');
            var html = '';
            
            if (recommendations.length > 0) {
                html += '<div class="recommendations-grid">';
                
                recommendations.forEach(function(template) {
                    var isCurrentClass = template.is_current ? ' current-template' : '';
                    var recommendedBadge = template.is_current ? '' : '<span class="recommended-badge">' + vcardCustomizer.strings.recommendedForYou + '</span>';
                    
                    html += '<div class="recommendation-item' + isCurrentClass + '">';
                    html += '<div class="recommendation-template">';
                    html += '<img src="' + vcardCustomizer.assetsUrl + 'images/templates/' + template.key + '-thumb.svg" alt="' + template.name + '" onerror="this.src=\'' + vcardCustomizer.assetsUrl + 'images/templates/default-thumb.svg\'">';
                    html += '<h6>' + template.name + '</h6>';
                    html += recommendedBadge;
                    html += '</div>';
                    
                    if (template.recommended_schemes) {
                        html += '<div class="recommendation-schemes">';
                        Object.keys(template.recommended_schemes).forEach(function(paletteKey) {
                            var palette = template.recommended_schemes[paletteKey];
                            Object.keys(palette.schemes).forEach(function(schemeKey) {
                                var scheme = palette.schemes[schemeKey];
                                html += '<div class="mini-color-palette" data-template="' + template.key + '" data-scheme="' + schemeKey + '">';
                                html += '<div class="mini-swatch" style="background-color: ' + scheme.primary + '"></div>';
                                html += '<div class="mini-swatch" style="background-color: ' + scheme.secondary + '"></div>';
                                html += '<div class="mini-swatch" style="background-color: ' + scheme.accent + '"></div>';
                                html += '</div>';
                            });
                        });
                        html += '</div>';
                    }
                    
                    html += '</div>';
                });
                
                html += '</div>';
                
                $('.recommendations-section').show();
            } else {
                $('.recommendations-section').hide();
            }
            
            $container.html(html);
            
            // Bind recommendation clicks
            this.bindRecommendationClicks();
        },
        
        /**
         * Bind recommendation click events
         */
        bindRecommendationClicks: function() {
            var self = this;
            
            // Template recommendation clicks
            $('.recommendation-item').on('click', function() {
                var templateKey = $(this).find('.mini-color-palette').first().data('template');
                if (templateKey) {
                    $('input[name="vcard_template"][value="' + templateKey + '"]').prop('checked', true).trigger('change');
                }
            });
            
            // Color scheme recommendation clicks
            $('.mini-color-palette').on('click', function(e) {
                e.stopPropagation();
                var templateKey = $(this).data('template');
                var schemeKey = $(this).data('scheme');
                
                // Set template in streamlined radio buttons
                $('input[name="vcard_template_name"][value="' + templateKey + '"]').prop('checked', true).trigger('change');
                
                // Set color scheme (need to switch to correct industry tab first)
                setTimeout(function() {
                    $('input[name="vcard_color_scheme"][value="' + schemeKey + '"]').prop('checked', true).trigger('change');
                }, 100);
            });
        },
        
        /**
         * Schedule preview update with debouncing
         */
        schedulePreviewUpdate: function() {
            var self = this;
            
            if (this.previewTimer) {
                clearTimeout(this.previewTimer);
            }
            
            this.previewTimer = setTimeout(function() {
                // Use static preview instead of AJAX
                self.showStaticPreview();
            }, 300);
        },
        
        /**
         * Load initial preview
         */
        loadInitialPreview: function() {
            var self = this;
            
            // Check if we have the required elements
            if (this.$previewFrame.length === 0) {
                console.error('Preview frame not found');
                return;
            }
            
            console.log('Loading initial preview - using static preview for reliability');
            
            // Always use static preview for now since AJAX is problematic
            setTimeout(function() {
                self.showStaticPreview('Static preview - AJAX disabled for stability');
            }, 500);
        },
        
        /**
         * Load template preview
         */
        loadPreview: function() {
            var self = this;
            
            // Show loading state
            this.$previewLoading.show();
            this.$previewFrame.hide();
            
            // Debug log
            console.log('Loading preview with:', {
                template: this.currentTemplate,
                color_scheme: this.currentColorScheme,
                post_id: vcardCustomizer.postId
            });
            
            $.ajax({
                url: vcardCustomizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vcard_preview_template',
                    nonce: vcardCustomizer.nonce,
                    post_id: vcardCustomizer.postId,
                    template: this.currentTemplate,
                    color_scheme: this.currentColorScheme
                },
                success: function(response) {
                    console.log('Preview response:', response);
                    if (response && response.success) {
                        console.log('Preview HTML length:', response.data.html.length);
                        self.renderPreview(response.data.html);
                    } else {
                        console.error('Preview error:', response);
                        var errorMsg = 'Error loading preview';
                        if (response && response.data) {
                            errorMsg = response.data;
                        }
                        self.showPreviewError(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                    var errorMsg = (vcardCustomizer.strings && vcardCustomizer.strings.previewError) || 'Error loading preview';
                    self.showPreviewError(errorMsg + ' (' + status + ')');
                },
                complete: function() {
                    self.$previewLoading.hide();
                }
            });
        },
        
        /**
         * Render preview HTML
         */
        renderPreview: function(html) {
            var self = this;
            
            // Create blob URL for the HTML content
            var blob = new Blob([html], { type: 'text/html' });
            var url = URL.createObjectURL(blob);
            
            // Load in iframe
            this.$previewFrame.attr('src', url);
            
            // Show frame when loaded
            this.$previewFrame.on('load', function() {
                self.$previewFrame.show();
                
                // Clean up blob URL after a delay
                setTimeout(function() {
                    URL.revokeObjectURL(url);
                }, 1000);
            });
        },
        
        /**
         * Show preview error
         */
        showPreviewError: function(message) {
            console.log('Showing preview error:', message);
            
            // Try to show a static preview instead of just an error
            this.showStaticPreview(message);
        },
        
        /**
         * Show static preview when AJAX fails
         */
        showStaticPreview: function(errorMessage) {
            var scheme = this.getColorSchemeColors(this.currentColorScheme);
            var templateStyle = this.getTemplateStyle(this.currentTemplate);
            
            var staticHtml = '<!DOCTYPE html><html><head><title>Template Preview</title>';
            staticHtml += '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
            staticHtml += '<style>';
            staticHtml += 'body { margin:0; padding:20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background:#f5f5f5; }';
            staticHtml += '.preview-container { max-width:' + templateStyle.maxWidth + '; margin:0 auto; background:white; border-radius:8px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.1); }';
            staticHtml += '.header { background:' + scheme.primary + '; color:white; padding:' + templateStyle.headerPadding + '; text-align:' + templateStyle.headerAlign + '; }';
            staticHtml += '.header h1 { margin:0 0 10px 0; font-size:' + templateStyle.titleSize + '; font-weight:600; }';
            staticHtml += '.header p { margin:0; opacity:0.9; font-size:' + templateStyle.subtitleSize + '; }';
            staticHtml += '.content { padding:' + templateStyle.contentPadding + '; }';
            staticHtml += '.contact-card { background:' + scheme.accent + '; padding:20px; border-radius:6px; border-left:4px solid ' + scheme.primary + '; margin-bottom:20px; }';
            staticHtml += '.contact-card h3 { margin:0 0 15px 0; color:' + scheme.primary + '; font-size:18px; }';
            staticHtml += '.contact-item { margin:8px 0; color:' + scheme.text + '; }';
            staticHtml += '.contact-item a { color:' + scheme.primary + '; text-decoration:none; }';
            staticHtml += '.contact-item a:hover { text-decoration:underline; }';
            staticHtml += '.services { display:' + templateStyle.servicesDisplay + '; gap:15px; margin-top:20px; }';
            staticHtml += '.service-item { background:white; border:1px solid ' + scheme.border + '; padding:15px; border-radius:4px; flex:1; }';
            staticHtml += '.service-item h4 { margin:0 0 8px 0; color:' + scheme.primary + '; font-size:16px; }';
            staticHtml += '.footer { background:' + scheme.secondary + '; color:white; padding:15px 20px; text-align:center; font-size:12px; }';
            staticHtml += '.template-badge { background:' + scheme.primary + '; color:white; padding:4px 8px; border-radius:12px; font-size:10px; margin-right:10px; }';
            staticHtml += '@media (max-width: 600px) { .services { flex-direction:column; } .preview-container { margin:10px; } }';
            staticHtml += '</style></head><body>';
            
            staticHtml += '<div class="preview-container">';
            staticHtml += '<div class="header">';
            staticHtml += '<h1>' + templateStyle.businessName + '</h1>';
            staticHtml += '<p>' + templateStyle.tagline + '</p>';
            staticHtml += '</div>';
            
            staticHtml += '<div class="content">';
            staticHtml += '<div class="contact-card">';
            staticHtml += '<h3>Contact Information</h3>';
            staticHtml += '<div class="contact-item"><strong>Email:</strong> <a href="mailto:contact@business.com">contact@business.com</a></div>';
            staticHtml += '<div class="contact-item"><strong>Phone:</strong> <a href="tel:5551234567">(555) 123-4567</a></div>';
            staticHtml += '<div class="contact-item"><strong>Website:</strong> <a href="#" target="_blank">www.business.com</a></div>';
            staticHtml += '<div class="contact-item"><strong>Address:</strong> 123 Business St, City, State 12345</div>';
            staticHtml += '</div>';
            
            if (templateStyle.showServices) {
                staticHtml += '<div class="services">';
                staticHtml += '<div class="service-item"><h4>Service One</h4><p>Professional service description here.</p></div>';
                staticHtml += '<div class="service-item"><h4>Service Two</h4><p>Another great service we offer.</p></div>';
                staticHtml += '<div class="service-item"><h4>Service Three</h4><p>Premium service with excellent results.</p></div>';
                staticHtml += '</div>';
            }
            
            staticHtml += '</div>';
            
            staticHtml += '<div class="footer">';
            staticHtml += '<span class="template-badge">' + this.currentTemplate.toUpperCase() + '</span>';
            staticHtml += '<span class="template-badge">' + (scheme.name || this.currentColorScheme) + '</span>';
            if (errorMessage) {
                staticHtml += '<br><small style="opacity:0.8;">Static Preview: ' + errorMessage + '</small>';
            }
            staticHtml += '</div>';
            
            staticHtml += '</div></body></html>';
            
            this.renderPreview(staticHtml);
        },
        
        /**
         * Get template-specific styling
         */
        getTemplateStyle: function(templateKey) {
            var styles = {
                'ceo': {
                    maxWidth: '800px',
                    headerPadding: '40px 30px',
                    headerAlign: 'center',
                    titleSize: '32px',
                    subtitleSize: '18px',
                    contentPadding: '40px 30px',
                    businessName: 'Executive Business',
                    tagline: 'Professional Leadership & Excellence',
                    servicesDisplay: 'flex',
                    showServices: true
                },
                'freelancer': {
                    maxWidth: '700px',
                    headerPadding: '35px 25px',
                    headerAlign: 'left',
                    titleSize: '28px',
                    subtitleSize: '16px',
                    contentPadding: '30px 25px',
                    businessName: 'Creative Professional',
                    tagline: 'Design • Development • Innovation',
                    servicesDisplay: 'grid',
                    showServices: true
                },
                'restaurant': {
                    maxWidth: '750px',
                    headerPadding: '30px 25px',
                    headerAlign: 'center',
                    titleSize: '30px',
                    subtitleSize: '16px',
                    contentPadding: '25px',
                    businessName: 'Delicious Restaurant',
                    tagline: 'Fresh • Local • Authentic Cuisine',
                    servicesDisplay: 'block',
                    showServices: false
                },
                'healthcare': {
                    maxWidth: '750px',
                    headerPadding: '35px 30px',
                    headerAlign: 'center',
                    titleSize: '26px',
                    subtitleSize: '15px',
                    contentPadding: '35px 30px',
                    businessName: 'Healthcare Professional',
                    tagline: 'Caring • Professional • Trusted',
                    servicesDisplay: 'flex',
                    showServices: true
                }
            };
            
            return styles[templateKey] || styles['ceo'];
        },
        
        /**
         * Get color scheme colors with fallbacks
         */
        getColorSchemeColors: function(schemeKey) {
            // Predefined color schemes as fallback - matching PHP scheme keys
            var colorSchemes = {
                // Professional schemes
                'corporate_blue': {
                    name: 'Corporate Blue',
                    primary: '#1e40af',
                    secondary: '#374151',
                    accent: '#f8fafc',
                    text: '#1e293b',
                    border: '#e2e8f0'
                },
                'executive_navy': {
                    name: 'Executive Navy',
                    primary: '#0f172a',
                    secondary: '#475569',
                    accent: '#f1f5f9',
                    text: '#0f172a',
                    border: '#cbd5e1'
                },
                'business_gray': {
                    name: 'Business Gray',
                    primary: '#374151',
                    secondary: '#6b7280',
                    accent: '#f9fafb',
                    text: '#111827',
                    border: '#d1d5db'
                },
                
                // Healthcare schemes
                'medical_green': {
                    name: 'Medical Green',
                    primary: '#059669',
                    secondary: '#047857',
                    accent: '#f0fdf4',
                    text: '#111827',
                    border: '#d1d5db'
                },
                'healthcare_blue': {
                    name: 'Healthcare Blue',
                    primary: '#0284c7',
                    secondary: '#0369a1',
                    accent: '#f0f9ff',
                    text: '#0c4a6e',
                    border: '#e2e8f0'
                },
                'wellness_teal': {
                    name: 'Wellness Teal',
                    primary: '#0d9488',
                    secondary: '#0f766e',
                    accent: '#f0fdfa',
                    text: '#134e4a',
                    border: '#ccfbf1'
                },
                
                // Creative schemes
                'creative_purple': {
                    name: 'Creative Purple',
                    primary: '#7c3aed',
                    secondary: '#a855f7',
                    accent: '#faf5ff',
                    text: '#1f2937',
                    border: '#e5e7eb'
                },
                'artistic_pink': {
                    name: 'Artistic Pink',
                    primary: '#ec4899',
                    secondary: '#f472b6',
                    accent: '#fdf2f8',
                    text: '#831843',
                    border: '#fce7f3'
                },
                'vibrant_orange': {
                    name: 'Vibrant Orange',
                    primary: '#ea580c',
                    secondary: '#fb923c',
                    accent: '#fff7ed',
                    text: '#9a3412',
                    border: '#fed7aa'
                },
                
                // Finance schemes
                'finance_navy': {
                    name: 'Finance Navy',
                    primary: '#1e40af',
                    secondary: '#374151',
                    accent: '#f9fafb',
                    text: '#111827',
                    border: '#d1d5db'
                },
                'investment_green': {
                    name: 'Investment Green',
                    primary: '#16a34a',
                    secondary: '#15803d',
                    accent: '#f7fee7',
                    text: '#14532d',
                    border: '#d1d5db'
                },
                'wealth_gold': {
                    name: 'Wealth Gold',
                    primary: '#d97706',
                    secondary: '#92400e',
                    accent: '#fffbeb',
                    text: '#78350f',
                    border: '#fde68a'
                },
                
                // Hospitality schemes
                'warm_orange': {
                    name: 'Warm Orange',
                    primary: '#ea580c',
                    secondary: '#92400e',
                    accent: '#fff7ed',
                    text: '#1c1917',
                    border: '#e7e5e4'
                },
                'cozy_brown': {
                    name: 'Cozy Brown',
                    primary: '#92400e',
                    secondary: '#78350f',
                    accent: '#fef7f0',
                    text: '#451a03',
                    border: '#fed7aa'
                },
                'restaurant_red': {
                    name: 'Restaurant Red',
                    primary: '#dc2626',
                    secondary: '#991b1b',
                    accent: '#fef2f2',
                    text: '#7f1d1d',
                    border: '#fecaca'
                },
                
                // Fitness schemes
                'energetic_red': {
                    name: 'Energetic Red',
                    primary: '#dc2626',
                    secondary: '#991b1b',
                    accent: '#fef2f2',
                    text: '#1f2937',
                    border: '#f3f4f6'
                },
                'active_blue': {
                    name: 'Active Blue',
                    primary: '#2563eb',
                    secondary: '#1d4ed8',
                    accent: '#eff6ff',
                    text: '#1e3a8a',
                    border: '#dbeafe'
                },
                'power_green': {
                    name: 'Power Green',
                    primary: '#16a34a',
                    secondary: '#15803d',
                    accent: '#f7fee7',
                    text: '#14532d',
                    border: '#bbf7d0'
                },
                
                // Construction schemes
                'industrial_gray': {
                    name: 'Industrial Gray',
                    primary: '#374151',
                    secondary: '#6b7280',
                    accent: '#f9fafb',
                    text: '#111827',
                    border: '#d1d5db'
                },
                'construction_orange': {
                    name: 'Construction Orange',
                    primary: '#ea580c',
                    secondary: '#c2410c',
                    accent: '#fff7ed',
                    text: '#9a3412',
                    border: '#fed7aa'
                },
                'steel_blue': {
                    name: 'Steel Blue',
                    primary: '#0f766e',
                    secondary: '#0d9488',
                    accent: '#f0fdfa',
                    text: '#134e4a',
                    border: '#99f6e4'
                },
                
                // Luxury schemes
                'luxury_gold': {
                    name: 'Luxury Gold',
                    primary: '#d97706',
                    secondary: '#92400e',
                    accent: '#fffbeb',
                    text: '#1c1917',
                    border: '#f3f4f6'
                },
                'premium_black': {
                    name: 'Premium Black',
                    primary: '#111827',
                    secondary: '#374151',
                    accent: '#f9fafb',
                    text: '#111827',
                    border: '#e5e7eb'
                },
                'elegant_purple': {
                    name: 'Elegant Purple',
                    primary: '#581c87',
                    secondary: '#7c3aed',
                    accent: '#faf5ff',
                    text: '#3b0764',
                    border: '#e9d5ff'
                }
            };
            
            // Try to get colors from vcardCustomizer if available
            if (typeof vcardCustomizer !== 'undefined' && vcardCustomizer.industryPalettes) {
                for (var industry in vcardCustomizer.industryPalettes) {
                    var schemes = vcardCustomizer.industryPalettes[industry].schemes;
                    if (schemes && schemes[schemeKey]) {
                        return schemes[schemeKey];
                    }
                }
            }
            
            // Use predefined scheme or default
            return colorSchemes[schemeKey] || colorSchemes['corporate_blue'];
        },
        
        /**
         * Switch preview device
         */
        switchPreviewDevice: function(device) {
            $('.device-toggle').removeClass('active');
            $('.device-toggle[data-device="' + device + '"]').addClass('active');
            
            this.$previewFrame.removeClass('desktop tablet mobile').addClass(device);
        },
        
        /**
         * Test AJAX connection
         */
        testAjax: function() {
            console.log('Testing AJAX connection...');
            console.log('AJAX URL:', vcardCustomizer.ajaxUrl);
            console.log('Nonce:', vcardCustomizer.nonce);
            console.log('Post ID:', vcardCustomizer.postId);
            
            $.ajax({
                url: vcardCustomizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vcard_test_preview',
                    nonce: vcardCustomizer.nonce,
                    post_id: vcardCustomizer.postId
                },
                success: function(response) {
                    console.log('Test AJAX success:', response);
                    alert('AJAX Test SUCCESS!\n\nResponse: ' + JSON.stringify(response, null, 2));
                },
                error: function(xhr, status, error) {
                    console.error('Test AJAX error:', {xhr: xhr, status: status, error: error});
                    console.log('Response text:', xhr.responseText);
                    alert('AJAX Test FAILED!\n\nStatus: ' + status + '\nError: ' + error + '\nResponse: ' + xhr.responseText);
                }
            });
        }
    };
    
    // Initialize customizer only if we're on the right page
    if ($('#vcard-template-customizer').length > 0) {
        customizer.init();
    } else {
        console.log('Template customizer not found on this page');
    }
    
    // Make customizer available globally for debugging
    window.vcardCustomizer = customizer;
});
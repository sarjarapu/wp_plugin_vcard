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
                self.loadPreview();
            });
            
            // Test AJAX button
            $('#test-ajax').on('click', function(e) {
                e.preventDefault();
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
                self.loadPreview();
            }, 500);
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
            
            // Check if we have basic data
            if (typeof vcardCustomizer === 'undefined') {
                console.error('vcardCustomizer not loaded, showing static preview');
                this.showStaticPreview('JavaScript configuration not loaded');
                return;
            }
            
            if (!vcardCustomizer.postId || vcardCustomizer.postId === 0) {
                console.error('No post ID available, showing static preview');
                this.showStaticPreview('No post ID available');
                return;
            }
            
            // Small delay to ensure DOM is ready, then try AJAX preview
            setTimeout(function() {
                self.loadPreview();
            }, 1000);
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
                    if (response.success) {
                        self.renderPreview(response.data.html);
                    } else {
                        console.error('Preview error:', response.data);
                        self.showPreviewError(response.data || (vcardCustomizer.strings && vcardCustomizer.strings.previewError) || 'Error loading preview');
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
            
            var staticHtml = '<!DOCTYPE html><html><head><title>Static Preview</title></head><body style="margin:0;padding:20px;font-family:Arial,sans-serif;background:#f5f5f5;">';
            staticHtml += '<div style="max-width:600px;margin:0 auto;background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);">';
            staticHtml += '<div style="background:' + scheme.primary + ';color:white;padding:30px 20px;text-align:center;">';
            staticHtml += '<h1 style="margin:0 0 10px 0;font-size:28px;">Sample Business</h1>';
            staticHtml += '<p style="margin:0;opacity:0.9;">Your business tagline here</p>';
            staticHtml += '</div>';
            staticHtml += '<div style="padding:30px 20px;">';
            staticHtml += '<div style="background:' + scheme.accent + ';padding:20px;border-radius:6px;border-left:4px solid ' + scheme.primary + ';">';
            staticHtml += '<h3 style="margin:0 0 15px 0;color:' + scheme.primary + ';">Contact Information</h3>';
            staticHtml += '<p style="margin:8px 0;"><strong>Email:</strong> <a href="#" style="color:' + scheme.primary + ';">contact@business.com</a></p>';
            staticHtml += '<p style="margin:8px 0;"><strong>Phone:</strong> <a href="#" style="color:' + scheme.primary + ';">(555) 123-4567</a></p>';
            staticHtml += '<p style="margin:8px 0;"><strong>Website:</strong> <a href="#" style="color:' + scheme.primary + ';">www.business.com</a></p>';
            staticHtml += '</div>';
            if (errorMessage) {
                staticHtml += '<div style="margin-top:20px;padding:15px;background:#fff3cd;border:1px solid #ffeaa7;border-radius:4px;color:#856404;">';
                staticHtml += '<strong>Preview Note:</strong> ' + errorMessage + '<br><small>Showing static preview instead.</small>';
                staticHtml += '</div>';
            }
            staticHtml += '</div>';
            staticHtml += '<div style="background:' + scheme.secondary + ';color:white;padding:15px 20px;text-align:center;font-size:12px;">';
            staticHtml += 'Template: ' + this.currentTemplate + ' | Color: ' + this.currentColorScheme;
            staticHtml += '</div>';
            staticHtml += '</div></body></html>';
            
            this.renderPreview(staticHtml);
        },
        
        /**
         * Get color scheme colors with fallbacks
         */
        getColorSchemeColors: function(schemeKey) {
            // Default colors as fallback
            var defaultColors = {
                primary: '#2271b1',
                secondary: '#646970',
                accent: '#f6f7f7',
                text: '#1d2327'
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
            
            return defaultColors;
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
                    alert('AJAX Test Result: ' + JSON.stringify(response, null, 2));
                },
                error: function(xhr, status, error) {
                    console.error('Test AJAX error:', {xhr: xhr, status: status, error: error});
                    alert('AJAX Test Failed: ' + status + ' - ' + error);
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
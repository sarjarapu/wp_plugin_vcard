/**
 * Template Customizer JavaScript
 * Handles real-time preview, color scheme selection, and template recommendations
 */

jQuery(document).ready(function($) {
    
    var customizer = {
        
        // Current selections - use existing template dropdown
        currentTemplate: $('#vcard_template_name').val() || 'ceo',
        currentColorScheme: $('input[name="vcard_color_scheme"]:checked').val() || 'corporate_blue',
        currentIndustry: $('#vcard_industry').val() || 'business',
        
        // Preview elements (support both full and compact)
        $previewFrame: $('#template-preview-frame'),
        $previewLoading: $('.preview-loading, .preview-loading-compact'),
        
        // Debounce timer
        previewTimer: null,
        
        /**
         * Initialize customizer
         */
        init: function() {
            this.bindEvents();
            this.initIndustryTabs();
            this.loadRecommendations();
            this.loadInitialPreview();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Industry selection
            $('#vcard_industry').on('change', function() {
                self.currentIndustry = $(this).val();
                self.switchIndustryTab(self.currentIndustry);
                self.loadRecommendations();
                self.updateColorSchemeVisibility();
                self.schedulePreviewUpdate();
            });
            
            // Template selection - use existing dropdown
            $('#vcard_template_name').on('change', function() {
                self.currentTemplate = $(this).val();
                self.loadRecommendations();
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
            
            // Device toggle
            $('.device-toggle').on('click', function(e) {
                e.preventDefault();
                var device = $(this).data('device');
                self.switchPreviewDevice(device);
            });
            

            
            // Color scheme option clicks (both full and compact)
            $(document).on('click', '.color-scheme-option, .color-scheme-option-compact', function() {
                var schemeKey = $(this).data('scheme');
                $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            });
        },
        
        /**
         * Initialize industry tabs
         */
        initIndustryTabs: function() {
            this.switchIndustryTab(this.currentIndustry);
            this.updateColorSchemeVisibility();
        },
        
        /**
         * Switch industry tab
         */
        switchIndustryTab: function(industry) {
            // Update tab navigation (if exists)
            $('.scheme-tab').removeClass('active');
            $('.scheme-tab[data-industry="' + industry + '"]').addClass('active');
            
            // Update tab content (both full and compact)
            $('.scheme-panel, .scheme-panel-inline').removeClass('active');
            $('.scheme-panel[data-industry="' + industry + '"], .scheme-panel-inline[data-industry="' + industry + '"]').addClass('active');
            
            // Auto-select first color scheme if current one is not available
            var $activePanel = $('.scheme-panel[data-industry="' + industry + '"], .scheme-panel-inline[data-industry="' + industry + '"]');
            var $currentScheme = $activePanel.find('input[value="' + this.currentColorScheme + '"]');
            
            if ($currentScheme.length === 0) {
                var $firstScheme = $activePanel.find('input[name="vcard_color_scheme"]:first');
                if ($firstScheme.length > 0) {
                    $firstScheme.prop('checked', true);
                    this.currentColorScheme = $firstScheme.val();
                    this.updateColorSchemeSelection();
                }
            }
        },
        

        
        /**
         * Update color scheme selection UI
         */
        updateColorSchemeSelection: function() {
            $('.color-scheme-option, .color-scheme-option-compact').removeClass('selected');
            $('.color-scheme-option[data-scheme="' + this.currentColorScheme + '"], .color-scheme-option-compact[data-scheme="' + this.currentColorScheme + '"]').addClass('selected');
        },
        
        /**
         * Update color scheme visibility based on industry
         */
        updateColorSchemeVisibility: function() {
            // This is handled by the tab switching, but we can add additional logic here
            // For example, highlighting recommended schemes
            this.highlightRecommendedSchemes();
        },
        
        /**
         * Highlight recommended color schemes
         */
        highlightRecommendedSchemes: function() {
            var recommendations = vcardCustomizer.templateRecommendations[this.currentIndustry];
            if (!recommendations) return;
            
            // Remove existing recommendations
            $('.color-scheme-option').removeClass('recommended');
            
            // Add recommended class to relevant schemes
            var $activePanel = $('.scheme-panel.active');
            recommendations.palettes.forEach(function(paletteKey) {
                if (vcardCustomizer.industryPalettes[paletteKey]) {
                    Object.keys(vcardCustomizer.industryPalettes[paletteKey].schemes).forEach(function(schemeKey) {
                        $activePanel.find('.color-scheme-option[data-scheme="' + schemeKey + '"]').addClass('recommended');
                    });
                }
            });
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
                
                // Set template in existing dropdown
                $('#vcard_template_name').val(templateKey).trigger('change');
                
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
            
            // Small delay to ensure DOM is ready
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
                    if (response.success) {
                        self.renderPreview(response.data.html);
                    } else {
                        self.showPreviewError(response.data || vcardCustomizer.strings.previewError);
                    }
                },
                error: function() {
                    self.showPreviewError(vcardCustomizer.strings.previewError);
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
            var errorHtml = '<div style="padding: 40px; text-align: center; color: #666;">';
            errorHtml += '<p>' + message + '</p>';
            errorHtml += '<button type="button" onclick="location.reload()" style="margin-top: 10px; padding: 8px 16px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">Retry</button>';
            errorHtml += '</div>';
            
            this.renderPreview('<!DOCTYPE html><html><head><title>Preview Error</title></head><body>' + errorHtml + '</body></html>');
        },
        
        /**
         * Switch preview device
         */
        switchPreviewDevice: function(device) {
            $('.device-toggle').removeClass('active');
            $('.device-toggle[data-device="' + device + '"]').addClass('active');
            
            this.$previewFrame.removeClass('desktop tablet mobile').addClass(device);
        }
    };
    
    // Initialize customizer
    customizer.init();
    
    // Make customizer available globally for debugging
    window.vcardCustomizer = customizer;
});
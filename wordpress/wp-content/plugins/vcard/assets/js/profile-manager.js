/**
 * vCard Profile Manager JavaScript
 * 
 * Handles dynamic profile management functionality including
 * services/products CRUD operations and gallery management.
 */

(function($) {
    'use strict';
    
    window.vCardManager = {
        
        /**
         * Initialize profile manager
         */
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.initTabSwitching();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Service management
            $(document).on('click', '.add-service-btn', function() {
                self.addService();
            });
            
            // Product management
            $(document).on('click', '.add-product-btn', function() {
                self.addProduct();
            });
            
            // Gallery management
            $(document).on('click', '.add-gallery-images-btn, .add-first-image-btn', function() {
                self.addGalleryImages();
            });
            
            // Copy URL functionality
            $(document).on('click', '.copy-url-btn', function() {
                self.copyUrl($(this).data('url'));
            });
            
            // Tab switching for meta boxes
            $(document).on('click', '.nav-tab', function(e) {
                e.preventDefault();
                self.switchTab($(this));
            });
        },
        
        /**
         * Initialize sortable functionality for gallery
         */
        initSortable: function() {
            $('#gallery-grid').sortable({
                items: '.gallery-item',
                cursor: 'move',
                opacity: 0.8,
                placeholder: 'gallery-placeholder',
                update: function(event, ui) {
                    vCardManager.updateGalleryOrder();
                }
            });
        },
        
        /**
         * Initialize tab switching
         */
        initTabSwitching: function() {
            // Show first tab by default
            $('.nav-tab-wrapper .nav-tab:first').addClass('nav-tab-active');
            $('.tab-content:first').addClass('active');
        },
        
        /**
         * Switch between tabs
         */
        switchTab: function($tab) {
            var target = $tab.attr('href');
            
            // Update tab states
            $tab.siblings().removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Update content visibility
            $('.tab-content').removeClass('active');
            $(target).addClass('active');
        },
        
        /**
         * Add new service
         */
        addService: function() {
            var $servicesList = $('#services-list');
            var $noServicesMessage = $('.no-services-message');
            var template = $('#service-template').html();
            var index = Date.now(); // Use timestamp as unique index
            
            // Replace template placeholders
            template = template.replace(/\{\{INDEX\}\}/g, index);
            
            // Hide no services message
            $noServicesMessage.hide();
            
            // Add new service
            $servicesList.append(template);
            
            // Focus on name field
            $servicesList.find('.service-item:last .service-field input:first').focus();
        },
        
        /**
         * Remove service
         */
        removeService: function(button) {
            if (confirm(vCardManager.strings.confirmRemove)) {
                var $serviceItem = $(button).closest('.service-item');
                var $servicesList = $('#services-list');
                
                $serviceItem.fadeOut(300, function() {
                    $(this).remove();
                    
                    // Show no services message if empty
                    if ($servicesList.find('.service-item').length === 0) {
                        $('.no-services-message').show();
                    }
                });
            }
        },
        
        /**
         * Update service title when name changes
         */
        updateServiceTitle: function(input) {
            var $serviceItem = $(input).closest('.service-item');
            var $title = $serviceItem.find('.service-title');
            var name = $(input).val().trim();
            
            $title.text(name || vCardManager.strings.newService || 'New Service');
        },
        
        /**
         * Add new product
         */
        addProduct: function() {
            var $productsList = $('#products-list');
            var $noProductsMessage = $('.no-products-message');
            var template = $('#product-template').html();
            var index = Date.now(); // Use timestamp as unique index
            
            // Replace template placeholders
            template = template.replace(/\{\{INDEX\}\}/g, index);
            
            // Hide no products message
            $noProductsMessage.hide();
            
            // Add new product
            $productsList.append(template);
            
            // Focus on name field
            $productsList.find('.product-item:last .product-field input:first').focus();
        },
        
        /**
         * Remove product
         */
        removeProduct: function(button) {
            if (confirm(vCardManager.strings.confirmRemove)) {
                var $productItem = $(button).closest('.product-item');
                var $productsList = $('#products-list');
                
                $productItem.fadeOut(300, function() {
                    $(this).remove();
                    
                    // Show no products message if empty
                    if ($productsList.find('.product-item').length === 0) {
                        $('.no-products-message').show();
                    }
                });
            }
        },
        
        /**
         * Update product title when name changes
         */
        updateProductTitle: function(input) {
            var $productItem = $(input).closest('.product-item');
            var $title = $productItem.find('.product-title');
            var name = $(input).val().trim();
            
            $title.text(name || vCardManager.strings.newProduct || 'New Product');
        },
        
        /**
         * Select service image
         */
        selectServiceImage: function(button) {
            var $button = $(button);
            var $container = $button.closest('.image-upload-container');
            var $input = $container.find('.service-image-id');
            var $preview = $container.find('.image-preview');
            
            var mediaUploader = wp.media({
                title: 'Select Service Image',
                button: {
                    text: 'Use This Image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                
                $input.val(attachment.id);
                $preview.html('<img src="' + attachment.sizes.thumbnail.url + '" alt="">');
                $button.text('Change Image');
                
                // Add remove button if not exists
                if (!$container.find('.remove-image-btn').length) {
                    $button.after('<button type="button" class="button remove-image-btn" onclick="vCardManager.removeServiceImage(this)">Remove</button>');
                }
            });
            
            mediaUploader.open();
        },
        
        /**
         * Remove service image
         */
        removeServiceImage: function(button) {
            var $button = $(button);
            var $container = $button.closest('.image-upload-container');
            var $input = $container.find('.service-image-id');
            var $preview = $container.find('.image-preview');
            var $selectBtn = $container.find('.select-image-btn');
            
            $input.val('');
            $preview.empty();
            $selectBtn.text('Select Image');
            $button.remove();
        },
        
        /**
         * Select product images
         */
        selectProductImages: function(button) {
            var $button = $(button);
            var $container = $button.closest('.product-images-container');
            var $input = $container.find('.product-images-input');
            var $grid = $container.find('.product-images-grid');
            
            var mediaUploader = wp.media({
                title: 'Select Product Images',
                button: {
                    text: 'Use These Images'
                },
                multiple: true,
                library: {
                    type: 'image'
                }
            });
            
            mediaUploader.on('select', function() {
                var attachments = mediaUploader.state().get('selection').toJSON();
                var currentIds = $input.val() ? $input.val().split(',') : [];
                
                attachments.forEach(function(attachment) {
                    if (currentIds.indexOf(attachment.id.toString()) === -1) {
                        currentIds.push(attachment.id);
                        
                        var imageHtml = '<div class="product-image-item" data-id="' + attachment.id + '">' +
                            '<img src="' + attachment.sizes.thumbnail.url + '" alt="">' +
                            '<button type="button" class="remove-product-image-btn" onclick="vCardManager.removeProductImage(this)" title="Remove Image">×</button>' +
                            '</div>';
                        
                        $grid.append(imageHtml);
                    }
                });
                
                $input.val(currentIds.join(','));
            });
            
            mediaUploader.open();
        },
        
        /**
         * Remove product image
         */
        removeProductImage: function(button) {
            var $button = $(button);
            var $imageItem = $button.closest('.product-image-item');
            var $container = $button.closest('.product-images-container');
            var $input = $container.find('.product-images-input');
            var imageId = $imageItem.data('id').toString();
            
            // Remove from input value
            var currentIds = $input.val() ? $input.val().split(',') : [];
            var index = currentIds.indexOf(imageId);
            if (index > -1) {
                currentIds.splice(index, 1);
            }
            $input.val(currentIds.join(','));
            
            // Remove from DOM
            $imageItem.fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        /**
         * Add gallery images
         */
        addGalleryImages: function() {
            var mediaUploader = wp.media({
                title: 'Select Gallery Images',
                button: {
                    text: 'Add to Gallery'
                },
                multiple: true,
                library: {
                    type: 'image'
                }
            });
            
            mediaUploader.on('select', function() {
                var attachments = mediaUploader.state().get('selection').toJSON();
                var $input = $('#vcard-gallery-input');
                var $grid = $('#gallery-grid');
                var $noImagesMessage = $('#no-images-message');
                var currentIds = $input.val() ? $input.val().split(',') : [];
                
                attachments.forEach(function(attachment) {
                    if (currentIds.indexOf(attachment.id.toString()) === -1) {
                        currentIds.push(attachment.id);
                        
                        var imageHtml = '<div class="gallery-item" data-id="' + attachment.id + '">' +
                            '<img src="' + attachment.sizes.thumbnail.url + '" alt="">' +
                            '<div class="gallery-item-actions">' +
                            '<button type="button" class="gallery-action-btn remove-image-btn" onclick="vCardManager.removeGalleryImage(this)" title="Remove Image">×</button>' +
                            '</div>' +
                            '</div>';
                        
                        $grid.append(imageHtml);
                    }
                });
                
                $input.val(currentIds.join(','));
                $noImagesMessage.hide();
            });
            
            mediaUploader.open();
        },
        
        /**
         * Remove gallery image
         */
        removeGalleryImage: function(button) {
            var $button = $(button);
            var $galleryItem = $button.closest('.gallery-item');
            var $input = $('#vcard-gallery-input');
            var $grid = $('#gallery-grid');
            var $noImagesMessage = $('#no-images-message');
            var imageId = $galleryItem.data('id').toString();
            
            // Remove from input value
            var currentIds = $input.val() ? $input.val().split(',') : [];
            var index = currentIds.indexOf(imageId);
            if (index > -1) {
                currentIds.splice(index, 1);
            }
            $input.val(currentIds.join(','));
            
            // Remove from DOM
            $galleryItem.fadeOut(300, function() {
                $(this).remove();
                
                // Show no images message if empty
                if ($grid.find('.gallery-item').length === 0) {
                    $noImagesMessage.show();
                }
            });
        },
        
        /**
         * Update gallery order after sorting
         */
        updateGalleryOrder: function() {
            var $input = $('#vcard-gallery-input');
            var $items = $('#gallery-grid .gallery-item');
            var ids = [];
            
            $items.each(function() {
                ids.push($(this).data('id'));
            });
            
            $input.val(ids.join(','));
        },
        
        /**
         * Copy URL to clipboard
         */
        copyUrl: function(url) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(function() {
                    vCardManager.showNotice(vCardManager.strings.urlCopied || 'URL copied to clipboard!', 'success');
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = url;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    vCardManager.showNotice(vCardManager.strings.urlCopied || 'URL copied to clipboard!', 'success');
                } catch (err) {
                    vCardManager.showNotice('Failed to copy URL', 'error');
                }
                
                document.body.removeChild(textArea);
            }
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },
        
        /**
         * Generate QR Code
         */
        generateQR: function(postId) {
            // This would integrate with QR code generation functionality
            // For now, show a placeholder
            alert('QR Code generation will be implemented in a future task.');
        },
        
        /**
         * Export vCard
         */
        exportVCard: function(postId) {
            // This would integrate with vCard export functionality
            // For now, show a placeholder
            alert('vCard export will be implemented in a future task.');
        },
        
        /**
         * Duplicate profile
         */
        duplicateProfile: function(postId) {
            if (confirm('Are you sure you want to duplicate this profile?')) {
                // This would integrate with profile duplication functionality
                alert('Profile duplication will be implemented in a future task.');
            }
        },
        
        /**
         * Reset analytics
         */
        resetAnalytics: function(postId) {
            if (confirm('Are you sure you want to reset all analytics data for this profile?')) {
                $.ajax({
                    url: vCardManager.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vcard_reset_analytics',
                        post_id: postId,
                        nonce: vCardManager.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data || 'Failed to reset analytics');
                        }
                    },
                    error: function() {
                        alert('An error occurred while resetting analytics');
                    }
                });
            }
        },
        
        /**
         * Preview template
         */
        previewTemplate: function(postId) {
            var url = vCardManager.getProfileUrl(postId);
            if (url) {
                window.open(url, '_blank');
            }
        },
        
        /**
         * Change template
         */
        changeTemplate: function(postId) {
            // This would open a template selection modal
            alert('Template changing interface will be implemented in a future task.');
        },
        
        /**
         * Get profile URL
         */
        getProfileUrl: function(postId) {
            // This would need to be passed from PHP or retrieved via AJAX
            return null;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        vCardManager.init();
    });
    
})(jQuery);
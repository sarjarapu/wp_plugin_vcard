/**
 * Enhanced Admin Meta Box JavaScript
 * Handles tabbed interface, repeatable fields, and media library integration
 */

jQuery(document).ready(function($) {
    
    // Tab switching functionality
    $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs and content
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        var target = $(this).attr('href');
        $(target).addClass('active');
    });
    
    // Services management
    var serviceIndex = $('.service-item').length;
    
    $('.add-service').on('click', function(e) {
        e.preventDefault();
        
        var serviceHtml = `
            <div class="service-item">
                <div class="service-header">
                    <h5>${vcardAdmin.strings.service} #${serviceIndex + 1}</h5>
                    <a href="#" class="remove-service remove-item">${vcardAdmin.strings.remove}</a>
                </div>
                <div class="service-fields">
                    <div class="field-row">
                        <div class="field-col">
                            <label>${vcardAdmin.strings.serviceName}:</label>
                            <input type="text" name="vcard_services[${serviceIndex}][name]" class="regular-text" required>
                        </div>
                        <div class="field-col">
                            <label>${vcardAdmin.strings.servicePrice}:</label>
                            <input type="text" name="vcard_services[${serviceIndex}][price]" class="regular-text" placeholder="$0.00">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-col">
                            <label>${vcardAdmin.strings.serviceCategory}:</label>
                            <input type="text" name="vcard_services[${serviceIndex}][category]" class="regular-text">
                        </div>
                        <div class="field-col">
                            <label>${vcardAdmin.strings.serviceDuration}:</label>
                            <input type="text" name="vcard_services[${serviceIndex}][duration]" class="regular-text" placeholder="60 min">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-col full-width">
                            <label>${vcardAdmin.strings.serviceDescription}:</label>
                            <textarea name="vcard_services[${serviceIndex}][description]" rows="3" class="large-text"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('.services-list').append(serviceHtml);
        serviceIndex++;
        updateServiceNumbers();
    });
    
    // Remove service
    $(document).on('click', '.remove-service', function(e) {
        e.preventDefault();
        $(this).closest('.service-item').remove();
        updateServiceNumbers();
    });
    
    function updateServiceNumbers() {
        $('.service-item').each(function(index) {
            $(this).find('h5').text(vcardAdmin.strings.service + ' #' + (index + 1));
        });
    }
    
    // Products management
    var productIndex = $('.product-item').length;
    
    $('.add-product').on('click', function(e) {
        e.preventDefault();
        
        var productHtml = `
            <div class="product-item">
                <div class="product-header">
                    <h5>${vcardAdmin.strings.product} #${productIndex + 1}</h5>
                    <a href="#" class="remove-product remove-item">${vcardAdmin.strings.remove}</a>
                </div>
                <div class="product-fields">
                    <div class="field-row">
                        <div class="field-col">
                            <label>${vcardAdmin.strings.productName}:</label>
                            <input type="text" name="vcard_products[${productIndex}][name]" class="regular-text" required>
                        </div>
                        <div class="field-col">
                            <label>${vcardAdmin.strings.productPrice}:</label>
                            <input type="text" name="vcard_products[${productIndex}][price]" class="regular-text" placeholder="$0.00">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-col">
                            <label>${vcardAdmin.strings.productCategory}:</label>
                            <input type="text" name="vcard_products[${productIndex}][category]" class="regular-text">
                        </div>
                        <div class="field-col">
                            <label>${vcardAdmin.strings.productSKU}:</label>
                            <input type="text" name="vcard_products[${productIndex}][sku]" class="regular-text">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-col">
                            <label>
                                <input type="checkbox" name="vcard_products[${productIndex}][in_stock]" value="1">
                                ${vcardAdmin.strings.inStock}
                            </label>
                        </div>
                        <div class="field-col">
                            <label>
                                <input type="checkbox" name="vcard_products[${productIndex}][featured]" value="1">
                                ${vcardAdmin.strings.featured}
                            </label>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-col full-width">
                            <label>${vcardAdmin.strings.productDescription}:</label>
                            <textarea name="vcard_products[${productIndex}][description]" rows="3" class="large-text"></textarea>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-col full-width">
                            <label>${vcardAdmin.strings.productImage}:</label>
                            <div class="product-image-container">
                                <input type="hidden" name="vcard_products[${productIndex}][image_id]" class="product-image-id">
                                <div class="product-image-preview"></div>
                                <button type="button" class="button select-product-image">${vcardAdmin.strings.selectImage}</button>
                                <button type="button" class="button remove-product-image" style="display:none;">${vcardAdmin.strings.removeImage}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('.products-list').append(productHtml);
        productIndex++;
        updateProductNumbers();
    });
    
    // Remove product
    $(document).on('click', '.remove-product', function(e) {
        e.preventDefault();
        $(this).closest('.product-item').remove();
        updateProductNumbers();
    });
    
    function updateProductNumbers() {
        $('.product-item').each(function(index) {
            $(this).find('h5').text(vcardAdmin.strings.product + ' #' + (index + 1));
        });
    }
    
    // Gallery management
    var galleryFrame;
    var currentGalleryContainer;
    
    $('.add-gallery-image').on('click', function(e) {
        e.preventDefault();
        currentGalleryContainer = $(this).siblings('.gallery-images');
        
        if (galleryFrame) {
            galleryFrame.open();
            return;
        }
        
        galleryFrame = wp.media({
            title: vcardAdmin.strings.selectGalleryImages,
            button: {
                text: vcardAdmin.strings.addToGallery
            },
            multiple: true,
            library: {
                type: 'image'
            }
        });
        
        galleryFrame.on('select', function() {
            var selection = galleryFrame.state().get('selection');
            var galleryIds = currentGalleryContainer.find('.gallery-ids').val();
            var ids = galleryIds ? galleryIds.split(',') : [];
            
            selection.map(function(attachment) {
                attachment = attachment.toJSON();
                if (ids.indexOf(attachment.id.toString()) === -1) {
                    ids.push(attachment.id);
                    
                    var imageHtml = `
                        <div class="gallery-image" data-id="${attachment.id}">
                            <img src="${attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url}" alt="">
                            <div class="gallery-image-actions">
                                <button type="button" class="remove-gallery-image">&times;</button>
                            </div>
                        </div>
                    `;
                    
                    currentGalleryContainer.find('.gallery-grid').append(imageHtml);
                }
            });
            
            currentGalleryContainer.find('.gallery-ids').val(ids.join(','));
        });
        
        galleryFrame.open();
    });
    
    // Remove gallery image
    $(document).on('click', '.remove-gallery-image', function(e) {
        e.preventDefault();
        var imageContainer = $(this).closest('.gallery-image');
        var imageId = imageContainer.data('id');
        var galleryContainer = imageContainer.closest('.gallery-images');
        var galleryIds = galleryContainer.find('.gallery-ids').val();
        
        if (galleryIds) {
            var ids = galleryIds.split(',');
            ids = ids.filter(function(id) {
                return id !== imageId.toString();
            });
            galleryContainer.find('.gallery-ids').val(ids.join(','));
        }
        
        imageContainer.remove();
    });
    
    // Product image selection
    var productImageFrame;
    var currentProductImageContainer;
    
    $(document).on('click', '.select-product-image', function(e) {
        e.preventDefault();
        currentProductImageContainer = $(this).closest('.product-image-container');
        
        if (productImageFrame) {
            productImageFrame.open();
            return;
        }
        
        productImageFrame = wp.media({
            title: vcardAdmin.strings.selectProductImage,
            button: {
                text: vcardAdmin.strings.selectImage
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        productImageFrame.on('select', function() {
            var attachment = productImageFrame.state().get('selection').first().toJSON();
            
            currentProductImageContainer.find('.product-image-id').val(attachment.id);
            currentProductImageContainer.find('.product-image-preview').html(
                '<img src="' + (attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) + '" alt="">'
            );
            currentProductImageContainer.find('.select-product-image').hide();
            currentProductImageContainer.find('.remove-product-image').show();
        });
        
        productImageFrame.open();
    });
    
    // Remove product image
    $(document).on('click', '.remove-product-image', function(e) {
        e.preventDefault();
        var container = $(this).closest('.product-image-container');
        container.find('.product-image-id').val('');
        container.find('.product-image-preview').empty();
        container.find('.select-product-image').show();
        container.find('.remove-product-image').hide();
    });
    
    // Business hours toggle
    $(document).on('change', '.business-hours-day input[type="checkbox"]', function() {
        var dayContainer = $(this).closest('.business-hours-day');
        var timeInputs = dayContainer.find('.time-input');
        
        if ($(this).is(':checked')) {
            timeInputs.prop('disabled', true).addClass('disabled');
        } else {
            timeInputs.prop('disabled', false).removeClass('disabled');
        }
    });
    
    // Initialize business hours state
    $('.business-hours-day input[type="checkbox"]:checked').each(function() {
        var dayContainer = $(this).closest('.business-hours-day');
        dayContainer.find('.time-input').prop('disabled', true).addClass('disabled');
    });
    
    // Logo and cover image selection
    var logoFrame, coverFrame;
    
    $('.select-business-logo').on('click', function(e) {
        e.preventDefault();
        
        if (logoFrame) {
            logoFrame.open();
            return;
        }
        
        logoFrame = wp.media({
            title: vcardAdmin.strings.selectBusinessLogo,
            button: {
                text: vcardAdmin.strings.selectImage
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        logoFrame.on('select', function() {
            var attachment = logoFrame.state().get('selection').first().toJSON();
            $('#vcard_business_logo').val(attachment.id);
            $('.business-logo-preview').html('<img src="' + (attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) + '" alt="">');
            $('.select-business-logo').hide();
            $('.remove-business-logo').show();
        });
        
        logoFrame.open();
    });
    
    $('.remove-business-logo').on('click', function(e) {
        e.preventDefault();
        $('#vcard_business_logo').val('');
        $('.business-logo-preview').empty();
        $('.select-business-logo').show();
        $('.remove-business-logo').hide();
    });
    
    $('.select-cover-image').on('click', function(e) {
        e.preventDefault();
        
        if (coverFrame) {
            coverFrame.open();
            return;
        }
        
        coverFrame = wp.media({
            title: vcardAdmin.strings.selectCoverImage,
            button: {
                text: vcardAdmin.strings.selectImage
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        coverFrame.on('select', function() {
            var attachment = coverFrame.state().get('selection').first().toJSON();
            $('#vcard_cover_image').val(attachment.id);
            $('.cover-image-preview').html('<img src="' + (attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url) + '" alt="">');
            $('.select-cover-image').hide();
            $('.remove-cover-image').show();
        });
        
        coverFrame.open();
    });
    
    $('.remove-cover-image').on('click', function(e) {
        e.preventDefault();
        $('#vcard_cover_image').val('');
        $('.cover-image-preview').empty();
        $('.select-cover-image').show();
        $('.remove-cover-image').hide();
    });
    
    // Form validation
    $('form#post').on('submit', function(e) {
        var errors = [];
        
        // Check if it's a business profile
        var isBusinessProfile = $('#vcard_business_name').val().trim() !== '' || 
                               $('.service-item').length > 0 || 
                               $('.product-item').length > 0;
        
        if (isBusinessProfile) {
            // Business profile validation
            if ($('#vcard_business_name').val().trim() === '') {
                errors.push(vcardAdmin.strings.businessNameRequired);
            }
        } else {
            // Personal vCard validation
            if ($('#vcard_first_name').val().trim() === '' || $('#vcard_last_name').val().trim() === '') {
                errors.push(vcardAdmin.strings.nameRequired);
            }
        }
        
        // Email validation
        var email = $('#vcard_email').val().trim();
        if (email === '') {
            errors.push(vcardAdmin.strings.emailRequired);
        } else if (!isValidEmail(email)) {
            errors.push(vcardAdmin.strings.emailInvalid);
        }
        
        // Service validation
        $('.service-item').each(function(index) {
            var serviceName = $(this).find('input[name*="[name]"]').val().trim();
            if (serviceName === '') {
                errors.push(vcardAdmin.strings.serviceNameRequired.replace('%d', index + 1));
            }
        });
        
        // Product validation
        $('.product-item').each(function(index) {
            var productName = $(this).find('input[name*="[name]"]').val().trim();
            if (productName === '') {
                errors.push(vcardAdmin.strings.productNameRequired.replace('%d', index + 1));
            }
        });
        
        if (errors.length > 0) {
            e.preventDefault();
            alert(vcardAdmin.strings.validationErrors + '\n\n' + errors.join('\n'));
            return false;
        }
    });
    
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Make gallery sortable
    if ($.fn.sortable) {
        $('.gallery-grid').sortable({
            items: '.gallery-image',
            cursor: 'move',
            update: function() {
                var ids = [];
                $(this).find('.gallery-image').each(function() {
                    ids.push($(this).data('id'));
                });
                $(this).siblings('.gallery-ids').val(ids.join(','));
            }
        });
    }
});
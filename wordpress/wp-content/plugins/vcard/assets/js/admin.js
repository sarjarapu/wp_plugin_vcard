jQuery(document).ready(function($) {
    
    // Tab functionality
    $('.vcard-meta-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Remove active class from all tabs and content
        $('.vcard-meta-tabs .nav-tab').removeClass('nav-tab-active');
        $('.vcard-meta-tabs .tab-content').removeClass('active');
        
        // Add active class to clicked tab and corresponding content
        $(this).addClass('nav-tab-active');
        $(target).addClass('active');
        
        // Store active tab in localStorage
        localStorage.setItem('vcard_active_tab', target);
    });
    
    // Restore active tab from localStorage
    var activeTab = localStorage.getItem('vcard_active_tab');
    if (activeTab && $(activeTab).length) {
        $('.vcard-meta-tabs .nav-tab[href="' + activeTab + '"]').trigger('click');
    }
    
    // Services management
    var serviceIndex = $('.service-item').length;
    
    $('.add-service').on('click', function(e) {
        e.preventDefault();
        
        var serviceHtml = `
            <div class="service-item">
                <a href="#" class="remove-service remove-item">Remove</a>
                <h5>Service #${serviceIndex + 1}</h5>
                <p>
                    <label>Name:</label><br>
                    <input type="text" name="vcard_services[${serviceIndex}][name]" value="" class="regular-text">
                </p>
                <p>
                    <label>Description:</label><br>
                    <textarea name="vcard_services[${serviceIndex}][description]" rows="3" class="large-text"></textarea>
                </p>
                <p>
                    <label>Price:</label><br>
                    <input type="text" name="vcard_services[${serviceIndex}][price]" value="" class="regular-text">
                </p>
                <p>
                    <label>Category:</label><br>
                    <input type="text" name="vcard_services[${serviceIndex}][category]" value="" class="regular-text">
                </p>
            </div>
        `;
        
        $('.services-list').append(serviceHtml);
        serviceIndex++;
    });
    
    // Remove service
    $(document).on('click', '.remove-service', function(e) {
        e.preventDefault();
        $(this).closest('.service-item').remove();
    });
    
    // Products management
    var productIndex = $('.product-item').length;
    
    $('.add-product').on('click', function(e) {
        e.preventDefault();
        
        var productHtml = `
            <div class="product-item">
                <a href="#" class="remove-product remove-item">Remove</a>
                <h5>Product #${productIndex + 1}</h5>
                <p>
                    <label>Name:</label><br>
                    <input type="text" name="vcard_products[${productIndex}][name]" value="" class="regular-text">
                </p>
                <p>
                    <label>Description:</label><br>
                    <textarea name="vcard_products[${productIndex}][description]" rows="3" class="large-text"></textarea>
                </p>
                <p>
                    <label>Price:</label><br>
                    <input type="text" name="vcard_products[${productIndex}][price]" value="" class="regular-text">
                </p>
                <p>
                    <label>Category:</label><br>
                    <input type="text" name="vcard_products[${productIndex}][category]" value="" class="regular-text">
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="vcard_products[${productIndex}][in_stock]" value="1">
                        In Stock
                    </label>
                </p>
            </div>
        `;
        
        $('.products-list').append(productHtml);
        productIndex++;
    });
    
    // Remove product
    $(document).on('click', '.remove-product', function(e) {
        e.preventDefault();
        $(this).closest('.product-item').remove();
    });
    
    // Business hours - toggle time inputs when closed checkbox is checked
    $(document).on('change', '.business-hours-day input[type="checkbox"]', function() {
        var timeInputs = $(this).siblings('.time-input');
        if ($(this).is(':checked')) {
            timeInputs.prop('disabled', true).css('opacity', '0.5');
        } else {
            timeInputs.prop('disabled', false).css('opacity', '1');
        }
    });
    
    // Initialize business hours state
    $('.business-hours-day input[type="checkbox"]:checked').each(function() {
        $(this).siblings('.time-input').prop('disabled', true).css('opacity', '0.5');
    });
    
    // Color picker enhancement (if needed)
    if (typeof wp !== 'undefined' && wp.colorPicker) {
        $('.color-picker').wpColorPicker();
    }
    
    // Template preview (placeholder for future implementation)
    $('#vcard_template_name').on('change', function() {
        var selectedTemplate = $(this).val();
        console.log('Template changed to:', selectedTemplate);
        // Template preview functionality will be implemented in future tasks
    });
    
    // Form validation
    $('form#post').on('submit', function(e) {
        var hasErrors = false;
        var errorMessages = [];
        
        // Validate required fields
        var businessName = $('input[name="vcard_business_name"]').val();
        var email = $('input[name="vcard_email"]').val();
        
        if (!businessName.trim()) {
            errorMessages.push('Business name is required.');
            hasErrors = true;
        }
        
        if (email && !isValidEmail(email)) {
            errorMessages.push('Please enter a valid email address.');
            hasErrors = true;
        }
        
        // Validate URLs
        $('input[type="url"]').each(function() {
            var url = $(this).val();
            if (url && !isValidUrl(url)) {
                errorMessages.push('Please enter a valid URL for ' + $(this).prev('label').text());
                hasErrors = true;
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
            return false;
        }
    });
    
    // Helper functions
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }
    
    // Auto-save functionality (optional enhancement)
    var autoSaveTimer;
    $('.vcard-meta-tabs input, .vcard-meta-tabs textarea, .vcard-meta-tabs select').on('input change', function() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function() {
            // Auto-save functionality can be implemented here
            console.log('Auto-save triggered');
        }, 2000);
    });
});
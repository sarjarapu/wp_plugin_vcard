<?php
/**
 * vCard Export Class
 * 
 * Handles vCard generation with comprehensive business data support
 * Supports vCard 4.0 standard with business-specific fields
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_Export {
    
    /**
     * Business profile instance
     * @var VCard_Business_Profile
     */
    private $business_profile;
    
    /**
     * Export format
     * @var string
     */
    private $format = 'vcf';
    
    /**
     * vCard version
     * @var string
     */
    private $version = '4.0';
    
    /**
     * Constructor
     * 
     * @param VCard_Business_Profile $business_profile
     */
    public function __construct($business_profile) {
        $this->business_profile = $business_profile;
    }
    
    /**
     * Set export format
     * 
     * @param string $format Format: vcf, csv
     * @return VCard_Export
     */
    public function set_format($format) {
        $this->format = strtolower($format);
        return $this;
    }
    
    /**
     * Set vCard version
     * 
     * @param string $version Version: 3.0, 4.0
     * @return VCard_Export
     */
    public function set_version($version) {
        $this->version = $version;
        return $this;
    }
    
    /**
     * Generate vCard export data
     * 
     * @return string|array
     */
    public function generate() {
        switch ($this->format) {
            case 'csv':
                return $this->generate_csv();
            case 'vcf':
            default:
                return $this->generate_vcf();
        }
    }
    
    /**
     * Generate vCard 4.0 format with business data
     * 
     * @return string
     */
    private function generate_vcf() {
        $vcard = array();
        
        // vCard header
        $vcard[] = 'BEGIN:VCARD';
        $vcard[] = 'VERSION:' . $this->version;
        
        // Basic information
        $this->add_basic_info($vcard);
        
        // Contact information
        $this->add_contact_info($vcard);
        
        // Address information
        $this->add_address_info($vcard);
        
        // Business-specific information
        if ($this->business_profile->is_business_profile()) {
            $this->add_business_info($vcard);
        }
        
        // Social media (vCard 4.0 extended properties)
        $this->add_social_media($vcard);
        
        // Services and products (custom extensions)
        $this->add_services_products($vcard);
        
        // Business hours (custom extension)
        $this->add_business_hours($vcard);
        
        // Photo/Logo
        $this->add_photo($vcard);
        
        // Categories and tags
        $this->add_categories($vcard);
        
        // Revision timestamp
        $vcard[] = 'REV:' . gmdate('Ymd\THis\Z');
        
        // Unique identifier
        $vcard[] = 'UID:' . $this->generate_uid();
        
        // vCard footer
        $vcard[] = 'END:VCARD';
        
        return implode("\r\n", $vcard);
    }
    
    /**
     * Add basic information to vCard
     * 
     * @param array &$vcard
     */
    private function add_basic_info(&$vcard) {
        if ($this->business_profile->is_business_profile()) {
            // Business profile
            $business_name = $this->business_profile->get_data('business_name');
            $owner_name = trim($this->business_profile->get_data('first_name') . ' ' . $this->business_profile->get_data('last_name'));
            
            $vcard[] = 'FN:' . $this->escape_vcard_value($business_name);
            $vcard[] = 'ORG:' . $this->escape_vcard_value($business_name);
            
            if ($owner_name) {
                // Add owner as a separate contact point
                $vcard[] = 'N:' . $this->escape_vcard_value($this->business_profile->get_data('last_name')) . ';' . 
                          $this->escape_vcard_value($this->business_profile->get_data('first_name')) . ';;;';
            }
            
            $job_title = $this->business_profile->get_data('job_title') ?: 'Business Owner';
            $vcard[] = 'TITLE:' . $this->escape_vcard_value($job_title);
            
            // Business tagline as role
            $tagline = $this->business_profile->get_data('business_tagline');
            if ($tagline) {
                $vcard[] = 'ROLE:' . $this->escape_vcard_value($tagline);
            }
            
        } else {
            // Personal vCard (backward compatibility)
            $first_name = $this->business_profile->get_data('first_name');
            $last_name = $this->business_profile->get_data('last_name');
            $full_name = trim($first_name . ' ' . $last_name);
            
            $vcard[] = 'FN:' . $this->escape_vcard_value($full_name);
            $vcard[] = 'N:' . $this->escape_vcard_value($last_name) . ';' . 
                      $this->escape_vcard_value($first_name) . ';;;';
            
            $company = $this->business_profile->get_data('company');
            if ($company) {
                $vcard[] = 'ORG:' . $this->escape_vcard_value($company);
            }
            
            $job_title = $this->business_profile->get_data('job_title');
            if ($job_title) {
                $vcard[] = 'TITLE:' . $this->escape_vcard_value($job_title);
            }
        }
    }
    
    /**
     * Add contact information to vCard
     * 
     * @param array &$vcard
     */
    private function add_contact_info(&$vcard) {
        // Phone numbers
        $phone = $this->business_profile->get_data('phone');
        if ($phone) {
            $vcard[] = 'TEL;TYPE=work,voice:' . $this->escape_vcard_value($phone);
        }
        
        $secondary_phone = $this->business_profile->get_data('secondary_phone');
        if ($secondary_phone) {
            $vcard[] = 'TEL;TYPE=work,voice:' . $this->escape_vcard_value($secondary_phone);
        }
        
        $whatsapp = $this->business_profile->get_data('whatsapp');
        if ($whatsapp) {
            $vcard[] = 'TEL;TYPE=work,cell:' . $this->escape_vcard_value($whatsapp);
            // Add WhatsApp as messaging service (vCard 4.0)
            $vcard[] = 'IMPP;TYPE=work:whatsapp:' . $this->escape_vcard_value($whatsapp);
        }
        
        // Email
        $email = $this->business_profile->get_data('email');
        if ($email) {
            $vcard[] = 'EMAIL;TYPE=work:' . $this->escape_vcard_value($email);
        }
        
        // Website
        $website = $this->business_profile->get_data('website');
        if ($website) {
            $vcard[] = 'URL:' . $this->escape_vcard_value($website);
        }
    }
    
    /**
     * Add address information to vCard
     * 
     * @param array &$vcard
     */
    private function add_address_info(&$vcard) {
        $address_parts = array(
            '', // Post office box (not used)
            '', // Extended address (not used)
            $this->business_profile->get_data('address') ?: '',
            $this->business_profile->get_data('city') ?: '',
            $this->business_profile->get_data('state') ?: '',
            $this->business_profile->get_data('zip_code') ?: '',
            $this->business_profile->get_data('country') ?: ''
        );
        
        // Only add address if we have at least one component
        if (array_filter($address_parts)) {
            $escaped_parts = array_map(array($this, 'escape_vcard_value'), $address_parts);
            $vcard[] = 'ADR;TYPE=work:' . implode(';', $escaped_parts);
        }
        
        // Geographic coordinates (vCard 4.0)
        $latitude = $this->business_profile->get_data('latitude');
        $longitude = $this->business_profile->get_data('longitude');
        if ($latitude && $longitude) {
            $vcard[] = 'GEO:' . $latitude . ',' . $longitude;
        }
    }
    
    /**
     * Add business-specific information to vCard
     * 
     * @param array &$vcard
     */
    private function add_business_info(&$vcard) {
        // Business description as note
        $description = $this->business_profile->get_data('business_description');
        if ($description) {
            $vcard[] = 'NOTE:' . $this->escape_vcard_value($description);
        }
        
        // Business type/industry as category
        $template_name = $this->business_profile->get_data('template_name');
        if ($template_name) {
            $industry = $this->get_industry_from_template($template_name);
            $vcard[] = 'CATEGORIES:' . $this->escape_vcard_value($industry);
        }
    }
    
    /**
     * Add social media information to vCard
     * 
     * @param array &$vcard
     */
    private function add_social_media(&$vcard) {
        $social_links = $this->business_profile->get_social_media_links();
        
        foreach ($social_links as $platform => $url) {
            // vCard 4.0 social profile extension
            $vcard[] = 'X-SOCIALPROFILE;TYPE=' . $platform . ':' . $this->escape_vcard_value($url);
            
            // Also add as URL with label for better compatibility
            $platform_label = ucfirst($platform);
            $vcard[] = 'URL;TYPE=' . $platform . ':' . $this->escape_vcard_value($url);
        }
    }
    
    /**
     * Add services and products as custom extensions
     * 
     * @param array &$vcard
     */
    private function add_services_products(&$vcard) {
        // Services
        $services = $this->business_profile->get_services();
        if (!empty($services)) {
            $service_names = array();
            foreach ($services as $service) {
                if (!empty($service['name'])) {
                    $service_names[] = $service['name'];
                    
                    // Add detailed service information as custom field
                    $service_info = $service['name'];
                    if (!empty($service['price'])) {
                        $service_info .= ' - ' . $service['price'];
                    }
                    if (!empty($service['description'])) {
                        $service_info .= ': ' . $service['description'];
                    }
                    
                    $vcard[] = 'X-SERVICE:' . $this->escape_vcard_value($service_info);
                }
            }
            
            // Add services as categories
            if (!empty($service_names)) {
                $vcard[] = 'CATEGORIES:' . $this->escape_vcard_value(implode(',', $service_names));
            }
        }
        
        // Products
        $products = $this->business_profile->get_products();
        if (!empty($products)) {
            foreach ($products as $product) {
                if (!empty($product['name'])) {
                    $product_info = $product['name'];
                    if (!empty($product['price'])) {
                        $product_info .= ' - ' . $product['price'];
                    }
                    if (!empty($product['description'])) {
                        $product_info .= ': ' . $product['description'];
                    }
                    
                    $vcard[] = 'X-PRODUCT:' . $this->escape_vcard_value($product_info);
                }
            }
        }
    }
    
    /**
     * Add business hours as custom extension
     * 
     * @param array &$vcard
     */
    private function add_business_hours(&$vcard) {
        $business_hours = $this->business_profile->get_formatted_business_hours();
        
        if (!empty($business_hours)) {
            foreach ($business_hours as $day => $schedule) {
                if ($schedule['closed']) {
                    $vcard[] = 'X-BUSINESS-HOURS;DAY=' . strtoupper($day) . ':CLOSED';
                } else {
                    $hours = $schedule['open'] . '-' . $schedule['close'];
                    $vcard[] = 'X-BUSINESS-HOURS;DAY=' . strtoupper($day) . ':' . $hours;
                }
            }
        }
    }
    
    /**
     * Add photo/logo to vCard
     * 
     * @param array &$vcard
     */
    private function add_photo(&$vcard) {
        $photo_id = null;
        
        if ($this->business_profile->is_business_profile()) {
            $photo_id = $this->business_profile->get_data('business_logo');
        } else {
            // For personal vCards, try to get featured image
            $post_id = $this->business_profile->get_data('post_id');
            if ($post_id && has_post_thumbnail($post_id)) {
                $photo_id = get_post_thumbnail_id($post_id);
            }
        }
        
        if ($photo_id) {
            $photo_url = wp_get_attachment_image_url($photo_id, 'medium');
            if ($photo_url) {
                $vcard[] = 'PHOTO:' . $this->escape_vcard_value($photo_url);
            }
        }
    }
    
    /**
     * Add categories based on business type
     * 
     * @param array &$vcard
     */
    private function add_categories(&$vcard) {
        $categories = array();
        
        // Add template-based category
        $template_name = $this->business_profile->get_data('template_name');
        if ($template_name) {
            $categories[] = $this->get_industry_from_template($template_name);
        }
        
        // Add service categories
        $services = $this->business_profile->get_services();
        foreach ($services as $service) {
            if (!empty($service['category'])) {
                $categories[] = $service['category'];
            }
        }
        
        // Add product categories
        $products = $this->business_profile->get_products();
        foreach ($products as $product) {
            if (!empty($product['category'])) {
                $categories[] = $product['category'];
            }
        }
        
        if (!empty($categories)) {
            $unique_categories = array_unique($categories);
            $vcard[] = 'CATEGORIES:' . $this->escape_vcard_value(implode(',', $unique_categories));
        }
    }
    
    /**
     * Generate CSV format export
     * 
     * @return array
     */
    private function generate_csv() {
        $data = array();
        
        // Basic information
        if ($this->business_profile->is_business_profile()) {
            $data['Business Name'] = $this->business_profile->get_data('business_name');
            $data['Owner First Name'] = $this->business_profile->get_data('first_name');
            $data['Owner Last Name'] = $this->business_profile->get_data('last_name');
            $data['Business Tagline'] = $this->business_profile->get_data('business_tagline');
            $data['Business Description'] = $this->business_profile->get_data('business_description');
        } else {
            $data['First Name'] = $this->business_profile->get_data('first_name');
            $data['Last Name'] = $this->business_profile->get_data('last_name');
            $data['Company'] = $this->business_profile->get_data('company');
        }
        
        $data['Job Title'] = $this->business_profile->get_data('job_title');
        
        // Contact information
        $data['Phone'] = $this->business_profile->get_data('phone');
        $data['Secondary Phone'] = $this->business_profile->get_data('secondary_phone');
        $data['WhatsApp'] = $this->business_profile->get_data('whatsapp');
        $data['Email'] = $this->business_profile->get_data('email');
        $data['Website'] = $this->business_profile->get_data('website');
        
        // Address
        $data['Address'] = $this->business_profile->get_data('address');
        $data['City'] = $this->business_profile->get_data('city');
        $data['State'] = $this->business_profile->get_data('state');
        $data['Zip Code'] = $this->business_profile->get_data('zip_code');
        $data['Country'] = $this->business_profile->get_data('country');
        $data['Latitude'] = $this->business_profile->get_data('latitude');
        $data['Longitude'] = $this->business_profile->get_data('longitude');
        
        // Social media
        $social_links = $this->business_profile->get_social_media_links();
        foreach ($social_links as $platform => $url) {
            $data[ucfirst($platform)] = $url;
        }
        
        // Business-specific data
        if ($this->business_profile->is_business_profile()) {
            // Services
            $services = $this->business_profile->get_services();
            $service_list = array();
            foreach ($services as $service) {
                $service_text = $service['name'];
                if (!empty($service['price'])) {
                    $service_text .= ' (' . $service['price'] . ')';
                }
                $service_list[] = $service_text;
            }
            $data['Services'] = implode('; ', $service_list);
            
            // Products
            $products = $this->business_profile->get_products();
            $product_list = array();
            foreach ($products as $product) {
                $product_text = $product['name'];
                if (!empty($product['price'])) {
                    $product_text .= ' (' . $product['price'] . ')';
                }
                $product_list[] = $product_text;
            }
            $data['Products'] = implode('; ', $product_list);
            
            // Business hours
            $business_hours = $this->business_profile->get_formatted_business_hours();
            $hours_list = array();
            foreach ($business_hours as $day => $schedule) {
                $hours_list[] = $schedule['label'] . ': ' . $schedule['status'];
            }
            $data['Business Hours'] = implode('; ', $hours_list);
        }
        
        return $data;
    }
    
    /**
     * Escape vCard value according to RFC 6350
     * 
     * @param string $value
     * @return string
     */
    private function escape_vcard_value($value) {
        if (empty($value)) {
            return '';
        }
        
        // Convert to string if not already
        $value = (string) $value;
        
        // Escape special characters
        $value = str_replace(array('\\', ';', ',', "\n", "\r"), 
                           array('\\\\', '\\;', '\\,', '\\n', '\\n'), 
                           $value);
        
        return $value;
    }
    
    /**
     * Generate unique identifier for vCard
     * 
     * @return string
     */
    private function generate_uid() {
        $post_id = $this->business_profile->get_data('post_id');
        $site_url = get_site_url();
        $domain = parse_url($site_url, PHP_URL_HOST);
        
        return 'vcard-' . $post_id . '@' . $domain;
    }
    
    /**
     * Get industry name from template
     * 
     * @param string $template_name
     * @return string
     */
    private function get_industry_from_template($template_name) {
        $industries = array(
            'ceo' => 'Executive/Corporate',
            'freelancer' => 'Freelance/Consulting',
            'restaurant' => 'Food & Beverage',
            'construction' => 'Construction',
            'education' => 'Education',
            'fitness' => 'Health & Fitness',
            'coffeebar' => 'Food & Beverage',
            'handyman' => 'Home Services',
            'healthcare' => 'Healthcare',
            'immigration' => 'Legal Services',
            'lawyer' => 'Legal Services',
            'makeup-artist' => 'Beauty & Cosmetics',
            'ngo' => 'Non-Profit',
            'saloon' => 'Beauty & Personal Care',
            'tour' => 'Travel & Tourism'
        );
        
        return $industries[$template_name] ?? 'Business';
    }
    
    /**
     * Validate vCard compliance
     * 
     * @param string $vcard_content
     * @return array Validation results
     */
    public function validate_vcard($vcard_content) {
        $errors = array();
        $warnings = array();
        
        // Check basic structure
        if (!preg_match('/^BEGIN:VCARD/m', $vcard_content)) {
            $errors[] = 'Missing BEGIN:VCARD';
        }
        
        if (!preg_match('/^END:VCARD/m', $vcard_content)) {
            $errors[] = 'Missing END:VCARD';
        }
        
        if (!preg_match('/^VERSION:[34]\.0/m', $vcard_content)) {
            $errors[] = 'Missing or invalid VERSION';
        }
        
        if (!preg_match('/^FN:/m', $vcard_content)) {
            $errors[] = 'Missing required FN (Formatted Name) field';
        }
        
        // Check for common issues
        $lines = explode("\n", $vcard_content);
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            
            // Check line length (should be folded if > 75 characters)
            if (strlen($line) > 75) {
                $warnings[] = "Line " . ($line_num + 1) . " exceeds 75 characters and should be folded";
            }
            
            // Check for unescaped special characters
            if (preg_match('/[;,\\n\\r]/', $line) && !preg_match('/\\\\[;,nr]/', $line)) {
                $warnings[] = "Line " . ($line_num + 1) . " may contain unescaped special characters";
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        );
    }
    
    /**
     * Get file extension for format
     * 
     * @return string
     */
    public function get_file_extension() {
        switch ($this->format) {
            case 'csv':
                return 'csv';
            case 'vcf':
            default:
                return 'vcf';
        }
    }
    
    /**
     * Get MIME type for format
     * 
     * @return string
     */
    public function get_mime_type() {
        switch ($this->format) {
            case 'csv':
                return 'text/csv';
            case 'vcf':
            default:
                return 'text/vcard';
        }
    }
    
    /**
     * Generate filename for export
     * 
     * @return string
     */
    public function get_filename() {
        if ($this->business_profile->is_business_profile()) {
            $name = $this->business_profile->get_data('business_name');
        } else {
            $name = trim($this->business_profile->get_data('first_name') . '_' . $this->business_profile->get_data('last_name'));
        }
        
        $filename = sanitize_file_name($name ?: 'vcard');
        return $filename . '.' . $this->get_file_extension();
    }
}
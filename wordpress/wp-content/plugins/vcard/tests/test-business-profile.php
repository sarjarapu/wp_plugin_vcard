<?php
/**
 * Unit Tests for VCard_Business_Profile Class
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Test_VCard_Business_Profile extends WP_UnitTestCase {
    
    /**
     * Test profile instance
     * @var VCard_Business_Profile
     */
    private $profile;
    
    /**
     * Test post ID
     * @var int
     */
    private $post_id;
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create a test post
        $this->post_id = $this->factory->post->create(array(
            'post_type' => 'vcard_profile',
            'post_title' => 'Test Business Profile'
        ));
        
        // Create profile instance
        $this->profile = new VCard_Business_Profile($this->post_id);
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        if ($this->post_id) {
            wp_delete_post($this->post_id, true);
        }
        parent::tearDown();
    }
    
    /**
     * Test profile instantiation
     */
    public function test_profile_instantiation() {
        $this->assertInstanceOf('VCard_Business_Profile', $this->profile);
    }
    
    /**
     * Test setting and getting profile data
     */
    public function test_set_and_get_data() {
        $test_data = array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890'
        );
        
        $this->profile->set_data($test_data);
        
        $this->assertEquals('Test Business', $this->profile->get_data('business_name'));
        $this->assertEquals('test@example.com', $this->profile->get_data('email'));
        $this->assertEquals('+1234567890', $this->profile->get_data('phone'));
    }
    
    /**
     * Test business profile validation - valid data
     */
    public function test_business_profile_validation_valid() {
        $valid_data = array(
            'business_name' => 'Valid Business',
            'email' => 'valid@example.com',
            'phone' => '+1234567890',
            'website' => 'https://example.com',
            'business_hours' => wp_json_encode(array(
                'monday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'tuesday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false)
            ))
        );
        
        $this->profile->set_data($valid_data);
        $this->assertTrue($this->profile->validate());
        $this->assertEmpty($this->profile->get_validation_errors());
    }
    
    /**
     * Test business profile validation - missing required fields
     */
    public function test_business_profile_validation_missing_required() {
        $invalid_data = array(
            'business_name' => '', // Required field missing
            'email' => 'valid@example.com',
            'phone' => '+1234567890'
        );
        
        $this->profile->set_data($invalid_data);
        $this->assertFalse($this->profile->validate());
        
        $errors = $this->profile->get_validation_errors();
        $this->assertArrayHasKey('business_name', $errors);
    }
    
    /**
     * Test personal vCard validation (backward compatibility)
     */
    public function test_personal_vcard_validation() {
        $personal_data = array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        );
        
        $this->profile->set_data($personal_data);
        $this->assertTrue($this->profile->validate());
        $this->assertEmpty($this->profile->get_validation_errors());
    }
    
    /**
     * Test email validation
     */
    public function test_email_validation() {
        // Valid email
        $this->profile->set_data(array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'valid@example.com'
        ));
        $this->assertTrue($this->profile->validate());
        
        // Invalid email
        $this->profile->set_data(array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'invalid-email'
        ));
        $this->assertFalse($this->profile->validate());
        
        $errors = $this->profile->get_validation_errors();
        $this->assertArrayHasKey('email', $errors);
    }
    
    /**
     * Test URL validation
     */
    public function test_url_validation() {
        // Valid URLs
        $valid_data = array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'website' => 'https://example.com',
            'social_facebook' => 'https://facebook.com/testbusiness'
        );
        
        $this->profile->set_data($valid_data);
        $this->assertTrue($this->profile->validate());
        
        // Invalid URLs
        $invalid_data = array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'website' => 'not-a-url',
            'social_facebook' => 'invalid-url'
        );
        
        $this->profile->set_data($invalid_data);
        $this->assertFalse($this->profile->validate());
        
        $errors = $this->profile->get_validation_errors();
        $this->assertArrayHasKey('website', $errors);
        $this->assertArrayHasKey('social_facebook', $errors);
    }
    
    /**
     * Test phone number validation
     */
    public function test_phone_validation() {
        // Valid phone numbers
        $valid_phones = array('+1234567890', '(123) 456-7890', '123-456-7890', '1234567890');
        
        foreach ($valid_phones as $phone) {
            $this->profile->set_data(array(
                'business_name' => 'Test Business',
                'email' => 'test@example.com',
                'phone' => $phone
            ));
            $this->assertTrue($this->profile->validate(), "Phone number $phone should be valid");
        }
        
        // Invalid phone number (too short)
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '123'
        ));
        $this->assertFalse($this->profile->validate());
        
        $errors = $this->profile->get_validation_errors();
        $this->assertArrayHasKey('phone', $errors);
    }
    
    /**
     * Test business hours validation
     */
    public function test_business_hours_validation() {
        // Valid business hours
        $valid_hours = array(
            'monday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
            'tuesday' => array('closed' => true),
            'wednesday' => array('open' => '08:30', 'close' => '18:30', 'closed' => false)
        );
        
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'business_hours' => wp_json_encode($valid_hours)
        ));
        $this->assertTrue($this->profile->validate());
        
        // Invalid business hours (missing close time)
        $invalid_hours = array(
            'monday' => array('open' => '09:00', 'closed' => false) // Missing close time
        );
        
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'business_hours' => wp_json_encode($invalid_hours)
        ));
        $this->assertFalse($this->profile->validate());
        
        $errors = $this->profile->get_validation_errors();
        $this->assertArrayHasKey('business_hours', $errors);
    }
    
    /**
     * Test services validation
     */
    public function test_services_validation() {
        // Valid services
        $valid_services = array(
            array(
                'name' => 'Web Development',
                'description' => 'Custom website development',
                'price' => '$500',
                'category' => 'Development'
            ),
            array(
                'name' => 'SEO Optimization',
                'description' => 'Search engine optimization',
                'price' => '200',
                'category' => 'Marketing'
            )
        );
        
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'services' => wp_json_encode($valid_services)
        ));
        $this->assertTrue($this->profile->validate());
        
        // Invalid services (missing name)
        $invalid_services = array(
            array(
                'description' => 'Service without name',
                'price' => '$100'
            )
        );
        
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'services' => wp_json_encode($invalid_services)
        ));
        $this->assertFalse($this->profile->validate());
        
        $errors = $this->profile->get_validation_errors();
        $this->assertArrayHasKey('services', $errors);
    }
    
    /**
     * Test products validation
     */
    public function test_products_validation() {
        // Valid products
        $valid_products = array(
            array(
                'name' => 'Premium Package',
                'description' => 'Complete business solution',
                'price' => '999.99',
                'category' => 'Packages',
                'in_stock' => true
            )
        );
        
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'products' => wp_json_encode($valid_products)
        ));
        $this->assertTrue($this->profile->validate());
        
        // Invalid products (invalid price)
        $invalid_products = array(
            array(
                'name' => 'Test Product',
                'price' => 'not-a-number'
            )
        );
        
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'products' => wp_json_encode($invalid_products)
        ));
        $this->assertFalse($this->profile->validate());
        
        $errors = $this->profile->get_validation_errors();
        $this->assertArrayHasKey('products', $errors);
    }
    
    /**
     * Test template settings validation
     */
    public function test_template_validation() {
        // Valid template settings
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'template_name' => 'ceo',
            'primary_color' => '#007cba',
            'secondary_color' => '#666666'
        ));
        $this->assertTrue($this->profile->validate());
        
        // Invalid template name
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'template_name' => 'invalid-template'
        ));
        $this->assertFalse($this->profile->validate());
        
        $errors = $this->profile->get_validation_errors();
        $this->assertArrayHasKey('template_name', $errors);
        
        // Invalid color format
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'template_name' => 'ceo',
            'primary_color' => 'not-a-color'
        ));
        $this->assertFalse($this->profile->validate());
        
        $errors = $this->profile->get_validation_errors();
        $this->assertArrayHasKey('primary_color', $errors);
    }
    
    /**
     * Test profile type detection
     */
    public function test_profile_type_detection() {
        // Test business profile detection
        $this->profile->set_data(array(
            'business_name' => 'Test Business'
        ));
        $this->assertTrue($this->profile->is_business_profile());
        $this->assertFalse($this->profile->is_personal_vcard());
        
        // Test personal vCard detection
        $this->profile->set_data(array(
            'first_name' => 'John',
            'last_name' => 'Doe'
        ));
        $this->assertFalse($this->profile->is_business_profile());
        $this->assertTrue($this->profile->is_personal_vcard());
    }
    
    /**
     * Test formatted business hours
     */
    public function test_formatted_business_hours() {
        $hours_data = array(
            'monday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
            'tuesday' => array('closed' => true),
            'wednesday' => array('open' => '08:30', 'close' => '18:30', 'closed' => false)
        );
        
        $this->profile->set_data(array(
            'business_hours' => wp_json_encode($hours_data)
        ));
        
        $formatted = $this->profile->get_formatted_business_hours();
        
        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('monday', $formatted);
        $this->assertArrayHasKey('tuesday', $formatted);
        $this->assertArrayHasKey('wednesday', $formatted);
        
        $this->assertEquals('09:00 - 17:00', $formatted['monday']['status']);
        $this->assertTrue($formatted['tuesday']['closed']);
        $this->assertEquals('08:30 - 18:30', $formatted['wednesday']['status']);
    }
    
    /**
     * Test services and products getters
     */
    public function test_services_and_products_getters() {
        $services_data = array(
            array('name' => 'Service 1', 'price' => '$100'),
            array('name' => 'Service 2', 'price' => '$200')
        );
        
        $products_data = array(
            array('name' => 'Product 1', 'price' => '$50'),
            array('name' => 'Product 2', 'price' => '$75')
        );
        
        $this->profile->set_data(array(
            'services' => wp_json_encode($services_data),
            'products' => wp_json_encode($products_data)
        ));
        
        $services = $this->profile->get_services();
        $products = $this->profile->get_products();
        
        $this->assertIsArray($services);
        $this->assertIsArray($products);
        $this->assertCount(2, $services);
        $this->assertCount(2, $products);
        $this->assertEquals('Service 1', $services[0]['name']);
        $this->assertEquals('Product 1', $products[0]['name']);
    }
    
    /**
     * Test social media links getter
     */
    public function test_social_media_links() {
        $this->profile->set_data(array(
            'social_facebook' => 'https://facebook.com/test',
            'social_linkedin' => 'https://linkedin.com/test',
            'social_twitter' => '' // Empty should be excluded
        ));
        
        $links = $this->profile->get_social_media_links();
        
        $this->assertIsArray($links);
        $this->assertArrayHasKey('facebook', $links);
        $this->assertArrayHasKey('linkedin', $links);
        $this->assertArrayNotHasKey('twitter', $links); // Empty values excluded
        $this->assertEquals('https://facebook.com/test', $links['facebook']);
    }
    
    /**
     * Test vCard export data generation
     */
    public function test_vcard_export_data() {
        // Test business profile export
        $this->profile->set_data(array(
            'business_name' => 'Test Business',
            'business_description' => 'A test business',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'website' => 'https://example.com',
            'address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'zip_code' => '12345',
            'country' => 'Test Country',
            'social_facebook' => 'https://facebook.com/test'
        ));
        
        $export_data = $this->profile->get_vcard_export_data();
        
        $this->assertIsArray($export_data);
        $this->assertEquals('Test Business', $export_data['fn']);
        $this->assertEquals('Test Business', $export_data['org']);
        $this->assertEquals('test@example.com', $export_data['email']);
        $this->assertEquals('+1234567890', $export_data['tel_work']);
        $this->assertEquals('https://example.com', $export_data['url']);
        $this->assertContains('123 Test St', $export_data['adr']);
        $this->assertContains('A test business', $export_data['note']);
        $this->assertArrayHasKey('facebook', $export_data['social_media']);
    }
    
    /**
     * Test profile creation
     */
    public function test_profile_creation() {
        $profile_data = array(
            'business_name' => 'New Test Business',
            'email' => 'newtest@example.com',
            'phone' => '+1987654321'
        );
        
        $new_profile = VCard_Business_Profile::create($profile_data);
        
        $this->assertInstanceOf('VCard_Business_Profile', $new_profile);
        $this->assertEquals('New Test Business', $new_profile->get_data('business_name'));
        $this->assertEquals('newtest@example.com', $new_profile->get_data('email'));
        
        // Clean up
        if ($new_profile) {
            $post = get_post($new_profile->post_id);
            if ($post) {
                wp_delete_post($post->ID, true);
            }
        }
    }
    
    /**
     * Test backward compatibility with existing vCard data
     */
    public function test_backward_compatibility() {
        // Simulate existing vCard data
        update_post_meta($this->post_id, '_vcard_first_name', 'John');
        update_post_meta($this->post_id, '_vcard_last_name', 'Doe');
        update_post_meta($this->post_id, '_vcard_email', 'john@example.com');
        update_post_meta($this->post_id, '_vcard_company', 'Old Company');
        
        // Create new profile instance (should load existing data)
        $profile = new VCard_Business_Profile($this->post_id);
        
        $this->assertEquals('John', $profile->get_data('first_name'));
        $this->assertEquals('Doe', $profile->get_data('last_name'));
        $this->assertEquals('john@example.com', $profile->get_data('email'));
        $this->assertEquals('Old Company', $profile->get_data('company'));
        
        // Should be detected as personal vCard
        $this->assertTrue($profile->is_personal_vcard());
        $this->assertFalse($profile->is_business_profile());
        
        // Should validate as personal vCard
        $this->assertTrue($profile->validate());
    }
}
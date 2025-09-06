<?php
/**
 * Unit Tests for VCard_Template_Engine Class
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Test_VCard_Template_Engine extends WP_UnitTestCase {
    
    /**
     * Template engine instance
     * @var VCard_Template_Engine
     */
    private $template_engine;
    
    /**
     * Test profile data
     * @var array
     */
    private $test_profile_data;
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        $this->template_engine = new VCard_Template_Engine();
        
        // Create test profile data
        $this->test_profile_data = array(
            'business_name' => 'Test Business',
            'business_tagline' => 'Your trusted partner',
            'business_description' => 'We provide excellent services',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'website' => 'https://example.com',
            'address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'zip_code' => '12345',
            'services' => wp_json_encode(array(
                array(
                    'name' => 'Consulting',
                    'description' => 'Business consulting services',
                    'price' => '$100/hour',
                    'duration' => '60 min'
                )
            )),
            'products' => wp_json_encode(array(
                array(
                    'name' => 'Premium Package',
                    'description' => 'Complete business solution',
                    'price' => '$999',
                    'in_stock' => true
                )
            ))
        );
    }
    
    /**
     * Test template engine instantiation
     */
    public function test_template_engine_instantiation() {
        $this->assertInstanceOf('VCard_Template_Engine', $this->template_engine);
    }
    
    /**
     * Test getting available templates
     */
    public function test_get_available_templates() {
        $templates = $this->template_engine->get_available_templates();
        
        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates);
        
        // Check that required templates exist
        $this->assertArrayHasKey('ceo', $templates);
        $this->assertArrayHasKey('freelancer', $templates);
        $this->assertArrayHasKey('restaurant', $templates);
        $this->assertArrayHasKey('healthcare', $templates);
        
        // Check template structure
        $ceo_template = $templates['ceo'];
        $this->assertArrayHasKey('name', $ceo_template);
        $this->assertArrayHasKey('description', $ceo_template);
        $this->assertArrayHasKey('recommended_schemes', $ceo_template);
        $this->assertArrayHasKey('layout', $ceo_template);
        $this->assertArrayHasKey('features', $ceo_template);
        $this->assertArrayHasKey('industries', $ceo_template);
    }
    
    /**
     * Test getting templates by industry
     */
    public function test_get_templates_by_industry() {
        $business_templates = $this->template_engine->get_available_templates('business');
        $healthcare_templates = $this->template_engine->get_available_templates('healthcare');
        
        $this->assertIsArray($business_templates);
        $this->assertIsArray($healthcare_templates);
        
        // CEO template should be in business category
        $this->assertArrayHasKey('ceo', $business_templates);
        
        // Healthcare template should be in healthcare category
        $this->assertArrayHasKey('healthcare', $healthcare_templates);
    }
    
    /**
     * Test getting available color schemes
     */
    public function test_get_available_color_schemes() {
        $schemes = $this->template_engine->get_available_color_schemes();
        
        $this->assertIsArray($schemes);
        $this->assertNotEmpty($schemes);
        
        // Check that required color schemes exist
        $this->assertArrayHasKey('professional', $schemes);
        $this->assertArrayHasKey('healthcare', $schemes);
        $this->assertArrayHasKey('creative', $schemes);
        $this->assertArrayHasKey('finance', $schemes);
        
        // Check color scheme structure
        $professional_scheme = $schemes['professional'];
        $this->assertArrayHasKey('name', $professional_scheme);
        $this->assertArrayHasKey('description', $professional_scheme);
        $this->assertArrayHasKey('primary', $professional_scheme);
        $this->assertArrayHasKey('secondary', $professional_scheme);
        $this->assertArrayHasKey('accent', $professional_scheme);
        $this->assertArrayHasKey('text', $professional_scheme);
        $this->assertArrayHasKey('background', $professional_scheme);
        
        // Check color format (should be hex colors)
        $this->assertMatchesRegularExpression('/^#[a-fA-F0-9]{6}$/', $professional_scheme['primary']);
        $this->assertMatchesRegularExpression('/^#[a-fA-F0-9]{6}$/', $professional_scheme['secondary']);
    }
    
    /**
     * Test getting recommended color schemes for template
     */
    public function test_get_recommended_color_schemes() {
        $ceo_schemes = $this->template_engine->get_available_color_schemes('ceo');
        $healthcare_schemes = $this->template_engine->get_available_color_schemes('healthcare');
        
        $this->assertIsArray($ceo_schemes);
        $this->assertIsArray($healthcare_schemes);
        
        // CEO template should have professional schemes
        $this->assertArrayHasKey('professional', $ceo_schemes);
        $this->assertArrayHasKey('finance', $ceo_schemes);
        
        // Healthcare template should have healthcare schemes
        $this->assertArrayHasKey('healthcare', $healthcare_schemes);
    }
    
    /**
     * Test getting specific template
     */
    public function test_get_template() {
        $ceo_template = $this->template_engine->get_template('ceo');
        $invalid_template = $this->template_engine->get_template('invalid');
        
        $this->assertIsArray($ceo_template);
        $this->assertEquals('Executive', $ceo_template['name']);
        $this->assertEquals('header-focused', $ceo_template['layout']);
        
        $this->assertNull($invalid_template);
    }
    
    /**
     * Test getting specific color scheme
     */
    public function test_get_color_scheme() {
        $professional_scheme = $this->template_engine->get_color_scheme('professional');
        $invalid_scheme = $this->template_engine->get_color_scheme('invalid');
        
        $this->assertIsArray($professional_scheme);
        $this->assertEquals('Professional Blue', $professional_scheme['name']);
        $this->assertEquals('#2563eb', $professional_scheme['primary']);
        
        $this->assertNull($invalid_scheme);
    }
    
    /**
     * Test template data validation
     */
    public function test_validate_template_data() {
        // Valid data
        $valid_data = array(
            'business_name' => 'Test Business',
            'email' => 'test@example.com',
            'phone' => '+1234567890'
        );
        
        $errors = $this->template_engine->validate_template_data($valid_data);
        $this->assertEmpty($errors);
        
        // Invalid data
        $invalid_data = array(
            'business_name' => '', // Required field missing
            'email' => 'invalid-email', // Invalid email format
            'phone' => '123' // Invalid phone format
        );
        
        $errors = $this->template_engine->validate_template_data($invalid_data);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('business_name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('phone', $errors);
    }
    
    /**
     * Test fallback template rendering
     */
    public function test_fallback_template_rendering() {
        // Create a mock template engine that will trigger fallback
        $template_engine = $this->getMockBuilder('VCard_Template_Engine')
            ->setMethods(['load_template_file'])
            ->getMock();
        
        // Mock load_template_file to return false (template not found)
        $template_engine->method('load_template_file')->willReturn(false);
        
        // This should trigger fallback template
        $result = $template_engine->render_template('nonexistent', $this->test_profile_data);
        
        $this->assertIsString($result);
        $this->assertStringContainsString('Test Business', $result);
        $this->assertStringContainsString('test@example.com', $result);
    }
    
    /**
     * Test CSS generation for color schemes
     */
    public function test_css_generation() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->template_engine);
        $method = $reflection->getMethod('generate_template_css');
        $method->setAccessible(true);
        
        $color_scheme = $this->template_engine->get_color_scheme('professional');
        $css = $method->invoke($this->template_engine, 'ceo', $color_scheme);
        
        $this->assertIsString($css);
        $this->assertStringContainsString('--primary-color: #2563eb', $css);
        $this->assertStringContainsString('--secondary-color: #64748b', $css);
        $this->assertStringContainsString('.vcard-template.template-ceo', $css);
    }
    
    /**
     * Test address formatting
     */
    public function test_address_formatting() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->template_engine);
        $method = $reflection->getMethod('format_address');
        $method->setAccessible(true);
        
        $profile = new VCard_Business_Profile();
        $profile->set_data($this->test_profile_data);
        
        $address = $method->invoke($this->template_engine, $profile);
        
        $this->assertIsString($address);
        $this->assertStringContainsString('123 Test St', $address);
        $this->assertStringContainsString('Test City', $address);
        $this->assertStringContainsString('TS', $address);
        $this->assertStringContainsString('12345', $address);
    }
    
    /**
     * Test business hours formatting
     */
    public function test_business_hours_formatting() {
        // Add business hours to test data
        $hours_data = array(
            'monday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
            'tuesday' => array('closed' => true),
            'wednesday' => array('open' => '08:30', 'close' => '18:30', 'closed' => false)
        );
        
        $profile_data = $this->test_profile_data;
        $profile_data['business_hours'] = wp_json_encode($hours_data);
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->template_engine);
        $method = $reflection->getMethod('format_business_hours');
        $method->setAccessible(true);
        
        $profile = new VCard_Business_Profile();
        $profile->set_data($profile_data);
        
        $hours_html = $method->invoke($this->template_engine, $profile);
        
        $this->assertIsString($hours_html);
        $this->assertStringContainsString('business-hours', $hours_html);
        $this->assertStringContainsString('09:00 - 17:00', $hours_html);
        $this->assertStringContainsString('Closed', $hours_html);
        $this->assertStringContainsString('08:30 - 18:30', $hours_html);
    }
    
    /**
     * Test services formatting
     */
    public function test_services_formatting() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->template_engine);
        $method = $reflection->getMethod('format_services');
        $method->setAccessible(true);
        
        $profile = new VCard_Business_Profile();
        $profile->set_data($this->test_profile_data);
        
        $template = $this->template_engine->get_template('ceo');
        $services_html = $method->invoke($this->template_engine, $profile, $template);
        
        $this->assertIsString($services_html);
        $this->assertStringContainsString('services-section', $services_html);
        $this->assertStringContainsString('Consulting', $services_html);
        $this->assertStringContainsString('Business consulting services', $services_html);
        $this->assertStringContainsString('$100/hour', $services_html);
        $this->assertStringContainsString('60 min', $services_html);
    }
    
    /**
     * Test products formatting
     */
    public function test_products_formatting() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->template_engine);
        $method = $reflection->getMethod('format_products');
        $method->setAccessible(true);
        
        $profile = new VCard_Business_Profile();
        $profile->set_data($this->test_profile_data);
        
        $template = $this->template_engine->get_template('ceo');
        $products_html = $method->invoke($this->template_engine, $profile, $template);
        
        $this->assertIsString($products_html);
        $this->assertStringContainsString('products-section', $products_html);
        $this->assertStringContainsString('Premium Package', $products_html);
        $this->assertStringContainsString('Complete business solution', $products_html);
        $this->assertStringContainsString('$999', $products_html);
        $this->assertStringContainsString('In Stock', $products_html);
    }
    
    /**
     * Test social media formatting
     */
    public function test_social_media_formatting() {
        // Add social media to test data
        $profile_data = $this->test_profile_data;
        $profile_data['social_facebook'] = 'https://facebook.com/test';
        $profile_data['social_linkedin'] = 'https://linkedin.com/test';
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->template_engine);
        $method = $reflection->getMethod('format_social_media');
        $method->setAccessible(true);
        
        $profile = new VCard_Business_Profile();
        $profile->set_data($profile_data);
        
        $social_html = $method->invoke($this->template_engine, $profile);
        
        $this->assertIsString($social_html);
        $this->assertStringContainsString('social-media-section', $social_html);
        $this->assertStringContainsString('https://facebook.com/test', $social_html);
        $this->assertStringContainsString('https://linkedin.com/test', $social_html);
        $this->assertStringContainsString('social-facebook', $social_html);
        $this->assertStringContainsString('social-linkedin', $social_html);
    }
    
    /**
     * Test conditional sections parsing
     */
    public function test_conditional_sections_parsing() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->template_engine);
        $method = $reflection->getMethod('parse_conditional_sections');
        $method->setAccessible(true);
        
        $template_html = '
            {{#if_services}}<div class="services">Services content</div>{{/if_services}}
            {{#if_products}}<div class="products">Products content</div>{{/if_products}}
            {{#if_gallery}}<div class="gallery">Gallery content</div>{{/if_gallery}}
        ';
        
        $profile = new VCard_Business_Profile();
        $profile->set_data($this->test_profile_data);
        
        $template = $this->template_engine->get_template('ceo');
        $parsed_html = $method->invoke($this->template_engine, $template_html, $profile, $template);
        
        // Should include services and products (they exist in test data)
        $this->assertStringContainsString('Services content', $parsed_html);
        $this->assertStringContainsString('Products content', $parsed_html);
        
        // Should not include gallery (doesn't exist in test data)
        $this->assertStringNotContainsString('Gallery content', $parsed_html);
    }
    
    /**
     * Test template output wrapping
     */
    public function test_template_output_wrapping() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->template_engine);
        $method = $reflection->getMethod('wrap_template_output');
        $method->setAccessible(true);
        
        $html = '<div>Test content</div>';
        $css = '.test { color: red; }';
        $template_key = 'ceo';
        $color_scheme_key = 'professional';
        
        $wrapped = $method->invoke($this->template_engine, $html, $css, $template_key, $color_scheme_key);
        
        $this->assertIsString($wrapped);
        $this->assertStringContainsString('<style>', $wrapped);
        $this->assertStringContainsString('.test { color: red; }', $wrapped);
        $this->assertStringContainsString('vcard-template', $wrapped);
        $this->assertStringContainsString('template-ceo', $wrapped);
        $this->assertStringContainsString('color-scheme-professional', $wrapped);
        $this->assertStringContainsString('Test content', $wrapped);
    }
    
    /**
     * Test template data placeholder replacement
     */
    public function test_template_placeholder_replacement() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->template_engine);
        $method = $reflection->getMethod('parse_template_data');
        $method->setAccessible(true);
        
        $template_html = '
            <h1>{{business_name}}</h1>
            <p>{{business_tagline}}</p>
            <p>Email: {{email}}</p>
            <p>Phone: {{phone}}</p>
            <div>{{services}}</div>
        ';
        
        $profile = new VCard_Business_Profile();
        $profile->set_data($this->test_profile_data);
        
        $template = $this->template_engine->get_template('ceo');
        $color_scheme = $this->template_engine->get_color_scheme('professional');
        
        $parsed_html = $method->invoke($this->template_engine, $template_html, $this->test_profile_data, $template, $color_scheme);
        
        $this->assertStringContainsString('Test Business', $parsed_html);
        $this->assertStringContainsString('Your trusted partner', $parsed_html);
        $this->assertStringContainsString('test@example.com', $parsed_html);
        $this->assertStringContainsString('+1234567890', $parsed_html);
        $this->assertStringContainsString('Consulting', $parsed_html);
    }
}
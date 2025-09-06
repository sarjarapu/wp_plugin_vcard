<?php
/**
 * Template Customizer Tests
 * 
 * Tests for the template customization system
 * 
 * @package vCard
 * @since 1.0.0
 */

class VCard_Template_Customizer_Test extends WP_UnitTestCase {
    
    private $customizer;
    
    public function setUp(): void {
        parent::setUp();
        $this->customizer = new VCard_Template_Customizer();
    }
    
    /**
     * Test industry palettes initialization
     */
    public function test_industry_palettes_initialization() {
        $palettes = $this->customizer->get_industry_palettes();
        
        $this->assertIsArray($palettes);
        $this->assertArrayHasKey('professional', $palettes);
        $this->assertArrayHasKey('healthcare', $palettes);
        $this->assertArrayHasKey('creative', $palettes);
        $this->assertArrayHasKey('finance', $palettes);
        
        // Test professional palette structure
        $professional = $palettes['professional'];
        $this->assertArrayHasKey('name', $professional);
        $this->assertArrayHasKey('description', $professional);
        $this->assertArrayHasKey('schemes', $professional);
        
        // Test color scheme structure
        $schemes = $professional['schemes'];
        $this->assertArrayHasKey('corporate_blue', $schemes);
        
        $corporate_blue = $schemes['corporate_blue'];
        $this->assertArrayHasKey('name', $corporate_blue);
        $this->assertArrayHasKey('primary', $corporate_blue);
        $this->assertArrayHasKey('secondary', $corporate_blue);
        $this->assertArrayHasKey('accent', $corporate_blue);
        $this->assertArrayHasKey('text', $corporate_blue);
        $this->assertArrayHasKey('background', $corporate_blue);
    }
    
    /**
     * Test template recommendations
     */
    public function test_template_recommendations() {
        $recommendations = $this->customizer->get_template_recommendations();
        
        $this->assertIsArray($recommendations);
        $this->assertArrayHasKey('business', $recommendations);
        $this->assertArrayHasKey('healthcare', $recommendations);
        
        // Test business recommendations structure
        $business = $recommendations['business'];
        $this->assertArrayHasKey('templates', $business);
        $this->assertArrayHasKey('palettes', $business);
        $this->assertIsArray($business['templates']);
        $this->assertIsArray($business['palettes']);
    }
    
    /**
     * Test color scheme retrieval
     */
    public function test_get_color_scheme() {
        $scheme = $this->customizer->get_color_scheme('corporate_blue');
        
        $this->assertIsArray($scheme);
        $this->assertArrayHasKey('name', $scheme);
        $this->assertArrayHasKey('primary', $scheme);
        $this->assertEquals('#1e40af', $scheme['primary']);
        
        // Test non-existent scheme
        $non_existent = $this->customizer->get_color_scheme('non_existent');
        $this->assertNull($non_existent);
    }
    
    /**
     * Test CSS generation
     */
    public function test_generate_color_scheme_css() {
        $css = $this->customizer->generate_color_scheme_css('corporate_blue', 'ceo');
        
        $this->assertIsString($css);
        $this->assertStringContainsString('--primary-color: #1e40af', $css);
        $this->assertStringContainsString('.vcard-template.template-ceo', $css);
        $this->assertStringContainsString('.primary-bg { background-color: var(--primary-color)', $css);
        
        // Test without template key
        $css_no_template = $this->customizer->generate_color_scheme_css('corporate_blue');
        $this->assertStringContainsString('.vcard-template {', $css_no_template);
    }
    
    /**
     * Test meta box registration
     */
    public function test_meta_box_registration() {
        global $wp_meta_boxes;
        
        // Simulate add_meta_boxes action
        do_action('add_meta_boxes', 'vcard_profile', get_post());
        
        $this->assertArrayHasKey('vcard_profile', $wp_meta_boxes);
        $this->assertArrayHasKey('normal', $wp_meta_boxes['vcard_profile']);
        $this->assertArrayHasKey('vcard_template_customization', $wp_meta_boxes['vcard_profile']['normal']['high']);
    }
    
    /**
     * Test AJAX handlers registration
     */
    public function test_ajax_handlers() {
        $this->assertTrue(has_action('wp_ajax_vcard_preview_template'));
        $this->assertTrue(has_action('wp_ajax_vcard_get_color_schemes'));
        $this->assertTrue(has_action('wp_ajax_vcard_get_template_recommendations'));
    }
    
    /**
     * Test template preview generation
     */
    public function test_template_preview() {
        // Create a test post
        $post_id = $this->factory->post->create(array(
            'post_type' => 'vcard_profile',
            'post_title' => 'Test Business'
        ));
        
        // Add some meta data
        update_post_meta($post_id, '_vcard_business_name', 'Test Business');
        update_post_meta($post_id, '_vcard_email', 'test@example.com');
        update_post_meta($post_id, '_vcard_phone', '123-456-7890');
        
        // Mock AJAX request
        $_POST['post_id'] = $post_id;
        $_POST['template'] = 'ceo';
        $_POST['color_scheme'] = 'corporate_blue';
        $_POST['nonce'] = wp_create_nonce('vcard_customizer_nonce');
        
        // Set current user
        wp_set_current_user($this->factory->user->create(array('role' => 'administrator')));
        
        // Capture output
        ob_start();
        try {
            $this->customizer->handle_template_preview();
        } catch (WPDieException $e) {
            // Expected for successful AJAX response
        }
        $output = ob_get_clean();
        
        // Should contain JSON response
        $this->assertStringContainsString('success', $output);
    }
    
    /**
     * Test color scheme AJAX handler
     */
    public function test_get_color_schemes_ajax() {
        $_POST['industry'] = 'professional';
        $_POST['nonce'] = wp_create_nonce('vcard_customizer_nonce');
        
        ob_start();
        try {
            $this->customizer->handle_get_color_schemes();
        } catch (WPDieException $e) {
            // Expected for successful AJAX response
        }
        $output = ob_get_clean();
        
        $this->assertStringContainsString('success', $output);
        $this->assertStringContainsString('corporate_blue', $output);
    }
    
    /**
     * Test template recommendations AJAX handler
     */
    public function test_get_template_recommendations_ajax() {
        $_POST['industry'] = 'business';
        $_POST['current_template'] = 'ceo';
        $_POST['nonce'] = wp_create_nonce('vcard_customizer_nonce');
        
        ob_start();
        try {
            $this->customizer->handle_get_template_recommendations();
        } catch (WPDieException $e) {
            // Expected for successful AJAX response
        }
        $output = ob_get_clean();
        
        $this->assertStringContainsString('success', $output);
    }
}
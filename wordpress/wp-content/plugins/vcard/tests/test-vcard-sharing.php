<?php
/**
 * Unit Tests for VCard Sharing Class
 * 
 * @package vCard
 * @since 1.0.0
 */

class Test_VCard_Sharing extends WP_UnitTestCase {
    
    private $business_profile;
    private $personal_profile;
    private $post_id_business;
    private $post_id_personal;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create test business profile
        $this->post_id_business = $this->factory->post->create(array(
            'post_type' => 'vcard_profile',
            'post_title' => 'Test Business Profile',
            'post_status' => 'publish'
        ));
        
        // Add business profile meta data
        $business_meta = array(
            '_vcard_business_name' => 'Acme Corporation',
            '_vcard_business_tagline' => 'Quality Solutions',
            '_vcard_business_description' => 'We provide quality business solutions for all your needs.',
            '_vcard_first_name' => 'John',
            '_vcard_last_name' => 'Doe',
            '_vcard_job_title' => 'CEO',
            '_vcard_phone' => '+1-555-123-4567',
            '_vcard_email' => 'john@acmecorp.com',
            '_vcard_website' => 'https://acmecorp.com',
            '_vcard_social_facebook' => 'https://facebook.com/acmecorp',
            '_vcard_social_linkedin' => 'https://linkedin.com/company/acmecorp',
            '_vcard_social_twitter' => 'https://twitter.com/acmecorp',
        );
        
        foreach ($business_meta as $key => $value) {
            update_post_meta($this->post_id_business, $key, $value);
        }
        
        // Create test personal profile
        $this->post_id_personal = $this->factory->post->create(array(
            'post_type' => 'vcard_profile',
            'post_title' => 'Test Personal Profile',
            'post_status' => 'publish'
        ));
        
        // Add personal profile meta data
        $personal_meta = array(
            '_vcard_first_name' => 'Jane',
            '_vcard_last_name' => 'Smith',
            '_vcard_company' => 'Tech Solutions Inc',
            '_vcard_job_title' => 'Software Developer',
            '_vcard_phone' => '+1-555-987-6543',
            '_vcard_email' => 'jane.smith@techsolutions.com',
            '_vcard_website' => 'https://janesmith.dev',
            '_vcard_social_linkedin' => 'https://linkedin.com/in/janesmith',
        );
        
        foreach ($personal_meta as $key => $value) {
            update_post_meta($this->post_id_personal, $key, $value);
        }
        
        // Create profile instances
        $this->business_profile = new VCard_Business_Profile($this->post_id_business);
        $this->personal_profile = new VCard_Business_Profile($this->post_id_personal);
    }
    
    public function test_qr_code_generation() {
        $sharing = new VCard_Sharing($this->business_profile);
        
        $qr_data = $sharing->generate_qr_code();
        
        $this->assertIsArray($qr_data);
        $this->assertArrayHasKey('url', $qr_data);
        $this->assertArrayHasKey('profile_url', $qr_data);
        $this->assertArrayHasKey('options', $qr_data);
        $this->assertArrayHasKey('download_url', $qr_data);
        
        // Test URL format
        $this->assertStringContainsString('chart.googleapis.com', $qr_data['url']);
        $this->assertStringContainsString('cht=qr', $qr_data['url']);
        
        // Test profile URL
        $expected_url = get_permalink($this->post_id_business);
        $this->assertEquals($expected_url, $qr_data['profile_url']);
    }
    
    public function test_qr_code_customization() {
        $sharing = new VCard_Sharing($this->business_profile);
        
        $options = array(
            'size' => 400,
            'foreground_color' => 'FF0000',
            'background_color' => '00FF00',
            'error_correction' => 'H'
        );
        
        $qr_data = $sharing->generate_qr_code($options);
        
        $this->assertEquals($options, $qr_data['options']);
        $this->assertStringContainsString('400x400', $qr_data['url']);
        $this->assertStringContainsString('chco=FF0000,00FF00', $qr_data['url']);
        $this->assertStringContainsString('chld=H', $qr_data['url']);
    }
    
    public function test_social_sharing_links() {
        $sharing = new VCard_Sharing($this->business_profile);
        
        $sharing_links = $sharing->get_social_sharing_links();
        
        $this->assertIsArray($sharing_links);
        
        // Test required platforms
        $required_platforms = array('facebook', 'twitter', 'linkedin', 'whatsapp', 'email', 'copy');
        foreach ($required_platforms as $platform) {
            $this->assertArrayHasKey($platform, $sharing_links);
            $this->assertArrayHasKey('url', $sharing_links[$platform]);
            $this->assertArrayHasKey('label', $sharing_links[$platform]);
            $this->assertArrayHasKey('icon', $sharing_links[$platform]);
            $this->assertArrayHasKey('color', $sharing_links[$platform]);
        }
        
        // Test URL formats
        $profile_url = get_permalink($this->post_id_business);
        $encoded_url = urlencode($profile_url);
        
        $this->assertStringContainsString($encoded_url, $sharing_links['facebook']['url']);
        $this->assertStringContainsString($encoded_url, $sharing_links['twitter']['url']);
        $this->assertStringContainsString($encoded_url, $sharing_links['linkedin']['url']);
        $this->assertStringContainsString($encoded_url, $sharing_links['whatsapp']['url']);
        $this->assertStringContainsString($encoded_url, $sharing_links['email']['url']);
    }
    
    public function test_short_url_generation() {
        $sharing = new VCard_Sharing($this->business_profile);
        
        $short_url = $sharing->generate_short_url();
        
        $this->assertIsString($short_url);
        $this->assertStringContainsString(home_url('/vc/'), $short_url);
        
        // Test that short URL is stored
        $stored_short_url = get_post_meta($this->post_id_business, '_vcard_short_url', true);
        $this->assertEquals($short_url, $stored_short_url);
        
        // Test that short code is stored
        $short_code = get_post_meta($this->post_id_business, '_vcard_short_code', true);
        $this->assertNotEmpty($short_code);
        
        // Test that mapping is stored
        $mappings = get_option('vcard_short_url_mappings', array());
        $this->assertArrayHasKey($short_code, $mappings);
        $this->assertEquals($this->post_id_business, $mappings[$short_code]);
    }
    
    public function test_short_url_uniqueness() {
        $sharing1 = new VCard_Sharing($this->business_profile);
        $sharing2 = new VCard_Sharing($this->personal_profile);
        
        $short_url1 = $sharing1->generate_short_url();
        $short_url2 = $sharing2->generate_short_url();
        
        $this->assertNotEquals($short_url1, $short_url2);
        
        $short_code1 = get_post_meta($this->post_id_business, '_vcard_short_code', true);
        $short_code2 = get_post_meta($this->post_id_personal, '_vcard_short_code', true);
        
        $this->assertNotEquals($short_code1, $short_code2);
    }
    
    public function test_share_tracking() {
        $sharing = new VCard_Sharing($this->business_profile);
        
        // Initial share count should be 0
        $initial_shares = get_post_meta($this->post_id_business, '_vcard_shares', true);
        $this->assertEquals(0, intval($initial_shares));
        
        // Track a share
        $sharing->track_share('facebook');
        
        // Check total shares increased
        $total_shares = get_post_meta($this->post_id_business, '_vcard_shares', true);
        $this->assertEquals(1, intval($total_shares));
        
        // Check platform-specific shares
        $facebook_shares = get_post_meta($this->post_id_business, '_vcard_shares_facebook', true);
        $this->assertEquals(1, intval($facebook_shares));
        
        // Track another share on different platform
        $sharing->track_share('twitter');
        
        $total_shares = get_post_meta($this->post_id_business, '_vcard_shares', true);
        $this->assertEquals(2, intval($total_shares));
        
        $twitter_shares = get_post_meta($this->post_id_business, '_vcard_shares_twitter', true);
        $this->assertEquals(1, intval($twitter_shares));
    }
    
    public function test_sharing_analytics() {
        $sharing = new VCard_Sharing($this->business_profile);
        
        // Track some shares
        $sharing->track_share('facebook');
        $sharing->track_share('twitter');
        $sharing->track_share('facebook');
        
        $analytics = $sharing->get_sharing_analytics();
        
        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('total_shares', $analytics);
        $this->assertArrayHasKey('qr_scans', $analytics);
        $this->assertArrayHasKey('short_url_clicks', $analytics);
        $this->assertArrayHasKey('platform_shares', $analytics);
        
        $this->assertEquals(3, $analytics['total_shares']);
        $this->assertEquals(2, $analytics['platform_shares']['facebook']);
        $this->assertEquals(1, $analytics['platform_shares']['twitter']);
        $this->assertEquals(0, $analytics['platform_shares']['linkedin']);
    }
    
    public function test_profile_title_generation() {
        // Test business profile title
        $sharing = new VCard_Sharing($this->business_profile);
        $reflection = new ReflectionClass($sharing);
        $method = $reflection->getMethod('get_profile_title');
        $method->setAccessible(true);
        
        $title = $method->invoke($sharing);
        $this->assertEquals('Acme Corporation - Quality Solutions', $title);
        
        // Test personal profile title
        $sharing_personal = new VCard_Sharing($this->personal_profile);
        $title_personal = $method->invoke($sharing_personal);
        $this->assertEquals('Jane Smith - Software Developer at Tech Solutions Inc', $title_personal);
    }
    
    public function test_profile_description_generation() {
        $sharing = new VCard_Sharing($this->business_profile);
        $reflection = new ReflectionClass($sharing);
        $method = $reflection->getMethod('get_profile_description');
        $method->setAccessible(true);
        
        $description = $method->invoke($sharing);
        $this->assertEquals('We provide quality business solutions for all your needs.', $description);
    }
    
    public function test_nfc_data_generation() {
        $sharing = new VCard_Sharing($this->business_profile);
        
        $nfc_data = $sharing->generate_nfc_data();
        
        $this->assertIsArray($nfc_data);
        $this->assertArrayHasKey('type', $nfc_data);
        $this->assertArrayHasKey('data', $nfc_data);
        $this->assertArrayHasKey('format', $nfc_data);
        $this->assertArrayHasKey('instructions', $nfc_data);
        
        $this->assertEquals('url', $nfc_data['type']);
        $this->assertEquals(get_permalink($this->post_id_business), $nfc_data['data']);
        $this->assertEquals('text/plain', $nfc_data['format']);
        
        $this->assertArrayHasKey('android', $nfc_data['instructions']);
        $this->assertArrayHasKey('ios', $nfc_data['instructions']);
    }
    
    public function test_embed_code_generation() {
        $sharing = new VCard_Sharing($this->business_profile);
        
        $options = array(
            'width' => 400,
            'height' => 500,
            'theme' => 'dark',
            'show_qr' => true,
            'show_contact_form' => false
        );
        
        $embed_code = $sharing->get_embed_code($options);
        
        $this->assertIsString($embed_code);
        $this->assertStringContainsString('<iframe', $embed_code);
        $this->assertStringContainsString('width="400"', $embed_code);
        $this->assertStringContainsString('height="500"', $embed_code);
        $this->assertStringContainsString('vcard_embed=1', $embed_code);
        $this->assertStringContainsString('theme=dark', $embed_code);
        $this->assertStringContainsString('show_qr=1', $embed_code);
        $this->assertStringContainsString('show_contact_form=0', $embed_code);
    }
    
    public function test_short_url_click_tracking() {
        $sharing = new VCard_Sharing($this->business_profile);
        $short_url = $sharing->generate_short_url();
        $short_code = get_post_meta($this->post_id_business, '_vcard_short_code', true);
        
        // Initial click count should be 0
        $initial_clicks = get_post_meta($this->post_id_business, '_vcard_short_url_clicks', true);
        $this->assertEquals(0, intval($initial_clicks));
        
        // Track a click
        VCard_Sharing::track_short_url_click($short_code);
        
        // Check click count increased
        $click_count = get_post_meta($this->post_id_business, '_vcard_short_url_clicks', true);
        $this->assertEquals(1, intval($click_count));
        
        // Track another click
        VCard_Sharing::track_short_url_click($short_code);
        
        $click_count = get_post_meta($this->post_id_business, '_vcard_short_url_clicks', true);
        $this->assertEquals(2, intval($click_count));
    }
    
    public function test_qr_code_tracking() {
        $sharing = new VCard_Sharing($this->business_profile);
        
        // Initial QR scan count should be 0
        $initial_scans = get_post_meta($this->post_id_business, '_vcard_qr_scans', true);
        $this->assertEquals(0, intval($initial_scans));
        
        // Generate QR code (should track generation)
        $sharing->generate_qr_code();
        
        // Check QR scan count increased
        $qr_scans = get_post_meta($this->post_id_business, '_vcard_qr_scans', true);
        $this->assertEquals(1, intval($qr_scans));
    }
    
    public function test_sharing_links_filter() {
        $sharing = new VCard_Sharing($this->business_profile);
        
        // Add filter to modify sharing links
        add_filter('vcard_social_sharing_links', function($links, $profile) {
            $links['custom'] = array(
                'url' => 'https://custom.com/share',
                'label' => 'Custom Platform',
                'icon' => 'fas fa-custom',
                'color' => '#FF0000'
            );
            return $links;
        }, 10, 2);
        
        $sharing_links = $sharing->get_social_sharing_links();
        
        $this->assertArrayHasKey('custom', $sharing_links);
        $this->assertEquals('Custom Platform', $sharing_links['custom']['label']);
        
        // Remove filter
        remove_all_filters('vcard_social_sharing_links');
    }
    
    public function tearDown(): void {
        wp_delete_post($this->post_id_business, true);
        wp_delete_post($this->post_id_personal, true);
        
        // Clean up options
        delete_option('vcard_short_url_mappings');
        
        parent::tearDown();
    }
}
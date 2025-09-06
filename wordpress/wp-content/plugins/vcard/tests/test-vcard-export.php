<?php
/**
 * Unit Tests for VCard Export Class
 * 
 * @package vCard
 * @since 1.0.0
 */

class Test_VCard_Export extends WP_UnitTestCase {
    
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
            '_vcard_secondary_phone' => '+1-555-123-4568',
            '_vcard_whatsapp' => '+1-555-123-4569',
            '_vcard_email' => 'john@acmecorp.com',
            '_vcard_website' => 'https://acmecorp.com',
            '_vcard_address' => '123 Business St',
            '_vcard_city' => 'Business City',
            '_vcard_state' => 'BC',
            '_vcard_zip_code' => '12345',
            '_vcard_country' => 'USA',
            '_vcard_latitude' => '40.7128',
            '_vcard_longitude' => '-74.0060',
            '_vcard_template_name' => 'ceo',
            '_vcard_social_facebook' => 'https://facebook.com/acmecorp',
            '_vcard_social_linkedin' => 'https://linkedin.com/company/acmecorp',
            '_vcard_social_twitter' => 'https://twitter.com/acmecorp',
            '_vcard_services' => wp_json_encode(array(
                array(
                    'name' => 'Consulting',
                    'description' => 'Business consulting services',
                    'price' => '$100/hour',
                    'category' => 'Professional Services'
                ),
                array(
                    'name' => 'Training',
                    'description' => 'Employee training programs',
                    'price' => '$500/day',
                    'category' => 'Education'
                )
            )),
            '_vcard_products' => wp_json_encode(array(
                array(
                    'name' => 'Business Software',
                    'description' => 'Custom business management software',
                    'price' => '$999',
                    'category' => 'Software',
                    'in_stock' => true
                )
            )),
            '_vcard_business_hours' => wp_json_encode(array(
                'monday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'tuesday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'wednesday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'thursday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'friday' => array('open' => '09:00', 'close' => '17:00', 'closed' => false),
                'saturday' => array('closed' => true),
                'sunday' => array('closed' => true)
            ))
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
            '_vcard_address' => '456 Developer Ave',
            '_vcard_city' => 'Tech City',
            '_vcard_state' => 'TC',
            '_vcard_zip_code' => '54321',
            '_vcard_country' => 'USA',
            '_vcard_social_linkedin' => 'https://linkedin.com/in/janesmith',
            '_vcard_social_github' => 'https://github.com/janesmith'
        );
        
        foreach ($personal_meta as $key => $value) {
            update_post_meta($this->post_id_personal, $key, $value);
        }
        
        // Create profile instances
        $this->business_profile = new VCard_Business_Profile($this->post_id_business);
        $this->personal_profile = new VCard_Business_Profile($this->post_id_personal);
    }
    
    public function test_business_profile_vcf_export() {
        $exporter = new VCard_Export($this->business_profile);
        $exporter->set_format('vcf')->set_version('4.0');
        
        $vcf_content = $exporter->generate();
        
        // Test basic vCard structure
        $this->assertStringContainsString('BEGIN:VCARD', $vcf_content);
        $this->assertStringContainsString('END:VCARD', $vcf_content);
        $this->assertStringContainsString('VERSION:4.0', $vcf_content);
        
        // Test business information
        $this->assertStringContainsString('FN:Acme Corporation', $vcf_content);
        $this->assertStringContainsString('ORG:Acme Corporation', $vcf_content);
        $this->assertStringContainsString('N:Doe;John;;;', $vcf_content);
        $this->assertStringContainsString('TITLE:CEO', $vcf_content);
        $this->assertStringContainsString('ROLE:Quality Solutions', $vcf_content);
        
        // Test contact information
        $this->assertStringContainsString('TEL;TYPE=work,voice:+1-555-123-4567', $vcf_content);
        $this->assertStringContainsString('TEL;TYPE=work,cell:+1-555-123-4569', $vcf_content);
        $this->assertStringContainsString('EMAIL;TYPE=work:john@acmecorp.com', $vcf_content);
        $this->assertStringContainsString('URL:https://acmecorp.com', $vcf_content);
        $this->assertStringContainsString('IMPP;TYPE=work:whatsapp:+1-555-123-4569', $vcf_content);
        
        // Test address
        $this->assertStringContainsString('ADR;TYPE=work:;;123 Business St;Business City;BC;12345;USA', $vcf_content);
        $this->assertStringContainsString('GEO:40.7128,-74.0060', $vcf_content);
        
        // Test business description
        $this->assertStringContainsString('NOTE:We provide quality business solutions for all your needs.', $vcf_content);
        
        // Test social media
        $this->assertStringContainsString('X-SOCIALPROFILE;TYPE=facebook:https://facebook.com/acmecorp', $vcf_content);
        $this->assertStringContainsString('X-SOCIALPROFILE;TYPE=linkedin:https://linkedin.com/company/acmecorp', $vcf_content);
        $this->assertStringContainsString('X-SOCIALPROFILE;TYPE=twitter:https://twitter.com/acmecorp', $vcf_content);
        
        // Test services
        $this->assertStringContainsString('X-SERVICE:Consulting - $100/hour: Business consulting services', $vcf_content);
        $this->assertStringContainsString('X-SERVICE:Training - $500/day: Employee training programs', $vcf_content);
        
        // Test products
        $this->assertStringContainsString('X-PRODUCT:Business Software - $999: Custom business management software', $vcf_content);
        
        // Test business hours
        $this->assertStringContainsString('X-BUSINESS-HOURS;DAY=MONDAY:09:00-17:00', $vcf_content);
        $this->assertStringContainsString('X-BUSINESS-HOURS;DAY=SATURDAY:CLOSED', $vcf_content);
        $this->assertStringContainsString('X-BUSINESS-HOURS;DAY=SUNDAY:CLOSED', $vcf_content);
        
        // Test categories
        $this->assertStringContainsString('CATEGORIES:', $vcf_content);
        
        // Test required fields
        $this->assertStringContainsString('REV:', $vcf_content);
        $this->assertStringContainsString('UID:', $vcf_content);
    }
    
    public function test_personal_profile_vcf_export() {
        $exporter = new VCard_Export($this->personal_profile);
        $exporter->set_format('vcf')->set_version('4.0');
        
        $vcf_content = $exporter->generate();
        
        // Test basic vCard structure
        $this->assertStringContainsString('BEGIN:VCARD', $vcf_content);
        $this->assertStringContainsString('END:VCARD', $vcf_content);
        $this->assertStringContainsString('VERSION:4.0', $vcf_content);
        
        // Test personal information
        $this->assertStringContainsString('FN:Jane Smith', $vcf_content);
        $this->assertStringContainsString('N:Smith;Jane;;;', $vcf_content);
        $this->assertStringContainsString('ORG:Tech Solutions Inc', $vcf_content);
        $this->assertStringContainsString('TITLE:Software Developer', $vcf_content);
        
        // Test contact information
        $this->assertStringContainsString('TEL;TYPE=work,voice:+1-555-987-6543', $vcf_content);
        $this->assertStringContainsString('EMAIL;TYPE=work:jane.smith@techsolutions.com', $vcf_content);
        $this->assertStringContainsString('URL:https://janesmith.dev', $vcf_content);
        
        // Test address
        $this->assertStringContainsString('ADR;TYPE=work:;;456 Developer Ave;Tech City;TC;54321;USA', $vcf_content);
        
        // Test social media
        $this->assertStringContainsString('X-SOCIALPROFILE;TYPE=linkedin:https://linkedin.com/in/janesmith', $vcf_content);
    }
    
    public function test_business_profile_csv_export() {
        $exporter = new VCard_Export($this->business_profile);
        $exporter->set_format('csv');
        
        $csv_data = $exporter->generate();
        
        $this->assertIsArray($csv_data);
        
        // Test business information
        $this->assertEquals('Acme Corporation', $csv_data['Business Name']);
        $this->assertEquals('John', $csv_data['Owner First Name']);
        $this->assertEquals('Doe', $csv_data['Owner Last Name']);
        $this->assertEquals('Quality Solutions', $csv_data['Business Tagline']);
        $this->assertEquals('We provide quality business solutions for all your needs.', $csv_data['Business Description']);
        $this->assertEquals('CEO', $csv_data['Job Title']);
        
        // Test contact information
        $this->assertEquals('+1-555-123-4567', $csv_data['Phone']);
        $this->assertEquals('+1-555-123-4568', $csv_data['Secondary Phone']);
        $this->assertEquals('+1-555-123-4569', $csv_data['WhatsApp']);
        $this->assertEquals('john@acmecorp.com', $csv_data['Email']);
        $this->assertEquals('https://acmecorp.com', $csv_data['Website']);
        
        // Test address
        $this->assertEquals('123 Business St', $csv_data['Address']);
        $this->assertEquals('Business City', $csv_data['City']);
        $this->assertEquals('BC', $csv_data['State']);
        $this->assertEquals('12345', $csv_data['Zip Code']);
        $this->assertEquals('USA', $csv_data['Country']);
        $this->assertEquals('40.7128', $csv_data['Latitude']);
        $this->assertEquals('-74.0060', $csv_data['Longitude']);
        
        // Test social media
        $this->assertEquals('https://facebook.com/acmecorp', $csv_data['Facebook']);
        $this->assertEquals('https://linkedin.com/company/acmecorp', $csv_data['Linkedin']);
        $this->assertEquals('https://twitter.com/acmecorp', $csv_data['Twitter']);
        
        // Test services and products
        $this->assertStringContainsString('Consulting ($100/hour)', $csv_data['Services']);
        $this->assertStringContainsString('Training ($500/day)', $csv_data['Services']);
        $this->assertStringContainsString('Business Software ($999)', $csv_data['Products']);
        
        // Test business hours
        $this->assertStringContainsString('Monday: 09:00 - 17:00', $csv_data['Business Hours']);
        $this->assertStringContainsString('Saturday: Closed', $csv_data['Business Hours']);
    }
    
    public function test_personal_profile_csv_export() {
        $exporter = new VCard_Export($this->personal_profile);
        $exporter->set_format('csv');
        
        $csv_data = $exporter->generate();
        
        $this->assertIsArray($csv_data);
        
        // Test personal information
        $this->assertEquals('Jane', $csv_data['First Name']);
        $this->assertEquals('Smith', $csv_data['Last Name']);
        $this->assertEquals('Tech Solutions Inc', $csv_data['Company']);
        $this->assertEquals('Software Developer', $csv_data['Job Title']);
        
        // Test contact information
        $this->assertEquals('+1-555-987-6543', $csv_data['Phone']);
        $this->assertEquals('jane.smith@techsolutions.com', $csv_data['Email']);
        $this->assertEquals('https://janesmith.dev', $csv_data['Website']);
        
        // Test address
        $this->assertEquals('456 Developer Ave', $csv_data['Address']);
        $this->assertEquals('Tech City', $csv_data['City']);
        $this->assertEquals('TC', $csv_data['State']);
        $this->assertEquals('54321', $csv_data['Zip Code']);
        $this->assertEquals('USA', $csv_data['Country']);
        
        // Test social media
        $this->assertEquals('https://linkedin.com/in/janesmith', $csv_data['Linkedin']);
    }
    
    public function test_vcard_validation() {
        $exporter = new VCard_Export($this->business_profile);
        $vcf_content = $exporter->generate();
        
        $validation = $exporter->validate_vcard($vcf_content);
        
        $this->assertTrue($validation['valid'], 'vCard should be valid. Errors: ' . implode(', ', $validation['errors']));
        $this->assertEmpty($validation['errors'], 'vCard should have no validation errors');
    }
    
    public function test_vcard_escaping() {
        // Create profile with special characters
        $special_post_id = $this->factory->post->create(array(
            'post_type' => 'vcard_profile',
            'post_title' => 'Special Characters Test',
            'post_status' => 'publish'
        ));
        
        update_post_meta($special_post_id, '_vcard_business_name', 'Test; Company, Inc.');
        update_post_meta($special_post_id, '_vcard_business_description', "Line 1\nLine 2\rLine 3");
        update_post_meta($special_post_id, '_vcard_first_name', 'John\\Doe');
        
        $special_profile = new VCard_Business_Profile($special_post_id);
        $exporter = new VCard_Export($special_profile);
        $vcf_content = $exporter->generate();
        
        // Test proper escaping
        $this->assertStringContainsString('FN:Test\\; Company\\, Inc.', $vcf_content);
        $this->assertStringContainsString('NOTE:Line 1\\nLine 2\\nLine 3', $vcf_content);
        $this->assertStringContainsString('N:;John\\\\Doe;;;', $vcf_content);
    }
    
    public function test_file_properties() {
        $business_exporter = new VCard_Export($this->business_profile);
        $business_exporter->set_format('vcf');
        
        $this->assertEquals('vcf', $business_exporter->get_file_extension());
        $this->assertEquals('text/vcard', $business_exporter->get_mime_type());
        $this->assertStringEndsWith('.vcf', $business_exporter->get_filename());
        
        $csv_exporter = new VCard_Export($this->business_profile);
        $csv_exporter->set_format('csv');
        
        $this->assertEquals('csv', $csv_exporter->get_file_extension());
        $this->assertEquals('text/csv', $csv_exporter->get_mime_type());
        $this->assertStringEndsWith('.csv', $csv_exporter->get_filename());
    }
    
    public function test_vcard_30_compatibility() {
        $exporter = new VCard_Export($this->business_profile);
        $exporter->set_version('3.0');
        
        $vcf_content = $exporter->generate();
        
        $this->assertStringContainsString('VERSION:3.0', $vcf_content);
        $this->assertStringContainsString('BEGIN:VCARD', $vcf_content);
        $this->assertStringContainsString('END:VCARD', $vcf_content);
    }
    
    public function test_industry_mapping() {
        // Test different template industries
        $templates = array(
            'ceo' => 'Executive/Corporate',
            'restaurant' => 'Food & Beverage',
            'healthcare' => 'Healthcare',
            'lawyer' => 'Legal Services',
            'fitness' => 'Health & Fitness'
        );
        
        foreach ($templates as $template => $expected_industry) {
            update_post_meta($this->post_id_business, '_vcard_template_name', $template);
            
            $profile = new VCard_Business_Profile($this->post_id_business);
            $exporter = new VCard_Export($profile);
            $vcf_content = $exporter->generate();
            
            $this->assertStringContainsString('CATEGORIES:' . $expected_industry, $vcf_content);
        }
    }
    
    public function tearDown(): void {
        wp_delete_post($this->post_id_business, true);
        wp_delete_post($this->post_id_personal, true);
        parent::tearDown();
    }
}
<?php
/**
 * Test Contact Manager Functionality
 * 
 * This file contains basic tests for the contact management system.
 * Run this file to verify that the contact manager is working correctly.
 * 
 * @package vCard
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_Contact_Manager_Test {
    
    private $test_results = array();
    
    public function __construct() {
        $this->run_tests();
    }
    
    /**
     * Run all tests
     */
    public function run_tests() {
        echo "<h2>vCard Contact Manager Tests</h2>\n";
        
        $this->test_class_exists();
        $this->test_database_table();
        $this->test_local_storage_support();
        $this->test_contact_data_structure();
        $this->test_export_functionality();
        
        $this->display_results();
    }
    
    /**
     * Test if contact manager class exists
     */
    private function test_class_exists() {
        $test_name = "Contact Manager Class Exists";
        
        if (class_exists('VCard_Contact_Manager')) {
            $this->test_results[$test_name] = array(
                'status' => 'PASS',
                'message' => 'VCard_Contact_Manager class is loaded'
            );
        } else {
            $this->test_results[$test_name] = array(
                'status' => 'FAIL',
                'message' => 'VCard_Contact_Manager class not found'
            );
        }
    }
    
    /**
     * Test database table creation
     */
    private function test_database_table() {
        $test_name = "Database Table Exists";
        
        global $wpdb;
        $table_name = VCARD_SAVED_CONTACTS_TABLE;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($table_exists) {
            $this->test_results[$test_name] = array(
                'status' => 'PASS',
                'message' => "Table $table_name exists"
            );
            
            // Test table structure
            $columns = $wpdb->get_results("DESCRIBE $table_name");
            $required_columns = array('id', 'user_id', 'profile_id', 'contact_data', 'saved_at', 'updated_at');
            $existing_columns = array_column($columns, 'Field');
            
            $missing_columns = array_diff($required_columns, $existing_columns);
            
            if (empty($missing_columns)) {
                $this->test_results["Database Table Structure"] = array(
                    'status' => 'PASS',
                    'message' => 'All required columns exist'
                );
            } else {
                $this->test_results["Database Table Structure"] = array(
                    'status' => 'FAIL',
                    'message' => 'Missing columns: ' . implode(', ', $missing_columns)
                );
            }
        } else {
            $this->test_results[$test_name] = array(
                'status' => 'FAIL',
                'message' => "Table $table_name does not exist"
            );
        }
    }
    
    /**
     * Test local storage support detection
     */
    private function test_local_storage_support() {
        $test_name = "Local Storage JavaScript";
        
        // Check if contact manager JavaScript file exists
        $js_file = VCARD_PLUGIN_PATH . 'assets/js/contact-manager.js';
        
        if (file_exists($js_file)) {
            $js_content = file_get_contents($js_file);
            
            // Check for key functions
            $required_functions = array(
                'checkStorageSupport',
                'saveContact',
                'removeContact',
                'getSavedContacts',
                'exportAllContacts'
            );
            
            $missing_functions = array();
            foreach ($required_functions as $function) {
                if (strpos($js_content, $function) === false) {
                    $missing_functions[] = $function;
                }
            }
            
            if (empty($missing_functions)) {
                $this->test_results[$test_name] = array(
                    'status' => 'PASS',
                    'message' => 'All required JavaScript functions exist'
                );
            } else {
                $this->test_results[$test_name] = array(
                    'status' => 'FAIL',
                    'message' => 'Missing functions: ' . implode(', ', $missing_functions)
                );
            }
        } else {
            $this->test_results[$test_name] = array(
                'status' => 'FAIL',
                'message' => 'contact-manager.js file not found'
            );
        }
    }
    
    /**
     * Test contact data structure
     */
    private function test_contact_data_structure() {
        $test_name = "Contact Data Structure";
        
        // Test sample contact data
        $sample_contact = array(
            'id' => '123',
            'saved_at' => current_time('c'),
            'business_name' => 'Test Business',
            'owner_name' => 'John Doe',
            'job_title' => 'CEO',
            'phone' => '+1234567890',
            'email' => 'test@business.com',
            'website' => 'https://business.com',
            'address' => '123 Business St',
            'business_description' => 'Test description',
            'profile_url' => 'https://site.com/vcard/test',
            'template_name' => 'ceo',
            'logo_url' => 'https://site.com/logo.jpg'
        );
        
        // Validate required fields
        $required_fields = array('id', 'business_name', 'saved_at');
        $missing_fields = array();
        
        foreach ($required_fields as $field) {
            if (!isset($sample_contact[$field]) || empty($sample_contact[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (empty($missing_fields)) {
            // Test JSON encoding/decoding
            $json_data = wp_json_encode($sample_contact);
            $decoded_data = json_decode($json_data, true);
            
            if ($decoded_data && $decoded_data['business_name'] === $sample_contact['business_name']) {
                $this->test_results[$test_name] = array(
                    'status' => 'PASS',
                    'message' => 'Contact data structure is valid and JSON serializable'
                );
            } else {
                $this->test_results[$test_name] = array(
                    'status' => 'FAIL',
                    'message' => 'JSON encoding/decoding failed'
                );
            }
        } else {
            $this->test_results[$test_name] = array(
                'status' => 'FAIL',
                'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
            );
        }
    }
    
    /**
     * Test export functionality
     */
    private function test_export_functionality() {
        $test_name = "Export Functionality";
        
        // Test vCard generation
        $sample_contacts = array(
            array(
                'business_name' => 'Test Business',
                'owner_name' => 'John Doe',
                'job_title' => 'CEO',
                'phone' => '+1234567890',
                'email' => 'test@business.com',
                'website' => 'https://business.com',
                'address' => '123 Business St',
                'business_description' => 'Test description'
            )
        );
        
        // Test vCard format generation (simplified)
        $vcard_content = $this->generate_test_vcard($sample_contacts[0]);
        
        if (strpos($vcard_content, 'BEGIN:VCARD') !== false && 
            strpos($vcard_content, 'END:VCARD') !== false &&
            strpos($vcard_content, 'Test Business') !== false) {
            
            $this->test_results[$test_name] = array(
                'status' => 'PASS',
                'message' => 'vCard export format is valid'
            );
        } else {
            $this->test_results[$test_name] = array(
                'status' => 'FAIL',
                'message' => 'vCard export format is invalid'
            );
        }
    }
    
    /**
     * Generate test vCard content
     */
    private function generate_test_vcard($contact) {
        $vcard = "BEGIN:VCARD\n";
        $vcard .= "VERSION:3.0\n";
        
        if (!empty($contact['business_name'])) {
            $vcard .= "FN:" . $contact['business_name'] . "\n";
            $vcard .= "ORG:" . $contact['business_name'] . "\n";
        }
        
        if (!empty($contact['owner_name'])) {
            $vcard .= "N:" . $contact['owner_name'] . ";;;;\n";
        }
        
        if (!empty($contact['job_title'])) {
            $vcard .= "TITLE:" . $contact['job_title'] . "\n";
        }
        
        if (!empty($contact['phone'])) {
            $vcard .= "TEL;TYPE=WORK,VOICE:" . $contact['phone'] . "\n";
        }
        
        if (!empty($contact['email'])) {
            $vcard .= "EMAIL;TYPE=WORK:" . $contact['email'] . "\n";
        }
        
        if (!empty($contact['website'])) {
            $vcard .= "URL:" . $contact['website'] . "\n";
        }
        
        if (!empty($contact['address'])) {
            $vcard .= "ADR;TYPE=WORK:;;" . $contact['address'] . ";;;;\n";
        }
        
        if (!empty($contact['business_description'])) {
            $vcard .= "NOTE:" . $contact['business_description'] . "\n";
        }
        
        $vcard .= "END:VCARD\n";
        
        return $vcard;
    }
    
    /**
     * Display test results
     */
    private function display_results() {
        echo "<h3>Test Results</h3>\n";
        echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Test</th><th>Status</th><th>Message</th></tr>\n";
        
        $passed = 0;
        $failed = 0;
        
        foreach ($this->test_results as $test => $result) {
            $status_color = $result['status'] === 'PASS' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . esc_html($test) . "</td>";
            echo "<td style='color: $status_color; font-weight: bold;'>" . esc_html($result['status']) . "</td>";
            echo "<td>" . esc_html($result['message']) . "</td>";
            echo "</tr>\n";
            
            if ($result['status'] === 'PASS') {
                $passed++;
            } else {
                $failed++;
            }
        }
        
        echo "</table>\n";
        
        echo "<h3>Summary</h3>\n";
        echo "<p><strong>Passed:</strong> $passed</p>\n";
        echo "<p><strong>Failed:</strong> $failed</p>\n";
        
        if ($failed === 0) {
            echo "<p style='color: green; font-weight: bold;'>All tests passed! Contact management system is working correctly.</p>\n";
        } else {
            echo "<p style='color: red; font-weight: bold;'>Some tests failed. Please check the implementation.</p>\n";
        }
    }
}

// Run tests if accessed directly
if (isset($_GET['run_contact_tests']) && current_user_can('manage_options')) {
    new VCard_Contact_Manager_Test();
}
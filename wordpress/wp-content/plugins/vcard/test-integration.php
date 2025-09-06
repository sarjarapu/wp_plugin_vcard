<?php
/**
 * Simple integration test for template customizer
 */

// Mock WordPress environment for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('VCARD_PLUGIN_PATH')) {
    define('VCARD_PLUGIN_PATH', __DIR__ . '/');
}

if (!defined('VCARD_ASSETS_URL')) {
    define('VCARD_ASSETS_URL', 'assets/');
}

// Mock WordPress functions
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current, $echo = true) {
        $result = ($selected == $current) ? ' selected="selected"' : '';
        if ($echo) echo $result;
        return $result;
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current, $echo = true) {
        $result = ($checked == $current) ? ' checked="checked"' : '';
        if ($echo) echo $result;
        return $result;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action, $name) {
        echo '<input type="hidden" name="' . $name . '" value="test_nonce" />';
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false) {
        // Mock data for testing
        $mock_data = array(
            '_vcard_template_name' => 'ceo',
            '_vcard_color_scheme' => 'corporate_blue',
            '_vcard_industry' => 'business'
        );
        
        return isset($mock_data[$key]) ? $mock_data[$key] : '';
    }
}

// Mock post object
$post = (object) array('ID' => 1);

// Load the customizer class
require_once 'includes/class-template-customizer.php';

// Test the customizer
try {
    $customizer = new VCard_Template_Customizer();
    echo "✓ Template Customizer class loaded successfully\n";
    
    // Test industry palettes
    $palettes = $customizer->get_industry_palettes();
    if (!empty($palettes) && isset($palettes['professional'])) {
        echo "✓ Industry palettes loaded successfully\n";
    } else {
        echo "✗ Industry palettes not loaded properly\n";
    }
    
    // Test color scheme retrieval
    $scheme = $customizer->get_color_scheme('corporate_blue');
    if ($scheme && isset($scheme['primary'])) {
        echo "✓ Color scheme retrieval working\n";
    } else {
        echo "✗ Color scheme retrieval failed\n";
    }
    
    // Test CSS generation
    $css = $customizer->generate_color_scheme_css('corporate_blue', 'ceo');
    if (strpos($css, '--primary-color') !== false) {
        echo "✓ CSS generation working\n";
    } else {
        echo "✗ CSS generation failed\n";
    }
    
    echo "\n✓ Integration test completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
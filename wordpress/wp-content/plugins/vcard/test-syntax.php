<?php
/**
 * Simple syntax test for vCard plugin
 * Run this file to check for PHP syntax errors
 */

// Test if the main plugin file has syntax errors
$plugin_file = __DIR__ . '/vcard.php';

if (file_exists($plugin_file)) {
    $output = shell_exec("php -l $plugin_file 2>&1");
    
    if (strpos($output, 'No syntax errors detected') !== false) {
        echo "✅ SUCCESS: No syntax errors detected in vcard.php\n";
        echo $output . "\n";
    } else {
        echo "❌ ERROR: Syntax errors found in vcard.php\n";
        echo $output . "\n";
    }
} else {
    echo "❌ ERROR: Plugin file not found at $plugin_file\n";
}

// Test class loading
echo "\n--- Testing Class Loading ---\n";

// Temporarily define WordPress constants if not defined
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Mock WordPress functions if not available
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) { return 'http://example.com/wp-content/plugins/vcard/'; }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) { return __DIR__ . '/'; }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) { return 'vcard/vcard.php'; }
}

try {
    // Test if we can include the file without fatal errors
    ob_start();
    include_once $plugin_file;
    $output = ob_get_clean();
    
    if (class_exists('VCardPlugin')) {
        echo "✅ SUCCESS: VCardPlugin class loaded successfully\n";
        
        // Check if the problematic method exists and is not duplicated
        $reflection = new ReflectionClass('VCardPlugin');
        $methods = $reflection->getMethods();
        
        $track_event_methods = array_filter($methods, function($method) {
            return $method->getName() === 'handle_track_event';
        });
        
        if (count($track_event_methods) === 1) {
            echo "✅ SUCCESS: handle_track_event method exists exactly once\n";
        } else {
            echo "❌ ERROR: handle_track_event method found " . count($track_event_methods) . " times\n";
        }
        
    } else {
        echo "❌ ERROR: VCardPlugin class not found\n";
    }
    
    if (!empty($output)) {
        echo "Output during loading:\n$output\n";
    }
    
} catch (ParseError $e) {
    echo "❌ PARSE ERROR: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\n--- Test Complete ---\n";
?>
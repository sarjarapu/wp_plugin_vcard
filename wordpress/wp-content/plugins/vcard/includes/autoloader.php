<?php
/**
 * Simple Autoloader for VCard Classes
 * 
 * @package VCard
 * @version 1.0.0
 */

spl_autoload_register(function ($class) {
    // Check if this is a VCard namespace class
    if (strpos($class, 'VCard\\') !== 0) {
        return;
    }
    
    // Remove namespace prefix
    $class_name = str_replace('VCard\\', '', $class);
    
    // Convert to file path
    $file = VCARD_INCLUDES_PATH . $class_name . '.php';
    
    // Load the file if it exists
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load Composer autoloader if available
$composer_autoload = VCARD_PLUGIN_PATH . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}
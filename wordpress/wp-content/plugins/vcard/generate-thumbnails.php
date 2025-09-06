<?php
/**
 * Generate template thumbnails
 * Run this script once to generate SVG thumbnails for all templates
 */

// Define WordPress constants for this script
define('VCARD_PLUGIN_PATH', __DIR__ . '/');
define('VCARD_ASSETS_URL', 'assets/');

require_once 'includes/class-template-thumbnail-generator.php';

VCard_Template_Thumbnail_Generator::generate_all_thumbnails();

echo "Template thumbnails generated successfully!\n";
?>
<?php

/**
 * Plugin Name: Minisite Manager
 * Description: Internal plugin powering business mini-sites with revisions, templates,
 *              and optional Timber/Twig rendering.
 * Version:     1.1.0
 * Author:      Shyam Arjarapu
 * Company:     Nimble AI Services LLC
 * License:     Proprietary
 * Text Domain: minisite-manager
 *
 * @package MinisiteManager
 */

if (! defined('ABSPATH')) {
    exit; // phpcs:ignore WordPress.Security.Exit
}

// Define plugin constants
define('MINISITE_PLUGIN_FILE', __FILE__);
define('MINISITE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MINISITE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MINISITE_VERSION', '1.1.0');
define('MINISITE_DB_VERSION', '1.1.0');
define('MINISITE_DB_OPTION', 'minisite_db_version');
define('MINISITE_DEFAULT_TEMPLATE', 'v2025');

// Environment: set to true in production to prevent destructive dev resets
if (! defined('MINISITE_LIVE_PRODUCTION')) {
    define('MINISITE_LIVE_PRODUCTION', false);
}

// Autoloader
$autoload = MINISITE_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Initialize the plugin
Minisite\Core\PluginBootstrap::initialize();

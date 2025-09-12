<?php
/**
 * Plugin Name: Minisite Manager
 * Description: Internal plugin powering business mini-sites (profiles) with revisions, templates, and optional Timber/Twig rendering.
 * Version:     1.0.0
 * Author:      Shyam Arjarapu
 * Company:     Nimble AI Services LLC
 * License:     Proprietary
 * Text Domain: minisite-manager
 *
 * @package MinisiteManager
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Constants
 */
define('MINISITE_PLUGIN_FILE', __FILE__);
define('MINISITE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MINISITE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MINISITE_DB_VERSION', '1.0.0');        // target schema version
define('MINISITE_DB_OPTION',  'minisite_db_version');
define('MINISITE_DEFAULT_TEMPLATE', 'v2025');
// Environment: set to true in production to prevent destructive dev resets
if (!defined('MINISITE_LIVE_PRODUCTION')) {
  define('MINISITE_LIVE_PRODUCTION', false);
}

/**
 * Autoload (Composer)
 */
$autoload = MINISITE_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
}

/**
 * Safe helpers (no fatal if classes not yet created during scaffolding)
 */
function minisite_class($class) {
  return class_exists($class) ? $class : null;
}

/**
 * Activation / Deactivation
 */
register_activation_hook(__FILE__, function () {
  // In non-production, optionally drop and reset before migrating (dev convenience)
  if (!MINISITE_LIVE_PRODUCTION) {
    global $wpdb;
    if (class_exists(\Minisite\Infrastructure\Versioning\Migrations\_1_0_0_CreateBase::class)) {
      $m = new \Minisite\Infrastructure\Versioning\Migrations\_1_0_0_CreateBase();
      $m->down($wpdb);
    }
    // Ensure runner applies base migration
    update_option(MINISITE_DB_OPTION, '0.0.0', false);
  }

  // Apply DB migrations
  if ($vcClass = minisite_class(\Minisite\Infrastructure\Versioning\VersioningController::class)) {
    $vc = new $vcClass(MINISITE_DB_VERSION, MINISITE_DB_OPTION);
    $vc->activate();
  }

  // Ensure rewrites are registered before flush
  do_action('minisite/register_rewrites');
  flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
  // In non-production, drop plugin tables and clear version option on deactivation
  if (!MINISITE_LIVE_PRODUCTION) {
    global $wpdb;
    if (class_exists(\Minisite\Infrastructure\Versioning\Migrations\_1_0_0_CreateBase::class)) {
      $m = new \Minisite\Infrastructure\Versioning\Migrations\_1_0_0_CreateBase();
      $m->down($wpdb);
    }
    delete_option(MINISITE_DB_OPTION);
  }
  flush_rewrite_rules();
});

/**
 * On admin init, run pending migrations (cheap version compare)
 */
add_action('admin_init', function () {
  if ($vcClass = minisite_class(\Minisite\Infrastructure\Versioning\VersioningController::class)) {
    $vc = new $vcClass(MINISITE_DB_VERSION, MINISITE_DB_OPTION);
    $vc->maybeRun();
  }
});

/**
 * Register rewrites for /b/{business}/{location}
 */
add_action('init', function () {
  /**
   * You’ll implement RewriteRegistrar to:
   * - add_rewrite_tag('%minisite_biz%','([^&]+)')
   * - add_rewrite_tag('%minisite_loc%','([^&]+)')
   * - add_rewrite_rule('^b/([^/]+)/([^/]+)/?$', 'index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]', 'top');
   */
  if ($rrClass = minisite_class(\Minisite\Application\Http\RewriteRegistrar::class)) {
    $rr = new $rrClass();
    $rr->register();
  }

  /**
   * Fallback: lightweight inline registration until class exists
   * (Safe to remove once RewriteRegistrar is implemented.)
   */
  if (!taxonomy_exists('minisite_stub')) {
    add_rewrite_tag('%minisite%', '([0-1])');
    add_rewrite_tag('%minisite_biz%', '([^&]+)');
    add_rewrite_tag('%minisite_loc%', '([^&]+)');
    add_rewrite_rule('^b/([^/]+)/([^/]+)/?$', 'index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]', 'top');
  }
}, 5);

/**
 * Map our query to a front controller if minisite=1
 */
add_action('parse_query', function (\WP_Query $q) {
  if (!is_admin() && isset($q->query_vars['minisite']) && (int)$q->query_vars['minisite'] === 1) {
    // Prevent WP from trying to load a post
    $q->is_home = false;
    $q->is_page = false;
    $q->is_singular = false;
    $q->is_404 = false;
  }
});

/**
 * Template redirect (render the minisite page)
 */
add_action('template_redirect', function () {
  if ((int) get_query_var('minisite') !== 1) {
    return;
  }

  $biz = get_query_var('minisite_biz');
  $loc = get_query_var('minisite_loc');

  // Resolve renderer: Timber if available, else PHP renderer
  $renderer = null;
  if (class_exists('Timber\Timber') && minisite_class(\Minisite\Application\Rendering\TimberRenderer::class)) {
    $renderer = new \Minisite\Application\Rendering\TimberRenderer(MINISITE_DEFAULT_TEMPLATE);
  } elseif ($phpRenderer = minisite_class(\Minisite\Application\Rendering\PhpRenderer::class)) {
    $renderer = new $phpRenderer(MINISITE_DEFAULT_TEMPLATE);
  }

  // Controller to build the view model
  if ($ctrlClass = minisite_class(\Minisite\Application\Controllers\Front\ProfilePageController::class)) {
    $ctrl = new $ctrlClass($renderer);
    $ctrl->handle($biz, $loc);
    exit;
  }

  // Temporary fallback: simple 503 until controllers are added
  status_header(503);
  nocache_headers();
  echo '<!doctype html><meta charset="utf-8"><title>Minisite</title><h1>Minisite route detected</h1><p>Scaffold the controllers/renderers to complete rendering.</p>';
  exit;
});

/**
 * Allow our custom query vars
 */
add_filter('query_vars', function (array $vars) {
  $vars[] = 'minisite';
  $vars[] = 'minisite_biz';
  $vars[] = 'minisite_loc';
  return $vars;
});

/**
 * Enqueue base assets (optional; template-specific assets are loaded by renderer)
 */
add_action('wp_enqueue_scripts', function () {
  // Example: enqueue a tiny base CSS if needed
  // wp_enqueue_style('minisite-base', MINISITE_PLUGIN_URL . 'assets/css/base.css', [], '1.0.0');
});

<?php
/**
 * @codingStandardsIgnoreFile
 * Main plugin file with mixed declarations and side effects - this is acceptable for WordPress plugin main files
 */

/**
 * Plugin Name: Minisite Manager
 * Description: Internal plugin powering business mini-sites (profiles) with revisions, templates,
 *              and optional Timber/Twig rendering.
 * Version:     1.1.0
 * Author:      Shyam Arjarapu
 * Company:     Nimble AI Services LLC
 * License:     Proprietary
 * Text Domain: minisite-manager
 *
 * @package MinisiteManager
 */

use Minisite\Application\Controllers\Admin\SubscriptionController;
use Minisite\Application\Controllers\Front\AuthController;
use Minisite\Application\Controllers\Front\MinisitePageController;
use Minisite\Application\Controllers\Front\NewMinisiteController;
use Minisite\Application\Controllers\Front\SitesController;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;
use Minisite\Application\Controllers\Front\VersionController;
use Minisite\Application\Http\RewriteRegistrar;
use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Domain\Entities\Version;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use Minisite\Infrastructure\Utils\ReservationCleanup;
use Minisite\Infrastructure\Versioning\Migrations\_1_0_0_CreateBase;
use Minisite\Infrastructure\Versioning\VersioningController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Constants
 */
define('MINISITE_PLUGIN_FILE', __FILE__);
define('MINISITE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MINISITE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MINISITE_DB_VERSION', '1.1.0');        // target schema version
define('MINISITE_DB_OPTION', 'minisite_db_version');
define('MINISITE_DEFAULT_TEMPLATE', 'v2025');
// Environment: set to true in production to prevent destructive dev resets
if (!defined('MINISITE_LIVE_PRODUCTION')) {
    define('MINISITE_LIVE_PRODUCTION', false);
}

// === Capability strings (use constants for consistency) ===
define('MINISITE_CAP_READ', 'minisite_read');
define('MINISITE_CAP_CREATE', 'minisite_create');
define('MINISITE_CAP_PUBLISH', 'minisite_publish');
define('MINISITE_CAP_EDIT_OWN', 'minisite_edit_own');
define('MINISITE_CAP_DELETE_OWN', 'minisite_delete_own');
define('MINISITE_CAP_EDIT_ASSIGNED', 'minisite_edit_assigned');
define('MINISITE_CAP_EDIT_ANY', 'minisite_edit_any');
define('MINISITE_CAP_DELETE_ANY', 'minisite_delete_any');
define('MINISITE_CAP_READ_PRIVATE', 'minisite_read_private');

define('MINISITE_CAP_VIEW_CONTACT_REPORTS_OWN', 'minisite_view_contact_reports_own');
define('MINISITE_CAP_VIEW_CONTACT_REPORTS_ALL', 'minisite_view_contact_reports_all');
define('MINISITE_CAP_VIEW_REVENUE_REPORTS', 'minisite_view_revenue_reports');

define('MINISITE_CAP_GENERATE_DISCOUNTS', 'minisite_generate_discounts');
define('MINISITE_CAP_APPLY_DISCOUNTS', 'minisite_apply_discounts');
define('MINISITE_CAP_MANAGE_REFERRALS', 'minisite_manage_referrals');

define('MINISITE_CAP_SAVE_CONTACT', 'minisite_save_contact');
define('MINISITE_CAP_VIEW_SAVED_CONTACTS', 'minisite_view_saved_contacts');

define('MINISITE_CAP_VIEW_BILLING', 'minisite_view_billing');
define('MINISITE_CAP_MANAGE_PLUGIN', 'minisite_manage_plugin');

// === Role slugs ===
define('MINISITE_ROLE_USER', 'minisite_user');
define('MINISITE_ROLE_MEMBER', 'minisite_member');
define('MINISITE_ROLE_POWER', 'minisite_power');
define('MINISITE_ROLE_ADMIN', 'minisite_admin');

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
function minisite_class($class)
{
    return class_exists($class) ? $class : null;
}

/**
 * Activation / Deactivation
 */
register_activation_hook(__FILE__, function () {
  // Sync roles and capabilities on activation
    minisite_sync_roles_and_caps();
  // In non-production, optionally drop and reset before migrating (dev convenience)
    if (!MINISITE_LIVE_PRODUCTION) {
        global $wpdb;
        if (class_exists(_1_0_0_CreateBase::class)) {
            $m = new _1_0_0_CreateBase();
            $m->down();
        }
      // Ensure runner applies base migration
        update_option(MINISITE_DB_OPTION, '0.0.0', false);
    }

  // Apply DB migrations
    if ($vcClass = minisite_class(VersioningController::class)) {
        $vc = new $vcClass(MINISITE_DB_VERSION, MINISITE_DB_OPTION);
        $vc->activate();
    }

  // Ensure roles and capabilities exist and are synced
    if (function_exists('minisite_sync_roles_and_caps')) {
        minisite_sync_roles_and_caps();
    }

  // Defer rewrite flush until after init has registered our rules
    update_option('minisite_flush_rewrites', 1, false);
});

register_deactivation_hook(__FILE__, function () {
  // In non-production, drop plugin tables and clear version option on deactivation
    if (!MINISITE_LIVE_PRODUCTION) {
        global $wpdb;
        if (class_exists(_1_0_0_CreateBase::class)) {
            $m = new _1_0_0_CreateBase();
            $m->down();
        }
        delete_option(MINISITE_DB_OPTION);
    }
    flush_rewrite_rules();

  // Optional cleanup of custom roles in non-production
    if (!MINISITE_LIVE_PRODUCTION) {
        foreach ([MINISITE_ROLE_USER, MINISITE_ROLE_MEMBER, MINISITE_ROLE_POWER, MINISITE_ROLE_ADMIN] as $roleSlug) {
            remove_role($roleSlug);
        }
    }
});

/**
 * On admin init, run pending migrations (cheap version compare)
 */
add_action('admin_init', function () {
    if ($vcClass = minisite_class(VersioningController::class)) {
        $vc = new $vcClass(MINISITE_DB_VERSION, MINISITE_DB_OPTION);
        $vc->ensureDatabaseUpToDate();
    }

  // Keep caps synchronized for existing roles (safe, idempotent)
    if (function_exists('minisite_sync_roles_and_caps')) {
        minisite_sync_roles_and_caps();
    }
});

/**
 * Roles & Capabilities — registration and meta-cap mapping
 */

/**
 * Return the list of all Minisite capability slugs
 */
function minisite_all_caps(): array
{
    return [
    MINISITE_CAP_READ,
    MINISITE_CAP_CREATE,
    MINISITE_CAP_PUBLISH,
    MINISITE_CAP_EDIT_OWN,
    MINISITE_CAP_DELETE_OWN,
    MINISITE_CAP_EDIT_ASSIGNED,
    MINISITE_CAP_EDIT_ANY,
    MINISITE_CAP_DELETE_ANY,
    MINISITE_CAP_READ_PRIVATE,
    MINISITE_CAP_VIEW_CONTACT_REPORTS_OWN,
    MINISITE_CAP_VIEW_CONTACT_REPORTS_ALL,
    MINISITE_CAP_VIEW_REVENUE_REPORTS,
    MINISITE_CAP_GENERATE_DISCOUNTS,
    MINISITE_CAP_APPLY_DISCOUNTS,
    MINISITE_CAP_MANAGE_REFERRALS,
    MINISITE_CAP_SAVE_CONTACT,
    MINISITE_CAP_VIEW_SAVED_CONTACTS,
    MINISITE_CAP_VIEW_BILLING,
    MINISITE_CAP_MANAGE_PLUGIN,
    ];
}

/**
 * Create/update custom roles and assign capabilities. Also grants all minisite caps to WP Administrator.
 * Idempotent and safe to call more than once.
 */
function minisite_sync_roles_and_caps(): void
{
  // Role capability maps
    $userCaps = [
    'read' => true,
    MINISITE_CAP_READ => true,
    MINISITE_CAP_CREATE => true,
    MINISITE_CAP_EDIT_OWN => true,
    MINISITE_CAP_DELETE_OWN => true,
    MINISITE_CAP_SAVE_CONTACT => true,
    MINISITE_CAP_VIEW_SAVED_CONTACTS => true,
    MINISITE_CAP_APPLY_DISCOUNTS => true,
    ];

    $memberCaps = $userCaps + [
    MINISITE_CAP_PUBLISH => true,
    MINISITE_CAP_READ_PRIVATE => true,
    MINISITE_CAP_VIEW_CONTACT_REPORTS_OWN => true,
    MINISITE_CAP_MANAGE_REFERRALS => true,
    // WordPress AJAX capabilities
    'edit_posts' => true,
    'upload_files' => true,
    ];

    $powerCaps = $memberCaps + [
    MINISITE_CAP_EDIT_ASSIGNED => true,
    MINISITE_CAP_EDIT_ANY => true,
    MINISITE_CAP_DELETE_ANY => true,
    MINISITE_CAP_VIEW_CONTACT_REPORTS_ALL => true,
    MINISITE_CAP_VIEW_REVENUE_REPORTS => true,
    MINISITE_CAP_GENERATE_DISCOUNTS => true,
    MINISITE_CAP_VIEW_BILLING => true,
    ];

    $adminCaps = $powerCaps + [
    MINISITE_CAP_MANAGE_PLUGIN => true,
    ];

  // Add or update roles
    minisite_add_or_update_role(MINISITE_ROLE_USER, 'Minisite User', $userCaps);
    minisite_add_or_update_role(MINISITE_ROLE_MEMBER, 'Minisite Member', $memberCaps);
    minisite_add_or_update_role(MINISITE_ROLE_POWER, 'Minisite Power', $powerCaps);
    minisite_add_or_update_role(MINISITE_ROLE_ADMIN, 'Minisite Admin', $adminCaps);

  // Ensure WordPress Administrator always has all minisite caps
    if ($wpAdmin = get_role('administrator')) {
        foreach (minisite_all_caps() as $cap) {
            $wpAdmin->add_cap($cap);
        }
    }
}

/**
 * Helper to add a role if missing, then ensure capabilities are present.
 */
function minisite_add_or_update_role(string $slug, string $name, array $caps): void
{
    $role = get_role($slug);
    if (!$role) {
        add_role($slug, $name, $caps);
        $role = get_role($slug);
    }
    if ($role) {
        foreach ($caps as $cap => $grant) {
            if ($grant) {
                $role->add_cap($cap);
            }
          // We do not remove caps here to avoid accidental revocation during upgrades
        }
    }
}

/**
 * Meta-cap mapping for object-specific checks on custom tables.
 * Enables calls like current_user_can('minisite_edit_profile', $minisiteId).
 */
add_filter('map_meta_cap', function (array $caps, string $cap, int $user_id, array $args) {
    $objectId = isset($args[0]) ? intval($args[0]) : 0;

    switch ($cap) {
        case 'minisite_edit_profile':
          // Full editors
            if (user_can($user_id, MINISITE_CAP_EDIT_ANY)) {
                return [MINISITE_CAP_EDIT_ANY];
            }
          // Assigned editors
            if (
                $objectId && minisite_user_is_assigned_to_profile($user_id, $objectId) &&
                user_can($user_id, MINISITE_CAP_EDIT_ASSIGNED)
            ) {
                return [MINISITE_CAP_EDIT_ASSIGNED];
            }
          // Owners
            if (
                $objectId && minisite_user_owns_profile($user_id, $objectId) &&
                user_can($user_id, MINISITE_CAP_EDIT_OWN)
            ) {
                return [MINISITE_CAP_EDIT_OWN];
            }
            return ['do_not_allow'];

        case 'minisite_delete_profile':
            if (user_can($user_id, MINISITE_CAP_DELETE_ANY)) {
                return [MINISITE_CAP_DELETE_ANY];
            }
            if (
                $objectId && minisite_user_owns_profile($user_id, $objectId) &&
                user_can($user_id, MINISITE_CAP_DELETE_OWN)
            ) {
                return [MINISITE_CAP_DELETE_OWN];
            }
            return ['do_not_allow'];

        case 'minisite_publish_profile':
            return user_can($user_id, MINISITE_CAP_PUBLISH) ? [MINISITE_CAP_PUBLISH] : ['do_not_allow'];

        case 'minisite_read_profile':
          // Public read is allowed if profile is published; otherwise require ownership/assignment or read_private
            if ($objectId && minisite_profile_is_public($objectId)) {
                return ['exist'];
            }
            if (user_can($user_id, MINISITE_CAP_READ_PRIVATE)) {
                return [MINISITE_CAP_READ_PRIVATE];
            }
            if (
                $objectId && (minisite_user_owns_profile($user_id, $objectId) ||
                minisite_user_is_assigned_to_profile($user_id, $objectId))
            ) {
                return ['exist'];
            }
            return ['do_not_allow'];
    }

    return $caps;
}, 10, 4);

/**
 * The following helper stubs allow your domain layer to determine ownership/assignment/publication
 * for profiles stored in custom tables. Hook or implement them using repositories.
 */
function minisite_user_owns_profile(int $userId, string $minisiteId): bool
{
  /**
   * Implement by querying your profiles table (e.g., owner_user_id column)
   * or hook via: add_filter('minisite_user_owns_profile', fn($v,$uid,$pid)=>bool, 10, 3)
   */
    return (bool) apply_filters('minisite_user_owns_profile', false, $userId, $minisiteId);
}

function minisite_user_is_assigned_to_profile(int $userId, string $minisiteId): bool
{
  /**
   * Implement by querying an assignment table/profile_editors relation
   * or hook via: add_filter('minisite_user_is_assigned_to_profile', fn($v,$uid,$pid)=>bool, 10, 3)
   */
    return (bool) apply_filters('minisite_user_is_assigned_to_profile', false, $userId, $minisiteId);
}

function minisite_profile_is_public(string $minisiteId): bool
{
  /**
   * Implement by checking profile status (e.g., published flag) in your custom table
   * or hook via: add_filter('minisite_profile_is_public', fn($v,$pid)=>bool, 10, 2)
   */
    return (bool) apply_filters('minisite_profile_is_public', false, $minisiteId);
}

/**
 * Implement the permission helper functions using database queries
 */
add_filter('minisite_user_owns_profile', function (bool $default, int $userId, string $minisiteId): bool {
    global $wpdb;

  // Check if user owns the profile (using created_by as owner surrogate for now)
    $ownerId = db::get_var(
        "SELECT created_by FROM {$wpdb->prefix}minisites WHERE id = %s",
        [$minisiteId]
    );

    return $ownerId && (int) $ownerId === $userId;
}, 10, 3);

add_filter('minisite_user_is_assigned_to_profile', function (bool $default, int $userId, string $minisiteId): bool {
  // For now, no assignment system is implemented
  // This would check an assignment table or profile_editors relation
    return false;
}, 10, 3);

add_filter('minisite_profile_is_public', function (bool $default, string $minisiteId): bool {
    global $wpdb;

  // Check if profile is published
    $status = db::get_var(
        "SELECT status FROM {$wpdb->prefix}minisites WHERE id = %s",
        [$minisiteId]
    );

    return $status === 'published';
}, 10, 2);

/**
 * Register rewrites for /b/{business}/{location}
 */
add_action('init', function () {
  /**
   * You’ll implement RewriteRegistrar to:
   * - add_rewrite_tag('%minisite_biz%','([^&]+)')
   * - add_rewrite_tag('%minisite_loc%','([^&]+)')
   * - add_rewrite_rule('^b/([^/]+)/([^/]+)/?$',
   *   'index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]', 'top');
   */
    if ($rrClass = minisite_class(RewriteRegistrar::class)) {
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
        add_rewrite_rule(
            '^b/([^/]+)/([^/]+)/?$',
            'index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]',
            'top'
        );
    }
  // One-time flush after activation to avoid manual Permalinks save
    if (get_option('minisite_flush_rewrites')) {
        flush_rewrite_rules();
        delete_option('minisite_flush_rewrites');
    }
}, 5);

/**
 * Map our query to a front controller if minisite=1 or minisite_account=1
 */
add_action('parse_query', function (WP_Query $q) {
    if (!is_admin()) {
      // Handle minisite profile routes
        if (isset($q->query_vars['minisite']) && (int)$q->query_vars['minisite'] === 1) {
            $q->is_home = false;
            $q->is_page = false;
            $q->is_singular = false;
            $q->is_404 = false;
        }

      // Handle account authentication routes
        if (isset($q->query_vars['minisite_account']) && (int)$q->query_vars['minisite_account'] === 1) {
            $q->is_home = false;
            $q->is_page = false;
            $q->is_singular = false;
            $q->is_404 = false;
        }
    }
});

/**
 * Template redirect (render the minisite page or account pages)
 */
add_action('template_redirect', function () {
  // Handle account authentication routes
    if ((int) get_query_var('minisite_account') === 1) {
        $action = get_query_var('minisite_account_action');

      // Resolve renderer: Timber if available
        $renderer = null;
        if (class_exists('Timber\Timber') && minisite_class(TimberRenderer::class)) {
            $renderer = new TimberRenderer(MINISITE_DEFAULT_TEMPLATE);
        }

      // Handle authentication actions
        if ($authCtrlClass = minisite_class(AuthController::class)) {
            $authCtrl = new $authCtrlClass($renderer);

            switch ($action) {
                case 'login':
                    $authCtrl->handleLogin();
                    break;
                case 'register':
                    $authCtrl->handleRegister();
                    break;
                case 'dashboard':
                    $authCtrl->handleDashboard();
                    break;
                case 'sites':
                  // Delegate to SitesController
                    if (
                        $sitesCtrlClass = minisite_class(
                            SitesController::class
                        )
                    ) {
                        $sitesCtrl = new $sitesCtrlClass($renderer);
                        $sitesCtrl->handleList();
                        break;
                    }
                  // Fallback if SitesController missing
                    status_header(503);
                    nocache_headers();
                    echo '<!doctype html><meta charset="utf-8"><title>Account</title>' .
                         '<h1>Sites listing unavailable</h1>';
                    exit;
                case 'new':
                  // Delegate to NewMinisiteController
                    if (
                        $newMinisiteCtrlClass = minisite_class(
                            NewMinisiteController::class
                        )
                    ) {
                        global $wpdb;
                        $profileRepo = new MinisiteRepository($wpdb);
                        $versionRepo = new VersionRepository($wpdb);
                        $newMinisiteCtrl = new $newMinisiteCtrlClass($profileRepo, $versionRepo);

                      // Handle form submission
                        if (
                            isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' &&
                            isset($_POST['minisite_nonce']) &&
                            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['minisite_nonce'])), 'minisite_nonce')
                        ) {
                              $newMinisiteCtrl->handleCreateSimple();
                        } else {
                            $newMinisiteCtrl->handleNew();
                        }
                        break;
                    }
                  // Fallback if NewMinisiteController missing
                    status_header(503);
                    nocache_headers();
                    echo '<!doctype html><meta charset="utf-8"><title>Account</title>' .
                         '<h1>New minisite creation unavailable</h1>';
                    exit;
                case 'publish':
                  // Handle publish page
                    if (class_exists('Timber\\Timber')) {
                        $viewsBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
                        $componentsBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/components';
                        \Timber\Timber::$locations = array_values(
                            array_unique(
                                array_merge(
                                    \Timber\Timber::$locations ?? [],
                                    [$viewsBase, $componentsBase]
                                )
                            )
                        );

                      // Get minisite ID from URL parameter or query var
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for display only, authorization checked below
                        $minisiteId = sanitize_text_field(
                            wp_unslash($_GET['minisite_id'] ?? get_query_var('minisite_site_id') ?? '')
                        );

                        if (empty($minisiteId)) {
                              wp_redirect(home_url('/account/sites'));
                              exit;
                        }

                      // Verify user owns this minisite
                        if (is_user_logged_in()) {
                            global $wpdb;
                            $repo = new MinisiteRepository($wpdb);
                            $minisite = $repo->findById($minisiteId);

                            if (!$minisite || $minisite->createdBy !== get_current_user_id()) {
                                wp_redirect(home_url('/account/sites'));
                                exit;
                            }
                        } else {
                            $redirect_url = home_url(
                                '/account/login?redirect_to=' . urlencode(
                                    isset($_SERVER['REQUEST_URI']) ?
                                    sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : ''
                                )
                            );
                            wp_redirect($redirect_url);
                            exit;
                        }

                        \Timber\Timber::render('account-sites-publish.twig', [
                        'page_title' => 'Publish Your Minisite',
                        'page_subtitle' => 'Choose your permanent URL and make your minisite live',
                        'minisite_id' => $minisiteId
                        ]);
                    } else {
                        status_header(503);
                        nocache_headers();
                        echo '<!doctype html><meta charset="utf-8"><title>Account</title>' .
                             '<h1>Publish page unavailable</h1>';
                    }
                    exit;
                case 'edit':
                  // Delegate to SitesController for editing
                    if (
                        $sitesCtrlClass = minisite_class(
                            SitesController::class
                        )
                    ) {
                        $sitesCtrl = new $sitesCtrlClass($renderer);
                        $sitesCtrl->handleEdit();
                        break;
                    }
                  // Fallback if SitesController missing
                    status_header(503);
                    nocache_headers();
                    echo '<!doctype html><meta charset="utf-8"><title>Account</title>' .
                         '<h1>Edit unavailable</h1>';
                    exit;
                case 'preview':
                  // Delegate to SitesController for previewing
                    if (
                        $sitesCtrlClass = minisite_class(
                            SitesController::class
                        )
                    ) {
                        $sitesCtrl = new $sitesCtrlClass($renderer);
                        $sitesCtrl->handlePreview();
                        break;
                    }
                  // Fallback if SitesController missing
                    status_header(503);
                    nocache_headers();
                    echo '<!doctype html><meta charset="utf-8"><title>Account</title>' .
                         '<h1>Preview unavailable</h1>';
                    exit;
                case 'versions':
                  // Delegate to VersionController for version management
                    if (
                        $versionCtrlClass = minisite_class(
                            VersionController::class
                        )
                    ) {
                        global $wpdb;
                        $minisiteRepo = new MinisiteRepository($wpdb);
                        $versionRepo = new VersionRepository($wpdb);
                        $versionCtrl = new $versionCtrlClass($minisiteRepo, $versionRepo);
                        $versionCtrl->handleListVersions();
                        break;
                    }
                  // Fallback if VersionController missing
                    status_header(503);
                    nocache_headers();
                    echo '<!doctype html><meta charset="utf-8"><title>Account</title>' .
                         '<h1>Version management unavailable</h1>';
                    exit;
                case 'logout':
                    $authCtrl->handleLogout();
                    break;
                case 'forgot':
                    $authCtrl->handleForgotPassword();
                    break;
                default:
                    wp_redirect(home_url('/account/login'));
                    exit;
            }
            exit;
        }

      // Fallback if AuthController not available
        status_header(503);
        nocache_headers();
        echo '<!doctype html><meta charset="utf-8"><title>Account</title>' .
             '<h1>Account system not available</h1><p>AuthController not found.</p>';
        exit;
    }

  // Handle minisite profile routes
    if ((int) get_query_var('minisite') === 1) {
        $biz = get_query_var('minisite_biz');
        $loc = get_query_var('minisite_loc');

      // Use Timber renderer (Timber is always available)
        $renderer = null;
        if (class_exists('Timber\Timber') && minisite_class(TimberRenderer::class)) {
            $renderer = new TimberRenderer(MINISITE_DEFAULT_TEMPLATE);
        }

      // Controller to build the view model
        if ($ctrlClass = minisite_class(MinisitePageController::class)) {
            $ctrl = new $ctrlClass($renderer);
            $ctrl->handle($biz, $loc);
            exit;
        }

      // Temporary fallback: simple 503 until controllers are added
        status_header(503);
        nocache_headers();
        echo '<!doctype html><meta charset="utf-8"><title>Minisite</title>' .
             '<h1>Minisite route detected</h1>' .
             '<p>Scaffold the controllers/renderers to complete rendering.</p>';
        exit;
    }
});

/**
 * Allow our custom query vars
 */
add_filter('query_vars', function (array $vars) {
    $vars[] = 'minisite';
    $vars[] = 'minisite_biz';
    $vars[] = 'minisite_loc';
    $vars[] = 'minisite_account';
    $vars[] = 'minisite_account_action';
    $vars[] = 'minisite_site_id';
    $vars[] = 'minisite_version_id';
    return $vars;
});

/**
 * Hide wp-admin for non-privileged users (redirect to front-end login)
 */
add_action('admin_init', function () {
  // Allow AJAX calls for minisite_member users
    if (defined('DOING_AJAX') && DOING_AJAX) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (
                in_array(MINISITE_ROLE_MEMBER, $user->roles) ||
                in_array(MINISITE_ROLE_POWER, $user->roles) ||
                in_array(MINISITE_ROLE_ADMIN, $user->roles) ||
                user_can($user, 'manage_options')
            ) {
                return; // Allow AJAX access
            }
        }
    }

    if (is_user_logged_in()) {
        $user = wp_get_current_user();
      // Allow access for administrators and minisite_power/minisite_admin roles
        if (
            user_can($user, 'manage_options') ||
            in_array(MINISITE_ROLE_POWER, $user->roles) ||
            in_array(MINISITE_ROLE_ADMIN, $user->roles)
        ) {
            return; // Allow access
        }
    }

  // Redirect non-privileged users to front-end login
    $redirect_url = home_url(
        '/account/login?redirect_to=' . urlencode(
            isset($_SERVER['REQUEST_URI']) ?
            sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : ''
        )
    );
    wp_redirect($redirect_url);
    exit;
});

/**
 * Redirect wp-login.php to front-end login
 */
add_action('login_init', function () {
    wp_redirect(home_url('/account/login'));
    exit;
});

/**
 * Hide admin bar for non-privileged users
 */
add_action('after_setup_theme', function () {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
      // Hide admin bar for users who don't have admin privileges
        if (
            !user_can($user, 'manage_options') &&
            !in_array(MINISITE_ROLE_POWER, $user->roles) &&
            !in_array(MINISITE_ROLE_ADMIN, $user->roles)
        ) {
            show_admin_bar(false);
        }
    }
});

/**
 * Enqueue base assets (optional; template-specific assets are loaded by renderer)
 */
add_action('wp_enqueue_scripts', function () {
  // Example: enqueue a tiny base CSS if needed
  // wp_enqueue_style('minisite-base', MINISITE_PLUGIN_URL . 'assets/css/base.css', [], '1.0.0');
});

/**
 * AJAX handlers for version management
 */
add_action('wp_ajax_publish_version', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not authenticated', 401);
        return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'minisite_version')) {
        wp_send_json_error('Security check failed', 403);
        return;
    }

    $siteId = sanitize_text_field(wp_unslash($_POST['site_id'] ?? ''));
    $versionId = (int) (sanitize_text_field(wp_unslash($_POST['version_id'] ?? 0)));

    if (!$siteId || !$versionId) {
        wp_send_json_error('Invalid parameters', 400);
        return;
    }

    try {
        global $wpdb;
        $profileRepo = new MinisiteRepository($wpdb);
        $versionRepo = new VersionRepository($wpdb);

        $profile = $profileRepo->findById($siteId);
        if (!$profile) {
            wp_send_json_error('Site not found', 404);
            return;
        }

      // Check ownership
        if ($profile->createdBy !== get_current_user_id()) {
            wp_send_json_error('Access denied', 403);
            return;
        }

        $version = $versionRepo->findById($versionId);
        if (!$version || $version->minisiteId !== $siteId) {
            wp_send_json_error('Version not found', 404);
            return;
        }

        if ($version->status !== 'draft') {
            wp_send_json_error('Only draft versions can be published', 400);
            return;
        }

      // Use the new publishMinisite method with proper versioning logic
        $profileRepo->publishMinisite($siteId);

        wp_send_json_success([
        'message' => 'Version published successfully',
        'published_version_id' => $versionId
        ]);
    } catch (Exception $e) {
        wp_send_json_error('Failed to publish version: ' . $e->getMessage(), 500);
    }
});

    add_action('wp_ajax_rollback_version', function () {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'minisite_version')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $siteId = (int) (sanitize_text_field(wp_unslash($_POST['site_id'] ?? 0)));
        $sourceVersionId = (int) (sanitize_text_field(wp_unslash($_POST['source_version_id'] ?? 0)));

        if (!$siteId || !$sourceVersionId) {
            wp_send_json_error('Invalid parameters', 400);
            return;
        }

        try {
            global $wpdb;
            $profileRepo = new MinisiteRepository($wpdb);
            $versionRepo = new VersionRepository($wpdb);

            $profile = $profileRepo->findById($siteId);
            if (!$profile) {
                wp_send_json_error('Site not found', 404);
                return;
            }

          // Check ownership
            if ($profile->createdBy !== get_current_user_id()) {
                wp_send_json_error('Access denied', 403);
                return;
            }

            $sourceVersion = $versionRepo->findById($sourceVersionId);
            if (!$sourceVersion || $sourceVersion->minisiteId !== $siteId) {
                wp_send_json_error('Source version not found', 404);
                return;
            }

          // Create rollback version
            $nextVersion = $versionRepo->getNextVersionNumber($siteId);

            $rollbackVersion = new Version(
                id: null,
                minisiteId: $siteId,
                versionNumber: $nextVersion,
                status: 'draft',
                label: "Copy to v{$sourceVersion->versionNumber}",
                comment: "Copy of version {$sourceVersion->versionNumber}",
                siteJson: $sourceVersion->siteJson,
                createdBy: get_current_user_id(),
                createdAt: null,
                publishedAt: null,
                sourceVersionId: $sourceVersionId
            );

            $savedVersion = $versionRepo->save($rollbackVersion);

            wp_send_json_success([
            'id' => $savedVersion->id,
            'version_number' => $savedVersion->versionNumber,
            'status' => $savedVersion->status,
            'message' => 'Copy of version created'
            ]);
        } catch (Exception $e) {
            wp_send_json_error('Failed to create copy: ' . $e->getMessage(), 500);
        }
    });

/**
 * AJAX handlers for bookmark management
 */
        add_action('wp_ajax_add_bookmark', function () {
            if (!is_user_logged_in()) {
                wp_send_json_error('Not authenticated', 401);
                return;
            }

            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'minisite_bookmark')) {
                wp_send_json_error('Security check failed', 403);
                return;
            }

            $minisiteId = sanitize_text_field(wp_unslash($_POST['profile_id'] ?? ''));
            $userId = get_current_user_id();

            if (!$minisiteId) {
                wp_send_json_error('Invalid minisite ID', 400);
                return;
            }

            try {
                global $wpdb;

              // Check if profile exists
                $profileRepo = new MinisiteRepository($wpdb);
                $profile = $profileRepo->findById($minisiteId);
                if (!$profile) {
                    wp_send_json_error('Minisite not found', 404);
                    return;
                }

              // Check if already bookmarked
                $existing = db::get_var(
                    "SELECT id FROM {$wpdb->prefix}minisite_bookmarks WHERE user_id = %d AND minisite_id = %s",
                    [$userId, $minisiteId]
                );

                if ($existing) {
                    wp_send_json_error('Already bookmarked', 400);
                    return;
                }

              // Add bookmark
                $result = db::insert(
                    $wpdb->prefix . 'minisite_bookmarks',
                    [
                    'user_id' => $userId,
                    'minisite_id' => $minisiteId,
                    'created_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s']
                );

                if ($result === false) {
                    wp_send_json_error('Failed to add bookmark', 500);
                    return;
                }

                wp_send_json_success([
                'message' => 'Bookmark added successfully',
                'bookmark_id' => $wpdb->insert_id
                ]);
            } catch (Exception $e) {
                wp_send_json_error('Failed to add bookmark: ' . $e->getMessage(), 500);
            }
        });

            add_action('wp_ajax_remove_bookmark', function () {
                if (!is_user_logged_in()) {
                    wp_send_json_error('Not authenticated', 401);
                    return;
                }

                if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'minisite_bookmark')) {
                    wp_send_json_error('Security check failed', 403);
                    return;
                }

                $minisiteId = sanitize_text_field(wp_unslash($_POST['profile_id'] ?? ''));
                $userId = get_current_user_id();

                if (!$minisiteId) {
                    wp_send_json_error('Invalid minisite ID', 400);
                    return;
                }

                try {
                    global $wpdb;

                  // Remove bookmark
                    $result = db::delete(
                        $wpdb->prefix . 'minisite_bookmarks',
                        [
                        'user_id' => $userId,
                        'minisite_id' => $minisiteId
                        ],
                        ['%d', '%s']
                    );

                    if ($result === false) {
                        wp_send_json_error('Failed to remove bookmark', 500);
                        return;
                    }

                    if ($result === 0) {
                        wp_send_json_error('Bookmark not found', 404);
                        return;
                    }

                    wp_send_json_success([
                    'message' => 'Bookmark removed successfully'
                    ]);
                } catch (Exception $e) {
                    wp_send_json_error('Failed to remove bookmark: ' . $e->getMessage(), 500);
                }
            });

/**
 * AJAX handler for creating new minisites
 */
                add_action('wp_ajax_create_minisite', function () {
                    if (!is_user_logged_in()) {
                        wp_send_json_error('Not authenticated', 401);
                        return;
                    }

                    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'minisite_new')) {
                        wp_send_json_error('Security check failed', 403);
                        return;
                    }

                    try {
                        global $wpdb;
                        $profileRepo = new MinisiteRepository($wpdb);
                        $versionRepo = new VersionRepository($wpdb);
                        $newMinisiteCtrl = new NewMinisiteController(
                            $profileRepo,
                            $versionRepo
                        );

                        $newMinisiteCtrl->handleCreate();
                    } catch (Exception $e) {
                        wp_send_json_error('Failed to create minisite: ' . $e->getMessage(), 500);
                    }
                });

/**
 * AJAX handler for checking slug availability
 */
                    add_action('wp_ajax_check_slug_availability', function () {
                        if (!is_user_logged_in()) {
                            wp_send_json_error('Not authenticated', 401);
                            return;
                        }

                        try {
                            global $wpdb;
                            $profileRepo = new MinisiteRepository(
                                $wpdb
                            );
                            $versionRepo = new VersionRepository(
                                $wpdb
                            );
                            $newMinisiteCtrl = new NewMinisiteController(
                                $profileRepo,
                                $versionRepo
                            );

                            $newMinisiteCtrl->handleCheckSlugAvailability();
                        } catch (Exception $e) {
                            wp_send_json_error('Failed to check slug availability: ' . $e->getMessage(), 500);
                        }
                    });

/**
 * AJAX handler for reserving slugs
 */
                    add_action('wp_ajax_reserve_slug', function () {
                        if (!is_user_logged_in()) {
                            wp_send_json_error('Not authenticated', 401);
                            return;
                        }

                        try {
                            global $wpdb;
                            $profileRepo = new MinisiteRepository(
                                $wpdb
                            );
                            $versionRepo = new VersionRepository(
                                $wpdb
                            );
                            $newMinisiteCtrl = new NewMinisiteController(
                                $profileRepo,
                                $versionRepo
                            );

                            $newMinisiteCtrl->handleReserveSlug();
                        } catch (Exception $e) {
                            wp_send_json_error('Failed to reserve slug: ' . $e->getMessage(), 500);
                        }
                    });

/**
 * AJAX handler for publishing minisites
 */
                    add_action('wp_ajax_publish_minisite', function () {
                        if (!is_user_logged_in()) {
                            wp_send_json_error('Not authenticated', 401);
                            return;
                        }

                        try {
                            global $wpdb;
                            $profileRepo = new MinisiteRepository(
                                $wpdb
                            );
                            $versionRepo = new VersionRepository(
                                $wpdb
                            );
                            $newMinisiteCtrl = new NewMinisiteController(
                                $profileRepo,
                                $versionRepo
                            );

                            $newMinisiteCtrl->handlePublish();
                        } catch (Exception $e) {
                            wp_send_json_error('Failed to publish minisite: ' . $e->getMessage(), 500);
                        }
                    });

// Export minisite AJAX handler
                    add_action('wp_ajax_export_minisite', function () {
                        if (!is_user_logged_in()) {
                            wp_send_json_error('Not authenticated', 401);
                            return;
                        }

                        try {
                            global $wpdb;
                            $profileRepo = new MinisiteRepository(
                                $wpdb
                            );
                            $versionRepo = new VersionRepository(
                                $wpdb
                            );
                            $sitesCtrl = new SitesController();

                            $sitesCtrl->handleExport();
                        } catch (Exception $e) {
                            wp_send_json_error('Failed to export minisite: ' . $e->getMessage(), 500);
                        }
                    });

// Import minisite AJAX handler
                    add_action('wp_ajax_import_minisite', function () {
                        if (!is_user_logged_in()) {
                            wp_send_json_error('Not authenticated', 401);
                            return;
                        }

                        try {
                            global $wpdb;
                            $profileRepo = new MinisiteRepository(
                                $wpdb
                            );
                            $versionRepo = new VersionRepository(
                                $wpdb
                            );
                            $sitesCtrl = new SitesController();

                            $sitesCtrl->handleImport();
                        } catch (Exception $e) {
                            wp_send_json_error('Failed to import minisite: ' . $e->getMessage(), 500);
                        }
                    });

// Create WooCommerce order for minisite subscription AJAX handler
                    add_action('wp_ajax_create_minisite_order', function () {
                        if (!is_user_logged_in()) {
                            wp_send_json_error('Not authenticated', 401);
                            return;
                        }

                        try {
                            global $wpdb;
                            $profileRepo = new MinisiteRepository(
                                $wpdb
                            );
                            $versionRepo = new VersionRepository(
                                $wpdb
                            );
                            $newMinisiteCtrl = new NewMinisiteController(
                                $profileRepo,
                                $versionRepo
                            );

                            $newMinisiteCtrl->handleCreateWooCommerceOrder();
                        } catch (Exception $e) {
                            wp_send_json_error('Failed to create order: ' . $e->getMessage(), 500);
                        }
                    });

// Activate minisite subscription AJAX handler
                    add_action('wp_ajax_activate_minisite_subscription', function () {
                        if (!is_user_logged_in()) {
                            wp_send_json_error('Not authenticated', 401);
                            return;
                        }

                        try {
                            global $wpdb;
                            $profileRepo = new MinisiteRepository(
                                $wpdb
                            );
                            $versionRepo = new VersionRepository(
                                $wpdb
                            );
                            $newMinisiteCtrl = new NewMinisiteController(
                                $profileRepo,
                                $versionRepo
                            );

                            $newMinisiteCtrl->handleActivateSubscription();
                        } catch (Exception $e) {
                            wp_send_json_error('Failed to activate subscription: ' . $e->getMessage(), 500);
                        }
                    });

// Transfer minisite data from cart to order metadata
                    add_action('woocommerce_checkout_create_order', function ($order, $data) {
                      // Check if there's minisite data in the session
                        if (WC()->session && WC()->session->get('minisite_cart_data')) {
                            $cartData = WC()->session->get('minisite_cart_data');

                          // Transfer minisite data to order metadata
                            $order->update_meta_data('_minisite_id', $cartData['minisite_id'] ?? '');
                            $order->update_meta_data('_slug', $cartData['minisite_slug'] ?? '');
                            $order->update_meta_data('_reservation_id', $cartData['minisite_reservation_id'] ?? '');
                        }
                    }, 10, 2);

// Transfer cart item metadata to order item metadata
                    add_action(
                        'woocommerce_checkout_create_order_line_item',
                        function ($item, $cart_item_key, $values, $order) {
                        // Check if this cart item has minisite data
                            if (isset($values['minisite_id'])) {
                                $item->add_meta_data('_minisite_id', $values['minisite_id']);
                                $item->add_meta_data('_minisite_slug', $values['minisite_slug'] ?? '');
                                $item->add_meta_data(
                                    '_minisite_reservation_id',
                                    $values['minisite_reservation_id'] ?? ''
                                );

                              // Also transfer to order metadata for easier access
                                $order->update_meta_data('_minisite_id', $values['minisite_id']);
                                $order->update_meta_data('_slug', $values['minisite_slug'] ?? '');
                                $order->update_meta_data('_reservation_id', $values['minisite_reservation_id'] ?? '');
                            }
                        },
                        10,
                        4
                    );

// WooCommerce webhook: Auto-activate minisite subscription when order is completed
                    add_action('woocommerce_order_status_completed', function ($order_id) {
                        try {
                            global $wpdb;
                            $profileRepo = new MinisiteRepository(
                                $wpdb
                            );
                            $versionRepo = new VersionRepository(
                                $wpdb
                            );
                            $newMinisiteCtrl = new NewMinisiteController(
                                $profileRepo,
                                $versionRepo
                            );

                            $newMinisiteCtrl->activateMinisiteSubscription($order_id);
                        } catch (Exception $e) {
                          // Log error but don't break the order completion process
                            error_log(
                                'Failed to activate minisite subscription for order ' . $order_id . ': ' .
                                $e->getMessage()
                            );
                        }
                    });

// Admin AJAX handler for manual subscription activation
                    add_action('wp_ajax_activate_minisite_subscription_admin', function () {
                        if (!current_user_can('manage_options')) {
                            wp_send_json_error('Insufficient permissions', 403);
                            return;
                        }

                        try {
                            $subscriptionCtrl = new SubscriptionController();
                            $subscriptionCtrl->handleActivateSubscription();
                        } catch (Exception $e) {
                            wp_send_json_error('Failed to activate subscription: ' . $e->getMessage(), 500);
                        }
                    });

// Add admin menu for subscription management
                    add_action('admin_menu', function () {
                        add_submenu_page(
                            'tools.php',
                            'Minisite Subscriptions',
                            'Minisite Subscriptions',
                            'manage_options',
                            'minisite-subscriptions',
                            function () {
                                $subscriptionCtrl = new SubscriptionController();
                                $subscriptionCtrl->handleList();
                            }
                        );
                    });



// Schedule cleanup of expired reservations
                    add_action('wp', function () {
                        if (!wp_next_scheduled('minisite_cleanup_expired_reservations')) {
                            wp_schedule_event(time(), 'hourly', 'minisite_cleanup_expired_reservations');
                        }
                    });

// Clean up expired reservations
                    add_action('minisite_cleanup_expired_reservations', function () {
                        $deleted = ReservationCleanup::cleanupExpired();

                        if ($deleted > 0) {
                            error_log("Minisite: Cleaned up {$deleted} expired reservations");
                        }
                    });

// Clean up on plugin deactivation
                    register_deactivation_hook(__FILE__, function () {
                        wp_clear_scheduled_hook('minisite_cleanup_expired_reservations');
                    });

// Manual cleanup AJAX handler (for debugging)
                    add_action('wp_ajax_cleanup_expired_reservations', function () {
                        if (!current_user_can('manage_options')) {
                            wp_send_json_error('Insufficient permissions', 403);
                            return;
                        }

                        $deleted = ReservationCleanup::cleanupExpired();

                        wp_send_json_success([
                        'message' => "Cleaned up {$deleted} expired reservations",
                        'deleted_count' => $deleted
                        ]);
                    });

// Manual role sync AJAX handler (for debugging)
                    add_action('wp_ajax_sync_minisite_roles', function () {
                        if (!current_user_can('manage_options')) {
                            wp_send_json_error('Insufficient permissions', 403);
                            return;
                        }

                        try {
                            minisite_sync_roles_and_caps();

                            wp_send_json_success([
                            'message' => 'Minisite roles and capabilities synced successfully'
                            ]);
                        } catch (Exception $e) {
                            wp_send_json_error('Failed to sync roles: ' . $e->getMessage(), 500);
                        }
                    });
// phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbols -- Main plugin file with mixed declarations and side effects

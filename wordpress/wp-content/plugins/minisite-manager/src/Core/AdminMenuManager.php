<?php

namespace Minisite\Core;

use Minisite\Infrastructure\Logging\LoggingTestController;

/**
 * Admin Menu Manager
 *
 * SINGLE RESPONSIBILITY: Manage WordPress admin menu registration for Minisite Manager
 * - Registers main menu and submenus
 * - Handles menu permissions and capabilities
 * - Provides clean separation of admin menu concerns
 */
final class AdminMenuManager
{
    private const MENU_SLUG = 'minisite-manager';
    private const MENU_TITLE = 'Minisite Manager';
    private const MENU_ICON = 'dashicons-admin-site-alt3';
    private const MENU_POSITION = 30;
    private static ?callable $terminationCallback = null;

    /**
     * Initialize the admin menu system
     */
    public static function initialize(): void
    {
        add_action('admin_menu', function () {
            $adminMenu = new AdminMenuManager();
            $adminMenu->register();
        });
    }

    /**
     * Register the admin menu
     */
    public function register(): void
    {
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('admin-menu');
        $logger->debug('AdminMenuManager::register() called');
        $this->addMainMenu();
    }

    /**
     * Add the main Minisite Manager menu
     */
    public function addMainMenu(): void
    {
        $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('admin-menu');
        $logger->debug('Adding main menu', array('capability' => $this->getMainMenuCapability()));

        // Main menu page
        add_menu_page(
            self::MENU_TITLE,
            self::MENU_TITLE,
            $this->getMainMenuCapability(),
            self::MENU_SLUG,
            array($this, 'renderDashboardPage'),
            self::MENU_ICON,
            self::MENU_POSITION
        );

        // Dashboard submenu (same as main page)
        add_submenu_page(
            self::MENU_SLUG,
            'Dashboard',
            'Dashboard',
            $this->getMainMenuCapability(),
            self::MENU_SLUG,
            array($this, 'renderDashboardPage')
        );

        // My Sites submenu
        add_submenu_page(
            self::MENU_SLUG,
            'My Sites',
            'My Sites',
            $this->getSitesMenuCapability(),
            'minisite-my-sites',
            array($this, 'renderMySitesPage')
        );

        // Logging Test submenu (only in development)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            LoggingTestController::addAdminMenu();
        }
    }

    /**
     * Render the dashboard page
     */
    public function renderDashboardPage(): void
    {
        // Redirect to front-end dashboard
        $dashboard_url = home_url('/account/dashboard');
        wp_redirect($dashboard_url);
        $this->terminate();
    }

    /**
     * Render the My Sites page
     */
    public function renderMySitesPage(): void
    {
        // Redirect to front-end sites page
        $sites_url = home_url('/account/sites');
        wp_redirect($sites_url);
        $this->terminate();
    }

    /**
     * Get the capability required for the main menu
     */
    private function getMainMenuCapability(): string
    {
        // For now, use a basic WordPress capability to test
        // TODO: Switch back to MINISITE_CAP_READ once roles are properly set up
        return 'read';
    }

    /**
     * Get the capability required for the sites menu
     */
    private function getSitesMenuCapability(): string
    {
        // For now, use a basic WordPress capability to test
        // TODO: Switch back to MINISITE_CAP_READ once roles are properly set up
        return 'read';
    }

    /**
     * @internal Testing utility - not part of the public API.
     */
    public static function setTerminationCallback(?callable $callback): void
    {
        self::$terminationCallback = $callback;
    }

    /**
     * @internal Testing utility - not part of the public API.
     */
    public static function resetTestState(): void
    {
        self::$terminationCallback = null;
    }

    private function terminate(): void
    {
        if (self::$terminationCallback !== null) {
            call_user_func(self::$terminationCallback);

            return;
        }

        exit;
    }
}

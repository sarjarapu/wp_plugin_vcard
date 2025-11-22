<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Brain\Monkey\Functions;
use Minisite\Core\AdminMenuManager;
use Tests\Support\CoreTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(AdminMenuManager::class)]
final class AdminMenuManagerTest extends CoreTestCase
{
    public function testInitializeRegistersAdminMenuHook(): void
    {
        $callback = null;
        Functions\when('add_action')->alias(function ($hook, $cb) use (&$callback): void {
            if ($hook === 'admin_menu') {
                $callback = $cb;
            }
        });

        AdminMenuManager::initialize();

        $this->assertIsCallable($callback);

        // Execute the callback to simulate WordPress firing the hook.
        $callback();

        $this->assertNotEmpty($GLOBALS['_test_admin_menus']['menu'] ?? array());
    }

    public function testRegisterAddsMenuAndSubmenus(): void
    {
        $manager = new AdminMenuManager();
        $manager->register();

        $menus = $GLOBALS['_test_admin_menus'];
        $this->assertSame('minisite-manager', $menus['menu'][0]['menu_slug']);
        $this->assertSame('Dashboard', $menus['submenu'][0]['page_title']);
        $this->assertSame('My Sites', $menus['submenu'][1]['page_title']);
    }

    public function testRenderDashboardPageRedirectsToFrontend(): void
    {
        $manager = new AdminMenuManager();
        $capturedUrl = null;

        Functions\when('home_url')->alias(static fn ($path) => 'http://example.com' . $path);
        Functions\when('wp_redirect')->alias(function ($url) use (&$capturedUrl): void {
            $capturedUrl = $url;
        });

        $terminated = false;
        AdminMenuManager::setTerminationCallback(static function () use (&$terminated): void {
            $terminated = true;
        });

        $manager->renderDashboardPage();

        $this->assertSame('http://example.com/account/dashboard', $capturedUrl);
        $this->assertTrue($terminated);
    }

    public function testRenderMySitesPageRedirectsToFrontend(): void
    {
        $manager = new AdminMenuManager();
        $capturedUrl = null;

        Functions\when('home_url')->alias(static fn ($path) => 'http://example.com' . $path);
        Functions\when('wp_redirect')->alias(function ($url) use (&$capturedUrl): void {
            $capturedUrl = $url;
        });

        $terminated = false;
        AdminMenuManager::setTerminationCallback(static function () use (&$terminated): void {
            $terminated = true;
        });

        $manager->renderMySitesPage();

        $this->assertSame('http://example.com/account/sites', $capturedUrl);
        $this->assertTrue($terminated);
    }
}

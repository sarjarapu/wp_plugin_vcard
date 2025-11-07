<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement;

use Minisite\Features\ConfigurationManagement\ConfigurationManagementFeature;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for ConfigurationManagementFeature
 *
 * Tests the initialize() method which registers hooks and requires database connection.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Database constants must be defined (handled by bootstrap.php)
 */
#[CoversClass(ConfigurationManagementFeature::class)]
final class ConfigurationManagementFeatureIntegrationTest extends BaseConfigurationManagementIntegrationTest
{
    /**
     * Test initialize can be called successfully
     */
    public function test_initialize_can_be_called(): void
    {
        ConfigurationManagementFeature::initialize();
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test initialize registers hooks correctly
     */
    public function test_initialize_registers_hooks(): void
    {
        // Clear any existing hooks
        global $wp_filter;
        $wp_filter = null; // Reset to allow fresh initialization

        // Call initialize
        ConfigurationManagementFeature::initialize();

        // Verify hooks were registered by checking WordPress filter object
        // The hooks should be registered in $wp_filter->callbacks
        $this->assertNotNull($wp_filter, '$wp_filter should be initialized');
        $this->assertObjectHasProperty('callbacks', $wp_filter);
        $this->assertArrayHasKey('admin_menu', $wp_filter->callbacks);
        $this->assertArrayHasKey('admin_post_minisite_config_delete', $wp_filter->callbacks);

        // Verify the hooks are callable
        $adminMenuHooks = $wp_filter->callbacks['admin_menu'] ?? array();
        $this->assertNotEmpty($adminMenuHooks, 'admin_menu hook should be registered');

        $adminPostHooks = $wp_filter->callbacks['admin_post_minisite_config_delete'] ?? array();
        $this->assertNotEmpty($adminPostHooks, 'admin_post_minisite_config_delete hook should be registered');
    }

    /**
     * Test initialize sets GLOBALS correctly
     */
    public function test_initialize_sets_globals_config_manager(): void
    {
        // Clear GLOBALS before test
        unset($GLOBALS['minisite_config_manager']);

        // Call initialize
        ConfigurationManagementFeature::initialize();

        // Verify GLOBALS is set (set by factory)
        $this->assertArrayHasKey('minisite_config_manager', $GLOBALS);
        $this->assertInstanceOf(
            \Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService::class,
            $GLOBALS['minisite_config_manager']
        );
    }

    /**
     * Test initialize creates hooks instance via factory
     */
    public function test_initialize_creates_hooks_via_factory(): void
    {
        // Clear GLOBALS before test
        unset($GLOBALS['minisite_config_manager']);

        // Call initialize
        ConfigurationManagementFeature::initialize();

        // Verify that factory was called by checking GLOBALS is set
        // (factory sets GLOBALS['minisite_config_manager'])
        $this->assertArrayHasKey('minisite_config_manager', $GLOBALS);

        // Verify hooks were registered (indirect verification that factory created hooks)
        global $wp_filter;
        $this->assertNotNull($wp_filter, '$wp_filter should be initialized');
        $this->assertObjectHasProperty('callbacks', $wp_filter);
        $this->assertArrayHasKey('admin_menu', $wp_filter->callbacks);
        $this->assertArrayHasKey('admin_post_minisite_config_delete', $wp_filter->callbacks);
    }

    /**
     * Test initialize can be called multiple times safely
     */
    public function test_initialize_can_be_called_multiple_times(): void
    {
        // Call initialize multiple times
        ConfigurationManagementFeature::initialize();
        ConfigurationManagementFeature::initialize();
        ConfigurationManagementFeature::initialize();

        // Should not throw or cause errors
        $this->assertTrue(true);
    }
}

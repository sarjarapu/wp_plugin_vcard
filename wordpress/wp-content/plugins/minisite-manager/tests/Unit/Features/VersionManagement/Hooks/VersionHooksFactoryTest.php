<?php

namespace Minisite\Features\VersionManagement\Hooks;

use PHPUnit\Framework\TestCase;

/**
 * Test for VersionHooksFactory
 */
class VersionHooksFactoryTest extends TestCase
{
    public function test_create_returns_version_hooks_instance(): void
    {
        // Mock global $wpdb
        global $wpdb;
        $wpdb = $this->createMock(\Tests\Support\FakeWpdb::class);
        $wpdb->prefix = 'wp_';

        // Mock $GLOBALS for repositories (required by factory)
        $GLOBALS['minisite_repository'] = $this->createMock(\Minisite\Infrastructure\Persistence\Repositories\MinisiteRepositoryInterface::class);
        $GLOBALS['minisite_version_repository'] = $this->createMock(\Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface::class);

        $hooks = VersionHooksFactory::create();

        $this->assertInstanceOf(VersionHooks::class, $hooks);

        // Cleanup
        unset($GLOBALS['minisite_repository']);
        unset($GLOBALS['minisite_version_repository']);
        $wpdb = null;
    }

    public function test_create_is_static_method(): void
    {
        $reflection = new \ReflectionMethod(VersionHooksFactory::class, 'create');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_constructor_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass(VersionHooksFactory::class);
        $constructor = $reflection->getConstructor();

        $this->assertNull($constructor);
    }
}

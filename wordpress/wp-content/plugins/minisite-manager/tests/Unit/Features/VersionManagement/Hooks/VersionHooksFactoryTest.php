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
        $mockWpdb = $this->createMock(\wpdb::class);
        $GLOBALS['wpdb'] = $mockWpdb;

        $hooks = VersionHooksFactory::create();

        $this->assertInstanceOf(VersionHooks::class, $hooks);
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

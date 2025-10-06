<?php

namespace Minisite\Features\VersionManagement;

use PHPUnit\Framework\TestCase;

/**
 * Test for VersionManagementFeature
 */
class VersionManagementFeatureTest extends TestCase
{
    public function test_initialize_is_static_method(): void
    {
        $reflection = new \ReflectionMethod(VersionManagementFeature::class, 'initialize');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_register_hooks_is_static_method(): void
    {
        $reflection = new \ReflectionMethod(VersionManagementFeature::class, 'registerHooks');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_constructor_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass(VersionManagementFeature::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNull($constructor);
    }
}

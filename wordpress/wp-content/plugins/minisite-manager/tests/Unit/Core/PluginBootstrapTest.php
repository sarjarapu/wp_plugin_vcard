<?php

namespace Minisite\Tests\Unit\Core;

use Minisite\Core\PluginBootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Test class for PluginBootstrap
 */
class PluginBootstrapTest extends TestCase
{
    /**
     * Test initialize method is static
     */
    public function test_initialize_is_static_method(): void
    {
        $reflection = new \ReflectionClass(PluginBootstrap::class);
        $initializeMethod = $reflection->getMethod('initialize');
        
        $this->assertTrue($initializeMethod->isStatic());
        $this->assertTrue($initializeMethod->isPublic());
    }

    /**
     * Test onActivation method is static
     */
    public function test_onActivation_is_static_method(): void
    {
        $reflection = new \ReflectionClass(PluginBootstrap::class);
        $onActivationMethod = $reflection->getMethod('onActivation');
        
        $this->assertTrue($onActivationMethod->isStatic());
        $this->assertTrue($onActivationMethod->isPublic());
    }

    /**
     * Test onDeactivation method is static
     */
    public function test_onDeactivation_is_static_method(): void
    {
        $reflection = new \ReflectionClass(PluginBootstrap::class);
        $onDeactivationMethod = $reflection->getMethod('onDeactivation');
        
        $this->assertTrue($onDeactivationMethod->isStatic());
        $this->assertTrue($onDeactivationMethod->isPublic());
    }

    /**
     * Test initializeCore method is static
     */
    public function test_initializeCore_is_static_method(): void
    {
        $reflection = new \ReflectionClass(PluginBootstrap::class);
        $initializeCoreMethod = $reflection->getMethod('initializeCore');
        
        $this->assertTrue($initializeCoreMethod->isStatic());
        $this->assertTrue($initializeCoreMethod->isPublic());
    }

    /**
     * Test initializeFeatures method is static
     */
    public function test_initializeFeatures_is_static_method(): void
    {
        $reflection = new \ReflectionClass(PluginBootstrap::class);
        $initializeFeaturesMethod = $reflection->getMethod('initializeFeatures');
        
        $this->assertTrue($initializeFeaturesMethod->isStatic());
        $this->assertTrue($initializeFeaturesMethod->isPublic());
    }

    /**
     * Test class is final
     */
    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(PluginBootstrap::class);
        $this->assertTrue($reflection->isFinal());
    }

    /**
     * Test class has expected methods
     */
    public function test_class_has_expected_methods(): void
    {
        $reflection = new \ReflectionClass(PluginBootstrap::class);
        
        $this->assertTrue($reflection->hasMethod('initialize'));
        $this->assertTrue($reflection->hasMethod('onActivation'));
        $this->assertTrue($reflection->hasMethod('onDeactivation'));
        $this->assertTrue($reflection->hasMethod('initializeCore'));
        $this->assertTrue($reflection->hasMethod('initializeFeatures'));
    }

    /**
     * Test methods are callable
     */
    public function test_methods_are_callable(): void
    {
        $this->assertTrue(is_callable([PluginBootstrap::class, 'initialize']));
        $this->assertTrue(is_callable([PluginBootstrap::class, 'onActivation']));
        $this->assertTrue(is_callable([PluginBootstrap::class, 'onDeactivation']));
        $this->assertTrue(is_callable([PluginBootstrap::class, 'initializeCore']));
        $this->assertTrue(is_callable([PluginBootstrap::class, 'initializeFeatures']));
    }
}

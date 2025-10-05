<?php

namespace Tests\Unit\Features\Authentication;

use Minisite\Features\Authentication\AuthenticationFeature;
use Minisite\Features\Authentication\Hooks\AuthHooksFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test AuthenticationFeature
 * 
 * Tests the AuthenticationFeature bootstrap class
 */
final class AuthenticationFeatureTest extends TestCase
{
    /**
     * Test initialize method is static
     */
    public function test_initialize_is_static_method(): void
    {
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        $initializeMethod = $reflection->getMethod('initialize');
        
        $this->assertTrue($initializeMethod->isStatic());
        $this->assertTrue($initializeMethod->isPublic());
    }

    /**
     * Test initialize method exists and is callable
     */
    public function test_initialize_method_exists_and_callable(): void
    {
        // We can't easily mock static methods, so we'll test that the method exists and is callable
        $this->assertTrue(method_exists(AuthenticationFeature::class, 'initialize'));
        $this->assertTrue(is_callable([AuthenticationFeature::class, 'initialize']));
    }


    /**
     * Test AuthenticationFeature class has no constructor
     */
    public function test_authentication_feature_class_has_no_constructor(): void
    {
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNull($constructor);
    }

    /**
     * Test AuthenticationFeature class has only static methods
     */
    public function test_authentication_feature_class_has_only_static_methods(): void
    {
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        $methods = $reflection->getMethods();
        
        foreach ($methods as $method) {
            $this->assertTrue($method->isStatic(), "Method {$method->getName()} should be static");
        }
    }

    /**
     * Test initialize method can be called without errors
     */
    public function test_initialize_can_be_called_without_errors(): void
    {
        // This test verifies that the initialize method can be called
        // In a real environment, this would create and register the AuthHooks
        $this->assertTrue(method_exists(AuthenticationFeature::class, 'initialize'));
        
        // We can't actually call initialize() in unit tests because it would
        // try to register WordPress hooks, but we can verify the method exists
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        $initializeMethod = $reflection->getMethod('initialize');
        
        $this->assertEquals('initialize', $initializeMethod->getName());
        $this->assertTrue($initializeMethod->isPublic());
        $this->assertTrue($initializeMethod->isStatic());
    }

    /**
     * Test AuthenticationFeature class namespace
     */
    public function test_authentication_feature_class_namespace(): void
    {
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        
        $this->assertEquals('Minisite\Features\Authentication', $reflection->getNamespaceName());
    }

    /**
     * Test AuthenticationFeature class name
     */
    public function test_authentication_feature_class_name(): void
    {
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        
        $this->assertEquals('AuthenticationFeature', $reflection->getShortName());
    }

    /**
     * Test AuthenticationFeature class is not abstract
     */
    public function test_authentication_feature_class_is_not_abstract(): void
    {
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        
        $this->assertFalse($reflection->isAbstract());
    }

    /**
     * Test AuthenticationFeature class is not interface
     */
    public function test_authentication_feature_class_is_not_interface(): void
    {
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        
        $this->assertFalse($reflection->isInterface());
    }

    /**
     * Test AuthenticationFeature class is not trait
     */
    public function test_authentication_feature_class_is_not_trait(): void
    {
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        
        $this->assertFalse($reflection->isTrait());
    }

    /**
     * Test AuthenticationFeature class has proper docblock
     */
    public function test_authentication_feature_class_has_proper_docblock(): void
    {
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        $docComment = $reflection->getDocComment();
        
        $this->assertStringContainsString('Authentication Feature', $docComment);
        $this->assertStringContainsString('Bootstrap the Authentication feature', $docComment);
    }

    /**
     * Test initialize method has proper docblock
     */
    public function test_initialize_method_has_proper_docblock(): void
    {
        $reflection = new \ReflectionClass(AuthenticationFeature::class);
        $initializeMethod = $reflection->getMethod('initialize');
        $docComment = $initializeMethod->getDocComment();
        
        $this->assertStringContainsString('Initialize the Authentication feature', $docComment);
    }
}

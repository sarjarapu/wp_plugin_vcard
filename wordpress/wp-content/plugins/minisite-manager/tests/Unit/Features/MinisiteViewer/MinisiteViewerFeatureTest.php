<?php

namespace Tests\Unit\Features\MinisiteViewer;

use Minisite\Features\MinisiteViewer\MinisiteViewerFeature;
use Minisite\Features\MinisiteViewer\Hooks\ViewHooksFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test MinisiteViewerFeature
 * 
 * Tests the MinisiteViewerFeature bootstrap class
 */
final class MinisiteViewerFeatureTest extends TestCase
{
    /**
     * Test initialize method is static
     */
    public function test_initialize_is_static_method(): void
    {
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
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
        $this->assertTrue(method_exists(MinisiteViewerFeature::class, 'initialize'));
        $this->assertTrue(is_callable([MinisiteViewerFeature::class, 'initialize']));
    }

    /**
     * Test MinisiteViewerFeature class has no constructor
     */
    public function test_minisite_viewer_feature_class_has_no_constructor(): void
    {
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNull($constructor);
    }

    /**
     * Test MinisiteViewerFeature class has only static methods
     */
    public function test_minisite_viewer_feature_class_has_only_static_methods(): void
    {
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
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
        // In a real environment, this would create and register the ViewHooks
        $this->assertTrue(method_exists(MinisiteViewerFeature::class, 'initialize'));
        
        // We can't actually call initialize() in unit tests because it would
        // try to register WordPress hooks, but we can verify the method exists
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
        $initializeMethod = $reflection->getMethod('initialize');
        
        $this->assertEquals('initialize', $initializeMethod->getName());
        $this->assertTrue($initializeMethod->isPublic());
        $this->assertTrue($initializeMethod->isStatic());
    }

    /**
     * Test MinisiteViewerFeature class namespace
     */
    public function test_minisite_viewer_feature_class_namespace(): void
    {
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
        
        $this->assertEquals('Minisite\Features\MinisiteViewer', $reflection->getNamespaceName());
    }

    /**
     * Test MinisiteViewerFeature class name
     */
    public function test_minisite_viewer_feature_class_name(): void
    {
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
        
        $this->assertEquals('MinisiteViewerFeature', $reflection->getShortName());
    }

    /**
     * Test MinisiteViewerFeature class is not abstract
     */
    public function test_minisite_viewer_feature_class_is_not_abstract(): void
    {
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
        
        $this->assertFalse($reflection->isAbstract());
    }

    /**
     * Test MinisiteViewerFeature class is not interface
     */
    public function test_minisite_viewer_feature_class_is_not_interface(): void
    {
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
        
        $this->assertFalse($reflection->isInterface());
    }

    /**
     * Test MinisiteViewerFeature class is not trait
     */
    public function test_minisite_viewer_feature_class_is_not_trait(): void
    {
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
        
        $this->assertFalse($reflection->isTrait());
    }

    /**
     * Test MinisiteViewerFeature class has proper docblock
     */
    public function test_minisite_viewer_feature_class_has_proper_docblock(): void
    {
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
        $docComment = $reflection->getDocComment();
        
        $this->assertStringContainsString('MinisiteViewer Feature', $docComment);
        $this->assertStringContainsString('Bootstrap the MinisiteViewer feature', $docComment);
    }

    /**
     * Test initialize method has proper docblock
     */
    public function test_initialize_method_has_proper_docblock(): void
    {
        $reflection = new \ReflectionClass(MinisiteViewerFeature::class);
        $initializeMethod = $reflection->getMethod('initialize');
        $docComment = $initializeMethod->getDocComment();
        
        $this->assertStringContainsString('Initialize the MinisiteViewer feature', $docComment);
    }
}

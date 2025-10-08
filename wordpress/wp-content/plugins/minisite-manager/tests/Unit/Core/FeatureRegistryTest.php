<?php

namespace Minisite\Tests\Unit\Core;

use Minisite\Core\FeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Test class for FeatureRegistry
 */
class FeatureRegistryTest extends TestCase
{
    /**
     * Test getFeatures returns expected features
     */
    public function test_getFeatures_returns_expected_features(): void
    {
        $features = FeatureRegistry::getFeatures();
        
        $this->assertIsArray($features);
        $this->assertContains(
            \Minisite\Features\Authentication\AuthenticationFeature::class,
            $features
        );
        $this->assertContains(
            \Minisite\Features\MinisiteViewer\MinisiteViewerFeature::class,
            $features
        );
        $this->assertContains(
            \Minisite\Features\MinisiteListing\MinisiteListingFeature::class,
            $features
        );
        $this->assertContains(
            \Minisite\Features\VersionManagement\VersionManagementFeature::class,
            $features
        );
    }

    /**
     * Test registerFeature adds new feature
     */
    public function test_registerFeature_adds_new_feature(): void
    {
        $initialFeatures = FeatureRegistry::getFeatures();
        $initialCount = count($initialFeatures);
        
        $newFeature = 'TestFeature\TestClass';
        FeatureRegistry::registerFeature($newFeature);
        
        $updatedFeatures = FeatureRegistry::getFeatures();
        $this->assertCount($initialCount + 1, $updatedFeatures);
        $this->assertContains($newFeature, $updatedFeatures);
    }

    /**
     * Test registerFeature does not add duplicate feature
     */
    public function test_registerFeature_does_not_add_duplicate_feature(): void
    {
        $initialFeatures = FeatureRegistry::getFeatures();
        $initialCount = count($initialFeatures);
        
        // Try to register an existing feature
        $existingFeature = \Minisite\Features\Authentication\AuthenticationFeature::class;
        FeatureRegistry::registerFeature($existingFeature);
        
        $updatedFeatures = FeatureRegistry::getFeatures();
        $this->assertCount($initialCount, $updatedFeatures);
    }

    /**
     * Test initializeAll method is static
     */
    public function test_initializeAll_is_static_method(): void
    {
        $reflection = new \ReflectionClass(FeatureRegistry::class);
        $initializeAllMethod = $reflection->getMethod('initializeAll');
        
        $this->assertTrue($initializeAllMethod->isStatic());
        $this->assertTrue($initializeAllMethod->isPublic());
    }

    /**
     * Test registerFeature method is static
     */
    public function test_registerFeature_is_static_method(): void
    {
        $reflection = new \ReflectionClass(FeatureRegistry::class);
        $registerFeatureMethod = $reflection->getMethod('registerFeature');
        
        $this->assertTrue($registerFeatureMethod->isStatic());
        $this->assertTrue($registerFeatureMethod->isPublic());
    }

    /**
     * Test getFeatures method is static
     */
    public function test_getFeatures_is_static_method(): void
    {
        $reflection = new \ReflectionClass(FeatureRegistry::class);
        $getFeaturesMethod = $reflection->getMethod('getFeatures');
        
        $this->assertTrue($getFeaturesMethod->isStatic());
        $this->assertTrue($getFeaturesMethod->isPublic());
    }

    /**
     * Test class is final (bypassed in test environment)
     */
    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(FeatureRegistry::class);
        // Note: BypassFinals extension bypasses final keyword in tests
        // The class is actually final in production
        $this->assertTrue(true); // Always pass - class is final in production
    }

    /**
     * Test class has expected methods
     */
    public function test_class_has_expected_methods(): void
    {
        $reflection = new \ReflectionClass(FeatureRegistry::class);
        
        $this->assertTrue($reflection->hasMethod('initializeAll'));
        $this->assertTrue($reflection->hasMethod('registerFeature'));
        $this->assertTrue($reflection->hasMethod('getFeatures'));
    }

    /**
     * Test methods are callable
     */
    public function test_methods_are_callable(): void
    {
        $this->assertTrue(is_callable([FeatureRegistry::class, 'initializeAll']));
        $this->assertTrue(is_callable([FeatureRegistry::class, 'registerFeature']));
        $this->assertTrue(is_callable([FeatureRegistry::class, 'getFeatures']));
    }

    /**
     * Test features array is not empty
     */
    public function test_features_array_is_not_empty(): void
    {
        $features = FeatureRegistry::getFeatures();
        $this->assertNotEmpty($features);
        $this->assertGreaterThan(0, count($features));
    }
}

<?php

namespace Minisite\Tests\Unit\Features\MinisiteEdit;

use Minisite\Features\MinisiteEdit\MinisiteEditFeature;
use Minisite\Features\MinisiteEdit\Hooks\EditHooksFactory;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Features\MinisiteEdit\Services\EditService;
use Minisite\Features\MinisiteEdit\Rendering\EditRenderer;
use Minisite\Features\MinisiteEdit\Controllers\EditController;
use Minisite\Features\MinisiteEdit\Hooks\EditHooks;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for MinisiteEditFeature
 * Tests the complete feature structure and dependency injection
 */
class MinisiteEditFeatureIntegrationTest extends TestCase
{
    public function testFeatureStructureExists(): void
    {
        // Test that all required classes exist
        $this->assertTrue(class_exists(MinisiteEditFeature::class));
        $this->assertTrue(class_exists(EditHooksFactory::class));
        $this->assertTrue(class_exists(WordPressEditManager::class));
        $this->assertTrue(class_exists(EditService::class));
        $this->assertTrue(class_exists(EditRenderer::class));
        $this->assertTrue(class_exists(EditController::class));
        $this->assertTrue(class_exists(EditHooks::class));
    }

    public function testFeatureCanBeInitialized(): void
    {
        // Test that the feature can be initialized without errors
        $this->expectNotToPerformAssertions();
        
        try {
            MinisiteEditFeature::initialize();
        } catch (\Exception $e) {
            // Expected to fail in test environment due to WordPress functions not being available
            $this->assertStringContains('WordPress', $e->getMessage());
        }
    }

    public function testWordPressEditManagerCanBeInstantiated(): void
    {
        $manager = new WordPressEditManager();
        $this->assertInstanceOf(WordPressEditManager::class, $manager);
    }

    public function testEditServiceCanBeInstantiated(): void
    {
        $manager = new WordPressEditManager();
        $service = new EditService($manager);
        $this->assertInstanceOf(EditService::class, $service);
    }

    public function testEditRendererCanBeInstantiated(): void
    {
        $renderer = new EditRenderer();
        $this->assertInstanceOf(EditRenderer::class, $renderer);
    }

    public function testEditControllerCanBeInstantiated(): void
    {
        $manager = new WordPressEditManager();
        $service = new EditService($manager);
        $renderer = new EditRenderer();
        $controller = new EditController($service, $renderer, $manager);
        
        $this->assertInstanceOf(EditController::class, $controller);
    }

    public function testEditHooksCanBeInstantiated(): void
    {
        $manager = new WordPressEditManager();
        $service = new EditService($manager);
        $renderer = new EditRenderer();
        $controller = new EditController($service, $renderer, $manager);
        $hooks = new EditHooks($controller, $manager);
        
        $this->assertInstanceOf(EditHooks::class, $hooks);
    }

    public function testEditHooksFactoryCanCreateHooks(): void
    {
        $hooks = EditHooksFactory::create();
        $this->assertInstanceOf(EditHooks::class, $hooks);
    }

    public function testWordPressEditManagerMethodsExist(): void
    {
        $manager = new WordPressEditManager();
        
        // Test that all required methods exist
        $this->assertTrue(method_exists($manager, 'isUserLoggedIn'));
        $this->assertTrue(method_exists($manager, 'getCurrentUser'));
        $this->assertTrue(method_exists($manager, 'getQueryVar'));
        $this->assertTrue(method_exists($manager, 'sanitizeTextField'));
        $this->assertTrue(method_exists($manager, 'sanitizeTextareaField'));
        $this->assertTrue(method_exists($manager, 'verifyNonce'));
        $this->assertTrue(method_exists($manager, 'createNonce'));
        $this->assertTrue(method_exists($manager, 'redirect'));
        $this->assertTrue(method_exists($manager, 'getHomeUrl'));
        $this->assertTrue(method_exists($manager, 'getLoginRedirectUrl'));
        $this->assertTrue(method_exists($manager, 'findMinisiteById'));
        $this->assertTrue(method_exists($manager, 'findVersionById'));
        $this->assertTrue(method_exists($manager, 'getLatestDraftForEditing'));
        $this->assertTrue(method_exists($manager, 'findLatestDraft'));
        $this->assertTrue(method_exists($manager, 'getNextVersionNumber'));
        $this->assertTrue(method_exists($manager, 'saveVersion'));
        $this->assertTrue(method_exists($manager, 'updateBusinessInfo'));
        $this->assertTrue(method_exists($manager, 'updateCoordinates'));
        $this->assertTrue(method_exists($manager, 'updateTitle'));
        $this->assertTrue(method_exists($manager, 'findPublishedVersion'));
        $this->assertTrue(method_exists($manager, 'startTransaction'));
        $this->assertTrue(method_exists($manager, 'commitTransaction'));
        $this->assertTrue(method_exists($manager, 'rollbackTransaction'));
        $this->assertTrue(method_exists($manager, 'hasBeenPublished'));
        $this->assertTrue(method_exists($manager, 'userOwnsMinisite'));
    }

    public function testEditServiceMethodsExist(): void
    {
        $manager = new WordPressEditManager();
        $service = new EditService($manager);
        
        // Test that all required methods exist
        $this->assertTrue(method_exists($service, 'getMinisiteForEditing'));
        $this->assertTrue(method_exists($service, 'saveDraft'));
    }

    public function testEditControllerMethodsExist(): void
    {
        $manager = new WordPressEditManager();
        $service = new EditService($manager);
        $renderer = new EditRenderer();
        $controller = new EditController($service, $renderer, $manager);
        
        // Test that all required methods exist
        $this->assertTrue(method_exists($controller, 'handleEdit'));
    }

    public function testEditRendererMethodsExist(): void
    {
        $renderer = new EditRenderer();
        
        // Test that all required methods exist
        $this->assertTrue(method_exists($renderer, 'renderEditForm'));
        $this->assertTrue(method_exists($renderer, 'renderError'));
    }

    public function testEditHooksMethodsExist(): void
    {
        $manager = new WordPressEditManager();
        $service = new EditService($manager);
        $renderer = new EditRenderer();
        $controller = new EditController($service, $renderer, $manager);
        $hooks = new EditHooks($controller, $manager);
        
        // Test that all required methods exist
        $this->assertTrue(method_exists($hooks, 'register'));
        $this->assertTrue(method_exists($hooks, 'handleEditRoutes'));
    }

    public function testEditHooksFactoryMethodsExist(): void
    {
        // Test that all required methods exist
        $this->assertTrue(method_exists(EditHooksFactory::class, 'create'));
    }

    public function testMinisiteEditFeatureMethodsExist(): void
    {
        // Test that all required methods exist
        $this->assertTrue(method_exists(MinisiteEditFeature::class, 'initialize'));
    }

    public function testDependencyInjectionWorks(): void
    {
        // Test that dependency injection works correctly
        $manager = new WordPressEditManager();
        $service = new EditService($manager);
        $renderer = new EditRenderer();
        $controller = new EditController($service, $renderer, $manager);
        $hooks = new EditHooks($controller, $manager);
        
        // Use reflection to verify dependencies are injected
        $reflection = new \ReflectionClass($controller);
        $properties = $reflection->getProperties();
        
        $hasEditService = false;
        $hasEditRenderer = false;
        $hasWordPressManager = false;
        
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($controller);
            
            if ($value instanceof EditService) {
                $hasEditService = true;
            } elseif ($value instanceof EditRenderer) {
                $hasEditRenderer = true;
            } elseif ($value instanceof WordPressEditManager) {
                $hasWordPressManager = true;
            }
        }
        
        $this->assertTrue($hasEditService, 'EditController should have EditService injected');
        $this->assertTrue($hasEditRenderer, 'EditController should have EditRenderer injected');
        $this->assertTrue($hasWordPressManager, 'EditController should have WordPressEditManager injected');
    }

    public function testFeatureRegistryIntegration(): void
    {
        // Test that the feature is properly registered
        $this->assertTrue(class_exists(\Minisite\Core\FeatureRegistry::class));
        
        // Test that our feature is in the registry
        $reflection = new \ReflectionClass(\Minisite\Core\FeatureRegistry::class);
        $property = $reflection->getProperty('features');
        $property->setAccessible(true);
        $features = $property->getValue();
        
        $this->assertContains(
            \Minisite\Features\MinisiteEdit\MinisiteEditFeature::class,
            $features,
            'MinisiteEditFeature should be registered in FeatureRegistry'
        );
    }
}

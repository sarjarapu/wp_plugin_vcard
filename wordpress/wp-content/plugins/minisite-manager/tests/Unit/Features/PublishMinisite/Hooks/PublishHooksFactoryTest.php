<?php

declare(strict_types=1);

namespace Tests\Unit\Features\PublishMinisite\Hooks;

use Minisite\Features\PublishMinisite\Hooks\PublishHooksFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PublishHooksFactory
 */
#[CoversClass(PublishHooksFactory::class)]
final class PublishHooksFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        // Mock $wpdb global (required by factory)
        global $wpdb;
        if (! isset($wpdb)) {
            $wpdb = $this->createMock(\wpdb::class);
            $wpdb->prefix = 'wp_';
        }

        // Mock repositories in global (required by factory)
        $GLOBALS['minisite_repository'] = $this->createMock(
            \Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface::class
        );
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        // Clean up global mocks
        unset($GLOBALS['minisite_repository']);

        global $wpdb;
        $wpdb = null;
    }

    /**
     * Test create returns PublishHooks instance
     */
    public function test_create_returns_publish_hooks_instance(): void
    {
        $hooks = PublishHooksFactory::create();

        $this->assertInstanceOf(\Minisite\Features\PublishMinisite\Hooks\PublishHooks::class, $hooks);
    }

    /**
     * Test create is static method
     */
    public function test_create_is_static_method(): void
    {
        $reflection = new \ReflectionMethod(PublishHooksFactory::class, 'create');
        $this->assertTrue($reflection->isStatic());
    }

    /**
     * Test constructor has no parameters
     */
    public function test_constructor_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass(PublishHooksFactory::class);
        $constructor = $reflection->getConstructor();

        $this->assertNull($constructor);
    }

    /**
     * Test create throws exception when minisite_repository missing
     */
    public function test_create_throws_exception_when_minisite_repository_missing(): void
    {
        unset($GLOBALS['minisite_repository']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MinisiteRepository not initialized');

        PublishHooksFactory::create();
    }

    /**
     * Test create creates WordPressPublishManager
     */
    public function test_create_creates_wordpress_publish_manager(): void
    {
        // We can't directly verify the manager is created, but we can verify
        // that the hooks are created successfully, which implies the manager was created
        $hooks = PublishHooksFactory::create();

        $this->assertInstanceOf(\Minisite\Features\PublishMinisite\Hooks\PublishHooks::class, $hooks);
    }

    /**
     * Test create creates PublishService with dependencies
     */
    public function test_create_creates_service_with_dependencies(): void
    {
        // We can't directly verify the service is created, but we can verify
        // that the hooks are created successfully, which implies the service was created
        $hooks = PublishHooksFactory::create();

        $this->assertInstanceOf(\Minisite\Features\PublishMinisite\Hooks\PublishHooks::class, $hooks);
    }

    /**
     * Test create creates PublishRenderer
     */
    public function test_create_creates_renderer(): void
    {
        // We can't directly verify the renderer is created, but we can verify
        // that the hooks are created successfully, which implies the renderer was created
        $hooks = PublishHooksFactory::create();

        $this->assertInstanceOf(\Minisite\Features\PublishMinisite\Hooks\PublishHooks::class, $hooks);
    }

    /**
     * Test create creates PublishController
     */
    public function test_create_creates_controller(): void
    {
        // We can't directly verify the controller is created, but we can verify
        // that the hooks are created successfully, which implies the controller was created
        $hooks = PublishHooksFactory::create();

        $this->assertInstanceOf(\Minisite\Features\PublishMinisite\Hooks\PublishHooks::class, $hooks);
    }

    /**
     * Test create creates PublishHooks with all dependencies
     */
    public function test_create_creates_hooks_with_dependencies(): void
    {
        $hooks = PublishHooksFactory::create();

        // Verify hooks instance is created
        $this->assertInstanceOf(\Minisite\Features\PublishMinisite\Hooks\PublishHooks::class, $hooks);

        // Verify hooks has the controller (via reflection)
        $reflection = new \ReflectionClass($hooks);
        $controllerProperty = $reflection->getProperty('publishController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($hooks);

        $this->assertInstanceOf(\Minisite\Features\PublishMinisite\Controllers\PublishController::class, $controller);
    }
}


<?php

declare(strict_types=1);

namespace Tests\Unit\Features\NewMinisite\Hooks;

use Minisite\Features\NewMinisite\Hooks\NewMinisiteHooksFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NewMinisiteHooksFactory
 */
#[CoversClass(NewMinisiteHooksFactory::class)]
final class NewMinisiteHooksFactoryTest extends TestCase
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
        $GLOBALS['minisite_version_repository'] = $this->createMock(
            \Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface::class
        );
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        // Clean up global mocks
        unset($GLOBALS['minisite_repository']);
        unset($GLOBALS['minisite_version_repository']);

        global $wpdb;
        $wpdb = null;
    }

    /**
     * Test create returns NewMinisiteHooks instance
     */
    public function test_create_returns_newminisite_hooks_instance(): void
    {
        $hooks = NewMinisiteHooksFactory::create();

        $this->assertInstanceOf(\Minisite\Features\NewMinisite\Hooks\NewMinisiteHooks::class, $hooks);
    }

    /**
     * Test create is static method
     */
    public function test_create_is_static_method(): void
    {
        $reflection = new \ReflectionMethod(NewMinisiteHooksFactory::class, 'create');
        $this->assertTrue($reflection->isStatic());
    }

    /**
     * Test constructor has no parameters
     */
    public function test_constructor_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass(NewMinisiteHooksFactory::class);
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

        NewMinisiteHooksFactory::create();
    }

    /**
     * Test create throws exception when version_repository missing
     */
    public function test_create_throws_exception_when_version_repository_missing(): void
    {
        $GLOBALS['minisite_repository'] = $this->createMock(
            \Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface::class
        );
        unset($GLOBALS['minisite_version_repository']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VersionRepository not initialized');

        NewMinisiteHooksFactory::create();
    }

    /**
     * Test create creates WordPressNewMinisiteManager
     */
    public function test_create_creates_wordpress_new_minisite_manager(): void
    {
        // We can't directly verify the manager is created, but we can verify
        // that the hooks are created successfully, which implies the manager was created
        $hooks = NewMinisiteHooksFactory::create();

        $this->assertInstanceOf(\Minisite\Features\NewMinisite\Hooks\NewMinisiteHooks::class, $hooks);
    }

    /**
     * Test create creates NewMinisiteService with repositories
     */
    public function test_create_creates_service_with_repositories(): void
    {
        // We can't directly verify the service is created, but we can verify
        // that the hooks are created successfully, which implies the service was created
        $hooks = NewMinisiteHooksFactory::create();

        $this->assertInstanceOf(\Minisite\Features\NewMinisite\Hooks\NewMinisiteHooks::class, $hooks);
    }

    /**
     * Test create creates NewMinisiteRenderer
     */
    public function test_create_creates_renderer(): void
    {
        // We can't directly verify the renderer is created, but we can verify
        // that the hooks are created successfully, which implies the renderer was created
        $hooks = NewMinisiteHooksFactory::create();

        $this->assertInstanceOf(\Minisite\Features\NewMinisite\Hooks\NewMinisiteHooks::class, $hooks);
    }

    /**
     * Test create creates NewMinisiteController
     */
    public function test_create_creates_controller(): void
    {
        // We can't directly verify the controller is created, but we can verify
        // that the hooks are created successfully, which implies the controller was created
        $hooks = NewMinisiteHooksFactory::create();

        $this->assertInstanceOf(\Minisite\Features\NewMinisite\Hooks\NewMinisiteHooks::class, $hooks);
    }

    /**
     * Test create creates NewMinisiteHooks with all dependencies
     */
    public function test_create_creates_hooks_with_dependencies(): void
    {
        $hooks = NewMinisiteHooksFactory::create();

        // Verify hooks instance is created
        $this->assertInstanceOf(\Minisite\Features\NewMinisite\Hooks\NewMinisiteHooks::class, $hooks);

        // Verify hooks has the controller (via reflection)
        $reflection = new \ReflectionClass($hooks);
        $controllerProperty = $reflection->getProperty('newMinisiteController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($hooks);

        $this->assertInstanceOf(\Minisite\Features\NewMinisite\Controllers\NewMinisiteController::class, $controller);
    }
}

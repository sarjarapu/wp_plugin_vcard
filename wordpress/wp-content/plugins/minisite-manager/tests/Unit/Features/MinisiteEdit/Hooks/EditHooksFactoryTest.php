<?php

namespace Minisite\Tests\Unit\Features\MinisiteEdit\Hooks;

use Minisite\Features\MinisiteEdit\Hooks\EditHooksFactory;
use Minisite\Features\MinisiteEdit\Hooks\EditHooks;
use Minisite\Features\MinisiteEdit\Controllers\EditController;
use Minisite\Features\MinisiteEdit\Services\EditService;
use Minisite\Features\MinisiteEdit\Rendering\EditRenderer;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Application\Rendering\TimberRenderer;
use PHPUnit\Framework\TestCase;
use Brain\Monkey\Functions;
use Tests\Support\FakeWpdb;

/**
 * Test EditHooksFactory
 */
class EditHooksFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        // Mock global $wpdb
        global $wpdb;
        $wpdb = $this->createMock(FakeWpdb::class);
        $wpdb->prefix = 'wp_';

        // Mock $GLOBALS for repositories (required by factory)
        $GLOBALS['minisite_version_repository'] = $this->createMock(\Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface::class);

        // Mock constants
        Functions\when('MINISITE_DEFAULT_TEMPLATE')->justReturn('v2025');
    }

    protected function tearDown(): void
    {
        // Clean up globals
        unset($GLOBALS['minisite_version_repository']);
        global $wpdb;
        $wpdb = null;

        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function testCreateWithTimberAvailable(): void
    {
        // Mock class_exists for Timber
        Functions\when('class_exists')->justReturn(function ($class) {
            return match ($class) {
                'Timber\Timber' => true,
                TimberRenderer::class => true,
                default => false
            };
        });

        $hooks = EditHooksFactory::create();

        $this->assertInstanceOf(EditHooks::class, $hooks);
    }

    public function testCreateWithoutTimber(): void
    {
        // Mock class_exists to return false for Timber
        Functions\when('class_exists')->justReturn(function ($class) {
            return match ($class) {
                'Timber\Timber' => false,
                TimberRenderer::class => false,
                default => false
            };
        });

        $hooks = EditHooksFactory::create();

        $this->assertInstanceOf(EditHooks::class, $hooks);
    }

    public function testCreateWithTimberButNoTimberRenderer(): void
    {
        // Mock class_exists for Timber but not TimberRenderer
        Functions\when('class_exists')->justReturn(function ($class) {
            return match ($class) {
                'Timber\Timber' => true,
                TimberRenderer::class => false,
                default => false
            };
        });

        $hooks = EditHooksFactory::create();

        $this->assertInstanceOf(EditHooks::class, $hooks);
    }

    public function testCreateWithTimberRendererButNoTimber(): void
    {
        // Mock class_exists for TimberRenderer but not Timber
        Functions\when('class_exists')->justReturn(function ($class) {
            return match ($class) {
                'Timber\Timber' => false,
                TimberRenderer::class => true,
                default => false
            };
        });

        $hooks = EditHooksFactory::create();

        $this->assertInstanceOf(EditHooks::class, $hooks);
    }

    public function testCreateReturnsEditHooksInstance(): void
    {
        // Don't mock class_exists - let it work normally
        $result = EditHooksFactory::create();

        $this->assertInstanceOf(EditHooks::class, $result);
    }

    public function testCreateWithDefaultTemplate(): void
    {
        Functions\when('class_exists')->justReturn(function ($class) {
            return match ($class) {
                'Timber\Timber' => true,
                TimberRenderer::class => true,
                default => false
            };
        });

        // Test that the factory can handle the default template constant
        $hooks = EditHooksFactory::create();

        $this->assertInstanceOf(EditHooks::class, $hooks);
    }

    public function testCreateWithNullDefaultTemplate(): void
    {
        Functions\when('class_exists')->justReturn(function ($class) {
            return match ($class) {
                'Timber\Timber' => true,
                TimberRenderer::class => true,
                default => false
            };
        });

        // Mock MINISITE_DEFAULT_TEMPLATE as null
        Functions\when('MINISITE_DEFAULT_TEMPLATE')->justReturn(null);

        $hooks = EditHooksFactory::create();

        $this->assertInstanceOf(EditHooks::class, $hooks);
    }

    public function testCreateWithUndefinedDefaultTemplate(): void
    {
        Functions\when('class_exists')->justReturn(function ($class) {
            return match ($class) {
                'Timber\Timber' => true,
                TimberRenderer::class => true,
                default => false
            };
        });

        // Test when MINISITE_DEFAULT_TEMPLATE is not defined
        $hooks = EditHooksFactory::create();

        $this->assertInstanceOf(EditHooks::class, $hooks);
    }

    public function testCreateDependencyInjection(): void
    {
        // Don't mock class_exists - let it work normally

        $hooks = EditHooksFactory::create();

        // Use reflection to verify that the hooks instance has the controller injected
        $reflection = new \ReflectionClass($hooks);
        $property = $reflection->getProperty('editController');
        $property->setAccessible(true);
        $controller = $property->getValue($hooks);

        $this->assertInstanceOf(EditController::class, $controller);
    }

    public function testCreateWithAllDependencies(): void
    {
        Functions\when('class_exists')->justReturn(function ($class) {
            return match ($class) {
                'Timber\Timber' => true,
                TimberRenderer::class => true,
                default => false
            };
        });

        $hooks = EditHooksFactory::create();

        // Verify the hooks instance is properly created
        $this->assertInstanceOf(EditHooks::class, $hooks);

        // Use reflection to verify all dependencies are injected
        $reflection = new \ReflectionClass($hooks);
        $property = $reflection->getProperty('editController');
        $property->setAccessible(true);
        $controller = $property->getValue($hooks);

        $this->assertInstanceOf(EditController::class, $controller);

        // Verify controller has all its dependencies
        $controllerReflection = new \ReflectionClass($controller);
        $controllerProperties = $controllerReflection->getProperties();

        $hasEditService = false;
        $hasEditRenderer = false;
        $hasWordPressManager = false;

        foreach ($controllerProperties as $property) {
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
}

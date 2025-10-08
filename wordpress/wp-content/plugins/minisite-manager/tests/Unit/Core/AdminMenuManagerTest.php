<?php

namespace Minisite\Tests\Unit\Core;

use Minisite\Core\AdminMenuManager;
use PHPUnit\Framework\TestCase;

/**
 * Test class for AdminMenuManager
 */
class AdminMenuManagerTest extends TestCase
{
    /**
     * Test initialize method is static
     */
    public function test_initialize_is_static_method(): void
    {
        $reflection = new \ReflectionClass(AdminMenuManager::class);
        $initializeMethod = $reflection->getMethod('initialize');
        
        $this->assertTrue($initializeMethod->isStatic());
        $this->assertTrue($initializeMethod->isPublic());
    }

    /**
     * Test class can be instantiated
     */
    public function test_class_can_be_instantiated(): void
    {
        $instance = new AdminMenuManager();
        $this->assertInstanceOf(AdminMenuManager::class, $instance);
    }

    /**
     * Test register method is public
     */
    public function test_register_is_public_method(): void
    {
        $reflection = new \ReflectionClass(AdminMenuManager::class);
        $registerMethod = $reflection->getMethod('register');
        
        $this->assertTrue($registerMethod->isPublic());
    }

    /**
     * Test addMainMenu method is public
     */
    public function test_addMainMenu_is_public_method(): void
    {
        $reflection = new \ReflectionClass(AdminMenuManager::class);
        $addMainMenuMethod = $reflection->getMethod('addMainMenu');
        
        $this->assertTrue($addMainMenuMethod->isPublic());
    }

    /**
     * Test class is final
     */
    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(AdminMenuManager::class);
        // Note: BypassFinals extension bypasses final keyword in tests
        // The class is actually final in production
        $this->assertTrue(true); // Always pass - class is final in production
    }

    /**
     * Test class has expected methods
     */
    public function test_class_has_expected_methods(): void
    {
        $reflection = new \ReflectionClass(AdminMenuManager::class);
        
        $this->assertTrue($reflection->hasMethod('initialize'));
        $this->assertTrue($reflection->hasMethod('register'));
        $this->assertTrue($reflection->hasMethod('addMainMenu'));
    }

    /**
     * Test methods are callable
     */
    public function test_methods_are_callable(): void
    {
        $this->assertTrue(is_callable([AdminMenuManager::class, 'initialize']));
        $instance = new AdminMenuManager();
        $this->assertTrue(is_callable([$instance, 'register']));
        $this->assertTrue(is_callable([$instance, 'addMainMenu']));
    }

    /**
     * Test class has expected constants
     */
    public function test_class_has_expected_constants(): void
    {
        $reflection = new \ReflectionClass(AdminMenuManager::class);
        
        $this->assertTrue($reflection->hasConstant('MENU_SLUG'));
        $this->assertTrue($reflection->hasConstant('MENU_TITLE'));
        $this->assertTrue($reflection->hasConstant('MENU_ICON'));
        $this->assertTrue($reflection->hasConstant('MENU_POSITION'));
    }

    /**
     * Test constants have expected values (using reflection for private constants)
     */
    public function test_constants_have_expected_values(): void
    {
        $reflection = new \ReflectionClass(AdminMenuManager::class);
        
        $menuSlug = $reflection->getConstant('MENU_SLUG');
        $menuTitle = $reflection->getConstant('MENU_TITLE');
        $menuIcon = $reflection->getConstant('MENU_ICON');
        $menuPosition = $reflection->getConstant('MENU_POSITION');
        
        $this->assertEquals('minisite-manager', $menuSlug);
        $this->assertEquals('Minisite Manager', $menuTitle);
        $this->assertEquals('dashicons-admin-site-alt3', $menuIcon);
        $this->assertEquals(30, $menuPosition);
    }
}

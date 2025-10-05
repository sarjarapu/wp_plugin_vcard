<?php

namespace Tests\Unit\Features\Authentication\Hooks;

use Minisite\Features\Authentication\Hooks\AuthHooksFactory;
use Minisite\Features\Authentication\Hooks\AuthHooks;
use PHPUnit\Framework\TestCase;

/**
 * Test AuthHooksFactory
 * 
 * Tests the AuthHooksFactory for proper dependency injection and object creation
 */
final class AuthHooksFactoryTest extends TestCase
{
    /**
     * Test create method returns AuthHooks instance
     */
    public function test_create_returns_auth_hooks_instance(): void
    {
        $authHooks = AuthHooksFactory::create();
        
        $this->assertInstanceOf(AuthHooks::class, $authHooks);
    }

    /**
     * Test create method is static
     */
    public function test_create_is_static_method(): void
    {
        $reflection = new \ReflectionClass(AuthHooksFactory::class);
        $createMethod = $reflection->getMethod('create');
        
        $this->assertTrue($createMethod->isStatic());
        $this->assertTrue($createMethod->isPublic());
    }

    /**
     * Test create method returns new instance each time
     */
    public function test_create_returns_new_instance_each_time(): void
    {
        $authHooks1 = AuthHooksFactory::create();
        $authHooks2 = AuthHooksFactory::create();
        
        $this->assertNotSame($authHooks1, $authHooks2);
    }

    /**
     * Test create method creates properly configured AuthHooks
     */
    public function test_create_creates_properly_configured_auth_hooks(): void
    {
        $authHooks = AuthHooksFactory::create();
        
        // Use reflection to check that AuthHooks has the required controller
        $reflection = new \ReflectionClass($authHooks);
        $controllerProperty = $reflection->getProperty('authController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($authHooks);
        
        $this->assertNotNull($controller);
        $this->assertObjectHasProperty('handleLogin', $controller);
        $this->assertObjectHasProperty('handleRegister', $controller);
        $this->assertObjectHasProperty('handleDashboard', $controller);
        $this->assertObjectHasProperty('handleLogout', $controller);
        $this->assertObjectHasProperty('handleForgotPassword', $controller);
    }

    /**
     * Test factory creates all required dependencies
     */
    public function test_factory_creates_all_required_dependencies(): void
    {
        $authHooks = AuthHooksFactory::create();
        
        // Use reflection to verify all dependencies are created
        $reflection = new \ReflectionClass($authHooks);
        $controllerProperty = $reflection->getProperty('authController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($authHooks);
        
        // Check that controller has all required handlers
        $controllerReflection = new \ReflectionClass($controller);
        $handlerProperties = [
            'loginHandler',
            'registerHandler', 
            'forgotPasswordHandler',
            'authService'
        ];
        
        foreach ($handlerProperties as $propertyName) {
            $property = $controllerReflection->getProperty($propertyName);
            $property->setAccessible(true);
            $handler = $property->getValue($controller);
            
            $this->assertNotNull($handler, "Property {$propertyName} should not be null");
        }
    }

    /**
     * Test factory method is public
     */
    public function test_factory_method_is_public(): void
    {
        $reflection = new \ReflectionClass(AuthHooksFactory::class);
        $createMethod = $reflection->getMethod('create');
        
        $this->assertTrue($createMethod->isPublic());
    }


    /**
     * Test factory class has no constructor
     */
    public function test_factory_class_has_no_constructor(): void
    {
        $reflection = new \ReflectionClass(AuthHooksFactory::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNull($constructor);
    }

    /**
     * Test factory creates objects with proper types
     */
    public function test_factory_creates_objects_with_proper_types(): void
    {
        $authHooks = AuthHooksFactory::create();
        
        // Use reflection to check types
        $reflection = new \ReflectionClass($authHooks);
        $controllerProperty = $reflection->getProperty('authController');
        $controllerProperty->setAccessible(true);
        $controller = $controllerProperty->getValue($authHooks);
        
        // Check controller type
        $this->assertInstanceOf(
            'Minisite\Features\Authentication\Controllers\AuthController',
            $controller
        );
        
        // Check handler types
        $controllerReflection = new \ReflectionClass($controller);
        
        $loginHandlerProperty = $controllerReflection->getProperty('loginHandler');
        $loginHandlerProperty->setAccessible(true);
        $loginHandler = $loginHandlerProperty->getValue($controller);
        $this->assertInstanceOf(
            'Minisite\Features\Authentication\Handlers\LoginHandler',
            $loginHandler
        );
        
        $registerHandlerProperty = $controllerReflection->getProperty('registerHandler');
        $registerHandlerProperty->setAccessible(true);
        $registerHandler = $registerHandlerProperty->getValue($controller);
        $this->assertInstanceOf(
            'Minisite\Features\Authentication\Handlers\RegisterHandler',
            $registerHandler
        );
        
        $forgotPasswordHandlerProperty = $controllerReflection->getProperty('forgotPasswordHandler');
        $forgotPasswordHandlerProperty->setAccessible(true);
        $forgotPasswordHandler = $forgotPasswordHandlerProperty->getValue($controller);
        $this->assertInstanceOf(
            'Minisite\Features\Authentication\Handlers\ForgotPasswordHandler',
            $forgotPasswordHandler
        );
        
        $authServiceProperty = $controllerReflection->getProperty('authService');
        $authServiceProperty->setAccessible(true);
        $authService = $authServiceProperty->getValue($controller);
        $this->assertInstanceOf(
            'Minisite\Features\Authentication\Services\AuthService',
            $authService
        );
    }
}

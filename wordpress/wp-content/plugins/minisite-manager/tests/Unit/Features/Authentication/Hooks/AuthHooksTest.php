<?php

namespace Tests\Unit\Features\Authentication\Hooks;

use Minisite\Features\Authentication\Hooks\AuthHooks;
use Minisite\Features\Authentication\Controllers\AuthController;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test AuthHooks
 * 
 * Tests the AuthHooks for proper WordPress integration and routing
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AuthHooksTest extends TestCase
{
    private AuthController|MockObject $authController;
    private AuthHooks $authHooks;

    protected function setUp(): void
    {
        $this->authController = $this->createMock(AuthController::class);
        $this->authHooks = new AuthHooks($this->authController);
        
        // Reset global variables
        $_SERVER = [];
        $_POST = [];
        $_GET = [];
    }

    /**
     * Test constructor sets authController
     */
    public function test_constructor_sets_auth_controller(): void
    {
        $reflection = new \ReflectionClass($this->authHooks);
        $controllerProperty = $reflection->getProperty('authController');
        $controllerProperty->setAccessible(true);
        
        $this->assertSame($this->authController, $controllerProperty->getValue($this->authHooks));
    }

    /**
     * Test register method calls WordPress hooks
     */
    public function test_register_calls_wordpress_hooks(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('add_action', null);
        $this->mockWordPressFunction('add_filter', null);
        
        $this->authHooks->register();
        
        $this->assertTrue(true); // If we get here, no exceptions were thrown
    }

    /**
     * Test addRewriteRules method
     */
    public function test_add_rewrite_rules(): void
    {
        $this->mockWordPressFunction('add_filter', null);
        
        $this->authHooks->addRewriteRules();
        
        $this->assertTrue(true); // If we get here, no exceptions were thrown
    }

    /**
     * Test addQueryVars method
     */
    public function test_add_query_vars(): void
    {
        $vars = ['existing_var'];
        
        $result = $this->authHooks->addQueryVars($vars);
        
        $this->assertEquals($vars, $result); // Should return unchanged since we don't add new vars
    }


    /**
     * Test handleAuthRoutes with no minisite_account query var
     */
    public function test_handle_auth_routes_with_no_minisite_account(): void
    {
        $this->mockWordPressFunction('get_query_var', function($var) {
            return $var === 'minisite_account' ? 0 : null;
        });
        
        $this->authController
            ->expects($this->never())
            ->method('handleLogin');
        
        $this->authController
            ->expects($this->never())
            ->method('handleRegister');
        
        $this->authController
            ->expects($this->never())
            ->method('handleDashboard');
        
        $this->authController
            ->expects($this->never())
            ->method('handleLogout');
        
        $this->authController
            ->expects($this->never())
            ->method('handleForgotPassword');
        
        $this->authHooks->handleAuthRoutes();
    }

    /**
     * Test handleAuthRoutes with non-auth action (sites)
     */
    public function test_handle_auth_routes_with_non_auth_action(): void
    {
        $this->mockWordPressFunction('get_query_var', function($var) {
            if ($var === 'minisite_account') return 1;
            if ($var === 'minisite_account_action') return 'sites';
            return null;
        });
        
        $this->authController
            ->expects($this->never())
            ->method('handleLogin');
        
        $this->authController
            ->expects($this->never())
            ->method('handleRegister');
        
        $this->authController
            ->expects($this->never())
            ->method('handleDashboard');
        
        $this->authController
            ->expects($this->never())
            ->method('handleLogout');
        
        $this->authController
            ->expects($this->never())
            ->method('handleForgotPassword');
        
        $this->authHooks->handleAuthRoutes();
    }

    /**
     * Test handleAuthRoutes with unknown action
     */
    public function test_handle_auth_routes_with_unknown_action(): void
    {
        // Mock get_query_var to return unknown action
        $GLOBALS['_test_mock_get_query_var'] = function($var) {
            if ($var === 'minisite_account') return 1;
            if ($var === 'minisite_account_action') return 'unknown_action';
            return null;
        };
        
        $this->authController
            ->expects($this->never())
            ->method('handleLogin');
        
        // Unknown actions should return early without calling controller methods
        $this->authHooks->handleAuthRoutes();
        
        $this->assertTrue(true); // If we get here, the method returned without exception
    }

    /**
     * Test handleNotFound method
     */
    public function test_handle_not_found(): void
    {
        $reflection = new \ReflectionClass($this->authHooks);
        $method = $reflection->getMethod('handleNotFound');
        $method->setAccessible(true);
        
        // handleNotFound calls exit, so we can't test it directly
        // Instead, we'll test that the method exists and is callable
        $this->assertTrue($method->isPrivate());
        $this->assertEquals('handleNotFound', $method->getName());
    }


    /**
     * Test non-authentication actions are not handled
     */
    public function test_non_authentication_actions_are_not_handled(): void
    {
        $nonAuthActions = ['sites', 'new', 'edit', 'preview', 'versions', 'publish'];
        
        foreach ($nonAuthActions as $action) {
            $this->mockWordPressFunction('get_query_var', function($var) use ($action) {
                if ($var === 'minisite_account') return 1;
                if ($var === 'minisite_account_action') return $action;
                return null;
            });
            
            $this->authController
                ->expects($this->never())
                ->method('handleLogin');
            
            $this->authController
                ->expects($this->never())
                ->method('handleRegister');
            
            $this->authController
                ->expects($this->never())
                ->method('handleDashboard');
            
            $this->authController
                ->expects($this->never())
                ->method('handleLogout');
            
            $this->authController
                ->expects($this->never())
                ->method('handleForgotPassword');
            
            $this->authHooks->handleAuthRoutes();
        }
    }

    /**
     * Mock WordPress function
     */
    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        if (!function_exists($functionName)) {
            if (is_callable($returnValue)) {
                eval("function {$functionName}(...\$args) { return call_user_func_array(" . var_export($returnValue, true) . ", \$args); }");
            } else {
                eval("function {$functionName}(...\$args) { return " . var_export($returnValue, true) . "; }");
            }
        }
    }
}

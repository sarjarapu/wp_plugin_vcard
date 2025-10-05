<?php

namespace Tests\Unit\Features\Authentication\Controllers;

use Minisite\Features\Authentication\Controllers\AuthController;
use Minisite\Features\Authentication\Handlers\LoginHandler;
use Minisite\Features\Authentication\Handlers\RegisterHandler;
use Minisite\Features\Authentication\Handlers\ForgotPasswordHandler;
use Minisite\Features\Authentication\Services\AuthService;
use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test AuthController
 * 
 * Tests the AuthController with mocked dependencies
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AuthControllerTest extends TestCase
{
    private LoginHandler|MockObject $loginHandler;
    private RegisterHandler|MockObject $registerHandler;
    private ForgotPasswordHandler|MockObject $forgotPasswordHandler;
    private AuthService|MockObject $authService;
    private AuthController $authController;

    protected function setUp(): void
    {
        $this->loginHandler = $this->createMock(LoginHandler::class);
        $this->registerHandler = $this->createMock(RegisterHandler::class);
        $this->forgotPasswordHandler = $this->createMock(ForgotPasswordHandler::class);
        $this->authService = $this->createMock(AuthService::class);
        
        $this->authController = new AuthController(
            $this->loginHandler,
            $this->registerHandler,
            $this->forgotPasswordHandler,
            $this->authService
        );
        
        // Reset global variables
        $_SERVER = [];
        $_POST = [];
        $_GET = [];
    }

    /**
     * Test handleLogin with POST request and valid nonce
     */
    public function test_handle_login_with_post_request_and_valid_nonce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_login_nonce'] = 'valid_nonce';
        $_POST['user_login'] = 'testuser';
        $_POST['user_pass'] = 'testpass';
        $_POST['remember'] = '1';
        $_POST['redirect_to'] = '/dashboard';
        
        // Mock WordPress functions
        $this->mockWordPressFunction('wp_verify_nonce', true);
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('sanitize_url', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        $this->mockWordPressFunction('wp_redirect', null);
        
        // Mock handler response
        $this->loginHandler
            ->expects($this->once())
            ->method('handle')
            ->willReturn([
                'success' => true,
                'user' => (object)['ID' => 1],
                'redirect_to' => '/dashboard'
            ]);
        
        // This should redirect, so we expect it to exit
        $this->expectException(\Exception::class);
        $this->authController->handleLogin();
    }

    /**
     * Test handleLogin with failed login
     */
    public function test_handle_login_with_failed_login(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_login_nonce'] = 'valid_nonce';
        $_POST['user_login'] = 'wronguser';
        $_POST['user_pass'] = 'wrongpass';
        
        $this->mockWordPressFunction('wp_verify_nonce', true);
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('sanitize_url', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        
        $this->loginHandler
            ->expects($this->once())
            ->method('handle')
            ->willReturn([
                'success' => false,
                'error' => 'Invalid credentials'
            ]);
        
        // Should not redirect on failure
        $this->authController->handleLogin();
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test handleLogin with invalid nonce
     */
    public function test_handle_login_with_invalid_nonce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_login_nonce'] = 'invalid_nonce';
        
        $this->mockWordPressFunction('wp_verify_nonce', false);
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        
        // Should not call handler with invalid nonce
        $this->loginHandler
            ->expects($this->never())
            ->method('handle');
        
        $this->authController->handleLogin();
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test handleLogin with GET request (no form submission)
     */
    public function test_handle_login_with_get_request(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        
        // Should not call handler with GET request
        $this->loginHandler
            ->expects($this->never())
            ->method('handle');
        
        $this->authController->handleLogin();
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test handleRegister with valid POST data
     */
    public function test_handle_register_with_valid_post_data(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_register_nonce'] = 'valid_nonce';
        $_POST['user_login'] = 'newuser';
        $_POST['user_email'] = 'newuser@example.com';
        $_POST['user_pass'] = 'password123';
        $_POST['redirect_to'] = '/dashboard';
        
        $this->mockWordPressFunction('wp_verify_nonce', true);
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('sanitize_email', fn($val) => $val);
        $this->mockWordPressFunction('sanitize_url', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        $this->mockWordPressFunction('wp_redirect', null);
        $this->mockWordPressFunction('defined', false); // MINISITE_ROLE_USER not defined
        
        $this->registerHandler
            ->expects($this->once())
            ->method('handle')
            ->willReturn([
                'success' => true,
                'user' => (object)['ID' => 2],
                'redirect_to' => '/dashboard'
            ]);
        
        $this->expectException(\Exception::class);
        $this->authController->handleRegister();
    }

    /**
     * Test handleRegister with failed registration
     */
    public function test_handle_register_with_failed_registration(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_register_nonce'] = 'valid_nonce';
        $_POST['user_login'] = 'newuser';
        $_POST['user_email'] = 'invalid-email';
        $_POST['user_pass'] = 'password123';
        
        $this->mockWordPressFunction('wp_verify_nonce', true);
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('sanitize_email', fn($val) => $val);
        $this->mockWordPressFunction('sanitize_url', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        
        $this->registerHandler
            ->expects($this->once())
            ->method('handle')
            ->willReturn([
                'success' => false,
                'error' => 'Invalid email address'
            ]);
        
        $this->authController->handleRegister();
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test handleDashboard when user is logged in
     */
    public function test_handle_dashboard_when_user_is_logged_in(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        
        $this->authService
            ->expects($this->once())
            ->method('isLoggedIn')
            ->willReturn(true);
        
        $this->authService
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);
        
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        
        $this->authController->handleDashboard();
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test handleDashboard when user is not logged in
     */
    public function test_handle_dashboard_when_user_is_not_logged_in(): void
    {
        $_SERVER['REQUEST_URI'] = '/account/dashboard';
        
        $this->authService
            ->expects($this->once())
            ->method('isLoggedIn')
            ->willReturn(false);
        
        $this->mockWordPressFunction('home_url', 'http://localhost/account/login');
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('urlencode', fn($val) => urlencode($val));
        $this->mockWordPressFunction('wp_redirect', null);
        
        $this->expectException(\Exception::class);
        $this->authController->handleDashboard();
    }

    /**
     * Test handleLogout
     */
    public function test_handle_logout(): void
    {
        $this->authService
            ->expects($this->once())
            ->method('logout');
        
        $this->mockWordPressFunction('home_url', 'http://localhost/account/login');
        $this->mockWordPressFunction('wp_redirect', null);
        
        $this->expectException(\Exception::class);
        $this->authController->handleLogout();
    }

    /**
     * Test handleForgotPassword with valid POST data
     */
    public function test_handle_forgot_password_with_valid_post_data(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_forgot_password_nonce'] = 'valid_nonce';
        $_POST['user_login'] = 'testuser@example.com';
        
        $this->mockWordPressFunction('wp_verify_nonce', true);
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        
        $this->forgotPasswordHandler
            ->expects($this->once())
            ->method('handle')
            ->willReturn([
                'success' => true,
                'message' => 'Password reset email sent'
            ]);
        
        $this->authController->handleForgotPassword();
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test handleForgotPassword with failed request
     */
    public function test_handle_forgot_password_with_failed_request(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_forgot_password_nonce'] = 'valid_nonce';
        $_POST['user_login'] = 'nonexistent@example.com';
        
        $this->mockWordPressFunction('wp_verify_nonce', true);
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        
        $this->forgotPasswordHandler
            ->expects($this->once())
            ->method('handle')
            ->willReturn([
                'success' => false,
                'error' => 'Invalid username or email address'
            ]);
        
        $this->authController->handleForgotPassword();
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test handleForgotPassword with GET request
     */
    public function test_handle_forgot_password_with_get_request(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        // Should not call handler with GET request
        $this->forgotPasswordHandler
            ->expects($this->never())
            ->method('handle');
        
        $this->authController->handleForgotPassword();
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test constructor sets all dependencies
     */
    public function test_constructor_sets_all_dependencies(): void
    {
        $reflection = new \ReflectionClass($this->authController);
        
        $loginHandlerProperty = $reflection->getProperty('loginHandler');
        $loginHandlerProperty->setAccessible(true);
        $this->assertSame($this->loginHandler, $loginHandlerProperty->getValue($this->authController));
        
        $registerHandlerProperty = $reflection->getProperty('registerHandler');
        $registerHandlerProperty->setAccessible(true);
        $this->assertSame($this->registerHandler, $registerHandlerProperty->getValue($this->authController));
        
        $forgotPasswordHandlerProperty = $reflection->getProperty('forgotPasswordHandler');
        $forgotPasswordHandlerProperty->setAccessible(true);
        $this->assertSame($this->forgotPasswordHandler, $forgotPasswordHandlerProperty->getValue($this->authController));
        
        $authServiceProperty = $reflection->getProperty('authService');
        $authServiceProperty->setAccessible(true);
        $this->assertSame($this->authService, $authServiceProperty->getValue($this->authController));
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

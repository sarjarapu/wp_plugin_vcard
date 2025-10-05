<?php

namespace Tests\Unit\Features\Authentication\Controllers;

use Minisite\Features\Authentication\Controllers\AuthController;
use Minisite\Features\Authentication\Handlers\LoginHandler;
use Minisite\Features\Authentication\Handlers\RegisterHandler;
use Minisite\Features\Authentication\Handlers\ForgotPasswordHandler;
use Minisite\Features\Authentication\Services\AuthService;
use Minisite\Features\Authentication\Http\AuthRequestHandler;
use Minisite\Features\Authentication\Http\AuthResponseHandler;
use Minisite\Features\Authentication\Rendering\AuthRenderer;
use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use PHPUnit\Framework\TestCase;

/**
 * Test AuthController
 * 
 * Tests the AuthController for proper coordination of authentication flow
 */
final class AuthControllerTest extends TestCase
{
    private AuthController $authController;
    private $loginHandler;
    private $registerHandler;
    private $forgotPasswordHandler;
    private $authService;
    private $requestHandler;
    private $responseHandler;
    private $renderer;

    protected function setUp(): void
    {
        // Define MINISITE_ROLE_USER constant if not already defined
        if (!defined('MINISITE_ROLE_USER')) {
            define('MINISITE_ROLE_USER', 'minisite_user');
        }

        // Create mocks for all dependencies
        $this->loginHandler = $this->createMock(LoginHandler::class);
        $this->registerHandler = $this->createMock(RegisterHandler::class);
        $this->forgotPasswordHandler = $this->createMock(ForgotPasswordHandler::class);
        $this->authService = $this->createMock(AuthService::class);
        $this->requestHandler = $this->createMock(AuthRequestHandler::class);
        $this->responseHandler = $this->createMock(AuthResponseHandler::class);
        $this->renderer = $this->createMock(AuthRenderer::class);

        // Create AuthController with mocked dependencies
        $this->authController = new AuthController(
            $this->loginHandler,
            $this->registerHandler,
            $this->forgotPasswordHandler,
            $this->authService,
            $this->requestHandler,
            $this->responseHandler,
            $this->renderer
        );
    }

    /**
     * Test AuthController can be instantiated
     */
    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(AuthController::class, $this->authController);
    }

    /**
     * Test handleLogin with successful login
     */
    public function test_handle_login_with_successful_login(): void
    {
        $loginCommand = new LoginCommand('testuser', 'password', false, '/account/dashboard');
        
        // Mock request handler to return a login command
        $this->requestHandler->method('handleLoginRequest')
            ->willReturn($loginCommand);
        
        // Mock login handler to return success
        $this->loginHandler->method('handle')
            ->with($loginCommand)
            ->willReturn(['success' => true, 'redirect_to' => '/account/dashboard']);
        
        // Mock response handler to redirect
        $this->responseHandler->expects($this->once())
            ->method('redirect')
            ->with('/account/dashboard');
        
        // Call the method
        $this->authController->handleLogin();
    }

    /**
     * Test handleLogin with failed login
     */
    public function test_handle_login_with_failed_login(): void
    {
        $loginCommand = new LoginCommand('testuser', 'wrongpassword', false, '/account/dashboard');
        
        // Mock request handler to return a login command
        $this->requestHandler->method('handleLoginRequest')
            ->willReturn($loginCommand);
        
        // Mock request handler to return redirect URL
        $this->requestHandler->method('getRedirectTo')
            ->willReturn('/account/dashboard');
        
        // Mock login handler to return failure
        $this->loginHandler->method('handle')
            ->with($loginCommand)
            ->willReturn(['success' => false, 'error' => 'Invalid credentials']);
        
        // Mock response handler to create error context
        $this->responseHandler->method('createErrorContext')
            ->with('Sign In', 'Invalid credentials', ['redirect_to' => '/account/dashboard'])
            ->willReturn(['page_title' => 'Sign In', 'error_msg' => 'Invalid credentials', 'redirect_to' => '/account/dashboard']);
        
        // Mock renderer to render login page with error
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('account-login.twig', ['page_title' => 'Sign In', 'error_msg' => 'Invalid credentials', 'redirect_to' => '/account/dashboard']);
        
        // Call the method
        $this->authController->handleLogin();
    }

    /**
     * Test handleLogin with no command (GET request)
     */
    public function test_handle_login_with_no_command(): void
    {
        // Mock request handler to return null (no POST data)
        $this->requestHandler->method('handleLoginRequest')
            ->willReturn(null);
        
        // Mock request handler to return redirect URL
        $this->requestHandler->method('getRedirectTo')
            ->willReturn('/account/dashboard');
        
        // Mock response handler to create context
        $this->responseHandler->method('createErrorContext')
            ->with('Sign In', '', ['redirect_to' => '/account/dashboard'])
            ->willReturn(['page_title' => 'Sign In', 'error_msg' => '', 'redirect_to' => '/account/dashboard']);
        
        // Mock renderer to render login page
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('account-login.twig', ['page_title' => 'Sign In', 'error_msg' => '', 'redirect_to' => '/account/dashboard']);
        
        // Call the method
        $this->authController->handleLogin();
    }

    /**
     * Test handleLogin with invalid argument exception
     */
    public function test_handle_login_with_invalid_argument_exception(): void
    {
        // Mock request handler to throw exception
        $this->requestHandler->method('handleLoginRequest')
            ->willThrowException(new \InvalidArgumentException('Invalid nonce'));
        
        // Mock request handler to return redirect URL
        $this->requestHandler->method('getRedirectTo')
            ->willReturn('/account/dashboard');
        
        // Mock response handler to create context
        $this->responseHandler->method('createErrorContext')
            ->with('Sign In', 'Invalid nonce', ['redirect_to' => '/account/dashboard'])
            ->willReturn(['page_title' => 'Sign In', 'error_msg' => 'Invalid nonce', 'redirect_to' => '/account/dashboard']);
        
        // Mock renderer to render login page with error
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('account-login.twig', ['page_title' => 'Sign In', 'error_msg' => 'Invalid nonce', 'redirect_to' => '/account/dashboard']);
        
        // Call the method
        $this->authController->handleLogin();
    }

    /**
     * Test handleRegister with successful registration
     */
    public function test_handle_register_with_successful_registration(): void
    {
        $registerCommand = new RegisterCommand('testuser', 'test@example.com', 'password', '/account/dashboard');
        
        // Mock request handler to return a register command
        $this->requestHandler->method('handleRegisterRequest')
            ->willReturn($registerCommand);
        
        // Mock register handler to return success with user object
        $mockUser = new \WP_User(123, 'testuser');
        $this->registerHandler->method('handle')
            ->with($registerCommand)
            ->willReturn(['success' => true, 'user' => $mockUser, 'redirect_to' => '/account/dashboard']);
        
        // Mock response handler to redirect
        $this->responseHandler->expects($this->once())
            ->method('redirect')
            ->with('/account/dashboard');
        
        // Call the method
        $this->authController->handleRegister();
    }

    /**
     * Test handleRegister with failed registration
     */
    public function test_handle_register_with_failed_registration(): void
    {
        $registerCommand = new RegisterCommand('testuser', 'invalid-email', 'password', '/account/dashboard');
        
        // Mock request handler to return a register command
        $this->requestHandler->method('handleRegisterRequest')
            ->willReturn($registerCommand);
        
        // Mock request handler to return redirect URL
        $this->requestHandler->method('getRedirectTo')
            ->willReturn('/account/dashboard');
        
        // Mock register handler to return failure
        $this->registerHandler->method('handle')
            ->with($registerCommand)
            ->willReturn(['success' => false, 'error' => 'Invalid email']);
        
        // Mock response handler to create error context
        $this->responseHandler->method('createErrorContext')
            ->with('Create Account', 'Invalid email', ['redirect_to' => '/account/dashboard'])
            ->willReturn(['page_title' => 'Create Account', 'error_msg' => 'Invalid email', 'redirect_to' => '/account/dashboard']);
        
        // Mock renderer to render register page with error
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('account-register.twig', ['page_title' => 'Create Account', 'error_msg' => 'Invalid email', 'redirect_to' => '/account/dashboard']);
        
        // Call the method
        $this->authController->handleRegister();
    }

    /**
     * Test handleRegister with no command (GET request)
     */
    public function test_handle_register_with_no_command(): void
    {
        // Mock request handler to return null (no POST data)
        $this->requestHandler->method('handleRegisterRequest')
            ->willReturn(null);
        
        // Mock request handler to return redirect URL
        $this->requestHandler->method('getRedirectTo')
            ->willReturn('/account/dashboard');
        
        // Mock response handler to create context
        $this->responseHandler->method('createErrorContext')
            ->with('Create Account', '', ['redirect_to' => '/account/dashboard'])
            ->willReturn(['page_title' => 'Create Account', 'error_msg' => '', 'redirect_to' => '/account/dashboard']);
        
        // Mock renderer to render register page
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('account-register.twig', ['page_title' => 'Create Account', 'error_msg' => '', 'redirect_to' => '/account/dashboard']);
        
        // Call the method
        $this->authController->handleRegister();
    }

    /**
     * Test handleForgotPassword with successful request
     */
    public function test_handle_forgot_password_with_successful_request(): void
    {
        $forgotCommand = new ForgotPasswordCommand('test@example.com');
        
        // Mock request handler to return a forgot password command
        $this->requestHandler->method('handleForgotPasswordRequest')
            ->willReturn($forgotCommand);
        
        // Mock forgot password handler to return success
        $this->forgotPasswordHandler->method('handle')
            ->with($forgotCommand)
            ->willReturn(['success' => true, 'message' => 'Password reset email sent']);
        
        // Mock response handler to create success context
        $this->responseHandler->method('createSuccessContext')
            ->with('Reset Password', 'Password reset email sent', [])
            ->willReturn(['page_title' => 'Reset Password', 'success_msg' => 'Password reset email sent']);
        
        // Mock renderer to render forgot password page with success message
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('account-forgot.twig', ['page_title' => 'Reset Password', 'success_msg' => 'Password reset email sent']);
        
        // Call the method
        $this->authController->handleForgotPassword();
    }

    /**
     * Test handleForgotPassword with failed request
     */
    public function test_handle_forgot_password_with_failed_request(): void
    {
        $forgotCommand = new ForgotPasswordCommand('invalid-email');
        
        // Mock request handler to return a forgot password command
        $this->requestHandler->method('handleForgotPasswordRequest')
            ->willReturn($forgotCommand);
        
        // Mock forgot password handler to return failure
        $this->forgotPasswordHandler->method('handle')
            ->with($forgotCommand)
            ->willReturn(['success' => false, 'error' => 'Email not found']);
        
        // Mock response handler to create error context
        $this->responseHandler->method('createErrorContext')
            ->with('Reset Password', 'Email not found', [])
            ->willReturn(['page_title' => 'Reset Password', 'error_msg' => 'Email not found']);
        
        // Mock renderer to render forgot password page with error
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('account-forgot.twig', ['page_title' => 'Reset Password', 'error_msg' => 'Email not found']);
        
        // Call the method
        $this->authController->handleForgotPassword();
    }

    /**
     * Test handleForgotPassword with no command (GET request)
     */
    public function test_handle_forgot_password_with_no_command(): void
    {
        // Mock request handler to return null (no POST data)
        $this->requestHandler->method('handleForgotPasswordRequest')
            ->willReturn(null);
        
        // Mock response handler to create context
        $this->responseHandler->method('createErrorContext')
            ->with('Reset Password', '', [])
            ->willReturn(['page_title' => 'Reset Password', 'error_msg' => '']);
        
        // Mock renderer to render forgot password page
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('account-forgot.twig', ['page_title' => 'Reset Password', 'error_msg' => '']);
        
        // Call the method
        $this->authController->handleForgotPassword();
    }

    /**
     * Test handleDashboard with logged in user
     */
    public function test_handle_dashboard_with_logged_in_user(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->user_email = 'test@example.com';
        
        // Mock auth service to return logged in user
        $this->authService->method('isLoggedIn')
            ->willReturn(true);
        $this->authService->method('getCurrentUser')
            ->willReturn($mockUser);
        
        // Mock renderer to render dashboard
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('dashboard.twig', $this->callback(function($context) {
                return isset($context['user']) && $context['user'] instanceof \WP_User;
            }));
        
        // Call the method
        $this->authController->handleDashboard();
    }

    /**
     * Test handleDashboard with not logged in user
     */
    public function test_handle_dashboard_with_not_logged_in_user(): void
    {
        // Mock auth service to return not logged in
        $this->authService->method('isLoggedIn')
            ->willReturn(false);
        
        // Mock response handler to redirect to login
        $this->responseHandler->expects($this->once())
            ->method('redirectToLogin')
            ->with('');
        
        // Call the method
        $this->authController->handleDashboard();
    }

    /**
     * Test handleLogout
     */
    public function test_handle_logout(): void
    {
        // Mock auth service to logout
        $this->authService->expects($this->once())
            ->method('logout');
        
        // Mock response handler to redirect to login
        $this->responseHandler->expects($this->once())
            ->method('redirectToLogin');
        
        // Call the method
        $this->authController->handleLogout();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->authController);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(7, $constructor->getNumberOfParameters());
        
        $params = $constructor->getParameters();
        $expectedTypes = [
            LoginHandler::class,
            RegisterHandler::class,
            ForgotPasswordHandler::class,
            AuthService::class,
            AuthRequestHandler::class,
            AuthResponseHandler::class,
            AuthRenderer::class
        ];
        
        foreach ($params as $index => $param) {
            $this->assertEquals($expectedTypes[$index], $param->getType()->getName());
        }
    }
}

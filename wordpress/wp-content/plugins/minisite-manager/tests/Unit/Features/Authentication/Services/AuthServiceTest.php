<?php

namespace Tests\Unit\Features\Authentication\Services;

use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use Minisite\Features\Authentication\Services\AuthService;
use PHPUnit\Framework\TestCase;

/**
 * Test AuthService
 * 
 * Tests the AuthService with mocked WordPress functions
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AuthServiceTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        $this->authService = new AuthService();
    }

    /**
     * Test login with valid credentials
     */
    public function test_login_with_valid_credentials(): void
    {
        $command = new LoginCommand('testuser', 'testpass', false, '/dashboard');
        
        // Mock WordPress functions
        $mockUser = new \WP_User(1, 'testuser');
        
        // Mock wp_signon to return successful user
        $this->mockWordPressFunction('wp_signon', $mockUser);
        
        $result = $this->authService->login($command);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($mockUser, $result['user']);
        $this->assertEquals('/dashboard', $result['redirect_to']);
    }

    /**
     * Test login with invalid credentials
     */
    public function test_login_with_invalid_credentials(): void
    {
        $command = new LoginCommand('wronguser', 'wrongpass', false, '/dashboard');
        
        // Mock wp_signon to return WP_Error
        $wpError = new \WP_Error('invalid_credentials', 'Invalid username or password');
        $this->mockWordPressFunction('wp_signon', $wpError);
        
        $result = $this->authService->login($command);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid username or password', $result['error']);
    }

    /**
     * Test login with empty credentials
     */
    public function test_login_with_empty_credentials(): void
    {
        $command = new LoginCommand('', '', false, '/dashboard');
        
        $result = $this->authService->login($command);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Please enter both username/email and password.', $result['error']);
    }

    /**
     * Test login with remember me enabled
     */
    public function test_login_with_remember_me(): void
    {
        $command = new LoginCommand('testuser', 'testpass', true, '/dashboard');
        
        $mockUser = new \WP_User(1, 'testuser');
        $this->mockWordPressFunction('wp_signon', $mockUser);
        
        $result = $this->authService->login($command);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($mockUser, $result['user']);
    }

    /**
     * Test register with valid data
     */
    public function test_register_with_valid_data(): void
    {
        $command = new RegisterCommand('newuser', 'newuser@example.com', 'password123', '/dashboard');
        
        // Mock wp_create_user to return user ID
        $this->mockWordPressFunction('wp_create_user', 2);
        
        // Mock get_user_by to return user object
        $mockUser = new \WP_User(2, 'newuser');
        $this->mockWordPressFunction('get_user_by', $mockUser);
        
        // Mock wp_set_current_user and wp_set_auth_cookie
        $this->mockWordPressFunction('wp_set_current_user', null);
        $this->mockWordPressFunction('wp_set_auth_cookie', null);
        
        $result = $this->authService->register($command);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($mockUser, $result['user']);
        $this->assertEquals('/dashboard', $result['redirect_to']);
    }

    /**
     * Test register with invalid email
     */
    public function test_register_with_invalid_email(): void
    {
        $command = new RegisterCommand('newuser', 'invalid-email', 'password123', '/dashboard');
        
        $result = $this->authService->register($command);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Please enter a valid email address.', $result['error']);
    }

    /**
     * Test register with weak password
     */
    public function test_register_with_weak_password(): void
    {
        $command = new RegisterCommand('newuser', 'newuser@example.com', '123', '/dashboard');
        
        $result = $this->authService->register($command);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Password must be at least 6 characters long.', $result['error']);
    }

    /**
     * Test register with empty fields
     */
    public function test_register_with_empty_fields(): void
    {
        $command = new RegisterCommand('', '', '', '/dashboard');
        
        $result = $this->authService->register($command);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Please fill in all required fields.', $result['error']);
    }

    /**
     * Test register with duplicate user
     */
    public function test_register_with_duplicate_user(): void
    {
        $command = new RegisterCommand('existinguser', 'existing@example.com', 'password123', '/dashboard');
        
        // Mock wp_create_user to return WP_Error
        $wpError = new \WP_Error('existing_user_login', 'Username already exists');
        $this->mockWordPressFunction('wp_create_user', $wpError);
        
        $result = $this->authService->register($command);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Username already exists', $result['error']);
    }

    /**
     * Test forgot password with valid username
     */
    public function test_forgot_password_with_valid_username(): void
    {
        $command = new ForgotPasswordCommand('testuser');
        
        // Mock get_user_by to return user
        $mockUser = (object)['ID' => 1, 'user_login' => 'testuser'];
        $this->mockWordPressFunction('get_user_by', $mockUser);
        
        // Mock retrieve_password to return success
        $this->mockWordPressFunction('retrieve_password', true);
        
        $result = $this->authService->forgotPassword($command);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Password reset email sent. Please check your inbox.', $result['message']);
    }

    /**
     * Test forgot password with invalid username
     */
    public function test_forgot_password_with_invalid_username(): void
    {
        $command = new ForgotPasswordCommand('nonexistent');
        
        // Mock get_user_by to return false
        $this->mockWordPressFunction('get_user_by', false);
        
        $result = $this->authService->forgotPassword($command);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid username or email address.', $result['error']);
    }

    /**
     * Test forgot password with empty username
     */
    public function test_forgot_password_with_empty_username(): void
    {
        $command = new ForgotPasswordCommand('');
        
        $result = $this->authService->forgotPassword($command);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Please enter your username or email address.', $result['error']);
    }

    /**
     * Test forgot password with email sending failure
     */
    public function test_forgot_password_with_email_failure(): void
    {
        $command = new ForgotPasswordCommand('testuser');
        
        $mockUser = (object)['ID' => 1, 'user_login' => 'testuser'];
        $this->mockWordPressFunction('get_user_by', $mockUser);
        
        // Mock retrieve_password to return WP_Error
        $wpError = new \WP_Error('email_failed', 'Unable to send email');
        $this->mockWordPressFunction('retrieve_password', $wpError);
        
        $result = $this->authService->forgotPassword($command);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Unable to send email', $result['error']);
    }

    /**
     * Test logout functionality
     */
    public function test_logout(): void
    {
        // Mock wp_logout
        $this->mockWordPressFunction('wp_logout', null);
        
        // This should not throw any exceptions
        $this->authService->logout();
        
        $this->assertTrue(true); // If we get here, logout didn't throw
    }

    /**
     * Test isLoggedIn when user is logged in
     */
    public function test_is_logged_in_when_logged_in(): void
    {
        $this->mockWordPressFunction('is_user_logged_in', true);
        
        $result = $this->authService->isLoggedIn();
        
        $this->assertTrue($result);
    }

    /**
     * Test isLoggedIn when user is not logged in
     */
    public function test_is_logged_in_when_not_logged_in(): void
    {
        $this->mockWordPressFunction('is_user_logged_in', false);
        
        $result = $this->authService->isLoggedIn();
        
        $this->assertFalse($result);
    }

    /**
     * Test getCurrentUser when user exists
     */
    public function test_get_current_user_when_user_exists(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $this->mockWordPressFunction('wp_get_current_user', $mockUser);
        
        $result = $this->authService->getCurrentUser();
        
        $this->assertEquals($mockUser, $result);
    }

    /**
     * Test getCurrentUser when no user
     */
    public function test_get_current_user_when_no_user(): void
    {
        $mockUser = (object)['ID' => 0];
        $this->mockWordPressFunction('wp_get_current_user', $mockUser);
        
        $result = $this->authService->getCurrentUser();
        
        $this->assertNull($result);
    }

    /**
     * Mock WordPress function
     */
    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        if (!function_exists($functionName)) {
            eval("function {$functionName}(...\$args) { return " . var_export($returnValue, true) . "; }");
        }
    }
}

<?php

namespace Tests\Unit\Features\Authentication\Http;

use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use Minisite\Features\Authentication\Http\AuthRequestHandler;
use PHPUnit\Framework\TestCase;

/**
 * Test AuthRequestHandler
 * 
 * Tests the AuthRequestHandler for proper request processing and validation
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AuthRequestHandlerTest extends TestCase
{
    private AuthRequestHandler $requestHandler;

    protected function setUp(): void
    {
        $this->requestHandler = new AuthRequestHandler();
        
        // Reset $_SERVER and $_POST
        $_SERVER = [];
        $_POST = [];
        $_GET = [];
    }

    /**
     * Test handleLoginRequest with valid POST data
     */
    public function test_handle_login_request_with_valid_post_data(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_login_nonce'] = 'valid_nonce';
        $_POST['user_login'] = 'testuser';
        $_POST['user_pass'] = 'testpass';
        $_POST['remember'] = '1';
        $_POST['redirect_to'] = '/dashboard';
        
        // Mock wp_verify_nonce
        $this->mockWordPressFunction('wp_verify_nonce', true);
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('sanitize_url', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        
        $command = $this->requestHandler->handleLoginRequest();
        
        $this->assertInstanceOf(LoginCommand::class, $command);
        $this->assertEquals('testuser', $command->userLogin);
        $this->assertEquals('testpass', $command->userPassword);
        $this->assertTrue($command->remember);
        $this->assertEquals('/dashboard', $command->redirectTo);
    }

    /**
     * Test handleLoginRequest with invalid nonce
     */
    public function test_handle_login_request_with_invalid_nonce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_login_nonce'] = 'invalid_nonce';
        
        $this->mockWordPressFunction('wp_verify_nonce', false);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid nonce');
        
        $this->requestHandler->handleLoginRequest();
    }

    /**
     * Test handleLoginRequest with non-POST request
     */
    public function test_handle_login_request_with_non_post_request(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $result = $this->requestHandler->handleLoginRequest();
        
        $this->assertNull($result);
    }

    /**
     * Test handleLoginRequest without remember checkbox
     */
    public function test_handle_login_request_without_remember(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_login_nonce'] = 'valid_nonce';
        $_POST['user_login'] = 'testuser';
        $_POST['user_pass'] = 'testpass';
        // No remember checkbox
        
        $this->mockWordPressFunction('wp_verify_nonce', true);
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('sanitize_url', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        
        $command = $this->requestHandler->handleLoginRequest();
        
        $this->assertInstanceOf(LoginCommand::class, $command);
        $this->assertFalse($command->remember);
    }

    /**
     * Test handleRegisterRequest with valid POST data
     */
    public function test_handle_register_request_with_valid_post_data(): void
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
        
        $command = $this->requestHandler->handleRegisterRequest();
        
        $this->assertInstanceOf(RegisterCommand::class, $command);
        $this->assertEquals('newuser', $command->userLogin);
        $this->assertEquals('newuser@example.com', $command->userEmail);
        $this->assertEquals('password123', $command->userPassword);
        $this->assertEquals('/dashboard', $command->redirectTo);
    }

    /**
     * Test handleRegisterRequest with invalid nonce
     */
    public function test_handle_register_request_with_invalid_nonce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_register_nonce'] = 'invalid_nonce';
        
        $this->mockWordPressFunction('wp_verify_nonce', false);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid nonce');
        
        $this->requestHandler->handleRegisterRequest();
    }

    /**
     * Test handleForgotPasswordRequest with valid POST data
     */
    public function test_handle_forgot_password_request_with_valid_post_data(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_forgot_password_nonce'] = 'valid_nonce';
        $_POST['user_login'] = 'testuser@example.com';
        
        $this->mockWordPressFunction('wp_verify_nonce', true);
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        
        $command = $this->requestHandler->handleForgotPasswordRequest();
        
        $this->assertInstanceOf(ForgotPasswordCommand::class, $command);
        $this->assertEquals('testuser@example.com', $command->userLogin);
    }

    /**
     * Test handleForgotPasswordRequest with invalid nonce
     */
    public function test_handle_forgot_password_request_with_invalid_nonce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_forgot_password_nonce'] = 'invalid_nonce';
        
        $this->mockWordPressFunction('wp_verify_nonce', false);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid nonce');
        
        $this->requestHandler->handleForgotPasswordRequest();
    }

    /**
     * Test getRedirectTo with query parameter
     */
    public function test_get_redirect_to_with_query_parameter(): void
    {
        $_GET['redirect_to'] = '/custom/redirect';
        
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        
        $result = $this->requestHandler->getRedirectTo();
        
        $this->assertEquals('/custom/redirect', $result);
    }

    /**
     * Test getRedirectTo without query parameter
     */
    public function test_get_redirect_to_without_query_parameter(): void
    {
        $this->mockWordPressFunction('sanitize_text_field', fn($val) => $val);
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        
        $result = $this->requestHandler->getRedirectTo();
        
        $this->assertEquals('/account/dashboard', $result);
    }

    /**
     * Test sanitization functions are called
     */
    public function test_sanitization_functions_are_called(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['minisite_login_nonce'] = 'valid_nonce';
        $_POST['user_login'] = 'testuser';
        $_POST['user_pass'] = 'testpass';
        
        $sanitizeCalled = false;
        $this->mockWordPressFunction('wp_verify_nonce', true);
        $this->mockWordPressFunction('sanitize_text_field', function($val) use (&$sanitizeCalled) {
            $sanitizeCalled = true;
            return $val;
        });
        $this->mockWordPressFunction('wp_unslash', fn($val) => $val);
        $this->mockWordPressFunction('sanitize_url', fn($val) => $val);
        $this->mockWordPressFunction('home_url', '/account/dashboard');
        
        $this->requestHandler->handleLoginRequest();
        
        $this->assertTrue($sanitizeCalled);
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

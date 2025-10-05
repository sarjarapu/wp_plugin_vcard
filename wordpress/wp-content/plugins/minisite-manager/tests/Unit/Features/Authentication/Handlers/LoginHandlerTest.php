<?php

namespace Tests\Unit\Features\Authentication\Handlers;

use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Handlers\LoginHandler;
use Minisite\Features\Authentication\Services\AuthService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test LoginHandler
 * 
 * Tests the LoginHandler to ensure proper delegation to AuthService
 */
final class LoginHandlerTest extends TestCase
{
    private AuthService|MockObject $authService;
    private LoginHandler $loginHandler;

    protected function setUp(): void
    {
        $this->authService = $this->createMock(AuthService::class);
        $this->loginHandler = new LoginHandler($this->authService);
    }

    /**
     * Test handle method delegates to AuthService
     */
    public function test_handle_delegates_to_auth_service(): void
    {
        $command = new LoginCommand('testuser', 'testpass', false, '/dashboard');
        $expectedResult = [
            'success' => true,
            'user' => (object)['ID' => 1, 'user_login' => 'testuser'],
            'redirect_to' => '/dashboard'
        ];

        $this->authService
            ->expects($this->once())
            ->method('login')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->loginHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method with failed login
     */
    public function test_handle_with_failed_login(): void
    {
        $command = new LoginCommand('wronguser', 'wrongpass', false, '/dashboard');
        $expectedResult = [
            'success' => false,
            'error' => 'Invalid username or password'
        ];

        $this->authService
            ->expects($this->once())
            ->method('login')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->loginHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test handle method with remember me enabled
     */
    public function test_handle_with_remember_me_enabled(): void
    {
        $command = new LoginCommand('testuser', 'testpass', true, '/dashboard');
        $expectedResult = [
            'success' => true,
            'user' => (object)['ID' => 1, 'user_login' => 'testuser'],
            'redirect_to' => '/dashboard'
        ];

        $this->authService
            ->expects($this->once())
            ->method('login')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->loginHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method with empty credentials
     */
    public function test_handle_with_empty_credentials(): void
    {
        $command = new LoginCommand('', '', false, '/dashboard');
        $expectedResult = [
            'success' => false,
            'error' => 'Please enter both username/email and password.'
        ];

        $this->authService
            ->expects($this->once())
            ->method('login')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->loginHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test handle method with special characters in credentials
     */
    public function test_handle_with_special_characters(): void
    {
        $command = new LoginCommand('user@example.com', 'pass!@#$%', false, '/dashboard');
        $expectedResult = [
            'success' => true,
            'user' => (object)['ID' => 1, 'user_login' => 'user@example.com'],
            'redirect_to' => '/dashboard'
        ];

        $this->authService
            ->expects($this->once())
            ->method('login')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->loginHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method returns exactly what AuthService returns
     */
    public function test_handle_returns_auth_service_result_unchanged(): void
    {
        $command = new LoginCommand('testuser', 'testpass', false, '/dashboard');
        $expectedResult = [
            'success' => true,
            'user' => (object)['ID' => 1, 'user_login' => 'testuser'],
            'redirect_to' => '/dashboard',
            'additional_data' => 'some_value'
        ];

        $this->authService
            ->expects($this->once())
            ->method('login')
            ->willReturn($expectedResult);

        $result = $this->loginHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertArrayHasKey('additional_data', $result);
    }
}

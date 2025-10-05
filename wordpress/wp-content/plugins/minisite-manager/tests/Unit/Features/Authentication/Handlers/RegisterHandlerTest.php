<?php

namespace Tests\Unit\Features\Authentication\Handlers;

use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Handlers\RegisterHandler;
use Minisite\Features\Authentication\Services\AuthService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test RegisterHandler
 * 
 * Tests the RegisterHandler to ensure proper delegation to AuthService
 */
final class RegisterHandlerTest extends TestCase
{
    private AuthService|MockObject $authService;
    private RegisterHandler $registerHandler;

    protected function setUp(): void
    {
        $this->authService = $this->createMock(AuthService::class);
        $this->registerHandler = new RegisterHandler($this->authService);
    }

    /**
     * Test handle method delegates to AuthService
     */
    public function test_handle_delegates_to_auth_service(): void
    {
        $command = new RegisterCommand('newuser', 'newuser@example.com', 'newpass123', '/dashboard');
        $expectedResult = [
            'success' => true,
            'user' => (object)['ID' => 2, 'user_login' => 'newuser'],
            'redirect_to' => '/dashboard'
        ];

        $this->authService
            ->expects($this->once())
            ->method('register')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->registerHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method with failed registration
     */
    public function test_handle_with_failed_registration(): void
    {
        $command = new RegisterCommand('newuser', 'invalid-email', 'weak', '/dashboard');
        $expectedResult = [
            'success' => false,
            'error' => 'Please enter a valid email address.'
        ];

        $this->authService
            ->expects($this->once())
            ->method('register')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->registerHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test handle method with empty fields
     */
    public function test_handle_with_empty_fields(): void
    {
        $command = new RegisterCommand('', '', '', '/dashboard');
        $expectedResult = [
            'success' => false,
            'error' => 'Please fill in all required fields.'
        ];

        $this->authService
            ->expects($this->once())
            ->method('register')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->registerHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test handle method with weak password
     */
    public function test_handle_with_weak_password(): void
    {
        $command = new RegisterCommand('newuser', 'newuser@example.com', '123', '/dashboard');
        $expectedResult = [
            'success' => false,
            'error' => 'Password must be at least 6 characters long.'
        ];

        $this->authService
            ->expects($this->once())
            ->method('register')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->registerHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test handle method with duplicate user
     */
    public function test_handle_with_duplicate_user(): void
    {
        $command = new RegisterCommand('existinguser', 'existing@example.com', 'password123', '/dashboard');
        $expectedResult = [
            'success' => false,
            'error' => 'Username already exists'
        ];

        $this->authService
            ->expects($this->once())
            ->method('register')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->registerHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test handle method with valid registration
     */
    public function test_handle_with_valid_registration(): void
    {
        $command = new RegisterCommand('newuser', 'newuser@example.com', 'password123', '/dashboard');
        $expectedResult = [
            'success' => true,
            'user' => (object)['ID' => 3, 'user_login' => 'newuser', 'user_email' => 'newuser@example.com'],
            'redirect_to' => '/dashboard'
        ];

        $this->authService
            ->expects($this->once())
            ->method('register')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->registerHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user', $result);
    }

    /**
     * Test handle method returns exactly what AuthService returns
     */
    public function test_handle_returns_auth_service_result_unchanged(): void
    {
        $command = new RegisterCommand('newuser', 'newuser@example.com', 'password123', '/dashboard');
        $expectedResult = [
            'success' => true,
            'user' => (object)['ID' => 3, 'user_login' => 'newuser'],
            'redirect_to' => '/dashboard',
            'additional_info' => 'auto_logged_in'
        ];

        $this->authService
            ->expects($this->once())
            ->method('register')
            ->willReturn($expectedResult);

        $result = $this->registerHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertArrayHasKey('additional_info', $result);
    }
}

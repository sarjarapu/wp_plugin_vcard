<?php

namespace Tests\Unit\Features\Authentication\Handlers;

use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use Minisite\Features\Authentication\Handlers\ForgotPasswordHandler;
use Minisite\Features\Authentication\Services\AuthService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test ForgotPasswordHandler
 * 
 * Tests the ForgotPasswordHandler to ensure proper delegation to AuthService
 */
final class ForgotPasswordHandlerTest extends TestCase
{
    private AuthService|MockObject $authService;
    private ForgotPasswordHandler $forgotPasswordHandler;

    protected function setUp(): void
    {
        $this->authService = $this->createMock(AuthService::class);
        $this->forgotPasswordHandler = new ForgotPasswordHandler($this->authService);
    }

    /**
     * Test handle method delegates to AuthService
     */
    public function test_handle_delegates_to_auth_service(): void
    {
        $command = new ForgotPasswordCommand('testuser@example.com');
        $expectedResult = [
            'success' => true,
            'message' => 'Password reset email sent. Please check your inbox.'
        ];

        $this->authService
            ->expects($this->once())
            ->method('forgotPassword')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->forgotPasswordHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test handle method with empty username
     */
    public function test_handle_with_empty_username(): void
    {
        $command = new ForgotPasswordCommand('');
        $expectedResult = [
            'success' => false,
            'error' => 'Please enter your username or email address.'
        ];

        $this->authService
            ->expects($this->once())
            ->method('forgotPassword')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->forgotPasswordHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test handle method with invalid username/email
     */
    public function test_handle_with_invalid_username(): void
    {
        $command = new ForgotPasswordCommand('nonexistent@example.com');
        $expectedResult = [
            'success' => false,
            'error' => 'Invalid username or email address.'
        ];

        $this->authService
            ->expects($this->once())
            ->method('forgotPassword')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->forgotPasswordHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test handle method with valid username
     */
    public function test_handle_with_valid_username(): void
    {
        $command = new ForgotPasswordCommand('existinguser');
        $expectedResult = [
            'success' => true,
            'message' => 'Password reset email sent. Please check your inbox.'
        ];

        $this->authService
            ->expects($this->once())
            ->method('forgotPassword')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->forgotPasswordHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * Test handle method with valid email
     */
    public function test_handle_with_valid_email(): void
    {
        $command = new ForgotPasswordCommand('existing@example.com');
        $expectedResult = [
            'success' => true,
            'message' => 'Password reset email sent. Please check your inbox.'
        ];

        $this->authService
            ->expects($this->once())
            ->method('forgotPassword')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->forgotPasswordHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertTrue($result['success']);
    }

    /**
     * Test handle method with email sending failure
     */
    public function test_handle_with_email_sending_failure(): void
    {
        $command = new ForgotPasswordCommand('validuser@example.com');
        $expectedResult = [
            'success' => false,
            'error' => 'Unable to send password reset email. Please try again later.'
        ];

        $this->authService
            ->expects($this->once())
            ->method('forgotPassword')
            ->with($command)
            ->willReturn($expectedResult);

        $result = $this->forgotPasswordHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test handle method returns exactly what AuthService returns
     */
    public function test_handle_returns_auth_service_result_unchanged(): void
    {
        $command = new ForgotPasswordCommand('testuser@example.com');
        $expectedResult = [
            'success' => true,
            'message' => 'Password reset email sent. Please check your inbox.',
            'additional_data' => 'email_id_123'
        ];

        $this->authService
            ->expects($this->once())
            ->method('forgotPassword')
            ->willReturn($expectedResult);

        $result = $this->forgotPasswordHandler->handle($command);

        $this->assertEquals($expectedResult, $result);
        $this->assertArrayHasKey('additional_data', $result);
    }
}

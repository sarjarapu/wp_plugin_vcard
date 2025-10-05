<?php

namespace Minisite\Features\Authentication\Handlers;

use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use Minisite\Features\Authentication\Services\AuthService;

/**
 * Forgot Password Handler
 * 
 * Handles password reset command execution by delegating to AuthService.
 */
final class ForgotPasswordHandler
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Handle forgot password command
     * 
     * @param ForgotPasswordCommand $command
     * @return array{success: bool, error?: string, message?: string}
     */
    public function handle(ForgotPasswordCommand $command): array
    {
        return $this->authService->forgotPassword($command);
    }
}
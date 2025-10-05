<?php

namespace Minisite\Features\Authentication\Handlers;

use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Services\AuthService;

/**
 * Login Handler
 *
 * Handles login command execution by delegating to AuthService.
 */
final class LoginHandler
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    /**
     * Handle login command
     *
     * @param LoginCommand $command
     * @return array{success: bool, error?: string, user?: \WP_User, redirect_to?: string}
     */
    public function handle(LoginCommand $command): array
    {
        return $this->authService->login($command);
    }
}

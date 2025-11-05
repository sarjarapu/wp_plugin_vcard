<?php

namespace Minisite\Features\Authentication\Handlers;

use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Services\AuthService;

/**
 * Register Handler
 *
 * Handles registration command execution by delegating to AuthService.
 */
class RegisterHandler
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    /**
     * Handle registration command
     *
     * @param RegisterCommand $command
     * @return array{success: bool, error?: string, user?: \WP_User, redirect_to?: string}
     */
    public function handle(RegisterCommand $command): array
    {
        return $this->authService->register($command);
    }
}

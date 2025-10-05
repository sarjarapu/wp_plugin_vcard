<?php

namespace Minisite\Features\Authentication\Hooks;

use Minisite\Features\Authentication\Controllers\AuthController;
use Minisite\Features\Authentication\Handlers\LoginHandler;
use Minisite\Features\Authentication\Handlers\RegisterHandler;
use Minisite\Features\Authentication\Handlers\ForgotPasswordHandler;
use Minisite\Features\Authentication\Services\AuthService;

/**
 * AuthHooks Factory
 * 
 * SINGLE RESPONSIBILITY: Create and configure AuthHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and handlers
 * - Configures the complete authentication system
 */
final class AuthHooksFactory
{
    /**
     * Create and configure AuthHooks
     */
    public static function create(): AuthHooks
    {
        // Create services
        $authService = new AuthService();

        // Create handlers
        $loginHandler = new LoginHandler($authService);
        $registerHandler = new RegisterHandler($authService);
        $forgotPasswordHandler = new ForgotPasswordHandler($authService);

        // Create controller
        $authController = new AuthController(
            $loginHandler,
            $registerHandler,
            $forgotPasswordHandler,
            $authService
        );

        // Create and return hooks
        return new AuthHooks($authController);
    }
}

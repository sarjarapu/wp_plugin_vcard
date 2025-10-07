<?php

namespace Minisite\Features\Authentication\Hooks;

use Minisite\Features\Authentication\Controllers\AuthController;
use Minisite\Features\Authentication\Handlers\LoginHandler;
use Minisite\Features\Authentication\Handlers\RegisterHandler;
use Minisite\Features\Authentication\Handlers\ForgotPasswordHandler;
use Minisite\Features\Authentication\Services\AuthService;
use Minisite\Features\Authentication\WordPress\WordPressUserManager;

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
        $wordPressManager = new WordPressUserManager();
        $authService = new AuthService($wordPressManager);

        // Create handlers
        $loginHandler = new LoginHandler($authService);
        $registerHandler = new RegisterHandler($authService);
        $forgotPasswordHandler = new ForgotPasswordHandler($authService);

        // Create additional dependencies for refactored controller
        $requestHandler = new \Minisite\Features\Authentication\Http\AuthRequestHandler($wordPressManager);
        $responseHandler = new \Minisite\Features\Authentication\Http\AuthResponseHandler($wordPressManager);
        $renderer = new \Minisite\Features\Authentication\Rendering\AuthRenderer();

        // Create controller
        $authController = new AuthController(
            $loginHandler,
            $registerHandler,
            $forgotPasswordHandler,
            $authService,
            $requestHandler,
            $responseHandler,
            $renderer
        );

        // Create and return hooks
        return new AuthHooks($authController);
    }
}

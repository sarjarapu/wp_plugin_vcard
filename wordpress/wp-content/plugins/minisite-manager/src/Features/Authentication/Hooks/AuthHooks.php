<?php

namespace Minisite\Features\Authentication\Hooks;

use Minisite\Features\Authentication\Controllers\AuthController;

/**
 * Authentication Hooks
 * 
 * SINGLE RESPONSIBILITY: Register WordPress hooks for authentication routes
 * - Registers rewrite rules for authentication pages
 * - Hooks into WordPress template_redirect
 * - Manages authentication route handling
 */
final class AuthHooks
{
    public function __construct(
        private AuthController $authController
    ) {}

    /**
     * Register all authentication hooks
     */
    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRules']);
        // Use priority 5 to run before the main plugin's template_redirect (which runs at priority 10)
        add_action('template_redirect', [$this, 'handleAuthRoutes'], 5);
    }

    /**
     * Add rewrite rules for authentication pages
     * Note: We don't add rewrite rules here because the existing RewriteRegistrar
     * already handles /account/* routes. We just need to hook into the existing system.
     */
    public function addRewriteRules(): void
    {
        // Add query vars for our new authentication system
        add_filter('query_vars', [$this, 'addQueryVars']);
    }

    /**
     * Add query variables for authentication routes
     * Note: We use the existing minisite_account and minisite_account_action vars
     */
    public function addQueryVars(array $vars): array
    {
        // We don't need to add any new query vars since we're using the existing system
        return $vars;
    }

    /**
     * Handle authentication routes
     * This hooks into the existing minisite_account system
     */
    public function handleAuthRoutes(): void
    {
        // Check if this is an account route handled by our new system
        if ((int) get_query_var('minisite_account') !== 1) {
            return;
        }

        $action = get_query_var('minisite_account_action');
        
        // Only handle authentication routes, let the old system handle sites, new, etc.
        $authActions = ['login', 'logout', 'dashboard', 'register', 'forgot'];
        if (!in_array($action, $authActions)) {
            return;
        }

        // Route to appropriate controller method
        match ($action) {
            'login' => $this->authController->handleLogin(),
            'logout' => $this->authController->handleLogout(),
            'dashboard' => $this->authController->handleDashboard(),
            'register' => $this->authController->handleRegister(),
            'forgot' => $this->authController->handleForgotPassword(),
            default => $this->handleNotFound()
        };

        // Exit to prevent the old system from handling this request
        exit;
    }

    /**
     * Handle 404 for unknown auth routes
     */
    private function handleNotFound(): void
    {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        get_template_part('404');
        exit;
    }
}

<?php

namespace Minisite\Features\Authentication\Controllers;

use Minisite\Features\Authentication\Commands\LoginCommand;
use Minisite\Features\Authentication\Commands\RegisterCommand;
use Minisite\Features\Authentication\Commands\ForgotPasswordCommand;
use Minisite\Features\Authentication\Handlers\LoginHandler;
use Minisite\Features\Authentication\Handlers\RegisterHandler;
use Minisite\Features\Authentication\Handlers\ForgotPasswordHandler;
use Minisite\Features\Authentication\Services\AuthService;

/**
 * Authentication Controller
 * 
 * Thin controller that handles HTTP requests and delegates to appropriate handlers.
 * Follows the Command/Handler pattern for clean separation of concerns.
 */
final class AuthController
{
    public function __construct(
        private LoginHandler $loginHandler,
        private RegisterHandler $registerHandler,
        private ForgotPasswordHandler $forgotPasswordHandler,
        private AuthService $authService
    ) {}

    /**
     * Handle login page and form submission
     */
    public function handleLogin(): void
    {
        if ($this->isPostRequest() && $this->isValidNonce('minisite_login')) {
            $this->processLogin();
            return;
        }

        $this->renderLoginPage();
    }

    /**
     * Handle registration page and form submission
     */
    public function handleRegister(): void
    {
        if ($this->isPostRequest() && $this->isValidNonce('minisite_register')) {
            $this->processRegistration();
            return;
        }

        $this->renderRegisterPage();
    }

    /**
     * Handle forgot password page and form submission
     */
    public function handleForgotPassword(): void
    {
        if ($this->isPostRequest() && $this->isValidNonce('minisite_forgot_password')) {
            $this->processForgotPassword();
            return;
        }

        $this->renderForgotPasswordPage();
    }

    /**
     * Handle dashboard page
     */
    public function handleDashboard(): void
    {
        // Check if user is logged in
        if (!$this->authService->isLoggedIn()) {
            $redirect_url = home_url(
                '/account/login?redirect_to=' . urlencode(
                    isset($_SERVER['REQUEST_URI']) ?
                    sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : ''
                )
            );
            wp_redirect($redirect_url);
            exit;
        }

        $user = $this->authService->getCurrentUser();
        $this->renderAuthPage('dashboard.twig', [
            'page_title' => 'Dashboard',
            'user' => $user,
        ]);
    }

    /**
     * Handle logout
     */
    public function handleLogout(): void
    {
        $this->authService->logout();
        wp_redirect(home_url('/account/login'));
        exit;
    }

    /**
     * Process login form submission
     */
    private function processLogin(): void
    {
        $command = new LoginCommand(
            userLogin: $this->sanitizeInput($_POST['user_login'] ?? ''),
            userPassword: $this->sanitizeInput($_POST['user_pass'] ?? ''),
            remember: isset($_POST['remember']),
            redirectTo: $this->sanitizeUrl($_POST['redirect_to'] ?? home_url('/account/dashboard'))
        );

        $result = $this->loginHandler->handle($command);

        if ($result['success']) {
            wp_redirect($result['redirect_to']);
            exit;
        }

        $this->renderLoginPage($result['error']);
    }

    /**
     * Process registration form submission
     */
    private function processRegistration(): void
    {
        $command = new RegisterCommand(
            userLogin: $this->sanitizeInput($_POST['user_login'] ?? ''),
            userEmail: $this->sanitizeEmail($_POST['user_email'] ?? ''),
            userPassword: $this->sanitizeInput($_POST['user_pass'] ?? ''),
            redirectTo: $this->sanitizeUrl($_POST['redirect_to'] ?? home_url('/account/dashboard'))
        );

        $result = $this->registerHandler->handle($command);

        if ($result['success']) {
            // Set user role if constant is defined
            if (defined('MINISITE_ROLE_USER')) {
                $result['user']->set_role(MINISITE_ROLE_USER);
            }
            wp_redirect($result['redirect_to']);
            exit;
        }

        $this->renderRegisterPage($result['error']);
    }

    /**
     * Process forgot password form submission
     */
    private function processForgotPassword(): void
    {
        $command = new ForgotPasswordCommand(
            userLogin: $this->sanitizeInput($_POST['user_login'] ?? '')
        );

        $result = $this->forgotPasswordHandler->handle($command);
        
        if ($result['success']) {
            $this->renderForgotPasswordPage(null, $result['message']);
        } else {
            $this->renderForgotPasswordPage($result['error']);
        }
    }

    /**
     * Render login page
     */
    private function renderLoginPage(?string $errorMessage = null): void
    {
        $this->renderAuthPage('account-login.twig', [
            'page_title' => 'Sign In',
            'error_msg' => $errorMessage,
            'redirect_to' => $this->getRedirectTo(),
        ]);
    }

    /**
     * Render registration page
     */
    private function renderRegisterPage(?string $errorMessage = null, ?string $successMessage = null): void
    {
        $this->renderAuthPage('account-register.twig', [
            'page_title' => 'Create Account',
            'error_msg' => $errorMessage,
            'success_msg' => $successMessage,
            'redirect_to' => $this->getRedirectTo(),
        ]);
    }

    /**
     * Render forgot password page
     */
    private function renderForgotPasswordPage(?string $errorMessage = null, ?string $successMessage = null): void
    {
        $this->renderAuthPage('account-forgot.twig', [
            'page_title' => 'Reset Password',
            'error_msg' => $errorMessage,
            'success_msg' => $successMessage,
        ]);
    }

    /**
     * Render authentication page using Timber
     */
    private function renderAuthPage(string $template, array $context): void
    {
        if (class_exists('Timber\\Timber')) {
            $viewsBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
            \Timber\Timber::$locations = array_values(
                array_unique(
                    array_merge(
                        \Timber\Timber::$locations ?? [],
                        [$viewsBase]
                    )
                )
            );

            \Timber\Timber::render($template, $context);
            return;
        }

        // Fallback for when Timber is not available
        $this->renderFallback($context);
    }

    /**
     * Fallback rendering when Timber is not available
     */
    private function renderFallback(array $context): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">';
        echo '<h1>' . esc_html($context['page_title'] ?? 'Authentication') . '</h1>';
        if (!empty($context['error_msg'])) {
            echo '<p style="color: red;">' . esc_html($context['error_msg']) . '</p>';
        }
        if (!empty($context['success_msg'])) {
            echo '<p style="color: green;">' . esc_html($context['success_msg']) . '</p>';
        }
        if (!empty($context['message'])) {
            echo '<p>' . esc_html($context['message']) . '</p>';
        }
        echo '<p>Authentication form not available (Timber required).</p>';
    }

    /**
     * Check if request is POST
     */
    private function isPostRequest(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Validate nonce for security
     */
    private function isValidNonce(string $action): bool
    {
        $nonceField = match($action) {
            'minisite_login' => 'minisite_login_nonce',
            'minisite_register' => 'minisite_register_nonce',
            'minisite_forgot_password' => 'minisite_forgot_password_nonce',
            default => 'minisite_nonce'
        };

        return isset($_POST[$nonceField]) && 
               wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonceField])), $action);
    }

    /**
     * Get redirect URL from query parameter
     */
    private function getRedirectTo(): string
    {
        return sanitize_text_field(wp_unslash($_GET['redirect_to'] ?? home_url('/account/dashboard')));
    }

    /**
     * Sanitize text input
     */
    private function sanitizeInput(string $input): string
    {
        return sanitize_text_field(wp_unslash($input));
    }

    /**
     * Sanitize email input
     */
    private function sanitizeEmail(string $email): string
    {
        return sanitize_email(wp_unslash($email));
    }

    /**
     * Sanitize URL input
     */
    private function sanitizeUrl(string $url): string
    {
        return sanitize_url(wp_unslash($url));
    }
}
<?php

namespace Minisite\Features\Authentication\Controllers;

use Minisite\Features\Authentication\Handlers\LoginHandler;
use Minisite\Features\Authentication\Handlers\RegisterHandler;
use Minisite\Features\Authentication\Handlers\ForgotPasswordHandler;
use Minisite\Features\Authentication\Services\AuthService;
use Minisite\Features\Authentication\Http\AuthRequestHandler;
use Minisite\Features\Authentication\Http\AuthResponseHandler;
use Minisite\Features\Authentication\Rendering\AuthRenderer;

/**
 * Refactored Auth Controller
 * 
 * SINGLE RESPONSIBILITY: Coordinate authentication flow
 * - Delegates HTTP handling to AuthRequestHandler
 * - Delegates business logic to Handlers
 * - Delegates responses to AuthResponseHandler
 * - Delegates rendering to AuthRenderer
 * 
 * This controller only orchestrates the flow - it doesn't do the work itself!
 */
final class AuthController
{
    public function __construct(
        private LoginHandler $loginHandler,
        private RegisterHandler $registerHandler,
        private ForgotPasswordHandler $forgotPasswordHandler,
        private AuthService $authService,
        private AuthRequestHandler $requestHandler,
        private AuthResponseHandler $responseHandler,
        private AuthRenderer $renderer
    ) {}

    /**
     * Handle login page and form submission
     */
    public function handleLogin(): void
    {
        try {
            $command = $this->requestHandler->handleLoginRequest();
            
            if ($command) {
                $this->processLogin($command);
                return;
            }
        } catch (\InvalidArgumentException $e) {
            $this->renderLoginPage($e->getMessage());
            return;
        }

        $this->renderLoginPage();
    }

    /**
     * Handle registration page and form submission
     */
    public function handleRegister(): void
    {
        try {
            $command = $this->requestHandler->handleRegisterRequest();
            
            if ($command) {
                $this->processRegistration($command);
                return;
            }
        } catch (\InvalidArgumentException $e) {
            $this->renderRegisterPage($e->getMessage());
            return;
        }

        $this->renderRegisterPage();
    }

    /**
     * Handle forgot password page and form submission
     */
    public function handleForgotPassword(): void
    {
        try {
            $command = $this->requestHandler->handleForgotPasswordRequest();
            
            if ($command) {
                $this->processForgotPassword($command);
                return;
            }
        } catch (\InvalidArgumentException $e) {
            $this->renderForgotPasswordPage($e->getMessage());
            return;
        }

        $this->renderForgotPasswordPage();
    }

    /**
     * Handle dashboard page
     */
    public function handleDashboard(): void
    {
        if (!$this->authService->isLoggedIn()) {
            $redirectTo = isset($_SERVER['REQUEST_URI']) ? 
                sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $this->responseHandler->redirectToLogin($redirectTo);
            return;
        }

        $user = $this->authService->getCurrentUser();
        $context = [
            'page_title' => 'Dashboard',
            'user' => $user,
        ];

        $this->renderer->render('dashboard.twig', $context);
    }

    /**
     * Handle logout
     */
    public function handleLogout(): void
    {
        $this->authService->logout();
        $this->responseHandler->redirectToLogin();
    }

    /**
     * Process login command
     */
    private function processLogin($command): void
    {
        $result = $this->loginHandler->handle($command);

        if ($result['success']) {
            $this->responseHandler->redirect($result['redirect_to']);
            return;
        }

        $this->renderLoginPage($result['error']);
    }

    /**
     * Process registration command
     */
    private function processRegistration($command): void
    {
        $result = $this->registerHandler->handle($command);

        if ($result['success']) {
            // Set user role if constant is defined
            if (defined('MINISITE_ROLE_USER')) {
                $result['user']->set_role(MINISITE_ROLE_USER);
            }
            $this->responseHandler->redirect($result['redirect_to']);
            return;
        }

        $this->renderRegisterPage($result['error']);
    }

    /**
     * Process forgot password command
     */
    private function processForgotPassword($command): void
    {
        $result = $this->forgotPasswordHandler->handle($command);
        
        if ($result['success']) {
            $context = $this->responseHandler->createSuccessContext(
                'Reset Password',
                $result['message']
            );
        } else {
            $context = $this->responseHandler->createErrorContext(
                'Reset Password',
                $result['error']
            );
        }

        $this->renderer->render('account-forgot.twig', $context);
    }

    /**
     * Render login page
     */
    private function renderLoginPage(?string $errorMessage = null): void
    {
        $context = $this->responseHandler->createErrorContext(
            'Sign In',
            $errorMessage ?? '',
            ['redirect_to' => $this->requestHandler->getRedirectTo()]
        );

        $this->renderer->render('account-login.twig', $context);
    }

    /**
     * Render registration page
     */
    private function renderRegisterPage(?string $errorMessage = null): void
    {
        $context = $this->responseHandler->createErrorContext(
            'Create Account',
            $errorMessage ?? '',
            ['redirect_to' => $this->requestHandler->getRedirectTo()]
        );

        $this->renderer->render('account-register.twig', $context);
    }

    /**
     * Render forgot password page
     */
    private function renderForgotPasswordPage(?string $errorMessage = null): void
    {
        $context = $this->responseHandler->createErrorContext(
            'Reset Password',
            $errorMessage ?? ''
        );

        $this->renderer->render('account-forgot.twig', $context);
    }
}

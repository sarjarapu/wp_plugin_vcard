<?php

namespace Minisite\Features\NewMinisite\Controllers;

use Minisite\Features\NewMinisite\Services\NewMinisiteService;
use Minisite\Features\NewMinisite\Rendering\NewMinisiteRenderer;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * New Minisite Controller
 *
 * SINGLE RESPONSIBILITY: Handle HTTP requests for new minisite creation
 * - Manages new minisite form display and submission
 * - Coordinates between services and renderers
 * - Handles authentication and authorization
 */
class NewMinisiteController
{
    private LoggerInterface $logger;

    public function __construct(
        private NewMinisiteService $newMinisiteService,
        private NewMinisiteRenderer $newMinisiteRenderer,
        private WordPressNewMinisiteManager $wordPressManager
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('new-minisite-controller');
    }

    /**
     * Handle new minisite request
     */
    public function handleNewMinisite(): void
    {
        $this->logger->info('NewMinisiteController::handleNewMinisite() called', [
            'request_method' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'unknown')),
            'request_uri' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? 'unknown')),
            'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')),
            'feature' => 'NewMinisite' // ← This confirms we're in NewMinisite feature
        ]);

        // Check authentication
        if (!$this->wordPressManager->isUserLoggedIn()) {
            $this->logger->warning('User not logged in, redirecting to login', [
                'feature' => 'NewMinisite'
            ]);
            $this->wordPressManager->redirect($this->wordPressManager->getLoginRedirectUrl());
        }

        $currentUser = $this->wordPressManager->getCurrentUser();
        $this->logger->info('User authenticated for NewMinisite', [
            'user_id' => $currentUser->ID ?? 'unknown',
            'user_login' => $currentUser->user_login ?? 'unknown',
            'feature' => 'NewMinisite'
        ]);

        // Check if user can create new minisites
        if (!$this->newMinisiteService->canCreateNewMinisite()) {
            $this->logger->warning('User lacks permission to create new minisites', [
                'user_id' => $currentUser->ID ?? 'unknown',
                'feature' => 'NewMinisite'
            ]);
            $this->newMinisiteRenderer->renderError('You do not have permission to create new minisites.');
            return;
        }

        // Handle form submission
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->logger->info('POST request detected, handling form submission', [
                'feature' => 'NewMinisite',
                'post_data_keys' => array_keys($_POST)
            ]);
            $this->handleFormSubmission();
            return;
        }

        // Display new minisite form
        $this->logger->info('Displaying new minisite form', [
            'feature' => 'NewMinisite'
        ]);
        $this->displayNewMinisiteForm();
    }

    /**
     * Handle form submission
     */
    private function handleFormSubmission(): void
    {
        $this->logger->info('NewMinisiteController::handleFormSubmission() called', [
            'feature' => 'NewMinisite',
            'post_data_count' => count($_POST),
            'post_data_sample' => array_slice($_POST, 0, 5, true) // First 5 fields for debugging
        ]);

        // Log key form fields with actual values for debugging
        $this->logger->debug('Form submission data - key fields', [
            'feature' => 'NewMinisite',
            'business_name' => $_POST['business_name'] ?? 'NOT_SET',
            'seo_title' => $_POST['seo_title'] ?? 'NOT_SET',
            'seo_description' => $_POST['seo_description'] ?? 'NOT_SET',
            'brand_name' => $_POST['brand_name'] ?? 'NOT_SET',
            'brand_logo' => $_POST['brand_logo'] ?? 'NOT_SET',
            'brand_industry' => $_POST['brand_industry'] ?? 'NOT_SET',
            'brand_palette' => $_POST['brand_palette'] ?? 'NOT_SET',
            'hero_heading' => $_POST['hero_heading'] ?? 'NOT_SET',
            'hero_subheading' => $_POST['hero_subheading'] ?? 'NOT_SET',
            'about_html' => $_POST['about_html'] ?? 'NOT_SET',
            'contact_email' => $_POST['contact_email'] ?? 'NOT_SET',
            'contact_phone_text' => $_POST['contact_phone_text'] ?? 'NOT_SET',
            'has_nonce' => isset($_POST['minisite_edit_nonce']),
            'nonce_value' => $_POST['minisite_edit_nonce'] ?? 'MISSING'
        ]);

        // Nonce verification is handled in NewMinisiteService::createNewMinisite()
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in NewMinisiteService::createNewMinisite()
        try {
            $result = $this->newMinisiteService->createNewMinisite($_POST);

            $this->logger->info('NewMinisiteService::createNewMinisite() completed', [
                'feature' => 'NewMinisite',
                'success' => $result->success ?? false,
                'has_errors' => !empty($result->errors ?? []),
                'error_count' => count($result->errors ?? []),
                'has_redirect_url' => !empty($result->redirectUrl ?? '')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('NewMinisiteService::createNewMinisite() threw exception', [
                'feature' => 'NewMinisite',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Exception logging only
                'post_data_keys' => array_keys($_POST),
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Exception logging only
                'post_data_count' => count($_POST)
            ]);

            // Create error result to maintain consistent flow
            $result = (object) [
                'success' => false,
                'errors' => ['An unexpected error occurred: ' . $e->getMessage()]
            ];
        }

        if ($result->success) {
            $this->logger->info('NewMinisite creation successful, redirecting', [
                'feature' => 'NewMinisite',
                'redirect_url' => $result->redirectUrl ?? 'unknown'
            ]);
            $this->wordPressManager->redirect($result->redirectUrl);
        } else {
            $this->logger->warning('NewMinisite creation failed, displaying errors', [
                'feature' => 'NewMinisite',
                'errors' => $result->errors ?? []
            ]);
            // Display form with errors
            $this->displayNewMinisiteForm($result->errors ?? []);
        }
    }

    /**
     * Display new minisite form
     */
    private function displayNewMinisiteForm(array $errors = []): void
    {
        try {
            $formData = $this->newMinisiteService->getEmptyFormData();
            $userMinisiteCount = $this->newMinisiteService->getUserMinisiteCount();

            $newMinisiteData = (object) [
                'formData' => $formData,
                'userMinisiteCount' => $userMinisiteCount,
                'errorMessage' => !empty($errors) ? implode(', ', $errors) : '',
                'successMessage' => ''
            ];

            $this->newMinisiteRenderer->renderNewMinisiteForm($newMinisiteData);
        } catch (\RuntimeException $e) {
            // Display error page
            $this->newMinisiteRenderer->renderError($e->getMessage());
        }
    }
}

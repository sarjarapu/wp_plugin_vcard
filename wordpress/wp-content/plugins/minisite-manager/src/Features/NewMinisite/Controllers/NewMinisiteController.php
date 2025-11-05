<?php

namespace Minisite\Features\NewMinisite\Controllers;

use Minisite\Features\NewMinisite\Rendering\NewMinisiteRenderer;
use Minisite\Features\NewMinisite\Services\NewMinisiteService;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Security\FormSecurityHelper;
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
        private WordPressNewMinisiteManager $wordPressManager,
        private FormSecurityHelper $formSecurityHelper
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('new-minisite-controller');
    }

    /**
     * Handle new minisite request
     */
    public function handleNewMinisite(): void
    {
        $this->logger->info('NewMinisiteController::handleNewMinisite() called', array(
            'request_method' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'unknown')),
            'request_uri' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? 'unknown')),
            'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')),
            'feature' => 'NewMinisite', // ← This confirms we're in NewMinisite feature
        ));

        // Check authentication
        if (! $this->wordPressManager->isUserLoggedIn()) {
            $this->logger->warning('User not logged in, redirecting to login', array(
                'feature' => 'NewMinisite',
            ));
            $this->wordPressManager->redirect($this->wordPressManager->getLoginRedirectUrl());
        }

        $currentUser = $this->wordPressManager->getCurrentUser();
        $this->logger->info('User authenticated for NewMinisite', array(
            'user_id' => $currentUser->ID ?? 'unknown',
            'user_login' => $currentUser->user_login ?? 'unknown',
            'feature' => 'NewMinisite',
        ));

        // Check if user can create new minisites
        if (! $this->newMinisiteService->canCreateNewMinisite()) {
            $this->logger->warning('User lacks permission to create new minisites', array(
                'user_id' => $currentUser->ID ?? 'unknown',
                'feature' => 'NewMinisite',
            ));
            $this->newMinisiteRenderer->renderError('You do not have permission to create new minisites.');

            return;
        }

        // Handle form submission
        if ($this->formSecurityHelper->isPostRequest()) {
            $this->logger->info('POST request detected, handling form submission', array(
                'feature' => 'NewMinisite',
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce will be verified in handleFormSubmission
                'post_data_keys' => array_keys($_POST),
            ));
            $this->handleFormSubmission();

            return;
        }

        // Display new minisite form
        $this->logger->info('Displaying new minisite form', array(
            'feature' => 'NewMinisite',
        ));
        $this->displayNewMinisiteForm();
    }

    /**
     * Handle form submission
     */
    private function handleFormSubmission(): void
    {
        // Verify nonce first
        if (! $this->formSecurityHelper->verifyNonce('minisite_edit', 'minisite_edit_nonce')) {
            $this->logger->warning('NewMinisiteController::handleFormSubmission() - Invalid nonce', array(
                'feature' => 'NewMinisite',
                'nonce_value' => $this->formSecurityHelper->getPostData('minisite_edit_nonce', 'MISSING'),
            ));
            $this->newMinisiteRenderer->renderError('Security check failed. Please try again.');

            return;
        }

        $this->logger->info('NewMinisiteController::handleFormSubmission() called', array(
            'feature' => 'NewMinisite',
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
            'post_data_count' => count($_POST),
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
            'post_data_sample' => array_slice($_POST, 0, 5, true), // First 5 fields for debugging
        ));

        // Log key form fields with actual values for debugging
        $this->logger->debug('Form submission data - key fields', array(
            'feature' => 'NewMinisite',
            'business_name' => $this->formSecurityHelper->getPostData('business_name', 'NOT_SET'),
            'seo_title' => $this->formSecurityHelper->getPostData('seo_title', 'NOT_SET'),
            'seo_description' => $this->formSecurityHelper->getPostData('seo_description', 'NOT_SET'),
            'brand_name' => $this->formSecurityHelper->getPostData('brand_name', 'NOT_SET'),
            'brand_logo' => $this->formSecurityHelper->getPostData('brand_logo', 'NOT_SET'),
            'brand_industry' => $this->formSecurityHelper->getPostData('brand_industry', 'NOT_SET'),
            'brand_palette' => $this->formSecurityHelper->getPostData('brand_palette', 'NOT_SET'),
            'hero_heading' => $this->formSecurityHelper->getPostData('hero_heading', 'NOT_SET'),
            'hero_subheading' => $this->formSecurityHelper->getPostData('hero_subheading', 'NOT_SET'),
            'about_html' => $this->formSecurityHelper->getPostDataTextarea('about_html', 'NOT_SET'),
            'contact_email' => $this->formSecurityHelper->getPostDataEmail('contact_email', 'NOT_SET'),
            'contact_phone_text' => $this->formSecurityHelper->getPostData('contact_phone_text', 'NOT_SET'),
            'has_nonce' => ! empty($this->formSecurityHelper->getPostData('minisite_edit_nonce')),
            'nonce_value' => $this->formSecurityHelper->getPostData('minisite_edit_nonce', 'MISSING'),
        ));

        // Pass sanitized POST data to service
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
        try {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
            $result = $this->newMinisiteService->createNewMinisite($_POST);

            $this->logger->info('NewMinisiteService::createNewMinisite() completed', array(
                'feature' => 'NewMinisite',
                'success' => $result->success ?? false,
                'has_errors' => ! empty($result->errors ?? array()),
                'error_count' => count($result->errors ?? array()),
                'has_redirect_url' => ! empty($result->redirectUrl ?? ''),
            ));
        } catch (\Exception $e) {
            $this->logger->error('NewMinisiteService::createNewMinisite() threw exception', array(
                'feature' => 'NewMinisite',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Exception logging only
                'post_data_keys' => array_keys($_POST),
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Exception logging only
                'post_data_count' => count($_POST),
            ));

            // Create error result to maintain consistent flow
            $result = (object) array(
                'success' => false,
                'errors' => array('An unexpected error occurred: ' . $e->getMessage()),
            );
        }

        if ($result->success) {
            $this->logger->info('NewMinisite creation successful, redirecting', array(
                'feature' => 'NewMinisite',
                'redirect_url' => $result->redirectUrl ?? 'unknown',
            ));
            $this->wordPressManager->redirect($result->redirectUrl);
        } else {
            $this->logger->warning('NewMinisite creation failed, displaying errors', array(
                'feature' => 'NewMinisite',
                'errors' => $result->errors ?? array(),
            ));
            // Display form with errors
            $this->displayNewMinisiteForm($result->errors ?? array());
        }
    }

    /**
     * Display new minisite form
     */
    private function displayNewMinisiteForm(array $errors = array()): void
    {
        try {
            $formData = $this->newMinisiteService->getEmptyFormData();
            $userMinisiteCount = $this->newMinisiteService->getUserMinisiteCount();

            $newMinisiteData = (object) array(
                'formData' => $formData,
                'userMinisiteCount' => $userMinisiteCount,
                'errorMessage' => ! empty($errors) ? implode(', ', $errors) : '',
                'successMessage' => '',
            );

            $this->newMinisiteRenderer->renderNewMinisiteForm($newMinisiteData);
        } catch (\RuntimeException $e) {
            // Display error page
            $this->newMinisiteRenderer->renderError($e->getMessage());
        }
    }
}

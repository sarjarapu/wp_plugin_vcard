<?php

namespace Minisite\Features\NewMinisite\Controllers;

use Minisite\Features\NewMinisite\Services\NewMinisiteService;
use Minisite\Features\NewMinisite\Rendering\NewMinisiteRenderer;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;

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
    public function __construct(
        private NewMinisiteService $newMinisiteService,
        private NewMinisiteRenderer $newMinisiteRenderer,
        private WordPressNewMinisiteManager $wordPressManager
    ) {
    }

    /**
     * Handle new minisite request
     */
    public function handleNewMinisite(): void
    {
        // Check authentication
        if (!$this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->redirect($this->wordPressManager->getLoginRedirectUrl());
        }

        // Check if user can create new minisites
        if (!$this->newMinisiteService->canCreateNewMinisite()) {
            $this->newMinisiteRenderer->renderError('You do not have permission to create new minisites.');
            return;
        }

        // Handle form submission
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleFormSubmission();
            return;
        }

        // Display new minisite form
        $this->displayNewMinisiteForm();
    }

    /**
     * Handle form submission
     */
    private function handleFormSubmission(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in NewMinisiteService::createNewMinisite()

        // Nonce verification is handled in NewMinisiteService::createNewMinisite()
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in NewMinisiteService::createNewMinisite()
        $result = $this->newMinisiteService->createNewMinisite($_POST);

        if ($result->success) {
            $this->wordPressManager->redirect($result->redirectUrl);
        } else {
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

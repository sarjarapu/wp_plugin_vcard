<?php

namespace Minisite\Features\MinisiteEdit\Controllers;

use Minisite\Features\MinisiteEdit\Services\EditService;
use Minisite\Features\MinisiteEdit\Rendering\EditRenderer;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;

/**
 * Edit Controller
 *
 * SINGLE RESPONSIBILITY: Handle HTTP requests for minisite editing
 * - Manages edit form display and submission
 * - Coordinates between services and renderers
 * - Handles authentication and authorization
 */
class EditController
{
    public function __construct(
        private EditService $editService,
        private EditRenderer $editRenderer,
        private WordPressEditManager $wordPressManager
    ) {
    }

    /**
     * Handle edit request
     */
    public function handleEdit(): void
    {
        // Check authentication
        if (!$this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->redirect($this->wordPressManager->getLoginRedirectUrl());
        }

        $siteId = $this->wordPressManager->getQueryVar('minisite_site_id');
        if (!$siteId) {
            $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
        }

        $versionId = $this->wordPressManager->getQueryVar('minisite_version_id');

        // Handle form submission
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleFormSubmission($siteId);
            return;
        }

        // Display edit form
        $this->displayEditForm($siteId, $versionId);
    }

    /**
     * Handle form submission
     */
    private function handleFormSubmission(string $siteId): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in EditService::saveDraft()

        // Nonce verification is handled in EditService::saveDraft()
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in EditService::saveDraft()
        $result = $this->editService->saveDraft($siteId, $_POST);

        if ($result->success) {
            $this->wordPressManager->redirect($result->redirectUrl);
        } else {
            // Display form with errors
            $this->displayEditForm($siteId, null, $result->errors ?? []);
        }
    }


    /**
     * Display edit form
     */
    private function displayEditForm(string $siteId, ?string $versionId, array $errors = []): void
    {
        try {
            $editData = $this->editService->getMinisiteForEditing($siteId, $versionId);
            $editData->errorMessage = !empty($errors) ? implode(', ', $errors) : '';

            $this->editRenderer->renderEditForm($editData);
        } catch (\RuntimeException $e) {
            // Handle access denied or not found
            if (strpos($e->getMessage(), 'Access denied') !== false) {
                $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
            } elseif (strpos($e->getMessage(), 'not found') !== false) {
                $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
            } else {
                // Display error page
                $this->editRenderer->renderError($e->getMessage());
            }
        }
    }
}

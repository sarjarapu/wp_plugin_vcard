<?php

namespace Minisite\Features\MinisiteEdit\Controllers;

use Minisite\Features\MinisiteEdit\Services\EditService;
use Minisite\Features\MinisiteEdit\Rendering\EditRenderer;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Security\FormSecurityHelper;
use Psr\Log\LoggerInterface;

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
    private LoggerInterface $logger;

    public function __construct(
        private EditService $editService,
        private EditRenderer $editRenderer,
        private WordPressEditManager $wordPressManager,
        private FormSecurityHelper $formSecurityHelper
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('minisite-edit-controller');
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
        if ($this->formSecurityHelper->isPostRequest()) {
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
        // Verify nonce first
        if (!$this->formSecurityHelper->verifyNonce('minisite_edit', 'minisite_edit_nonce')) {
            $this->logger->warning('EditController::handleFormSubmission() - Invalid nonce', [
                'site_id' => $siteId,
                'nonce_value' => $this->formSecurityHelper->getPostData('minisite_edit_nonce', 'MISSING')
            ]);
            $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
            return;
        }

        // Log form submission details for debugging
        $this->logger->debug('EditController::handleFormSubmission() called', [
            'site_id' => $siteId,
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
            'post_data_count' => count($_POST),
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
            'has_nonce' => !empty($this->formSecurityHelper->getPostData('minisite_edit_nonce')),
            'nonce_value' => $this->formSecurityHelper->getPostData('minisite_edit_nonce', 'MISSING')
        ]);

        // Pass sanitized POST data to service
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
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

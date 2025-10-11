<?php

namespace Minisite\Features\MinisiteEdit\Services;

use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\Entities\Version;
use Minisite\Domain\Services\MinisiteFormProcessor;
use Minisite\Domain\Services\MinisiteDatabaseCoordinator;

/**
 * Edit Service
 *
 * SINGLE RESPONSIBILITY: Handle business logic for minisite editing
 * - Manages edit form data processing
 * - Handles version creation and updates
 * - Coordinates between repositories and WordPress functions
 */
class EditService
{
    public function __construct(
        private WordPressEditManager $wordPressManager
    ) {
    }

    /**
     * Get minisite data for editing
     */
    public function getMinisiteForEditing(string $siteId, ?string $versionId = null): object
    {
        $minisite = $this->wordPressManager->findMinisiteById($siteId);
        if (!$minisite) {
            throw new \RuntimeException('Minisite not found');
        }

        // Check ownership
        $currentUser = $this->wordPressManager->getCurrentUser();
        if (!$this->wordPressManager->userOwnsMinisite($minisite, (int) $currentUser->ID)) {
            throw new \RuntimeException('Access denied');
        }

        // Get version to edit
        $editingVersion = $this->getEditingVersion($siteId, $versionId);
        $latestDraft = $this->wordPressManager->findLatestDraft($siteId);

        // Create profile object for form
        $profileForForm = $this->createProfileForForm($minisite, $editingVersion);

        return (object) [
            'minisite' => $minisite,
            'editingVersion' => $editingVersion,
            'latestDraft' => $latestDraft,
            'profileForForm' => $profileForForm,
            'siteJson' => $editingVersion ? $editingVersion->siteJson : $minisite->siteJson,
            'successMessage' => $this->getSuccessMessage(),
            'errorMessage' => ''
        ];
    }


    /**
     * Save draft version
     */
    public function saveDraft(string $siteId, array $formData): object
    {
        try {
            // Create shared components
            $formProcessor = new MinisiteFormProcessor($this->wordPressManager);
            $dbCoordinator = new MinisiteDatabaseCoordinator($this->wordPressManager);

            // Validate form data
            $errors = $formProcessor->validateFormData($formData);
            if (!empty($errors)) {
                return (object) ['success' => false, 'errors' => $errors];
            }

            // Verify nonce
            if (
                !$this->wordPressManager->verifyNonce(
                    $this->wordPressManager->sanitizeTextField($formData['minisite_edit_nonce']),
                    'minisite_edit'
                )
            ) {
                return (object) ['success' => false, 'errors' => ['Security check failed. Please try again.']];
            }

            $minisite = $this->wordPressManager->findMinisiteById($siteId);
            $currentUser = $this->wordPressManager->getCurrentUser();

            // Determine operation type based on minisite status
            $hasBeenPublished = $this->wordPressManager->hasBeenPublished($siteId);
            $operationType = $hasBeenPublished ? 'edit_published' : 'edit_draft';

            // Use shared database coordinator
            return $dbCoordinator->saveMinisiteData(
                $siteId,
                $formData,
                $operationType,
                $minisite,
                $currentUser,
                $hasBeenPublished
            );
        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'errors' => ['Failed to save draft: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Get editing version
     */
    private function getEditingVersion(string $siteId, ?string $versionId): ?object
    {
        if ($versionId === 'latest' || !$versionId) {
            return $this->wordPressManager->getLatestDraftForEditing($siteId);
        }

        $version = $this->wordPressManager->findVersionById((int) $versionId);
        if (!$version || $version->minisiteId !== $siteId) {
            $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl("/account/sites/{$siteId}/edit"));
        }

        return $version;
    }

    /**
     * Create profile object for form
     */
    private function createProfileForForm(object $minisite, ?object $editingVersion): object
    {
        $profileForForm = clone $minisite;

        if ($editingVersion) {
            $profileForForm->title = $editingVersion->title ?? $minisite->title;
            $profileForForm->name = $editingVersion->name ?? $minisite->name;
            $profileForForm->city = $editingVersion->city ?? $minisite->city;
            $profileForForm->region = $editingVersion->region ?? $minisite->region;
            $profileForForm->countryCode = $editingVersion->countryCode ?? $minisite->countryCode;
            $profileForForm->postalCode = $editingVersion->postalCode ?? $minisite->postalCode;
            $profileForForm->siteTemplate = $editingVersion->siteTemplate ?? $minisite->siteTemplate;
            $profileForForm->palette = $editingVersion->palette ?? $minisite->palette;
            $profileForForm->industry = $editingVersion->industry ?? $minisite->industry;
            $profileForForm->defaultLocale = $editingVersion->defaultLocale ?? $minisite->defaultLocale;
            $profileForForm->searchTerms = $editingVersion->searchTerms ?? $minisite->searchTerms;
        }

        return $profileForForm;
    }

    /**
     * Get success message
     */
    private function getSuccessMessage(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for success message doesn't require nonce verification
        if (isset($_GET['draft_saved']) && $_GET['draft_saved'] === '1') {
            return 'Draft saved successfully!';
        }
        return '';
    }
}

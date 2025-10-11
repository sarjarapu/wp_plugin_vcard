<?php

namespace Minisite\Features\NewMinisite\Services;

use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Domain\Services\MinisiteFormProcessor;
use Minisite\Domain\Services\MinisiteDatabaseCoordinator;
use Minisite\Domain\Services\MinisiteIdGenerator;

/**
 * New Minisite Service
 *
 * SINGLE RESPONSIBILITY: Handle business logic for new minisite creation
 * - Manages new minisite form data processing
 * - Handles initial minisite and version creation
 * - Coordinates between repositories and WordPress functions
 */
class NewMinisiteService
{
    public function __construct(
        private WordPressNewMinisiteManager $wordPressManager
    ) {
    }

    /**
     * Create a new minisite draft
     */
    public function createNewMinisite(array $formData): object
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
                    $this->wordPressManager->sanitizeTextField($formData['minisite_new_nonce']),
                    'minisite_new'
                )
            ) {
                return (object) ['success' => false, 'errors' => ['Security check failed. Please try again.']];
            }

            $currentUser = $this->wordPressManager->getCurrentUser();

            // Generate new minisite ID
            $minisiteId = MinisiteIdGenerator::generate();

            // Use shared database coordinator for new draft creation
            return $dbCoordinator->saveMinisiteData(
                $minisiteId,
                $formData,
                'new_draft',
                null, // No existing minisite
                $currentUser,
                false // Has never been published
            );
        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'errors' => ['Failed to create new minisite: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Get empty form data for new minisite creation
     */
    public function getEmptyFormData(): array
    {
        $formProcessor = new MinisiteFormProcessor($this->wordPressManager);
        return $formProcessor->buildEmptySiteJson();
    }

    /**
     * Check if user can create new minisites
     */
    public function canCreateNewMinisite(): bool
    {
        $currentUser = $this->wordPressManager->getCurrentUser();
        return $currentUser && $this->wordPressManager->userCanCreateMinisite((int) $currentUser->ID);
    }

    /**
     * Get user's minisite count for limits
     */
    public function getUserMinisiteCount(): int
    {
        $currentUser = $this->wordPressManager->getCurrentUser();
        if (!$currentUser) {
            return 0;
        }

        return $this->wordPressManager->getUserMinisiteCount((int) $currentUser->ID);
    }
}

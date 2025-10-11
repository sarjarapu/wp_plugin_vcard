<?php

namespace Minisite\Features\NewMinisite\Services;

use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Domain\Services\MinisiteFormProcessor;
use Minisite\Domain\Services\MinisiteDatabaseCoordinator;
use Minisite\Domain\Services\MinisiteIdGenerator;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

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
    private LoggerInterface $logger;
    
    public function __construct(
        private WordPressNewMinisiteManager $wordPressManager
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('new-minisite');
    }

    /**
     * Create a new minisite draft
     */
    public function createNewMinisite(array $formData): object
    {
        $this->logger->info('NewMinisiteService::createNewMinisite() called', [
            'feature' => 'NewMinisite',
            'user_id' => $this->wordPressManager->getCurrentUser()?->ID,
            'form_fields_count' => count($formData),
            'form_data_keys' => array_keys($formData),
            'has_nonce' => isset($formData['minisite_edit_nonce']),
            'nonce_value' => $formData['minisite_edit_nonce'] ?? 'missing'
        ]);
        
        try {
            // Create shared components
            $formProcessor = new MinisiteFormProcessor($this->wordPressManager);
            $dbCoordinator = new MinisiteDatabaseCoordinator($this->wordPressManager);

            // Validate form data
            $errors = $formProcessor->validateFormData($formData);
            if (!empty($errors)) {
                $this->logger->warning('Form validation failed', [
                    'errors' => $errors,
                    'form_data' => $formData
                ]);
                return (object) ['success' => false, 'errors' => $errors];
            }

            // Verify nonce
            if (
                !$this->wordPressManager->verifyNonce(
                    $this->wordPressManager->sanitizeTextField($formData['minisite_edit_nonce']),
                    'minisite_edit'
                )
            ) {
                $this->logger->error('Nonce verification failed', [
                    'nonce' => $formData['minisite_edit_nonce'] ?? 'missing',
                    'action' => 'minisite_edit'
                ]);
                return (object) ['success' => false, 'errors' => ['Security check failed. Please try again.']];
            }

            $currentUser = $this->wordPressManager->getCurrentUser();

            // Generate new minisite ID
            $minisiteId = MinisiteIdGenerator::generate();
            $this->logger->debug('Generated minisite ID', ['minisite_id' => $minisiteId]);

            // Use shared database coordinator for new draft creation
            $result = $dbCoordinator->saveMinisiteData(
                $minisiteId,
                $formData,
                'new_draft',
                null, // No existing minisite
                $currentUser,
                false // Has never been published
            );
            
            if ($result->success) {
                $this->logger->info('New minisite created successfully', [
                    'minisite_id' => $minisiteId,
                    'user_id' => $currentUser->ID
                ]);
            } else {
                $this->logger->error('Failed to create new minisite', [
                    'minisite_id' => $minisiteId,
                    'errors' => $result->errors ?? []
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Exception during minisite creation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'form_data' => $formData
            ]);
            
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

<?php

namespace Minisite\Features\NewMinisite\Services;

use Minisite\Domain\Services\MinisiteDatabaseCoordinator;
use Minisite\Domain\Services\MinisiteFormProcessor;
use Minisite\Domain\Services\MinisiteIdGenerator;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Persistence\WordPressTransactionManager;
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
        private WordPressNewMinisiteManager $wordPressManager,
        private MinisiteRepositoryInterface $minisiteRepository,
        private VersionRepositoryInterface $versionRepository
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('new-minisite');
    }

    /**
     * Create a new minisite draft
     */
    public function createNewMinisite(array $formData): object
    {
        $this->logger->info('NewMinisiteService::createNewMinisite() called', array(
            'feature' => 'NewMinisite',
            'user_id' => $this->wordPressManager->getCurrentUser()?->ID,
            'form_fields_count' => count($formData),
            'form_data_keys' => array_keys($formData),
            'has_nonce' => isset($formData['minisite_edit_nonce']),
            'nonce_value' => $formData['minisite_edit_nonce'] ?? 'missing',
        ));

        // Log detailed form data for debugging
        $this->logger->debug('NewMinisiteService - detailed form data', array(
            'feature' => 'NewMinisite',
            'user_id' => $this->wordPressManager->getCurrentUser()?->ID,
            'business_name' => $formData['business_name'] ?? 'NOT_SET',
            'business_city' => $formData['business_city'] ?? 'NOT_SET',
            'business_region' => $formData['business_region'] ?? 'NOT_SET',
            'business_country' => $formData['business_country'] ?? 'NOT_SET',
            'seo_title' => $formData['seo_title'] ?? 'NOT_SET',
            'seo_description' => $formData['seo_description'] ?? 'NOT_SET',
            'seo_keywords' => $formData['seo_keywords'] ?? 'NOT_SET',
            'seo_favicon' => $formData['seo_favicon'] ?? 'NOT_SET',
            'brand_name' => $formData['brand_name'] ?? 'NOT_SET',
            'brand_logo' => $formData['brand_logo'] ?? 'NOT_SET',
            'brand_industry' => $formData['brand_industry'] ?? 'NOT_SET',
            'brand_palette' => $formData['brand_palette'] ?? 'NOT_SET',
            'hero_heading' => $formData['hero_heading'] ?? 'NOT_SET',
            'hero_subheading' => $formData['hero_subheading'] ?? 'NOT_SET',
            'hero_image' => $formData['hero_image'] ?? 'NOT_SET',
            'about_html' => $formData['about_html'] ?? 'NOT_SET',
            'whyus_title' => $formData['whyus_title'] ?? 'NOT_SET',
            'whyus_html' => $formData['whyus_html'] ?? 'NOT_SET',
            'contact_email' => $formData['contact_email'] ?? 'NOT_SET',
            'contact_phone_text' => $formData['contact_phone_text'] ?? 'NOT_SET',
            'contact_phone_link' => $formData['contact_phone_link'] ?? 'NOT_SET',
            'contact_lat' => $formData['contact_lat'] ?? 'NOT_SET',
            'contact_lng' => $formData['contact_lng'] ?? 'NOT_SET',
            'site_template' => $formData['site_template'] ?? 'NOT_SET',
            'default_locale' => $formData['default_locale'] ?? 'NOT_SET',
            'search_terms' => $formData['search_terms'] ?? 'NOT_SET',
            'version_label' => $formData['version_label'] ?? 'NOT_SET',
            'version_comment' => $formData['version_comment'] ?? 'NOT_SET',
        ));

        try {
            // Create shared components
            $formProcessor = new MinisiteFormProcessor($this->wordPressManager, $this->minisiteRepository);
            $transactionManager = new WordPressTransactionManager();
            $dbCoordinator = new MinisiteDatabaseCoordinator(
                $this->wordPressManager,
                $this->versionRepository,
                $this->minisiteRepository,
                $transactionManager
            );

            // Validate form data
            $errors = $formProcessor->validateFormData($formData);
            if (! empty($errors)) {
                $this->logger->warning('Form validation failed', array(
                    'errors' => $errors,
                    'form_data' => $formData,
                ));

                return (object) array('success' => false, 'errors' => $errors);
            }

            // Verify nonce
            if (
                ! $this->wordPressManager->verifyNonce(
                    $this->wordPressManager->sanitizeTextField($formData['minisite_edit_nonce']),
                    'minisite_edit'
                )
            ) {
                $this->logger->error('Nonce verification failed', array(
                    'nonce' => $formData['minisite_edit_nonce'] ?? 'missing',
                    'action' => 'minisite_edit',
                ));

                return (object) array(
                    'success' => false,
                    'errors' => array('Security check failed. Please try again.'),
                );
            }

            $currentUser = $this->wordPressManager->getCurrentUser();

            // Generate new minisite ID
            $minisiteId = MinisiteIdGenerator::generate();
            $this->logger->debug('Generated minisite ID', array('minisite_id' => $minisiteId));

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
                $this->logger->info('New minisite created successfully', array(
                    'minisite_id' => $minisiteId,
                    'user_id' => $currentUser->ID,
                ));
            } else {
                $this->logger->error('Failed to create new minisite', array(
                    'minisite_id' => $minisiteId,
                    'errors' => $result->errors ?? array(),
                ));
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Exception during minisite creation', array(
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'form_data' => $formData,
            ));

            return (object) array(
                'success' => false,
                'errors' => array('Failed to create new minisite: ' . $e->getMessage()),
            );
        }
    }

    /**
     * Get empty form data for new minisite creation
     */
    public function getEmptyFormData(): array
    {
        $formProcessor = new MinisiteFormProcessor($this->wordPressManager, $this->minisiteRepository);

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
        if (! $currentUser) {
            return 0;
        }

        return $this->minisiteRepository->countByOwner((int) $currentUser->ID);
    }
}

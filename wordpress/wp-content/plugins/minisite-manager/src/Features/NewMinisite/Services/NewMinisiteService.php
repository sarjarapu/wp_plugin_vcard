<?php

namespace Minisite\Features\NewMinisite\Services;

use Minisite\Features\MinisiteManagement\Services\MinisiteFormProcessor;
use Minisite\Features\MinisiteManagement\Services\MinisiteIdGenerator;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
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

            $result = $this->createNewDraftMinisite(
                $minisiteId,
                $formData,
                $formProcessor,
                $transactionManager,
                $currentUser
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

    private function createNewDraftMinisite(
        string $minisiteId,
        array $formData,
        MinisiteFormProcessor $formProcessor,
        WordPressTransactionManager $transactionManager,
        object $currentUser
    ): object {
        $this->logger->info('Starting new draft creation', array(
            'minisite_id' => $minisiteId,
            'user_id' => $currentUser?->ID,
            'form_fields_count' => count($formData),
        ));

        $this->logger->debug('Database coordinator - form data values (inlined)', array(
            'minisite_id' => $minisiteId,
            'user_id' => $currentUser?->ID,
            'business_name' => $formData['business_name'] ?? 'NOT_SET',
            'business_city' => $formData['business_city'] ?? 'NOT_SET',
            'seo_title' => $formData['seo_title'] ?? 'NOT_SET',
            'seo_description' => $formData['seo_description'] ?? 'NOT_SET',
            'brand_name' => $formData['brand_name'] ?? 'NOT_SET',
            'brand_logo' => $formData['brand_logo'] ?? 'NOT_SET',
            'brand_industry' => $formData['brand_industry'] ?? 'NOT_SET',
            'brand_palette' => $formData['brand_palette'] ?? 'NOT_SET',
            'hero_heading' => $formData['hero_heading'] ?? 'NOT_SET',
            'hero_subheading' => $formData['hero_subheading'] ?? 'NOT_SET',
            'about_html' => $formData['about_html'] ?? 'NOT_SET',
            'contact_email' => $formData['contact_email'] ?? 'NOT_SET',
            'contact_phone_text' => $formData['contact_phone_text'] ?? 'NOT_SET',
            'site_template' => $formData['site_template'] ?? 'NOT_SET',
            'default_locale' => $formData['default_locale'] ?? 'NOT_SET',
            'search_terms' => $formData['search_terms'] ?? 'NOT_SET',
        ));

        if (! $currentUser) {
            $this->logger->error('Current user is required for new minisite creation');

            throw new \InvalidArgumentException('Current user is required for new minisite creation');
        }

        $siteJson = $formProcessor->buildSiteJsonFromForm($formData, $minisiteId);

        $this->logger->debug('Site JSON built successfully (inlined)', array(
            'minisite_id' => $minisiteId,
            'site_json_size' => strlen(json_encode($siteJson)),
        ));

        $this->logger->debug('SiteJson content being saved to database (inlined)', array(
            'minisite_id' => $minisiteId,
            'seo_section' => $siteJson['seo'] ?? 'NOT_SET',
            'brand_section' => $siteJson['brand'] ?? 'NOT_SET',
            'hero_section' => $siteJson['hero'] ?? 'NOT_SET',
        ));

        $lat = ! empty($formData['contact_lat']) ? (float) $formData['contact_lat'] : null;
        $lng = ! empty($formData['contact_lng']) ? (float) $formData['contact_lng'] : null;

        $draftBusinessSlug = 'biz-' . substr($minisiteId, 0, 8);
        $draftLocationSlug = 'loc-' . substr($minisiteId, 8, 8);
        $slugs = new \Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair(
            business: $draftBusinessSlug,
            location: $draftLocationSlug
        );

        $geo = null;
        if ($lat !== null && $lng !== null) {
            $geo = new \Minisite\Features\MinisiteManagement\Domain\ValueObjects\GeoPoint(lat: $lat, lng: $lng);
        }

        $this->logger->info('Starting database transaction for new draft creation (inlined)', array(
            'minisite_id' => $minisiteId,
            'user_id' => $currentUser->ID,
            'operation_type' => 'start_transaction',
        ));

        $transactionManager->startTransaction();

        try {
            $minisite = new \Minisite\Features\MinisiteManagement\Domain\Entities\Minisite(
                id: $minisiteId,
                slug: $slugs->full(),
                slugs: $slugs,
                title: $formProcessor->getFormValue($formData, array(), 'seo_title', 'seo_title', '')
                    ?: 'Untitled Minisite',
                name: $formProcessor->getFormValue($formData, array(), 'business_name', 'business_name', '')
                    ?: 'Untitled Minisite',
                city: $formProcessor->getFormValue($formData, array(), 'business_city', 'business_city', ''),
                region: $formProcessor->getFormValue($formData, array(), 'business_region', 'business_region', ''),
                countryCode: $formProcessor->getFormValue(
                    $formData,
                    array(),
                    'business_country',
                    'business_country',
                    ''
                ),
                postalCode: $formProcessor->getFormValue($formData, array(), 'business_postal', 'business_postal', ''),
                geo: $geo,
                siteTemplate: $formProcessor->getFormValue($formData, array(), 'site_template', 'site_template', '')
                    ?: 'v2025',
                palette: $formProcessor->getFormValue(
                    $formData,
                    array(),
                    'brand_palette',
                    'brand_palette',
                    ''
                ) ?: 'blue',
                industry: $formProcessor->getFormValue($formData, array(), 'brand_industry', 'brand_industry', ''),
                defaultLocale: $formProcessor->getFormValue($formData, array(), 'default_locale', 'default_locale', '')
                    ?: 'en-US',
                schemaVersion: 1,
                siteVersion: 1,
                siteJson: $siteJson,
                searchTerms: $formProcessor->getFormValue($formData, array(), 'search_terms', 'search_terms', ''),
                status: 'draft',
                publishStatus: 'draft',
                createdAt: new \DateTimeImmutable(),
                updatedAt: new \DateTimeImmutable(),
                publishedAt: null,
                createdBy: (int) $currentUser->ID,
                updatedBy: (int) $currentUser->ID,
                currentVersionId: null
            );

            $this->logger->debug('Inserting new minisite entity to database (inlined)', array(
                'minisite_id' => $minisiteId,
                'title' => $minisite->title,
                'status' => $minisite->status,
            ));

            $savedMinisite = $this->minisiteRepository->insert($minisite);

            $nextVersion = 1;
            $version = new Version(
                id: null,
                minisiteId: $minisiteId,
                versionNumber: $nextVersion,
                status: 'draft',
                label: $this->wordPressManager->sanitizeTextField(
                    $formData['version_label'] ?? 'Initial Draft'
                ),
                comment: $this->wordPressManager->sanitizeTextareaField(
                    $formData['version_comment'] ?? 'First draft of the new minisite'
                ),
                createdBy: (int) $currentUser->ID,
                createdAt: new \DateTimeImmutable(),
                publishedAt: null,
                sourceVersionId: null,
                siteJson: $siteJson,
                slugs: $slugs,
                title: $formProcessor->getFormValue($formData, array(), 'seo_title', 'seo_title', ''),
                name: $formProcessor->getFormValue($formData, array(), 'business_name', 'business_name', ''),
                city: $formProcessor->getFormValue($formData, array(), 'business_city', 'business_city', ''),
                region: $formProcessor->getFormValue($formData, array(), 'business_region', 'business_region', ''),
                countryCode: $formProcessor->getFormValue(
                    $formData,
                    array(),
                    'business_country',
                    'business_country',
                    ''
                ),
                postalCode: $formProcessor->getFormValue($formData, array(), 'business_postal', 'business_postal', ''),
                geo: $geo,
                siteTemplate: $formProcessor->getFormValue($formData, array(), 'site_template', 'site_template', ''),
                palette: $formProcessor->getFormValue($formData, array(), 'brand_palette', 'brand_palette', ''),
                industry: $formProcessor->getFormValue($formData, array(), 'brand_industry', 'brand_industry', ''),
                defaultLocale: $formProcessor->getFormValue($formData, array(), 'default_locale', 'default_locale', 'en'),
                schemaVersion: 1,
                siteVersion: 1,
                searchTerms: $formProcessor->getFormValue($formData, array(), 'search_terms', 'search_terms', '')
            );

            $this->logger->debug('Saving version entity (inlined)', array(
                'minisite_id' => $minisiteId,
                'version_number' => $nextVersion,
            ));

            $savedVersion = $this->versionRepository->save($version);

            $this->logger->info('Updating main minisite with current version ID (inlined)', array(
                'minisite_id' => $minisiteId,
                'version_id' => $savedVersion->id,
            ));

            $this->minisiteRepository->updateCurrentVersionId($minisiteId, $savedVersion->id);

            $transactionManager->commitTransaction();

            $this->logger->info('New draft created successfully (inlined)', array(
                'minisite_id' => $minisiteId,
                'version_id' => $savedVersion->id,
                'user_id' => $currentUser->ID,
            ));

            $this->logger->debug('Final saved minisite data verification (inlined)', array(
                'minisite_id' => $minisiteId,
                'saved_minisite_title' => $savedMinisite->title ?? 'UNKNOWN',
                'saved_minisite_name' => $savedMinisite->name ?? 'UNKNOWN',
                'saved_minisite_status' => $savedMinisite->status ?? 'UNKNOWN',
                'saved_version_id' => $savedVersion->id ?? 'UNKNOWN',
                'saved_version_status' => $savedVersion->status ?? 'UNKNOWN',
                'saved_version_label' => $savedVersion->label ?? 'UNKNOWN',
                'site_json_size' => strlen($savedVersion->siteJson ?? '{}'),
                'seo_title_in_version' => $this->getSiteJsonValue($savedVersion->siteJson, 'seo', 'title'),
                'brand_name_in_version' => $this->getSiteJsonValue($savedVersion->siteJson, 'brand', 'name'),
                'hero_heading_in_version' => $this->getSiteJsonValue($savedVersion->siteJson, 'hero', 'heading'),
            ));

            return (object) array(
                'success' => true,
                'redirectUrl' => $this->wordPressManager->getHomeUrl(
                    "/account/sites/{$minisiteId}/edit?draft_created=1"
                ),
            );
        } catch (\Exception $e) {
            $this->logger->error('Database transaction failed for new draft (inlined)', array(
                'minisite_id' => $minisiteId,
                'user_id' => $currentUser?->ID ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ));

            $transactionManager->rollbackTransaction();

            throw $e;
        }
    }

    private function getSiteJsonValue(?string $siteJson, string $key1, string $key2): string
    {
        if ($siteJson === null) {
            return 'NOT_SET';
        }

        $decoded = json_decode($siteJson, true);
        if (! is_array($decoded)) {
            return 'NOT_SET';
        }

        return $decoded[$key1][$key2] ?? 'NOT_SET';
    }
}

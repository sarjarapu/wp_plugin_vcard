<?php

namespace Minisite\Domain\Services;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\Entities\Version;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Minisite Database Coordinator
 *
 * SINGLE RESPONSIBILITY: Handle different database operation patterns for minisites
 * - Manages database operations for new drafts, edit drafts, and published minisites
 * - Handles transactions and rollbacks
 * - Coordinates between wp_minisites and wp_minisite_versions tables
 * - Shared between Edit and New minisite features
 */
class MinisiteDatabaseCoordinator
{
    private LoggerInterface $logger;

    public function __construct(
        private WordPressManagerInterface $wordPressManager
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('database-coordinator');
    }

    /**
     * Save minisite data based on operation type
     */
    public function saveMinisiteData(
        string $minisiteId,
        array $formData,
        string $operationType,
        ?object $minisite = null,
        ?object $currentUser = null,
        ?bool $hasBeenPublished = null
    ): object {
        // Determine hasBeenPublished if not provided
        if ($hasBeenPublished === null) {
            $hasBeenPublished = $this->wordPressManager->hasBeenPublished($minisiteId);
        }

        switch ($operationType) {
            case 'new_draft':
                return $this->createNewDraft($minisiteId, $formData, $currentUser);
            case 'edit_draft':
                return $this->updateDraftVersion($minisiteId, $formData, $minisite, $currentUser, $hasBeenPublished);
            case 'edit_published':
                return $this->updatePublishedMinisite(
                    $minisiteId,
                    $formData,
                    $minisite,
                    $currentUser,
                    $hasBeenPublished
                );
            case 'publish_draft':
                return $this->publishDraftMinisite($minisiteId, $formData, $minisite, $currentUser);
            default:
                throw new \InvalidArgumentException("Unknown operation type: {$operationType}");
        }
    }

    /**
     * Create new draft minisite
     */
    private function createNewDraft(string $minisiteId, array $formData, ?object $currentUser): object
    {
        $this->logger->info('Starting new draft creation', [
            'minisite_id' => $minisiteId,
            'user_id' => $currentUser?->ID,
            'form_fields_count' => count($formData)
        ]);

        // Log key form data values for debugging
        $this->logger->debug('Database coordinator - form data values', [
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
            'search_terms' => $formData['search_terms'] ?? 'NOT_SET'
        ]);

        if (!$currentUser) {
            $this->logger->error('Current user is required for new minisite creation');
            throw new \InvalidArgumentException('Current user is required for new minisite creation');
        }

        // Build site JSON from form data
        $formProcessor = new MinisiteFormProcessor($this->wordPressManager);
        $siteJson = $formProcessor->buildSiteJsonFromForm($formData, $minisiteId);

        $this->logger->debug('Site JSON built successfully', [
            'minisite_id' => $minisiteId,
            'site_json_size' => strlen(json_encode($siteJson))
        ]);

        // DEBUG: Log the actual siteJson content being saved
        $this->logger->debug('SiteJson content being saved to database', [
            'minisite_id' => $minisiteId,
            'seo_section' => $siteJson['seo'] ?? 'NOT_SET',
            'brand_section' => $siteJson['brand'] ?? 'NOT_SET',
            'hero_section' => $siteJson['hero'] ?? 'NOT_SET'
        ]);

        // Handle coordinate fields
        $lat = !empty($formData['contact_lat']) ? (float) $formData['contact_lat'] : null;
        $lng = !empty($formData['contact_lng']) ? (float) $formData['contact_lng'] : null;

        // Generate draft slugs (like the old implementation)
        $draftBusinessSlug = 'biz-' . substr($minisiteId, 0, 8);
        $draftLocationSlug = 'loc-' . substr($minisiteId, 8, 8);
        $slugs = new \Minisite\Domain\ValueObjects\SlugPair(
            business: $draftBusinessSlug,
            location: $draftLocationSlug
        );

        // Create GeoPoint from form data
        $geo = null;
        if ($lat !== null && $lng !== null) {
            $geo = new \Minisite\Domain\ValueObjects\GeoPoint(lat: $lat, lng: $lng);
        }

        // Start transaction
        $this->logger->info('Starting database transaction for new draft creation', [
            'minisite_id' => $minisiteId,
            'user_id' => $currentUser->ID,
            'operation_type' => 'start_transaction'
        ]);

        $this->wordPressManager->startTransaction();

        try {
            // Create main minisite entity first (like the old implementation)
            $minisite = new \Minisite\Domain\Entities\Minisite(
                id: $minisiteId,
                slug: $slugs->full(), // Use SlugPair's full() method for formatted slug
                slugs: $slugs,
                title: $formProcessor->getFormValue($formData, [], 'seo_title', 'seo_title', '')
                    ?: 'Untitled Minisite',
                name: $formProcessor->getFormValue($formData, [], 'business_name', 'business_name', '')
                    ?: 'Untitled Minisite',
                city: $formProcessor->getFormValue($formData, [], 'business_city', 'business_city', ''),
                region: $formProcessor->getFormValue($formData, [], 'business_region', 'business_region', ''),
                countryCode: $formProcessor->getFormValue($formData, [], 'business_country', 'business_country', ''),
                postalCode: $formProcessor->getFormValue($formData, [], 'business_postal', 'business_postal', ''),
                geo: $geo,
                siteTemplate: $formProcessor->getFormValue($formData, [], 'site_template', 'site_template', '')
                    ?: 'v2025',
                palette: $formProcessor->getFormValue($formData, [], 'brand_palette', 'brand_palette', '') ?: 'blue',
                industry: $formProcessor->getFormValue($formData, [], 'brand_industry', 'brand_industry', ''),
                defaultLocale: $formProcessor->getFormValue($formData, [], 'default_locale', 'default_locale', '')
                    ?: 'en-US',
                schemaVersion: 1,
                siteVersion: 1,
                siteJson: $siteJson,
                searchTerms: $formProcessor->getFormValue($formData, [], 'search_terms', 'search_terms', ''),
                status: 'draft',
                publishStatus: 'draft',
                createdAt: new \DateTimeImmutable(),
                updatedAt: new \DateTimeImmutable(),
                publishedAt: null,
                createdBy: (int) $currentUser->ID,
                updatedBy: (int) $currentUser->ID,
                currentVersionId: null // Will be set after version creation
            );

            // Insert new minisite record to wp_minisites table (use insert for new minisites)
            $this->logger->debug('Inserting new minisite entity to database', [
                'minisite_id' => $minisiteId,
                'title' => $minisite->title,
                'status' => $minisite->status,
                'minisite_data' => [
                    'id' => $minisite->id,
                    'slug' => $minisite->slug,
                    'title' => $minisite->title,
                    'name' => $minisite->name,
                    'city' => $minisite->city,
                    'countryCode' => $minisite->countryCode,
                    'createdBy' => $minisite->createdBy,
                    'updatedBy' => $minisite->updatedBy
                ]
            ]);

            try {
                $this->logger->info('Starting minisite database insert operation', [
                'minisite_id' => $minisiteId,
                'minisite_title' => $minisite->title,
                'minisite_name' => $minisite->name,
                'minisite_status' => $minisite->status,
                'operation_type' => 'insert_minisite'
                ]);

                $savedMinisite = $this->wordPressManager->getMinisiteRepository()->insert($minisite);

                    $this->logger->info('Minisite database insert completed successfully', [
                    'minisite_id' => $minisiteId,
                    'saved_id' => $savedMinisite->id ?? 'unknown',
                    'saved_title' => $savedMinisite->title ?? 'unknown',
                    'saved_name' => $savedMinisite->name ?? 'unknown',
                    'saved_status' => $savedMinisite->status ?? 'unknown',
                    'operation_type' => 'insert_minisite_success'
                    ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to insert new minisite entity', [
                'minisite_id' => $minisiteId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'minisite_data' => [
                    'id' => $minisite->id,
                    'slug' => $minisite->slug,
                    'title' => $minisite->title,
                    'name' => $minisite->name,
                    'city' => $minisite->city,
                    'countryCode' => $minisite->countryCode,
                    'createdBy' => $minisite->createdBy,
                    'updatedBy' => $minisite->updatedBy
                ]
                ]);
                throw $e; // Re-throw to maintain error handling flow
            }

            // Create initial draft version
            $nextVersion = 1; // First version for new minisite
            $version = new \Minisite\Domain\Entities\Version(
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
                // Profile fields from form data
                slugs: $slugs,
                title: $formProcessor->getFormValue($formData, [], 'seo_title', 'seo_title', ''),
                name: $formProcessor->getFormValue($formData, [], 'business_name', 'business_name', ''),
                city: $formProcessor->getFormValue($formData, [], 'business_city', 'business_city', ''),
                region: $formProcessor->getFormValue($formData, [], 'business_region', 'business_region', ''),
                countryCode: $formProcessor->getFormValue($formData, [], 'business_country', 'business_country', ''),
                postalCode: $formProcessor->getFormValue($formData, [], 'business_postal', 'business_postal', ''),
                geo: $geo,
                siteTemplate: $formProcessor->getFormValue($formData, [], 'site_template', 'site_template', ''),
                palette: $formProcessor->getFormValue($formData, [], 'brand_palette', 'brand_palette', ''),
                industry: $formProcessor->getFormValue($formData, [], 'brand_industry', 'brand_industry', ''),
                defaultLocale: $formProcessor->getFormValue($formData, [], 'default_locale', 'default_locale', 'en'),
                schemaVersion: 1,
                siteVersion: 1,
                searchTerms: $formProcessor->getFormValue($formData, [], 'search_terms', 'search_terms', '')
            );

            $this->logger->debug('Saving version entity', [
                'minisite_id' => $minisiteId,
                'version_number' => $nextVersion,
                'version_data' => [
                    'minisiteId' => $version->minisiteId,
                    'versionNumber' => $version->versionNumber,
                    'status' => $version->status,
                    'label' => $version->label,
                    'createdBy' => $version->createdBy
                ]
            ]);

            try {
                $this->logger->info('Starting version database save operation', [
                    'minisite_id' => $minisiteId,
                    'version_number' => $nextVersion,
                    'version_status' => $version->status,
                    'version_label' => $version->label,
                    'site_json_size' => strlen(json_encode($version->siteJson ?? [])),
                    'operation_type' => 'save_version'
                ]);

                $savedVersion = $this->wordPressManager->saveVersion($version);

                $this->logger->info('Version database save completed successfully', [
                    'minisite_id' => $minisiteId,
                    'version_id' => $savedVersion->id ?? 'unknown',
                    'version_number' => $nextVersion,
                    'version_status' => $savedVersion->status ?? 'unknown',
                    'version_label' => $savedVersion->label ?? 'unknown',
                    'saved_site_json_size' => strlen(json_encode($savedVersion->siteJson ?? [])),
                    'operation_type' => 'save_version_success'
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to save version entity', [
                    'minisite_id' => $minisiteId,
                    'version_number' => $nextVersion,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'error_trace' => $e->getTraceAsString(),
                    'version_data' => [
                        'minisiteId' => $version->minisiteId,
                        'versionNumber' => $version->versionNumber,
                        'status' => $version->status,
                        'label' => $version->label,
                        'createdBy' => $version->createdBy
                    ]
                ]);
                throw $e; // Re-throw to maintain error handling flow
            }

            // Update main minisite with current version ID
            $this->logger->info('Updating main minisite with current version ID', [
                'minisite_id' => $minisiteId,
                'version_id' => $savedVersion->id,
                'operation_type' => 'update_current_version'
            ]);

            $this->wordPressManager->getMinisiteRepository()->updateCurrentVersionId($minisiteId, $savedVersion->id);

            $this->logger->info('Committing database transaction', [
                'minisite_id' => $minisiteId,
                'operation_type' => 'commit_transaction'
            ]);

            $this->wordPressManager->commitTransaction();

            $this->logger->info('New draft created successfully', [
                'minisite_id' => $minisiteId,
                'version_id' => $savedVersion->id,
                'user_id' => $currentUser->ID,
                'minisite_saved_id' => $savedMinisite->id ?? 'unknown',
                'operation_type' => 'new_draft'
            ]);

            // Log final saved data for verification
            $this->logger->debug('Final saved minisite data verification', [
                'minisite_id' => $minisiteId,
                'saved_minisite_title' => $savedMinisite->title ?? 'UNKNOWN',
                'saved_minisite_name' => $savedMinisite->name ?? 'UNKNOWN',
                'saved_minisite_status' => $savedMinisite->status ?? 'UNKNOWN',
                'saved_version_id' => $savedVersion->id ?? 'UNKNOWN',
                'saved_version_status' => $savedVersion->status ?? 'UNKNOWN',
                'saved_version_label' => $savedVersion->label ?? 'UNKNOWN',
                'site_json_size' => strlen(json_encode($savedVersion->siteJson ?? [])),
                'seo_title_in_version' => $savedVersion->siteJson['seo']['title'] ?? 'NOT_SET',
                'brand_name_in_version' => $savedVersion->siteJson['brand']['name'] ?? 'NOT_SET',
                'hero_heading_in_version' => $savedVersion->siteJson['hero']['heading'] ?? 'NOT_SET'
            ]);

            return (object) [
                'success' => true,
                'redirectUrl' => $this->wordPressManager->getHomeUrl(
                    "/account/sites/{$minisiteId}/edit?draft_created=1"
                )
            ];
        } catch (\Exception $e) {
            $this->logger->error('Database transaction failed, rolling back', [
                'minisite_id' => $minisiteId,
                'user_id' => $currentUser?->ID ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'form_data_keys' => array_keys($formData),
                'form_data_count' => count($formData),
                'operation_type' => 'rollback_transaction'
            ]);

            $this->wordPressManager->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * Update draft version (existing minisite, creating new version)
     */
    private function updateDraftVersion(
        string $minisiteId,
        array $formData,
        ?object $minisite,
        ?object $currentUser,
        bool $hasBeenPublished
    ): object {
        if (!$minisite || !$currentUser) {
            throw new \InvalidArgumentException('Minisite and current user are required for draft updates');
        }

        // Build site JSON from form data
        $formProcessor = new MinisiteFormProcessor($this->wordPressManager);
        $siteJson = $formProcessor->buildSiteJsonFromForm($formData, $minisiteId, $minisite);

        // Handle coordinate fields
        $lat = !empty($formData['contact_lat']) ? (float) $formData['contact_lat'] : null;
        $lng = !empty($formData['contact_lng']) ? (float) $formData['contact_lng'] : null;

        // Start transaction
        $this->wordPressManager->startTransaction();

        try {
            // Create new draft version
            $nextVersion = $this->wordPressManager->getNextVersionNumber($minisiteId);
            $slugs = $minisite->slugs;

            // Create GeoPoint from form data
            $geo = null;
            if ($lat !== null && $lng !== null) {
                $geo = new GeoPoint(lat: $lat, lng: $lng);
            }

            $version = new Version(
                id: null,
                minisiteId: $minisiteId,
                versionNumber: $nextVersion,
                status: 'draft',
                label: $this->wordPressManager->sanitizeTextField(
                    $formData['version_label'] ?? "Version {$nextVersion}"
                ),
                comment: $this->wordPressManager->sanitizeTextareaField(
                    $formData['version_comment'] ?? ''
                ),
                createdBy: (int) $currentUser->ID,
                createdAt: null,
                publishedAt: null,
                sourceVersionId: null,
                siteJson: $siteJson,
                // Profile fields from form data
                slugs: $slugs,
                title: $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'seo_title',
                    'title'
                ),
                name: $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_name',
                    'name'
                ),
                city: $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_city',
                    'city'
                ),
                region: $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_region',
                    'region'
                ),
                countryCode: $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_country',
                    'countryCode'
                ),
                postalCode: $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_postal',
                    'postalCode'
                ),
                geo: $geo,
                siteTemplate: $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'site_template',
                    'siteTemplate'
                ),
                palette: $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'brand_palette',
                    'palette'
                ),
                industry: $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'brand_industry',
                    'industry'
                ),
                defaultLocale: $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'default_locale',
                    'defaultLocale'
                ),
                schemaVersion: $minisite->schemaVersion,
                siteVersion: $minisite->siteVersion,
                searchTerms: $formProcessor->getFormValueFromObject($formData, $minisite, 'search_terms', 'searchTerms')
            );

            $savedVersion = $this->wordPressManager->saveVersion($version);

            // Update main table for unpublished minisites
            $this->updateMainTableIfNeeded(
                $minisiteId,
                $formData,
                $minisite,
                $currentUser,
                $lat,
                $lng,
                $formProcessor,
                $hasBeenPublished
            );

            $this->wordPressManager->commitTransaction();

            return (object) [
                'success' => true,
                'redirectUrl' => $this->wordPressManager->getHomeUrl("/account/sites/{$minisiteId}/edit?draft_saved=1")
            ];
        } catch (\Exception $e) {
            $this->wordPressManager->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * Update published minisite (only create new version, don't update main table)
     */
    private function updatePublishedMinisite(
        string $minisiteId,
        array $formData,
        ?object $minisite,
        ?object $currentUser,
        bool $hasBeenPublished
    ): object {
        // For published minisites, we only create new versions
        // The main table remains unchanged to preserve published state
        return $this->updateDraftVersion($minisiteId, $formData, $minisite, $currentUser, $hasBeenPublished);
    }

    /**
     * Publish draft minisite (transition from draft to published)
     */
    private function publishDraftMinisite(
        string $minisiteId,
        array $formData,
        ?object $minisite,
        ?object $currentUser
    ): object {
        // This will be implemented when we handle publishing functionality
        // For now, throw an exception to indicate it's not implemented yet
        throw new \RuntimeException(
            'Publish draft functionality not implemented yet - will be handled by publishing feature'
        );
    }

    /**
     * Update main table if needed (for unpublished minisites only)
     */
    private function updateMainTableIfNeeded(
        string $siteId,
        array $formData,
        object $minisite,
        object $currentUser,
        ?float $lat,
        ?float $lng,
        MinisiteFormProcessor $formProcessor,
        bool $hasBeenPublished
    ): void {
        if (!$hasBeenPublished) {
            // For new minisites: Update main table so preview works with imported data
            $businessInfoFields = [
                'name' => $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_name',
                    'name'
                ),
                'city' => $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_city',
                    'city'
                ),
                'region' => $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_region',
                    'region'
                ),
                'country_code' => $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_country',
                    'countryCode'
                ),
                'postal_code' => $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_postal',
                    'postalCode'
                ),
                'site_template' => $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'site_template',
                    'siteTemplate'
                ),
                'palette' => $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'brand_palette',
                    'palette'
                ),
                'industry' => $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'brand_industry',
                    'industry'
                ),
                'default_locale' => $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'default_locale',
                    'defaultLocale'
                ),
                'search_terms' => $formProcessor->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'search_terms',
                    'searchTerms'
                ),
            ];

            // Consolidated update - all fields in one UPDATE statement
            $allUpdateFields = $businessInfoFields;

            // Add coordinates if provided
            if ($lat !== null && $lng !== null) {
                $allUpdateFields['location_point'] = "POINT($lng, $lat)";
            }

            // Add title if provided
            $newTitle = $formProcessor->getFormValue($formData, [], 'seo_title', 'seo_title', '');
            if (!empty($newTitle) && $newTitle !== $minisite->title) {
                $allUpdateFields['title'] = $newTitle;
            }

            $this->wordPressManager->updateMinisiteFields($siteId, $allUpdateFields, (int) $currentUser->ID);
        }
    }
}

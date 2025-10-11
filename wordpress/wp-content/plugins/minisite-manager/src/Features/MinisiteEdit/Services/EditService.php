<?php

namespace Minisite\Features\MinisiteEdit\Services;

use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\Entities\Version;

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
            // Log incoming form data for debugging
            error_log('MINISITE_EDIT_DEBUG: Form data received: ' . json_encode($formData, JSON_PRETTY_PRINT));

            // Validate form data
            $errors = $this->validateFormData($formData);
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

            // Log existing siteJson for comparison
            error_log('MINISITE_EDIT_DEBUG: Existing siteJson: ' . json_encode($minisite->siteJson, JSON_PRETTY_PRINT));

            // Build site JSON from form data
            $siteJson = $this->buildSiteJsonFromForm($formData, $siteId);

            // Log built siteJson for debugging
            error_log('MINISITE_EDIT_DEBUG: Built siteJson: ' . json_encode($siteJson, JSON_PRETTY_PRINT));

            // Handle coordinate fields
            $lat = !empty($formData['contact_lat']) ? (float) $formData['contact_lat'] : null;
            $lng = !empty($formData['contact_lng']) ? (float) $formData['contact_lng'] : null;

            // Start transaction
            $this->wordPressManager->startTransaction();

            try {
                // Create new draft version
                $nextVersion = $this->wordPressManager->getNextVersionNumber($siteId);
                $slugs = $minisite->slugs;

                // Create GeoPoint from form data
                $geo = null;
                if ($lat !== null && $lng !== null) {
                    $geo = new GeoPoint(lat: $lat, lng: $lng);
                }

                $version = new Version(
                    id: null,
                    minisiteId: $siteId,
                    versionNumber: $nextVersion,
                    status: 'draft',
                    label: $this->wordPressManager->sanitizeTextField($formData['version_label'] ?? "Version {$nextVersion}"),
                    comment: $this->wordPressManager->sanitizeTextareaField($formData['version_comment'] ?? ''),
                    createdBy: (int) $currentUser->ID,
                    createdAt: null,
                    publishedAt: null,
                    sourceVersionId: null,
                    siteJson: $siteJson,
                    // Profile fields from form data
                    slugs: $slugs,
                    title: $this->wordPressManager->sanitizeTextField($formData['seo_title'] ?? $minisite->title),
                    name: $this->wordPressManager->sanitizeTextField($formData['business_name'] ?? $minisite->name),
                    city: $this->wordPressManager->sanitizeTextField($formData['business_city'] ?? $minisite->city),
                    region: $this->wordPressManager->sanitizeTextField($formData['business_region'] ?? $minisite->region),
                    countryCode: $this->wordPressManager->sanitizeTextField($formData['business_country'] ?? $minisite->countryCode),
                    postalCode: $this->wordPressManager->sanitizeTextField($formData['business_postal'] ?? $minisite->postalCode),
                    geo: $geo,
                    siteTemplate: $this->wordPressManager->sanitizeTextField($formData['site_template'] ?? $minisite->siteTemplate),
                    palette: $this->wordPressManager->sanitizeTextField($formData['brand_palette'] ?? $minisite->palette),
                    industry: $this->wordPressManager->sanitizeTextField($formData['brand_industry'] ?? $minisite->industry),
                    defaultLocale: $this->wordPressManager->sanitizeTextField($formData['default_locale'] ?? $minisite->defaultLocale),
                    schemaVersion: $minisite->schemaVersion,
                    siteVersion: $minisite->siteVersion,
                    searchTerms: $this->wordPressManager->sanitizeTextField($formData['search_terms'] ?? $minisite->searchTerms)
                );

                $savedVersion = $this->wordPressManager->saveVersion($version);

                // Update main table for unpublished minisites
                $this->updateMainTableIfNeeded($siteId, $formData, $minisite, $currentUser, $lat, $lng);

                $this->wordPressManager->commitTransaction();

                return (object) [
                    'success' => true,
                    'redirectUrl' => $this->wordPressManager->getHomeUrl("/account/sites/{$siteId}/edit?draft_saved=1")
                ];
            } catch (\Exception $e) {
                $this->wordPressManager->rollbackTransaction();
                throw $e;
            }
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

    /**
     * Validate form data
     */
    private function validateFormData(array $data): array
    {
        $errors = [];

        // Add validation rules as needed
        if (empty($data['business_name'])) {
            $errors[] = 'Business name is required';
        }

        if (empty($data['business_city'])) {
            $errors[] = 'Business city is required';
        }

        return $errors;
    }

    /**
     * Build site JSON from form data
     * CRITICAL: This method must preserve ALL existing siteJson data and only update submitted fields
     */
    private function buildSiteJsonFromForm(array $formData, string $siteId): array
    {
        // Get existing siteJson to preserve all data
        $minisite = $this->wordPressManager->findMinisiteById($siteId);
        $existingSiteJson = $minisite && $minisite->siteJson ? $minisite->siteJson : [];

        // Log existing siteJson structure for debugging
        error_log('MINISITE_EDIT_DEBUG: Existing siteJson structure: ' . json_encode(array_keys($existingSiteJson), JSON_PRETTY_PRINT));

        // Start with existing siteJson to preserve all data
        $siteJson = $existingSiteJson;

        // Only update fields that are actually submitted in the form
        // This ensures we don't lose any existing data like hero, about, services, gallery, social, etc.

        // Update business information if provided
        if (
            isset($formData['business_name']) || isset($formData['business_city']) ||
            isset($formData['business_region']) || isset($formData['business_country']) ||
            isset($formData['business_postal'])
        ) {
            $siteJson['business'] = array_merge($siteJson['business'] ?? [], [
                'name' => $this->wordPressManager->sanitizeTextField($formData['business_name'] ?? $siteJson['business']['name'] ?? ''),
                'city' => $this->wordPressManager->sanitizeTextField($formData['business_city'] ?? $siteJson['business']['city'] ?? ''),
                'region' => $this->wordPressManager->sanitizeTextField($formData['business_region'] ?? $siteJson['business']['region'] ?? ''),
                'country' => $this->wordPressManager->sanitizeTextField($formData['business_country'] ?? $siteJson['business']['country'] ?? ''),
                'postal' => $this->wordPressManager->sanitizeTextField($formData['business_postal'] ?? $siteJson['business']['postal'] ?? ''),
            ]);
        }

        // Update contact coordinates if provided
        if (isset($formData['contact_lat']) || isset($formData['contact_lng'])) {
            $siteJson['contact'] = array_merge($siteJson['contact'] ?? [], [
                'lat' => !empty($formData['contact_lat']) ? (float) $formData['contact_lat'] : ($siteJson['contact']['lat'] ?? null),
                'lng' => !empty($formData['contact_lng']) ? (float) $formData['contact_lng'] : ($siteJson['contact']['lng'] ?? null),
            ]);
        }

        // Update brand information if provided
        if (isset($formData['brand_palette']) || isset($formData['brand_industry'])) {
            $siteJson['brand'] = array_merge($siteJson['brand'] ?? [], [
                'palette' => $this->wordPressManager->sanitizeTextField($formData['brand_palette'] ?? $siteJson['brand']['palette'] ?? ''),
                'industry' => $this->wordPressManager->sanitizeTextField($formData['brand_industry'] ?? $siteJson['brand']['industry'] ?? ''),
            ]);
        }

        // Update SEO information if provided
        if (isset($formData['seo_title']) || isset($formData['search_terms'])) {
            $siteJson['seo'] = array_merge($siteJson['seo'] ?? [], [
                'title' => $this->wordPressManager->sanitizeTextField($formData['seo_title'] ?? $siteJson['seo']['title'] ?? ''),
                'search_terms' => $this->wordPressManager->sanitizeTextField($formData['search_terms'] ?? $siteJson['seo']['search_terms'] ?? ''),
            ]);
        }

        // Update settings if provided
        if (isset($formData['site_template']) || isset($formData['default_locale'])) {
            $siteJson['settings'] = array_merge($siteJson['settings'] ?? [], [
                'template' => $this->wordPressManager->sanitizeTextField($formData['site_template'] ?? $siteJson['settings']['template'] ?? ''),
                'locale' => $this->wordPressManager->sanitizeTextField($formData['default_locale'] ?? $siteJson['settings']['locale'] ?? ''),
            ]);
        }

        // Log final siteJson structure for debugging
        error_log('MINISITE_EDIT_DEBUG: Final siteJson structure: ' . json_encode(array_keys($siteJson), JSON_PRETTY_PRINT));
        error_log('MINISITE_EDIT_DEBUG: Final siteJson size: ' . strlen(json_encode($siteJson)) . ' characters');

        return $siteJson;
    }

    /**
     * Update main table if needed
     */
    private function updateMainTableIfNeeded(string $siteId, array $formData, object $minisite, object $currentUser, ?float $lat, ?float $lng): void
    {
        $hasBeenPublished = $this->wordPressManager->hasBeenPublished($siteId);

        if (!$hasBeenPublished) {
            // For new minisites: Update main table so preview works with imported data
            $businessInfoFields = [
                'name' => $this->wordPressManager->sanitizeTextField($formData['business_name'] ?? $minisite->name),
                'city' => $this->wordPressManager->sanitizeTextField($formData['business_city'] ?? $minisite->city),
                'region' => $this->wordPressManager->sanitizeTextField($formData['business_region'] ?? $minisite->region),
                'country_code' => $this->wordPressManager->sanitizeTextField($formData['business_country'] ?? $minisite->countryCode),
                'postal_code' => $this->wordPressManager->sanitizeTextField($formData['business_postal'] ?? $minisite->postalCode),
                'site_template' => $this->wordPressManager->sanitizeTextField($formData['site_template'] ?? $minisite->siteTemplate),
                'palette' => $this->wordPressManager->sanitizeTextField($formData['brand_palette'] ?? $minisite->palette),
                'industry' => $this->wordPressManager->sanitizeTextField($formData['brand_industry'] ?? $minisite->industry),
                'default_locale' => $this->wordPressManager->sanitizeTextField($formData['default_locale'] ?? $minisite->defaultLocale),
                'search_terms' => $this->wordPressManager->sanitizeTextField($formData['search_terms'] ?? $minisite->searchTerms),
            ];

            $this->wordPressManager->updateBusinessInfo($siteId, $businessInfoFields, (int) $currentUser->ID);

            // Update coordinates if provided
            if ($lat !== null && $lng !== null) {
                $this->wordPressManager->updateCoordinates($siteId, $lat, $lng, (int) $currentUser->ID);
            }

            // Update title if provided
            $newTitle = $this->wordPressManager->sanitizeTextField($formData['seo_title'] ?? '');
            if (!empty($newTitle) && $newTitle !== $minisite->title) {
                $this->wordPressManager->updateTitle($siteId, $newTitle);
            }
        }
    }
}

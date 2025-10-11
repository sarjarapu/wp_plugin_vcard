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


            // Build site JSON from form data
            $siteJson = $this->buildSiteJsonFromForm($formData, $siteId);


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
                    title: $this->getFormValueFromObject($formData, $minisite, 'seo_title', 'title'),
                    name: $this->getFormValueFromObject($formData, $minisite, 'business_name', 'name'),
                    city: $this->getFormValueFromObject($formData, $minisite, 'business_city', 'city'),
                    region: $this->getFormValueFromObject($formData, $minisite, 'business_region', 'region'),
                    countryCode: $this->getFormValueFromObject($formData, $minisite, 'business_country', 'countryCode'),
                    postalCode: $this->getFormValueFromObject($formData, $minisite, 'business_postal', 'postalCode'),
                    geo: $geo,
                    siteTemplate: $this->getFormValueFromObject($formData, $minisite, 'site_template', 'siteTemplate'),
                    palette: $this->getFormValueFromObject($formData, $minisite, 'brand_palette', 'palette'),
                    industry: $this->getFormValueFromObject($formData, $minisite, 'brand_industry', 'industry'),
                    defaultLocale: $this->getFormValueFromObject(
                        $formData,
                        $minisite,
                        'default_locale',
                        'defaultLocale'
                    ),
                    schemaVersion: $minisite->schemaVersion,
                    siteVersion: $minisite->siteVersion,
                    searchTerms: $this->getFormValueFromObject($formData, $minisite, 'search_terms', 'searchTerms')
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
     * Helper function to get sanitized form data with fallback to existing value
     */
    private function getFormValue(
        array $formData,
        array $existingData,
        string $formKey,
        ?string $existingKey = null,
        string $default = ''
    ): string {
        $existingKey = $existingKey ?? $formKey;
        return $this->wordPressManager->sanitizeTextField(
            $formData[$formKey] ?? $existingData[$existingKey] ?? $default
        );
    }

    /**
     * Helper function to get sanitized form data with fallback to object property
     */
    private function getFormValueFromObject(
        array $formData,
        object $existingObject,
        string $formKey,
        string $propertyName,
        string $default = ''
    ): string {
        return $this->wordPressManager->sanitizeTextField(
            $formData[$formKey] ?? ($existingObject->$propertyName ?? $default)
        );
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

        // Start with existing siteJson to preserve all data
        $siteJson = $existingSiteJson;

        // Update each section if form data is provided
        $siteJson = $this->buildBusinessSection($formData, $siteJson);
        $siteJson = $this->buildContactSection($formData, $siteJson);
        $siteJson = $this->buildBrandSection($formData, $siteJson);
        $siteJson = $this->buildSeoSection($formData, $siteJson);
        $siteJson = $this->buildSettingsSection($formData, $siteJson);
        $siteJson = $this->buildHeroSection($formData, $siteJson);
        $siteJson = $this->buildAboutSection($formData, $siteJson);
        $siteJson = $this->buildWhyUsSection($formData, $siteJson);
        $siteJson = $this->buildServicesSection($formData, $siteJson);
        $siteJson = $this->buildGallerySection($formData, $siteJson);
        $siteJson = $this->buildSocialSection($formData, $siteJson);

        return $siteJson;
    }

    /**
     * Build business section from form data
     */
    private function buildBusinessSection(array $formData, array $siteJson): array
    {
        if (
            isset($formData['business_name']) || isset($formData['business_city']) ||
            isset($formData['business_region']) || isset($formData['business_country']) ||
            isset($formData['business_postal'])
        ) {
            $siteJson['business'] = array_merge($siteJson['business'] ?? [], [
                'name' => $this->getFormValue($formData, $siteJson['business'] ?? [], 'business_name', 'name'),
                'city' => $this->getFormValue($formData, $siteJson['business'] ?? [], 'business_city', 'city'),
                'region' => $this->getFormValue($formData, $siteJson['business'] ?? [], 'business_region', 'region'),
                'country' => $this->getFormValue($formData, $siteJson['business'] ?? [], 'business_country', 'country'),
                'postal' => $this->getFormValue($formData, $siteJson['business'] ?? [], 'business_postal', 'postal'),
            ]);
        }

        return $siteJson;
    }

    /**
     * Build contact section from form data
     */
    private function buildContactSection(array $formData, array $siteJson): array
    {
        if (
            isset($formData['contact_phone_text']) || isset($formData['contact_phone_link']) ||
            isset($formData['contact_whatsapp_text']) || isset($formData['contact_whatsapp_link']) ||
            isset($formData['contact_email']) || isset($formData['contact_website_text']) ||
            isset($formData['contact_website_link']) || isset($formData['contact_address1']) ||
            isset($formData['contact_address2']) || isset($formData['contact_address3']) ||
            isset($formData['contact_address4']) || isset($formData['contact_pluscode']) ||
            isset($formData['contact_pluscode_url']) || isset($formData['contact_lat']) ||
            isset($formData['contact_lng']) || $this->hasHoursData($formData)
        ) {
            $siteJson['contact'] = array_merge($siteJson['contact'] ?? [], [
                'phone' => [
                    'text' => $this->getFormValue(
                        $formData,
                        $siteJson['contact']['phone'] ?? [],
                        'contact_phone_text',
                        'text'
                    ),
                    'link' => $this->getFormValue(
                        $formData,
                        $siteJson['contact']['phone'] ?? [],
                        'contact_phone_link',
                        'link'
                    ),
                ],
                'whatsapp' => [
                    'text' => $this->getFormValue(
                        $formData,
                        $siteJson['contact']['whatsapp'] ?? [],
                        'contact_whatsapp_text',
                        'text'
                    ),
                    'link' => $this->getFormValue(
                        $formData,
                        $siteJson['contact']['whatsapp'] ?? [],
                        'contact_whatsapp_link',
                        'link'
                    ),
                ],
                'email' => $this->wordPressManager->sanitizeEmail(
                    $formData['contact_email'] ?? ($siteJson['contact']['email'] ?? '')
                ),
                'website' => [
                    'text' => $this->getFormValue(
                        $formData,
                        $siteJson['contact']['website'] ?? [],
                        'contact_website_text',
                        'text'
                    ),
                    'link' => $this->wordPressManager->sanitizeUrl(
                        $formData['contact_website_link'] ?? ($siteJson['contact']['website']['link'] ?? '')
                    ),
                ],
                'address_line1' => $this->getFormValue(
                    $formData,
                    $siteJson['contact'] ?? [],
                    'contact_address1',
                    'address_line1'
                ),
                'address_line2' => $this->getFormValue(
                    $formData,
                    $siteJson['contact'] ?? [],
                    'contact_address2',
                    'address_line2'
                ),
                'address_line3' => $this->getFormValue(
                    $formData,
                    $siteJson['contact'] ?? [],
                    'contact_address3',
                    'address_line3'
                ),
                'address_line4' => $this->getFormValue(
                    $formData,
                    $siteJson['contact'] ?? [],
                    'contact_address4',
                    'address_line4'
                ),
                'plusCode' => $this->getFormValue(
                    $formData,
                    $siteJson['contact'] ?? [],
                    'contact_pluscode',
                    'plusCode'
                ),
                'plusCodeUrl' => $this->wordPressManager->sanitizeUrl(
                    $formData['contact_pluscode_url'] ?? ($siteJson['contact']['plusCodeUrl'] ?? '')
                ),
                'lat' => !empty($formData['contact_lat']) ? (float) $formData['contact_lat'] :
                    ($siteJson['contact']['lat'] ?? null),
                'lng' => !empty($formData['contact_lng']) ? (float) $formData['contact_lng'] :
                    ($siteJson['contact']['lng'] ?? null),
                'hours' => $this->buildHoursFromForm($formData, $siteJson['contact']['hours'] ?? []),
            ]);
        }

        return $siteJson;
    }

    /**
     * Build brand section from form data
     */
    private function buildBrandSection(array $formData, array $siteJson): array
    {
        if (isset($formData['brand_palette']) || isset($formData['brand_industry'])) {
            $siteJson['brand'] = array_merge($siteJson['brand'] ?? [], [
                'palette' => $this->getFormValue($formData, $siteJson['brand'] ?? [], 'brand_palette', 'palette'),
                'industry' => $this->getFormValue(
                    $formData,
                    $siteJson['brand'] ?? [],
                    'brand_industry',
                    'industry'
                ),
            ]);
        }

        return $siteJson;
    }

    /**
     * Build SEO section from form data
     */
    private function buildSeoSection(array $formData, array $siteJson): array
    {
        if (isset($formData['seo_title']) || isset($formData['search_terms'])) {
            $siteJson['seo'] = array_merge($siteJson['seo'] ?? [], [
                'title' => $this->getFormValue($formData, $siteJson['seo'] ?? [], 'seo_title', 'title'),
                'search_terms' => $this->getFormValue(
                    $formData,
                    $siteJson['seo'] ?? [],
                    'search_terms',
                    'search_terms'
                ),
            ]);
        }

        return $siteJson;
    }

    /**
     * Build settings section from form data
     */
    private function buildSettingsSection(array $formData, array $siteJson): array
    {
        if (isset($formData['site_template']) || isset($formData['default_locale'])) {
            $siteJson['settings'] = array_merge($siteJson['settings'] ?? [], [
                'template' => $this->getFormValue(
                    $formData,
                    $siteJson['settings'] ?? [],
                    'site_template',
                    'template'
                ),
                'locale' => $this->getFormValue(
                    $formData,
                    $siteJson['settings'] ?? [],
                    'default_locale',
                    'locale'
                ),
            ]);
        }

        return $siteJson;
    }

    /**
     * Build hero section from form data
     */
    private function buildHeroSection(array $formData, array $siteJson): array
    {
        if (
            isset($formData['hero_badge']) || isset($formData['hero_heading']) ||
            isset($formData['hero_subheading']) || isset($formData['hero_image']) ||
            isset($formData['hero_image_alt']) || isset($formData['hero_cta1_text']) ||
            isset($formData['hero_cta1_url']) || isset($formData['hero_cta2_text']) ||
            isset($formData['hero_cta2_url']) || isset($formData['hero_rating_value']) ||
            isset($formData['hero_rating_count'])
        ) {
            $siteJson['hero'] = array_merge($siteJson['hero'] ?? [], [
                'badge' => $this->getFormValue($formData, $siteJson['hero'] ?? [], 'hero_badge', 'badge'),
                'heading' => $this->getFormValue($formData, $siteJson['hero'] ?? [], 'hero_heading', 'heading'),
                'subheading' => $this->sanitizeRichTextContent(
                    $formData['hero_subheading'] ?? ($siteJson['hero']['subheading'] ?? '')
                ),
                'image' => $this->wordPressManager->sanitizeUrl(
                    $formData['hero_image'] ?? ($siteJson['hero']['image'] ?? '')
                ),
                'imageAlt' => $this->getFormValue($formData, $siteJson['hero'] ?? [], 'hero_image_alt', 'imageAlt'),
                'ctas' => [
                    [
                        'text' => $this->getFormValue(
                            $formData,
                            $siteJson['hero']['ctas'][0] ?? [],
                            'hero_cta1_text',
                            'text'
                        ),
                        'url' => $this->wordPressManager->sanitizeUrl(
                            $formData['hero_cta1_url'] ?? ($siteJson['hero']['ctas'][0]['url'] ?? '')
                        ),
                    ],
                    [
                        'text' => $this->getFormValue(
                            $formData,
                            $siteJson['hero']['ctas'][1] ?? [],
                            'hero_cta2_text',
                            'text'
                        ),
                        'url' => $this->wordPressManager->sanitizeUrl(
                            $formData['hero_cta2_url'] ?? ($siteJson['hero']['ctas'][1]['url'] ?? '')
                        ),
                    ],
                ],
                'rating' => [
                    'value' => $this->getFormValue(
                        $formData,
                        $siteJson['hero']['rating'] ?? [],
                        'hero_rating_value',
                        'value'
                    ),
                    'count' => $this->getFormValue(
                        $formData,
                        $siteJson['hero']['rating'] ?? [],
                        'hero_rating_count',
                        'count'
                    ),
                ],
            ]);
        }

        return $siteJson;
    }

    /**
     * Build about section from form data
     */
    private function buildAboutSection(array $formData, array $siteJson): array
    {
        if (isset($formData['about_html'])) {
            $siteJson['about'] = array_merge($siteJson['about'] ?? [], [
                'html' => $this->sanitizeRichTextContent($formData['about_html']),
            ]);
        }

        return $siteJson;
    }

    /**
     * Build whyUs section from form data
     */
    private function buildWhyUsSection(array $formData, array $siteJson): array
    {
        if (isset($formData['whyus_title']) || isset($formData['whyus_html']) || isset($formData['whyus_image'])) {
            $siteJson['whyUs'] = array_merge($siteJson['whyUs'] ?? [], [
                'title' => $this->getFormValue($formData, $siteJson['whyUs'] ?? [], 'whyus_title', 'title'),
                'html' => $this->sanitizeRichTextContent(
                    $formData['whyus_html'] ?? ($siteJson['whyUs']['html'] ?? '')
                ),
                'image' => $this->wordPressManager->sanitizeUrl(
                    $formData['whyus_image'] ?? ($siteJson['whyUs']['image'] ?? '')
                ),
            ]);
        }

        return $siteJson;
    }

    /**
     * Build services section from form data
     */
    private function buildServicesSection(array $formData, array $siteJson): array
    {
        if (isset($formData['product_count']) || isset($formData['products_section_title'])) {
            $siteJson['services'] = $this->buildServicesFromForm($formData, $siteJson['services'] ?? []);
        }

        return $siteJson;
    }

    /**
     * Build gallery section from form data
     */
    private function buildGallerySection(array $formData, array $siteJson): array
    {
        if (isset($formData['gallery_count'])) {
            $siteJson['gallery'] = $this->buildGalleryFromForm($formData);
        }

        return $siteJson;
    }

    /**
     * Build social section from form data
     */
    private function buildSocialSection(array $formData, array $siteJson): array
    {
        if (
            isset($formData['social_facebook']) || isset($formData['social_instagram']) ||
            isset($formData['social_x']) || isset($formData['social_youtube']) ||
            isset($formData['social_linkedin']) || isset($formData['social_tiktok'])
        ) {
            $siteJson['social'] = $this->buildSocialFromForm($formData, $siteJson['social'] ?? []);
        }

        return $siteJson;
    }

    /**
     * Update main table if needed
     */
    private function updateMainTableIfNeeded(
        string $siteId,
        array $formData,
        object $minisite,
        object $currentUser,
        ?float $lat,
        ?float $lng
    ): void {
        $hasBeenPublished = $this->wordPressManager->hasBeenPublished($siteId);

        if (!$hasBeenPublished) {
            // For new minisites: Update main table so preview works with imported data
            $businessInfoFields = [
                'name' => $this->getFormValueFromObject($formData, $minisite, 'business_name', 'name'),
                'city' => $this->getFormValueFromObject($formData, $minisite, 'business_city', 'city'),
                'region' => $this->getFormValueFromObject($formData, $minisite, 'business_region', 'region'),
                'country_code' => $this->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'business_country',
                    'countryCode'
                ),
                'postal_code' => $this->getFormValueFromObject($formData, $minisite, 'business_postal', 'postalCode'),
                'site_template' => $this->getFormValueFromObject($formData, $minisite, 'site_template', 'siteTemplate'),
                'palette' => $this->getFormValueFromObject($formData, $minisite, 'brand_palette', 'palette'),
                'industry' => $this->getFormValueFromObject($formData, $minisite, 'brand_industry', 'industry'),
                'default_locale' => $this->getFormValueFromObject(
                    $formData,
                    $minisite,
                    'default_locale',
                    'defaultLocale'
                ),
                'search_terms' => $this->getFormValueFromObject($formData, $minisite, 'search_terms', 'searchTerms'),
            ];

            $this->wordPressManager->updateBusinessInfo($siteId, $businessInfoFields, (int) $currentUser->ID);

            // Update coordinates if provided
            if ($lat !== null && $lng !== null) {
                $this->wordPressManager->updateCoordinates($siteId, $lat, $lng, (int) $currentUser->ID);
            }

            // Update title if provided
            $newTitle = $this->getFormValue($formData, [], 'seo_title', 'seo_title', '');
            if (!empty($newTitle) && $newTitle !== $minisite->title) {
                $this->wordPressManager->updateTitle($siteId, $newTitle);
            }
        }
    }

    /**
     * Build services section from form data
     */
    private function buildServicesFromForm(array $formData, array $existingServices = []): array
    {
        $services = [];
        $serviceCount = (int) ($formData['product_count'] ?? 0);

        for ($i = 0; $i < $serviceCount; $i++) {
            $services[] = [
                'title' => $this->wordPressManager->sanitizeTextField($formData["product_{$i}_title"] ?? ''),
                'image' => $this->wordPressManager->sanitizeUrl($formData["product_{$i}_image"] ?? ''),
                'description' => $this->sanitizeRichTextContent($formData["product_{$i}_description"] ?? ''),
                'price' => $this->wordPressManager->sanitizeTextField($formData["product_{$i}_price"] ?? ''),
                'icon' => $this->wordPressManager->sanitizeTextField($formData["product_{$i}_icon"] ?? ''),
                'cta' => $this->wordPressManager->sanitizeTextField($formData["product_{$i}_cta_text"] ?? ''),
                'url' => $this->wordPressManager->sanitizeUrl($formData["product_{$i}_cta_url"] ?? ''),
            ];
        }

        return [
            'title' => $this->wordPressManager->sanitizeTextField(
                $formData['products_section_title'] ?? ($existingServices['title'] ?? 'Products & Services')
            ),
            'listing' => $services,
        ];
    }

    /**
     * Build gallery section from form data
     */
    private function buildGalleryFromForm(array $formData): array
    {
        $gallery = [];
        $imageCount = (int) ($formData['gallery_count'] ?? 0);

        for ($i = 0; $i < $imageCount; $i++) {
            $imageUrl = $this->wordPressManager->sanitizeUrl($formData["gallery_{$i}_image"] ?? '');
            $imageAlt = $this->wordPressManager->sanitizeTextField($formData["gallery_{$i}_alt"] ?? '');
            if (!empty($imageUrl)) {
                $gallery[] = [
                    'src' => $imageUrl,
                    'alt' => $imageAlt,
                    'caption' => $imageAlt, // Use alt as caption fallback
                ];
            }
        }

        return $gallery;
    }

    /**
     * Build social section from form data
     */
    private function buildSocialFromForm(array $formData, array $existingSocial = []): array
    {
        $networks = ['facebook', 'instagram', 'x', 'youtube', 'linkedin', 'tiktok'];
        $social = $existingSocial; // Start with existing data

        foreach ($networks as $network) {
            $url = $this->wordPressManager->sanitizeUrl($formData["social_{$network}"] ?? '');
            if (!empty($url)) {
                $social[$network] = $url;
            } elseif (isset($formData["social_{$network}"])) {
                // If field is explicitly set but empty, remove it
                unset($social[$network]);
            }
        }

        return $social;
    }

    /**
     * Build hours section from form data
     */
    private function buildHoursFromForm(array $formData, array $existingHours = []): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $hours = $existingHours; // Start with existing data

        foreach ($days as $day) {
            $isClosed = !empty($formData["hours_{$day}_closed"]);
            $openTime = $this->wordPressManager->sanitizeTextField($formData["hours_{$day}_open"] ?? '');
            $closeTime = $this->wordPressManager->sanitizeTextField($formData["hours_{$day}_close"] ?? '');

            $dayName = ucfirst($day);

            if ($isClosed) {
                $hours[$dayName] = [
                    'closed' => true,
                ];
            } elseif (!empty($openTime) && !empty($closeTime)) {
                // Store times in 24-hour format for HTML time inputs
                $hours[$dayName] = [
                    'open' => $openTime,
                    'close' => $closeTime,
                ];
            } elseif (isset($formData["hours_{$day}_open"]) || isset($formData["hours_{$day}_close"])) {
                // If fields are explicitly set but empty, remove the day
                unset($hours[$dayName]);
            }
        }

        return $hours;
    }

    /**
     * Check if form data contains hours information
     */
    private function hasHoursData(array $formData): bool
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            if (
                isset($formData["hours_{$day}_closed"]) ||
                isset($formData["hours_{$day}_open"]) ||
                isset($formData["hours_{$day}_close"])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize rich text content (HTML)
     */
    private function sanitizeRichTextContent(string $content): string
    {
        // Remove any existing slashes that might have been added by previous saves
        $content = wp_unslash($content);

        // Allow safe HTML tags for rich text content
        $allowedTags = [
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            'span' => [
                'class' => [],
                'style' => [],
            ],
            'div' => [
                'class' => [],
                'style' => [],
            ],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'a' => [
                'href' => [],
                'target' => [],
                'rel' => [],
            ],
        ];

        return wp_kses($content, $allowedTags);
    }
}

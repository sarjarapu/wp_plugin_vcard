<?php

namespace Minisite\Domain\Services;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Minisite Form Processor
 *
 * SINGLE RESPONSIBILITY: Handle form data processing for minisites
 * - Validates form data
 * - Builds site JSON from form data
 * - Provides helper methods for form value extraction
 * - Shared between Edit and New minisite features
 */
class MinisiteFormProcessor
{
    private LoggerInterface $logger;

    public function __construct(
        private WordPressManagerInterface $wordPressManager,
        private MinisiteRepositoryInterface $minisiteRepository
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('form-processor');
    }

    /**
     * Validate form data
     */
    public function validateFormData(array $data): array
    {
        $this->logger->info('MinisiteFormProcessor::validateFormData() called', array(
            'data_count' => count($data),
            'data_keys' => array_keys($data),
        ));

        $errors = array();

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
    public function getFormValue(
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
    public function getFormValueFromObject(
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
    public function buildSiteJsonFromForm(array $formData, string $siteId, ?object $minisite = null): array
    {
        $this->logger->info('MinisiteFormProcessor::buildSiteJsonFromForm() called', array(
            'site_id' => $siteId,
            'form_data_count' => count($formData),
            'has_existing_minisite' => $minisite !== null,
            'minisite_id' => $minisite?->id ?? 'new',
        ));

        // DEBUG: Log actual form data values for key fields
        $this->logger->debug('Form data values for key fields', array(
            'site_id' => $siteId,
            'seo_title' => $formData['seo_title'] ?? 'NOT_SET',
            'seo_description' => $formData['seo_description'] ?? 'NOT_SET',
            'brand_name' => $formData['brand_name'] ?? 'NOT_SET',
            'brand_logo' => $formData['brand_logo'] ?? 'NOT_SET',
            'brand_industry' => $formData['brand_industry'] ?? 'NOT_SET',
            'brand_palette' => $formData['brand_palette'] ?? 'NOT_SET',
            'hero_heading' => $formData['hero_heading'] ?? 'NOT_SET',
            'hero_subheading' => $formData['hero_subheading'] ?? 'NOT_SET',
            'about_html' => $formData['about_html'] ?? 'NOT_SET',
            'business_name' => $formData['business_name'] ?? 'NOT_SET',
            'business_city' => $formData['business_city'] ?? 'NOT_SET',
        ));

        // Get existing siteJson to preserve all data
        if (! $minisite) {
            $this->logger->debug('No existing minisite provided, fetching from database', array(
                'site_id' => $siteId,
            ));
            $minisite = $this->minisiteRepository->findById($siteId);
        }

        // For new minisites (no existing data), start with empty structure
        // For existing minisites, preserve existing data
        if (! $minisite || ! $minisite->siteJson) {
            $this->logger->debug('No existing minisite or siteJson found, starting with empty structure', array(
                'site_id' => $siteId,
                'has_minisite' => $minisite !== null,
                'has_site_json' => $minisite && $minisite->siteJson ? true : false,
            ));
            $siteJson = $this->buildEmptySiteJson();
        } else {
            $this->logger->debug('Using existing siteJson as base', array(
                'site_id' => $siteId,
                'existing_site_json_size' => strlen(json_encode($minisite->siteJson)),
            ));
            $siteJson = $minisite->siteJson;
        }

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

        $this->logger->info('Site JSON build completed', array(
            'site_id' => $siteId,
            'final_site_json_size' => strlen(json_encode($siteJson)),
            'sections_built' => array_keys($siteJson),
        ));

        // DEBUG: Log the actual siteJson content for key sections
        $this->logger->debug('Final siteJson content for key sections', array(
            'site_id' => $siteId,
            'seo_section' => $siteJson['seo'] ?? 'NOT_SET',
            'brand_section' => $siteJson['brand'] ?? 'NOT_SET',
            'hero_section' => $siteJson['hero'] ?? 'NOT_SET',
            'about_section' => $siteJson['about'] ?? 'NOT_SET',
        ));

        return $siteJson;
    }

    /**
     * Build empty site JSON structure for new minisites
     */
    public function buildEmptySiteJson(): array
    {
        return array(
            'seo' => array(
                'title' => '',
                'description' => '',
                'keywords' => '',
                'favicon' => '',
            ),
            'brand' => array(
                'name' => '',
                'logo' => '',
                'industry' => '',
                'palette' => 'blue',
            ),
            'hero' => array(
                'badge' => '',
                'heading' => '',
                'subheading' => '',
                'image' => '',
                'imageAlt' => '',
                'ctas' => array(
                    array(
                        'text' => '',
                        'url' => '',
                    ),
                    array(
                        'text' => '',
                        'url' => '',
                    ),
                ),
                'rating' => array(
                    'value' => '',
                    'count' => '',
                ),
            ),
            'about' => array(
                'html' => '',
            ),
            'contact' => array(
                'phone' => array(
                    'text' => '',
                    'link' => '',
                ),
                'whatsapp' => array(
                    'text' => '',
                    'link' => '',
                ),
                'email' => '',
                'website' => array(
                    'text' => '',
                    'link' => '',
                ),
                'city' => '',
                'region' => '',
                'country' => '',
                'postal' => '',
                'lat' => null,
                'lng' => null,
            ),
            'services' => array(
                'title' => 'Services',
                'listing' => array(),
            ),
            'social' => array(
                'facebook' => '',
                'instagram' => '',
                'x' => '',
                'youtube' => '',
                'linkedin' => '',
            ),
            'gallery' => array(),
        );
    }

    /**
     * Build business section from form data
     */
    private function buildBusinessSection(array $formData, array $siteJson): array
    {
        // Always build the business section structure
        $siteJson['business'] = array_merge($siteJson['business'] ?? array(), array(
            'name' => $this->getFormValue($formData, $siteJson['business'] ?? array(), 'business_name', 'name'),
            'city' => $this->getFormValue($formData, $siteJson['business'] ?? array(), 'business_city', 'city'),
            'region' => $this->getFormValue($formData, $siteJson['business'] ?? array(), 'business_region', 'region'),
            'country' => $this->getFormValue(
                $formData,
                $siteJson['business'] ?? array(),
                'business_country',
                'country'
            ),
            'postal' => $this->getFormValue($formData, $siteJson['business'] ?? array(), 'business_postal', 'postal'),
        ));

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
            $siteJson['contact'] = array_merge($siteJson['contact'] ?? array(), array(
                'phone' => array(
                    'text' => $this->getFormValue(
                        $formData,
                        $siteJson['contact']['phone'] ?? array(),
                        'contact_phone_text',
                        'text'
                    ),
                    'link' => $this->getFormValue(
                        $formData,
                        $siteJson['contact']['phone'] ?? array(),
                        'contact_phone_link',
                        'link'
                    ),
                ),
                'whatsapp' => array(
                    'text' => $this->getFormValue(
                        $formData,
                        $siteJson['contact']['whatsapp'] ?? array(),
                        'contact_whatsapp_text',
                        'text'
                    ),
                    'link' => $this->getFormValue(
                        $formData,
                        $siteJson['contact']['whatsapp'] ?? array(),
                        'contact_whatsapp_link',
                        'link'
                    ),
                ),
                'email' => $this->wordPressManager->sanitizeEmail(
                    $formData['contact_email'] ?? ($siteJson['contact']['email'] ?? '')
                ),
                'website' => array(
                    'text' => $this->getFormValue(
                        $formData,
                        $siteJson['contact']['website'] ?? array(),
                        'contact_website_text',
                        'text'
                    ),
                    'link' => $this->wordPressManager->sanitizeUrl(
                        $formData['contact_website_link'] ?? ($siteJson['contact']['website']['link'] ?? '')
                    ),
                ),
                'address_line1' => $this->getFormValue(
                    $formData,
                    $siteJson['contact'] ?? array(),
                    'contact_address1',
                    'address_line1'
                ),
                'address_line2' => $this->getFormValue(
                    $formData,
                    $siteJson['contact'] ?? array(),
                    'contact_address2',
                    'address_line2'
                ),
                'address_line3' => $this->getFormValue(
                    $formData,
                    $siteJson['contact'] ?? array(),
                    'contact_address3',
                    'address_line3'
                ),
                'address_line4' => $this->getFormValue(
                    $formData,
                    $siteJson['contact'] ?? array(),
                    'contact_address4',
                    'address_line4'
                ),
                'plusCode' => $this->getFormValue(
                    $formData,
                    $siteJson['contact'] ?? array(),
                    'contact_pluscode',
                    'plusCode'
                ),
                'plusCodeUrl' => $this->wordPressManager->sanitizeUrl(
                    $formData['contact_pluscode_url'] ?? ($siteJson['contact']['plusCodeUrl'] ?? '')
                ),
                'lat' => ! empty($formData['contact_lat']) ? (float) $formData['contact_lat'] :
                    ($siteJson['contact']['lat'] ?? null),
                'lng' => ! empty($formData['contact_lng']) ? (float) $formData['contact_lng'] :
                    ($siteJson['contact']['lng'] ?? null),
                'hours' => $this->buildHoursFromForm($formData, $siteJson['contact']['hours'] ?? array()),
            ));
        }

        return $siteJson;
    }

    /**
     * Build brand section from form data
     */
    private function buildBrandSection(array $formData, array $siteJson): array
    {
        // Always build the brand section structure
        $siteJson['brand'] = array_merge($siteJson['brand'] ?? array(), array(
            'name' => $this->getFormValue($formData, $siteJson['brand'] ?? array(), 'brand_name', 'name'),
            'logo' => $this->getFormValue($formData, $siteJson['brand'] ?? array(), 'brand_logo', 'logo'),
            'palette' => $this->getFormValue(
                $formData,
                $siteJson['brand'] ?? array(),
                'brand_palette',
                'palette',
                'blue'
            ),
            'industry' => $this->getFormValue($formData, $siteJson['brand'] ?? array(), 'brand_industry', 'industry'),
        ));

        return $siteJson;
    }

    /**
     * Build SEO section from form data
     */
    private function buildSeoSection(array $formData, array $siteJson): array
    {
        // Always build the SEO section structure
        $siteJson['seo'] = array_merge($siteJson['seo'] ?? array(), array(
            'title' => $this->getFormValue($formData, $siteJson['seo'] ?? array(), 'seo_title', 'title'),
            'description' => $this->getFormValue(
                $formData,
                $siteJson['seo'] ?? array(),
                'seo_description',
                'description'
            ),
            'keywords' => $this->getFormValue($formData, $siteJson['seo'] ?? array(), 'seo_keywords', 'keywords'),
            'favicon' => $this->getFormValue($formData, $siteJson['seo'] ?? array(), 'seo_favicon', 'favicon'),
            'search_terms' => $this->getFormValue(
                $formData,
                $siteJson['seo'] ?? array(),
                'search_terms',
                'search_terms'
            ),
        ));

        return $siteJson;
    }

    /**
     * Build settings section from form data
     */
    private function buildSettingsSection(array $formData, array $siteJson): array
    {
        // Always build the settings section structure
        $siteJson['settings'] = array_merge($siteJson['settings'] ?? array(), array(
            'template' => $this->getFormValue(
                $formData,
                $siteJson['settings'] ?? array(),
                'site_template',
                'template'
            ),
            'locale' => $this->getFormValue(
                $formData,
                $siteJson['settings'] ?? array(),
                'default_locale',
                'locale'
            ),
        ));

        return $siteJson;
    }

    /**
     * Build hero section from form data
     */
    private function buildHeroSection(array $formData, array $siteJson): array
    {
        // Always build the hero section structure
        $siteJson['hero'] = array_merge($siteJson['hero'] ?? array(), array(
            'badge' => $this->getFormValue($formData, $siteJson['hero'] ?? array(), 'hero_badge', 'badge'),
            'heading' => $this->getFormValue($formData, $siteJson['hero'] ?? array(), 'hero_heading', 'heading'),
            'subheading' => $this->sanitizeRichTextContent(
                $formData['hero_subheading'] ?? ($siteJson['hero']['subheading'] ?? '')
            ),
            'image' => $this->wordPressManager->sanitizeUrl(
                $formData['hero_image'] ?? ($siteJson['hero']['image'] ?? '')
            ),
            'imageAlt' => $this->getFormValue($formData, $siteJson['hero'] ?? array(), 'hero_image_alt', 'imageAlt'),
            'ctas' => array(
                array(
                    'text' => $this->getFormValue(
                        $formData,
                        $siteJson['hero']['ctas'][0] ?? array(),
                        'hero_cta1_text',
                        'text'
                    ),
                    'url' => $this->wordPressManager->sanitizeUrl(
                        $formData['hero_cta1_url'] ?? ($siteJson['hero']['ctas'][0]['url'] ?? '')
                    ),
                ),
                array(
                    'text' => $this->getFormValue(
                        $formData,
                        $siteJson['hero']['ctas'][1] ?? array(),
                        'hero_cta2_text',
                        'text'
                    ),
                    'url' => $this->wordPressManager->sanitizeUrl(
                        $formData['hero_cta2_url'] ?? ($siteJson['hero']['ctas'][1]['url'] ?? '')
                    ),
                ),
            ),
            'rating' => array(
                'value' => $this->getFormValue(
                    $formData,
                    $siteJson['hero']['rating'] ?? array(),
                    'hero_rating_value',
                    'value'
                ),
                'count' => $this->getFormValue(
                    $formData,
                    $siteJson['hero']['rating'] ?? array(),
                    'hero_rating_count',
                    'count'
                ),
            ),
        ));

        return $siteJson;
    }

    /**
     * Build about section from form data
     */
    private function buildAboutSection(array $formData, array $siteJson): array
    {
        // Always build the about section structure
        $siteJson['about'] = array_merge($siteJson['about'] ?? array(), array(
            'html' => $this->sanitizeRichTextContent(
                $formData['about_html'] ?? ($siteJson['about']['html'] ?? '')
            ),
        ));

        return $siteJson;
    }

    /**
     * Build whyUs section from form data
     */
    private function buildWhyUsSection(array $formData, array $siteJson): array
    {
        // Always build the whyUs section structure
        $siteJson['whyUs'] = array_merge($siteJson['whyUs'] ?? array(), array(
            'title' => $this->getFormValue($formData, $siteJson['whyUs'] ?? array(), 'whyus_title', 'title'),
            'html' => $this->sanitizeRichTextContent(
                $formData['whyus_html'] ?? ($siteJson['whyUs']['html'] ?? '')
            ),
            'image' => $this->wordPressManager->sanitizeUrl(
                $formData['whyus_image'] ?? ($siteJson['whyUs']['image'] ?? '')
            ),
        ));

        return $siteJson;
    }

    /**
     * Build services section from form data
     */
    private function buildServicesSection(array $formData, array $siteJson): array
    {
        // Always build the services section structure
        $siteJson['services'] = $this->buildServicesFromForm($formData, $siteJson['services'] ?? array());

        return $siteJson;
    }

    /**
     * Build gallery section from form data
     */
    private function buildGallerySection(array $formData, array $siteJson): array
    {
        // Always build the gallery section structure
        $siteJson['gallery'] = $this->buildGalleryFromForm($formData);

        return $siteJson;
    }

    /**
     * Build social section from form data
     */
    private function buildSocialSection(array $formData, array $siteJson): array
    {
        // Always build the social section structure
        $siteJson['social'] = $this->buildSocialFromForm($formData, $siteJson['social'] ?? array());

        return $siteJson;
    }

    /**
     * Build services section from form data
     */
    private function buildServicesFromForm(array $formData, array $existingServices = array()): array
    {
        $services = array();
        $serviceCount = (int) ($formData['product_count'] ?? 0);

        for ($i = 0; $i < $serviceCount; $i++) {
            $services[] = array(
                'title' => $this->wordPressManager->sanitizeTextField($formData["product_{$i}_title"] ?? ''),
                'image' => $this->wordPressManager->sanitizeUrl($formData["product_{$i}_image"] ?? ''),
                'description' => $this->sanitizeRichTextContent($formData["product_{$i}_description"] ?? ''),
                'price' => $this->wordPressManager->sanitizeTextField($formData["product_{$i}_price"] ?? ''),
                'icon' => $this->wordPressManager->sanitizeTextField($formData["product_{$i}_icon"] ?? ''),
                'cta' => $this->wordPressManager->sanitizeTextField($formData["product_{$i}_cta_text"] ?? ''),
                'url' => $this->wordPressManager->sanitizeUrl($formData["product_{$i}_cta_url"] ?? ''),
            );
        }

        return array(
            'title' => $this->wordPressManager->sanitizeTextField(
                $formData['products_section_title'] ?? ($existingServices['title'] ?? 'Products & Services')
            ),
            'listing' => $services,
        );
    }

    /**
     * Build gallery section from form data
     */
    private function buildGalleryFromForm(array $formData): array
    {
        $gallery = array();
        $imageCount = (int) ($formData['gallery_count'] ?? 0);

        for ($i = 0; $i < $imageCount; $i++) {
            $imageUrl = $this->wordPressManager->sanitizeUrl($formData["gallery_{$i}_image"] ?? '');
            $imageAlt = $this->wordPressManager->sanitizeTextField($formData["gallery_{$i}_alt"] ?? '');
            if (! empty($imageUrl)) {
                $gallery[] = array(
                    'src' => $imageUrl,
                    'alt' => $imageAlt,
                    'caption' => $imageAlt, // Use alt as caption fallback
                );
            }
        }

        return $gallery;
    }

    /**
     * Build social section from form data
     */
    private function buildSocialFromForm(array $formData, array $existingSocial = array()): array
    {
        $networks = array('facebook', 'instagram', 'x', 'youtube', 'linkedin', 'tiktok');
        $social = $existingSocial; // Start with existing data

        foreach ($networks as $network) {
            $url = $this->wordPressManager->sanitizeUrl($formData["social_{$network}"] ?? '');
            if (! empty($url)) {
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
    private function buildHoursFromForm(array $formData, array $existingHours = array()): array
    {
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $hours = $existingHours; // Start with existing data

        foreach ($days as $day) {
            $isClosed = ! empty($formData["hours_{$day}_closed"]);
            $openTime = $this->wordPressManager->sanitizeTextField($formData["hours_{$day}_open"] ?? '');
            $closeTime = $this->wordPressManager->sanitizeTextField($formData["hours_{$day}_close"] ?? '');

            $dayName = ucfirst($day);

            if ($isClosed) {
                $hours[$dayName] = array(
                    'closed' => true,
                );
            } elseif (! empty($openTime) && ! empty($closeTime)) {
                // Store times in 24-hour format for HTML time inputs
                $hours[$dayName] = array(
                    'open' => $openTime,
                    'close' => $closeTime,
                );
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
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');

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
        $allowedTags = array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'b' => array(),
            'em' => array(),
            'i' => array(),
            'u' => array(),
            'span' => array(
                'class' => array(),
                'style' => array(),
            ),
            'div' => array(
                'class' => array(),
                'style' => array(),
            ),
            'h1' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'a' => array(
                'href' => array(),
                'target' => array(),
                'rel' => array(),
            ),
        );

        return wp_kses($content, $allowedTags);
    }
}

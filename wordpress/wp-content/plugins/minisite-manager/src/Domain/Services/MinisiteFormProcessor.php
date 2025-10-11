<?php

namespace Minisite\Domain\Services;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
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
        private WordPressManagerInterface $wordPressManager
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('form-processor');
    }

    /**
     * Validate form data
     */
    public function validateFormData(array $data): array
    {
        $this->logger->info('MinisiteFormProcessor::validateFormData() called', [
            'data_count' => count($data),
            'data_keys' => array_keys($data)
        ]);
        
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
        $this->logger->info('MinisiteFormProcessor::buildSiteJsonFromForm() called', [
            'site_id' => $siteId,
            'form_data_count' => count($formData),
            'has_existing_minisite' => $minisite !== null,
            'minisite_id' => $minisite?->id ?? 'new'
        ]);
        
        // Get existing siteJson to preserve all data
        if (!$minisite) {
            $this->logger->debug('No existing minisite provided, fetching from database', [
                'site_id' => $siteId
            ]);
            $minisite = $this->wordPressManager->findMinisiteById($siteId);
        }
        $existingSiteJson = $minisite && $minisite->siteJson ? $minisite->siteJson : [];

        $this->logger->debug('Starting site JSON build', [
            'site_id' => $siteId,
            'has_existing_site_json' => !empty($existingSiteJson),
            'existing_site_json_size' => strlen(json_encode($existingSiteJson))
        ]);

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

        $this->logger->info('Site JSON build completed', [
            'site_id' => $siteId,
            'final_site_json_size' => strlen(json_encode($siteJson)),
            'sections_built' => array_keys($siteJson)
        ]);

        return $siteJson;
    }

    /**
     * Build empty site JSON structure for new minisites
     */
    public function buildEmptySiteJson(): array
    {
        return [
            'seo' => [
                'title' => '',
                'description' => '',
                'keywords' => '',
                'favicon' => '',
            ],
            'brand' => [
                'name' => '',
                'logo' => '',
                'industry' => '',
                'palette' => 'blue',
            ],
            'hero' => [
                'badge' => '',
                'heading' => '',
                'subheading' => '',
                'image' => '',
                'imageAlt' => '',
                'ctas' => [
                    [
                        'text' => '',
                        'url' => '',
                    ],
                    [
                        'text' => '',
                        'url' => '',
                    ],
                ],
                'rating' => [
                    'value' => '',
                    'count' => '',
                ],
            ],
            'about' => [
                'html' => '',
            ],
            'contact' => [
                'phone' => [
                    'text' => '',
                    'link' => '',
                ],
                'whatsapp' => [
                    'text' => '',
                    'link' => '',
                ],
                'email' => '',
                'website' => [
                    'text' => '',
                    'link' => '',
                ],
                'city' => '',
                'region' => '',
                'country' => '',
                'postal' => '',
                'lat' => null,
                'lng' => null,
            ],
            'services' => [
                'title' => 'Services',
                'listing' => [],
            ],
            'social' => [
                'facebook' => '',
                'instagram' => '',
                'x' => '',
                'youtube' => '',
                'linkedin' => '',
            ],
            'gallery' => [],
        ];
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

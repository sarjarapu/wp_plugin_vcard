<?php

namespace Minisite\Application\Controllers\Front;

use Minisite\Domain\Entities\Profile;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Infrastructure\Persistence\Repositories\ProfileRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;

final class NewMinisiteController
{
    public function __construct(
        private ProfileRepository $profileRepository,
        private VersionRepository $versionRepository
    ) {}

    /**
     * Handle the new minisite creation page
     */
    public function handleNew(): void
    {
        if (!is_user_logged_in()) {
            wp_redirect('/account/login');
            exit;
        }

        $currentUser = wp_get_current_user();
        
        $context = [
            'page_title' => 'Create New Minisite',
            'page_subtitle' => 'Start by providing your business and location slugs',
            'form_data' => $_POST ?? [],
            'error_msg' => $_GET['error'] ?? null,
            'success_msg' => $_GET['success'] ?? null,
            'user' => $currentUser,
        ];

        // Render the simple template
        if (class_exists('Timber\\Timber')) {
            $viewsBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
            $componentsBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/components';
            \Timber\Timber::$locations = array_values(array_unique(array_merge(\Timber\Timber::$locations ?? [], [$viewsBase, $componentsBase])));
            
            \Timber\Timber::render('account-sites-new-simple.twig', $context);
        }
    }

    /**
     * Handle the simple new minisite creation form submission
     */
    public function handleCreateSimple(): void
    {
        if (!is_user_logged_in()) {
            wp_redirect('/account/sites/new?error=' . urlencode('Not authenticated'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_redirect('/account/sites/new?error=' . urlencode('Method not allowed'));
            exit;
        }

        if (!wp_verify_nonce($_POST['minisite_nonce'] ?? '', 'minisite_create')) {
            wp_redirect('/account/sites/new?error=' . urlencode('Security check failed'));
            exit;
        }

        $currentUser = wp_get_current_user();
        
        // Sanitize and validate input
        $businessSlug = sanitize_text_field($_POST['business_slug'] ?? '');
        $locationSlug = sanitize_text_field($_POST['location_slug'] ?? '');
        
        // Validate business slug (required)
        if (empty($businessSlug)) {
            wp_redirect('/account/sites/new?error=' . urlencode('Business slug is required'));
            exit;
        }
        
        // Validate slug format
        if (!preg_match('/^[a-z0-9-]+$/', $businessSlug)) {
            wp_redirect('/account/sites/new?error=' . urlencode('Business slug can only contain lowercase letters, numbers, and hyphens'));
            exit;
        }
        
        if (!empty($locationSlug) && !preg_match('/^[a-z0-9-]+$/', $locationSlug)) {
            wp_redirect('/account/sites/new?error=' . urlencode('Location slug can only contain lowercase letters, numbers, and hyphens'));
            exit;
        }
        
        try {
            // Use database transaction to prevent race conditions
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            
            // Check if combination already exists (with row lock to prevent race conditions)
            $existingProfile = $this->profileRepository->findBySlugParams($businessSlug, $locationSlug);
            if ($existingProfile) {
                $wpdb->query('ROLLBACK');
                wp_redirect('/account/sites/new?error=' . urlencode('A minisite with this business and location combination already exists'));
                exit;
            }
            
            // Create empty site JSON structure
            $emptySiteJson = $this->getEmptySiteJson();
            
            // Create SlugPair and GeoPoint objects
            $slugs = new SlugPair($businessSlug, $locationSlug);
            $geo = new GeoPoint(0, 0); // Default coordinates
            
            // Create new profile
            $profile = new Profile(
                id: \Minisite\Domain\Services\MinisiteIdGenerator::generate(),
                slugs: $slugs,
                title: ucwords(str_replace('-', ' ', $businessSlug)), // Default title from slug
                name: ucwords(str_replace('-', ' ', $businessSlug)), // Default name from slug
                city: '',
                region: null,
                countryCode: '',
                postalCode: null,
                geo: $geo,
                siteTemplate: 'v2025',
                palette: 'blue',
                industry: '',
                defaultLocale: 'en-US',
                schemaVersion: 1,
                siteVersion: 1,
                siteJson: $emptySiteJson,
                searchTerms: null,
                status: 'draft',
                createdAt: null,
                updatedAt: null,
                publishedAt: null,
                createdBy: $currentUser->ID,
                updatedBy: $currentUser->ID,
                currentVersionId: null,
                isBookmarked: false,
                canEdit: true
            );

            // Save profile
            $savedProfile = $this->profileRepository->save($profile, 0); // 0 for new profile
            
            // Create initial version
            $version = new Version(
                id: \Minisite\Domain\Services\MinisiteIdGenerator::generate(),
                minisiteId: $savedProfile->id,
                versionNumber: 1,
                status: 'draft',
                label: 'Initial Draft',
                comment: 'Created from new minisite form',
                createdBy: $currentUser->ID,
                createdAt: null,
                publishedAt: null,
                sourceVersionId: null,
                siteJson: $emptySiteJson,
                slugs: $slugs,
                title: $savedProfile->title,
                name: $savedProfile->name,
                city: $savedProfile->city,
                region: $savedProfile->region,
                countryCode: $savedProfile->countryCode,
                postalCode: $savedProfile->postalCode,
                geo: $geo,
                siteTemplate: $savedProfile->siteTemplate,
                palette: $savedProfile->palette,
                industry: $savedProfile->industry,
                defaultLocale: $savedProfile->defaultLocale,
                schemaVersion: $savedProfile->schemaVersion,
                siteVersion: $savedProfile->siteVersion,
                searchTerms: $savedProfile->searchTerms
            );

            // Save version
            $this->versionRepository->save($version);
            
            // Commit transaction
            $wpdb->query('COMMIT');

            // Redirect to edit screen
            wp_redirect(home_url("/account/sites/edit/{$savedProfile->id}?success=" . urlencode('Minisite created successfully! You can now customize it.')));
            exit;

        } catch (\Exception $e) {
            // Rollback on error
            if (isset($wpdb)) {
                $wpdb->query('ROLLBACK');
            }
            error_log('Minisite creation error: ' . $e->getMessage());
            wp_redirect('/account/sites/new?error=' . urlencode('Failed to create minisite: ' . $e->getMessage()));
            exit;
        }
    }

    /**
     * Handle the new minisite creation form submission
     */
    public function handleCreate(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'minisite_new')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $currentUser = wp_get_current_user();

        try {
            // Generate unique slugs
            $businessSlug = $this->generateUniqueBusinessSlug($_POST['brand_name'] ?? '');
            $locationSlug = $this->generateUniqueLocationSlug($_POST['contact_city'] ?? '');
            
            if (!$businessSlug || !$locationSlug) {
                wp_send_json_error('Unable to generate unique slugs. Please try different business name or city.', 400);
                return;
            }

            $slugs = new SlugPair($businessSlug, $locationSlug);

            // Create GeoPoint if coordinates provided
            $geo = null;
            $lat = !empty($_POST['contact_lat']) ? (float) $_POST['contact_lat'] : null;
            $lng = !empty($_POST['contact_lng']) ? (float) $_POST['contact_lng'] : null;
            
            if ($lat !== null && $lng !== null) {
                $geo = new GeoPoint($lat, $lng);
            }

            // Build site JSON from form data
            $siteJson = $this->buildSiteJsonFromForm($_POST);

            // Create new profile
            $profile = new Profile(
                id: \Minisite\Domain\Services\MinisiteIdGenerator::generate(),
                slugs: $slugs,
                title: sanitize_text_field($_POST['seo_title'] ?? ''),
                name: sanitize_text_field($_POST['brand_name'] ?? ''),
                city: sanitize_text_field($_POST['contact_city'] ?? ''),
                region: sanitize_text_field($_POST['contact_region'] ?? ''),
                countryCode: sanitize_text_field($_POST['contact_country'] ?? ''),
                postalCode: sanitize_text_field($_POST['contact_postal'] ?? ''),
                geo: $geo,
                siteTemplate: 'v2025',
                palette: sanitize_text_field($_POST['brand_palette'] ?? 'blue'),
                industry: sanitize_text_field($_POST['brand_industry'] ?? ''),
                defaultLocale: 'en-US',
                schemaVersion: 1,
                siteVersion: 1,
                siteJson: $siteJson,
                searchTerms: $this->buildSearchTerms($_POST),
                status: 'draft',
                createdAt: null,
                updatedAt: null,
                publishedAt: null,
                createdBy: (int) $currentUser->ID,
                updatedBy: (int) $currentUser->ID,
                currentVersionId: null,
                isBookmarked: false,
                canEdit: true
            );

            // Save profile
            $savedProfile = $this->profileRepository->save($profile, 0);

            // Create initial version
            $version = new \Minisite\Domain\Entities\Version(
                id: \Minisite\Domain\Services\MinisiteIdGenerator::generate(),
                minisiteId: $savedProfile->id,
                versionNumber: 1,
                status: 'draft',
                label: 'Initial Draft',
                comment: 'Initial version created',
                createdBy: (int) $currentUser->ID,
                createdAt: null,
                publishedAt: null,
                sourceVersionId: null,
                siteJson: $siteJson,
                slugs: $slugs,
                title: $profile->title,
                name: $profile->name,
                city: $profile->city,
                region: $profile->region,
                countryCode: $profile->countryCode,
                postalCode: $profile->postalCode,
                geo: $geo,
                siteTemplate: $profile->siteTemplate,
                palette: $profile->palette,
                industry: $profile->industry,
                defaultLocale: $profile->defaultLocale,
                schemaVersion: $profile->schemaVersion,
                siteVersion: $profile->siteVersion,
                searchTerms: $profile->searchTerms
            );

            $savedVersion = $this->versionRepository->save($version);

            // Update profile with current version ID
            $this->profileRepository->updateCurrentVersionId($savedProfile->id, $savedVersion->id);

            wp_send_json_success([
                'message' => 'Minisite created successfully',
                'minisite_id' => $savedProfile->id,
                'version_id' => $savedVersion->id,
                'redirect_url' => "/account/sites/{$savedProfile->id}/edit"
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Failed to create minisite: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate unique business slug
     */
    private function generateUniqueBusinessSlug(string $businessName): ?string
    {
        if (empty($businessName)) {
            return null;
        }

        $baseSlug = sanitize_title($businessName);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->profileRepository->findBySlugs(new SlugPair($slug, 'temp')) !== null) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            
            if ($counter > 100) {
                return null; // Prevent infinite loop
            }
        }

        return $slug;
    }

    /**
     * Generate unique location slug
     */
    private function generateUniqueLocationSlug(string $city): ?string
    {
        if (empty($city)) {
            return 'location';
        }

        $baseSlug = sanitize_title($city);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->profileRepository->findBySlugs(new SlugPair('temp', $slug)) !== null) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            
            if ($counter > 100) {
                return null; // Prevent infinite loop
            }
        }

        return $slug;
    }

    /**
     * Build site JSON from form data
     */
    private function buildSiteJsonFromForm(array $formData): array
    {
        return [
            'seo' => [
                'title' => sanitize_text_field($formData['seo_title'] ?? ''),
                'description' => sanitize_textarea_field($formData['seo_description'] ?? ''),
                'keywords' => sanitize_text_field($formData['seo_keywords'] ?? ''),
                'favicon' => esc_url_raw($formData['seo_favicon'] ?? '')
            ],
            'brand' => [
                'name' => sanitize_text_field($formData['brand_name'] ?? ''),
                'logo' => esc_url_raw($formData['brand_logo'] ?? ''),
                'industry' => sanitize_text_field($formData['brand_industry'] ?? ''),
                'palette' => sanitize_text_field($formData['brand_palette'] ?? 'blue')
            ],
            'hero' => [
                'badge' => sanitize_text_field($formData['hero_badge'] ?? ''),
                'heading' => sanitize_text_field($formData['hero_heading'] ?? ''),
                'subheading' => wp_kses_post($formData['hero_subheading'] ?? ''),
                'image' => esc_url_raw($formData['hero_image'] ?? ''),
                'imageAlt' => sanitize_text_field($formData['hero_image_alt'] ?? ''),
                'ctas' => [
                    [
                        'text' => sanitize_text_field($formData['hero_cta1_text'] ?? ''),
                        'url' => sanitize_text_field($formData['hero_cta1_url'] ?? '')
                    ],
                    [
                        'text' => sanitize_text_field($formData['hero_cta2_text'] ?? ''),
                        'url' => sanitize_text_field($formData['hero_cta2_url'] ?? '')
                    ]
                ],
                'rating' => [
                    'value' => sanitize_text_field($formData['hero_rating_value'] ?? ''),
                    'count' => sanitize_text_field($formData['hero_rating_count'] ?? '')
                ]
            ],
            'about' => [
                'html' => wp_kses_post($formData['about_html'] ?? '')
            ],
            'contact' => [
                'phone' => [
                    'text' => sanitize_text_field($formData['contact_phone'] ?? ''),
                    'link' => sanitize_text_field($formData['contact_phone'] ?? '')
                ],
                'whatsapp' => [
                    'text' => sanitize_text_field($formData['contact_whatsapp'] ?? ''),
                    'link' => sanitize_text_field($formData['contact_whatsapp'] ?? '')
                ],
                'email' => sanitize_email($formData['contact_email'] ?? ''),
                'website' => [
                    'text' => sanitize_text_field($formData['contact_website'] ?? ''),
                    'link' => esc_url_raw($formData['contact_website'] ?? '')
                ],
                'city' => sanitize_text_field($formData['contact_city'] ?? ''),
                'region' => sanitize_text_field($formData['contact_region'] ?? ''),
                'country' => sanitize_text_field($formData['contact_country'] ?? ''),
                'postal' => sanitize_text_field($formData['contact_postal'] ?? ''),
                'lat' => !empty($formData['contact_lat']) ? (float) $formData['contact_lat'] : null,
                'lng' => !empty($formData['contact_lng']) ? (float) $formData['contact_lng'] : null
            ],
            'services' => [
                'title' => 'Services',
                'listing' => []
            ],
            'social' => [
                'facebook' => esc_url_raw($formData['social_facebook'] ?? ''),
                'instagram' => esc_url_raw($formData['social_instagram'] ?? ''),
                'x' => esc_url_raw($formData['social_twitter'] ?? ''),
                'youtube' => esc_url_raw($formData['social_youtube'] ?? ''),
                'linkedin' => esc_url_raw($formData['social_linkedin'] ?? '')
            ],
            'gallery' => []
        ];
    }

    /**
     * Build search terms from form data
     */
    private function buildSearchTerms(array $formData): string
    {
        $terms = [
            $formData['brand_name'] ?? '',
            $formData['contact_city'] ?? '',
            $formData['brand_industry'] ?? '',
            $formData['brand_palette'] ?? '',
            $formData['seo_title'] ?? ''
        ];
        
        return trim(strtolower(implode(' ', array_filter($terms))));
    }

    /**
     * Get empty site JSON structure
     */
    private function getEmptySiteJson(): array
    {
        return [
            'seo' => [
                'title' => '',
                'description' => '',
                'keywords' => '',
                'favicon' => ''
            ],
            'brand' => [
                'name' => '',
                'logo' => '',
                'industry' => '',
                'palette' => 'blue'
            ],
            'hero' => [
                'badge' => '',
                'heading' => '',
                'subheading' => '',
                'image' => '',
                'imageAlt' => '',
                'ctas' => [
                    ['text' => '', 'url' => ''],
                    ['text' => '', 'url' => '']
                ],
                'rating' => [
                    'value' => '',
                    'count' => ''
                ]
            ],
            'about' => [
                'html' => ''
            ],
            'contact' => [
                'phone' => ['text' => '', 'link' => ''],
                'whatsapp' => ['text' => '', 'link' => ''],
                'email' => '',
                'website' => ['text' => '', 'link' => ''],
                'city' => '',
                'region' => '',
                'country' => '',
                'postal' => '',
                'lat' => null,
                'lng' => null
            ],
            'services' => [
                'title' => 'Services',
                'listing' => []
            ],
            'social' => [
                'facebook' => '',
                'instagram' => '',
                'x' => '',
                'youtube' => '',
                'linkedin' => ''
            ],
            'gallery' => []
        ];
    }
}

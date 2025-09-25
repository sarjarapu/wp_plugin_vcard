<?php

namespace Minisite\Application\Controllers\Front;

use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\Entities\Version;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use Minisite\Infrastructure\Utils\ReservationCleanup;

final class NewMinisiteController
{
    public function __construct(
        private MinisiteRepository $minisiteRepository,
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
     * Handle the simple new minisite creation form submission (now creates draft)
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
        
        try {
            // Use database transaction to prevent race conditions
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            
            // Generate unique ID and temporary slug
            $minisiteId = \Minisite\Domain\Services\MinisiteIdGenerator::generate();
            $tempSlug = \Minisite\Domain\Services\MinisiteIdGenerator::generateTempSlug($minisiteId);
            
            // Generate unique draft slugs to avoid constraint violations
            $draftBusinessSlug = 'biz-' . substr($minisiteId, 0, 8);
            $draftLocationSlug = 'loc-' . substr($minisiteId, 8, 8);
            
            // Create empty site JSON structure
            $emptySiteJson = $this->getEmptySiteJson();
            
            // Create GeoPoint object (default coordinates)
            $geo = new GeoPoint(0, 0);
            
            // Create new profile with unique draft slugs
            $minisite = new Minisite(
                id: $minisiteId,
                slugs: new SlugPair($draftBusinessSlug, $draftLocationSlug), // Unique draft slugs
                title: 'Untitled Minisite',
                name: 'Untitled Minisite',
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

            // Insert new minisite
            $savedMinisite = $this->minisiteRepository->insert($minisite);
            
            // Update the minisite with the temporary slug
            $this->minisiteRepository->updateSlug($savedMinisite->id, $tempSlug);
            
            // Create initial version
            $version = new Version(
                id: null, // null for new versions - will be set by repository
                minisiteId: $savedMinisite->id, // String minisite ID
                versionNumber: 1,
                status: 'draft',
                label: 'Initial Draft',
                comment: 'Created as draft - ready for customization',
                createdBy: $currentUser->ID,
                createdAt: null,
                publishedAt: null,
                sourceVersionId: null,
                siteJson: $emptySiteJson,
                slugs: null, // No slugs for draft versions
                title: $savedMinisite->title,
                name: $savedMinisite->name,
                city: $savedMinisite->city,
                region: $savedMinisite->region,
                countryCode: $savedMinisite->countryCode,
                postalCode: $savedMinisite->postalCode,
                geo: $geo,
                siteTemplate: $savedMinisite->siteTemplate,
                palette: $savedMinisite->palette,
                industry: $savedMinisite->industry,
                defaultLocale: $savedMinisite->defaultLocale,
                schemaVersion: $savedMinisite->schemaVersion,
                siteVersion: $savedMinisite->siteVersion,
                searchTerms: $savedMinisite->searchTerms
            );

            // Save version
            $this->versionRepository->save($version);
            
            // Commit transaction
            $wpdb->query('COMMIT');

            // Redirect to edit screen
            wp_redirect(home_url("/account/sites/{$savedMinisite->id}/edit?success=" . urlencode('Draft created successfully! You can now customize it and publish when ready.')));
            exit;

        } catch (\Exception $e) {
            // Rollback on error
            if (isset($wpdb)) {
                $wpdb->query('ROLLBACK');
            }
            error_log('Draft creation error: ' . $e->getMessage());
            wp_redirect('/account/sites/new?error=' . urlencode('Failed to create draft: ' . $e->getMessage()));
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
            $minisite = new Minisite(
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
            $savedMinisite = $this->minisiteRepository->save($minisite, 0);

            // Create initial version
            $version = new \Minisite\Domain\Entities\Version(
                id: \Minisite\Domain\Services\MinisiteIdGenerator::generate(),
                minisiteId: $savedMinisite->id,
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
                title: $minisite->title,
                name: $minisite->name,
                city: $minisite->city,
                region: $minisite->region,
                countryCode: $minisite->countryCode,
                postalCode: $minisite->postalCode,
                geo: $geo,
                siteTemplate: $minisite->siteTemplate,
                palette: $minisite->palette,
                industry: $minisite->industry,
                defaultLocale: $minisite->defaultLocale,
                schemaVersion: $minisite->schemaVersion,
                siteVersion: $minisite->siteVersion,
                searchTerms: $minisite->searchTerms
            );

            $savedVersion = $this->versionRepository->save($version);

            // Update profile with current version ID
            $this->minisiteRepository->updateCurrentVersionId($savedMinisite->id, $savedVersion->id);

            wp_send_json_success([
                'message' => 'Minisite created successfully',
                'minisite_id' => $savedMinisite->id,
                'version_id' => $savedVersion->id,
                'redirect_url' => "/account/sites/{$savedMinisite->id}/edit"
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

        while ($this->minisiteRepository->findBySlugs(new SlugPair($slug, 'temp')) !== null) {
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

        while ($this->minisiteRepository->findBySlugs(new SlugPair('temp', $slug)) !== null) {
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
     * Handle slug availability checking (API endpoint)
     */
    public function handleCheckSlugAvailability(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'check_slug_availability')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $businessSlug = sanitize_text_field($_POST['business_slug'] ?? '');
        $locationSlug = sanitize_text_field($_POST['location_slug'] ?? '');

        // Validate slug format
        if (!preg_match('/^[a-z0-9-]+$/', $businessSlug)) {
            wp_send_json_error('Business slug can only contain lowercase letters, numbers, and hyphens', 400);
            return;
        }

        if (!empty($locationSlug) && !preg_match('/^[a-z0-9-]+$/', $locationSlug)) {
            wp_send_json_error('Location slug can only contain lowercase letters, numbers, and hyphens', 400);
            return;
        }

        try {
            global $wpdb;
            $reservationsTable = $wpdb->prefix . 'minisite_reservations';
            
            // Clean up expired reservations first
            ReservationCleanup::cleanupExpired();
            
            // Check if combination already exists in minisites table
            $existingMinisite = $this->minisiteRepository->findBySlugParams($businessSlug, $locationSlug);
            
            if ($existingMinisite) {
                wp_send_json_success([
                    'available' => false,
                    'message' => 'This slug combination is already taken by an existing minisite'
                ]);
                return;
            }
            
            // Check if combination is currently reserved
            $reservation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$reservationsTable} 
                 WHERE business_slug = %s AND location_slug = %s 
                 AND expires_at > NOW()",
                $businessSlug, $locationSlug
            ));
            
            if ($reservation) {
                $expiresIn = strtotime($reservation->expires_at) - time();
                $minutesLeft = max(0, ceil($expiresIn / 60));
                
                wp_send_json_success([
                    'available' => false,
                    'message' => "This slug combination is currently reserved (expires in {$minutesLeft} minutes)"
                ]);
                return;
            }
            
            // Slug combination is available
            wp_send_json_success([
                'available' => true,
                'message' => 'This slug combination is available'
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Failed to check slug availability: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle slug reservation (5-minute window for payment)
     */
    public function handleReserveSlug(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'reserve_slug')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $businessSlug = sanitize_text_field($_POST['business_slug'] ?? '');
        $locationSlug = sanitize_text_field($_POST['location_slug'] ?? '');

        // Validate slug format
        if (!preg_match('/^[a-z0-9-]+$/', $businessSlug)) {
            wp_send_json_error('Business slug can only contain lowercase letters, numbers, and hyphens', 400);
            return;
        }

        if (!empty($locationSlug) && !preg_match('/^[a-z0-9-]+$/', $locationSlug)) {
            wp_send_json_error('Location slug can only contain lowercase letters, numbers, and hyphens', 400);
            return;
        }

        try {
            global $wpdb;
            $reservationsTable = $wpdb->prefix . 'minisite_reservations';
            $userId = get_current_user_id();
            
            
            $wpdb->query('START TRANSACTION');

            // Clean up expired reservations first
            ReservationCleanup::cleanupExpired();

            // Check if combination already exists (with row lock)
            $existingMinisite = $this->minisiteRepository->findBySlugParams($businessSlug, $locationSlug);
            if ($existingMinisite) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('This slug combination is no longer available', 409);
                return;
            }

            // Check if combination is currently reserved by another user
            $existingReservation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$reservationsTable} 
                 WHERE business_slug = %s AND location_slug = %s 
                 AND expires_at > NOW() AND user_id != %d",
                $businessSlug, $locationSlug, $userId
            ));
            
            if ($existingReservation) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('This slug combination is currently reserved by another user', 409);
                return;
            }
            
            // If user already has a reservation for this slug, extend it
            $userReservation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$reservationsTable} 
                 WHERE business_slug = %s AND location_slug = %s 
                 AND user_id = %d AND expires_at > NOW()",
                $businessSlug, $locationSlug, $userId
            ));
            
            if ($userReservation) {
                // Extend existing reservation
                $newExpiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$reservationsTable} 
                     SET expires_at = %s, created_at = NOW() 
                     WHERE id = %d",
                    $newExpiresAt, $userReservation->id
                ));
                
                $wpdb->query('COMMIT');
                
                wp_send_json_success([
                    'reservation_id' => $userReservation->id,
                    'expires_at' => $newExpiresAt,
                    'expires_in_seconds' => 300,
                    'message' => 'Slug reservation extended for 5 minutes. Complete payment to secure it.'
                ]);
                return;
            }

            // Create reservation record
            $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            $userId = get_current_user_id();
            
            $result = $wpdb->insert(
                $reservationsTable,
                [
                    'business_slug' => $businessSlug,
                    'location_slug' => $locationSlug,
                    'user_id' => $userId,
                    'minisite_id' => null, // Will be set when payment completes
                    'expires_at' => $expiresAt
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to create reservation', 500);
                return;
            }
            
            $reservationId = $wpdb->insert_id;

            $wpdb->query('COMMIT');

            wp_send_json_success([
                'reservation_id' => $reservationId,
                'expires_at' => $expiresAt,
                'expires_in_seconds' => 300, // 5 minutes
                'message' => 'Slug reserved for 5 minutes. Complete payment to secure it.'
            ]);

        } catch (\Exception $e) {
            if (isset($wpdb)) {
                $wpdb->query('ROLLBACK');
            }
            wp_send_json_error('Failed to reserve slug: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle publishing (payment completion and slug migration)
     */
    public function handlePublish(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'publish_minisite')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $minisiteId = sanitize_text_field($_POST['minisite_id'] ?? '');
        $businessSlug = sanitize_text_field($_POST['business_slug'] ?? '');
        $locationSlug = sanitize_text_field($_POST['location_slug'] ?? '');
        $paymentReference = sanitize_text_field($_POST['payment_reference'] ?? '');

        if (empty($minisiteId) || empty($businessSlug) || empty($paymentReference)) {
            wp_send_json_error('Missing required fields', 400);
            return;
        }

        try {
            global $wpdb;
            $wpdb->query('START TRANSACTION');

            // Get the draft minisite
            $draftMinisite = $this->minisiteRepository->findById($minisiteId);
            if (!$draftMinisite) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Draft minisite not found', 404);
                return;
            }

            // Check if user owns this draft
            if ($draftMinisite->createdBy !== get_current_user_id()) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Unauthorized', 403);
                return;
            }

            // Check if slug combination is still available
            $existingMinisite = $this->minisiteRepository->findBySlugParams($businessSlug, $locationSlug);
            if ($existingMinisite && $existingMinisite->id !== $minisiteId) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('This slug combination is no longer available', 409);
                return;
            }

            // Update minisite with permanent slugs
            $this->minisiteRepository->updateSlugs($minisiteId, $businessSlug, $locationSlug);
            $this->minisiteRepository->updatePublishStatus($minisiteId, 'published');

            // Clean up any existing reservation for this slug combination
            $reservationsTable = $wpdb->prefix . 'minisite_reservations';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$reservationsTable} 
                 WHERE business_slug = %s AND location_slug = %s",
                $businessSlug, $locationSlug
            ));

            // Create payment record
            $paymentId = $this->createPaymentRecord($minisiteId, get_current_user_id(), $paymentReference);

            // Create payment history record
            $this->createPaymentHistoryRecord($minisiteId, $paymentId, 'initial_payment', $paymentReference);

            $wpdb->query('COMMIT');

            wp_send_json_success([
                'message' => 'Minisite published successfully!',
                'minisite_id' => $minisiteId,
                'payment_id' => $paymentId,
                'redirect_url' => "/account/sites/{$minisiteId}"
            ]);

        } catch (\Exception $e) {
            if (isset($wpdb)) {
                $wpdb->query('ROLLBACK');
            }
            wp_send_json_error('Failed to publish minisite: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create payment record
     */
    private function createPaymentRecord(string $minisiteId, int $userId, string $paymentReference): int
    {
        global $wpdb;
        
        $paidAt = current_time('mysql');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 year'));
        $gracePeriodEndsAt = date('Y-m-d H:i:s', strtotime('+1 year +1 month'));

        $wpdb->insert(
            $wpdb->prefix . 'minisite_payments',
            [
                'minisite_id' => $minisiteId,
                'user_id' => $userId,
                'status' => 'active',
                'amount' => 99.00, // TODO: Make this configurable
                'currency' => 'USD',
                'payment_method' => 'stripe', // TODO: Make this configurable
                'payment_reference' => $paymentReference,
                'paid_at' => $paidAt,
                'expires_at' => $expiresAt,
                'grace_period_ends_at' => $gracePeriodEndsAt,
                'renewed_at' => null,
                'reclaimed_at' => null
            ],
            [
                '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Handle WooCommerce order creation for minisite subscription
     */
    public function handleCreateWooCommerceOrder(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'create_minisite_order')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $minisiteId = sanitize_text_field($_POST['minisite_id'] ?? '');
        $businessSlug = sanitize_text_field($_POST['business_slug'] ?? '');
        $locationSlug = sanitize_text_field($_POST['location_slug'] ?? '');
        $reservationId = sanitize_text_field($_POST['reservation_id'] ?? '');

        if (empty($minisiteId) || empty($businessSlug) || empty($reservationId)) {
            wp_send_json_error('Missing required fields', 400);
            return;
        }

        try {
            // Check if WooCommerce is active
            if (!class_exists('WooCommerce')) {
                wp_send_json_error('WooCommerce is not active', 500);
                return;
            }

            // Find the minisite subscription product by SKU
            $productId = wc_get_product_id_by_sku('NMS001');
            if (!$productId) {
                wp_send_json_error('Minisite subscription product (SKU: NMS001) not found', 500);
                return;
            }

            // Add product to cart instead of creating order directly
            $product = wc_get_product($productId);
            
            // Clear any existing cart items to avoid confusion
            WC()->cart->empty_cart();
            
            // Add the subscription product to cart
            $cartItemKey = WC()->cart->add_to_cart($productId, 1);
            
            if (!$cartItemKey) {
                wp_send_json_error('Failed to add product to cart', 500);
                return;
            }
            
            // Add minisite-specific meta data to cart item
            WC()->cart->cart_contents[$cartItemKey]['minisite_id'] = $minisiteId;
            WC()->cart->cart_contents[$cartItemKey]['minisite_slug'] = $businessSlug . '/' . $locationSlug;
            WC()->cart->cart_contents[$cartItemKey]['minisite_reservation_id'] = $reservationId;
            
            // Also store in session for later retrieval
            WC()->session->set('minisite_cart_data', [
                'minisite_id' => $minisiteId,
                'minisite_slug' => $businessSlug . '/' . $locationSlug,
                'minisite_reservation_id' => $reservationId
            ]);
            
            // Save cart
            WC()->cart->set_session();
            
            // Get cart URL instead of checkout URL
            $cartUrl = wc_get_cart_url();

            wp_send_json_success([
                'cart_url' => $cartUrl,
                'message' => 'Product added to cart successfully. Redirecting to cart...'
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle minisite subscription activation after payment
     */
    public function handleActivateSubscription(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        $orderId = intval($_POST['order_id'] ?? 0);
        if (!$orderId) {
            wp_send_json_error('Order ID required', 400);
            return;
        }

        try {
            $this->activateMinisiteSubscription($orderId);
            wp_send_json_success(['message' => 'Subscription activated successfully']);
        } catch (\Exception $e) {
            wp_send_json_error('Failed to activate subscription: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activate minisite subscription from WooCommerce order
     */
    public function activateMinisiteSubscription(int $orderId): void
    {
        global $wpdb;

        $order = wc_get_order($orderId);
        if (!$order) {
            throw new \Exception('Order not found');
        }

        // Get minisite data from order meta (fallback) or session
        $minisiteId = $order->get_meta('_minisite_id');
        $slug = $order->get_meta('_slug');
        $reservationId = $order->get_meta('_reservation_id');
        
        // If not found in order meta, try to get from session (cart-based flow)
        if (!$minisiteId && WC()->session) {
            $cartData = WC()->session->get('minisite_cart_data');
            if ($cartData) {
                $minisiteId = $cartData['minisite_id'] ?? '';
                $slug = $cartData['minisite_slug'] ?? '';
                $reservationId = $cartData['minisite_reservation_id'] ?? '';
                
                // Clear session data after use
                WC()->session->set('minisite_cart_data', null);
            }
        }

        if (!$minisiteId) {
            throw new \Exception('No minisite ID found in order or session');
        }

        // Parse slug
        $slugParts = explode('/', $slug);
        $businessSlug = $slugParts[0] ?? '';
        $locationSlug = $slugParts[1] ?? '';

        if (!$businessSlug) {
            throw new \Exception('Invalid slug format');
        }

        $wpdb->query('START TRANSACTION');

        try {
            // Get current expiration date (if any)
            $currentExpiration = $wpdb->get_var($wpdb->prepare(
                "SELECT expires_at FROM {$wpdb->prefix}minisite_payments 
                 WHERE minisite_id = %s AND status = 'active' 
                 ORDER BY expires_at DESC LIMIT 1",
                $minisiteId
            ));

            // Calculate new expiration date using WordPress current_time()
            $baseDate = $currentExpiration ?: current_time('mysql');
            $newExpiration = date('Y-m-d H:i:s', strtotime($baseDate . ' +12 months'));
            $gracePeriodEnds = date('Y-m-d H:i:s', strtotime($newExpiration . ' +1 month'));

            // Update minisite with permanent slugs
            $this->minisiteRepository->updateSlugs($minisiteId, $businessSlug, $locationSlug);
            $this->minisiteRepository->updatePublishStatus($minisiteId, 'published');

            // Create payment record
            $paymentId = $wpdb->insert(
                $wpdb->prefix . 'minisite_payments',
                [
                    'minisite_id' => $minisiteId,
                    'woocommerce_order_id' => $orderId,
                    'status' => 'active',
                    'amount' => $order->get_total(),
                    'currency' => $order->get_currency(),
                    'payment_method' => 'upi_qr_code',
                    'payment_reference' => $order->get_transaction_id() ?: 'order_' . $orderId,
                    'paid_at' => current_time('mysql'),
                    'expires_at' => $newExpiration,
                    'grace_period_ends_at' => $gracePeriodEnds,
                    'renewed_at' => null,
                    'reclaimed_at' => null
                ],
                [
                    '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );

            if ($paymentId === false) {
                throw new \Exception('Failed to create payment record');
            }

            $paymentId = $wpdb->insert_id;

            // Create payment history record
            $this->createPaymentHistoryRecord($minisiteId, $paymentId, 'initial_payment', 'order_' . $orderId);

            // Clean up reservation
            if ($reservationId) {
                $reservationsTable = $wpdb->prefix . 'minisite_reservations';
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$reservationsTable} WHERE id = %s",
                    $reservationId
                ));
            }

            $wpdb->query('COMMIT');

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Create payment history record
     */
    private function createPaymentHistoryRecord(string $minisiteId, int $paymentId, string $action, string $paymentReference): void
    {
        global $wpdb;
        
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 year'));
        $gracePeriodEndsAt = date('Y-m-d H:i:s', strtotime('+1 year +1 month'));

        $wpdb->insert(
            $wpdb->prefix . 'minisite_payment_history',
            [
                'minisite_id' => $minisiteId,
                'payment_id' => $paymentId,
                'action' => $action,
                'amount' => 99.00, // TODO: Make this configurable
                'currency' => 'USD',
                'payment_reference' => $paymentReference,
                'expires_at' => $expiresAt,
                'grace_period_ends_at' => $gracePeriodEndsAt,
                'new_owner_user_id' => null
            ],
            [
                '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%d'
            ]
        );
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

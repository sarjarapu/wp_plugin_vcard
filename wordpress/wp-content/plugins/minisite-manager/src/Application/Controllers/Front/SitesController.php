<?php
namespace Minisite\Application\Controllers\Front;

use Minisite\Infrastructure\Persistence\Repositories\ProfileRepository;

final class SitesController
{
    public function __construct(private ?object $renderer = null) {}

    public function handleList(): void
    {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/account/login?redirect_to=' . urlencode($_SERVER['REQUEST_URI'])));
            exit;
        }

        $currentUser = wp_get_current_user();

        global $wpdb;
        $repo = new ProfileRepository($wpdb);

        // TODO: add pagination and filters
        $sites = $repo->listByOwner((int) $currentUser->ID, 50, 0);

        $items = array_map(function ($p) {
            // Derive presentational fields for v1
            $route = home_url('/b/' . rawurlencode($p->slugs->business) . '/' . rawurlencode($p->slugs->location));
            $statusChip = $p->status === 'published' ? 'Published' : 'Draft';
            return [
                'id' => $p->id,
                'title' => $p->title ?: $p->name,
                'name' => $p->name,
                'slugs' => [
                    'business' => $p->slugs->business,
                    'location' => $p->slugs->location,
                ],
                'route' => $route,
                'location' => trim($p->city . (isset($p->region) && $p->region ? ', ' . $p->region : '') . ', ' . $p->countryCode, ', '),
                'status' => $p->status,
                'status_chip' => $statusChip,
                'updated_at' => $p->updatedAt ? $p->updatedAt->format('Y-m-d H:i') : null,
                'published_at' => $p->publishedAt ? $p->publishedAt->format('Y-m-d H:i') : null,
                // TODO: real subscription and online flags
                'subscription' => 'Unknown',
                'online' => 'Unknown',
            ];
        }, $sites);

        // Render via Timber directly for auth pages, keeping consistency with other account views
        if (class_exists('Timber\\Timber')) {
            $base = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
            \Timber\Timber::$locations = array_values(array_unique(array_merge(\Timber\Timber::$locations ?? [], [$base])));

            \Timber\Timber::render('account-sites.twig', [
                'page_title' => 'My Minisites',
                'sites' => $items,
                'can_create' => current_user_can(MINISITE_CAP_CREATE),
            ]);
            return;
        }

        // Fallback minimal HTML
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><h1>My Minisites</h1>';
        foreach ($items as $it) {
            echo '<div><a href="' . htmlspecialchars($it['route']) . '">' . htmlspecialchars($it['title']) . '</a> — ' . htmlspecialchars($it['status_chip']) . '</div>';
        }
    }

    public function handleEdit(): void
    {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/account/login?redirect_to=' . urlencode($_SERVER['REQUEST_URI'])));
            exit;
        }

        $siteId = (int) get_query_var('minisite_site_id');
        if (!$siteId) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        $currentUser = wp_get_current_user();
        global $wpdb;
        $repo = new ProfileRepository($wpdb);
        
        $profile = $repo->findById($siteId);
        if (!$profile) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        // Check ownership (v1: using created_by as owner surrogate)
        if ($profile->createdBy !== (int) $currentUser->ID) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        $error_msg = '';
        $success_msg = '';

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['minisite_edit_nonce'])) {
            if (!wp_verify_nonce($_POST['minisite_edit_nonce'], 'minisite_edit')) {
                $error_msg = 'Security check failed. Please try again.';
            } else {
                try {
                    // Build siteJson from form data
                    $siteJson = $this->buildSiteJsonFromForm($_POST);
                    
                    // Update the profile
                    $updatedProfile = $repo->updateSiteJson($siteId, $siteJson, (int) $currentUser->ID);
                    $success_msg = 'Changes saved successfully!';
                    
                    // Refresh profile data
                    $profile = $updatedProfile;
                } catch (\Exception $e) {
                    $error_msg = 'Failed to save changes: ' . $e->getMessage();
                }
            }
        }

        // Render edit form
        if (class_exists('Timber\\Timber')) {
            $base = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
            \Timber\Timber::$locations = array_values(array_unique(array_merge(\Timber\Timber::$locations ?? [], [$base])));

            \Timber\Timber::render('account-sites-edit.twig', [
                'page_title' => 'Edit: ' . $profile->title,
                'profile' => $profile,
                'site_json' => $profile->siteJson,
                'error_msg' => $error_msg,
                'success_msg' => $success_msg,
                'preview_url' => home_url('/account/sites/' . $siteId . '/preview/current'),
            ]);
            return;
        }

        // Fallback
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><h1>Edit: ' . htmlspecialchars($profile->title) . '</h1>';
        echo '<p>Edit form not available (Timber required).</p>';
    }

    public function handlePreview(): void
    {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/account/login?redirect_to=' . urlencode($_SERVER['REQUEST_URI'])));
            exit;
        }

        $siteId = (int) get_query_var('minisite_site_id');
        $versionId = get_query_var('minisite_version_id');
        
        if (!$siteId) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        $currentUser = wp_get_current_user();
        global $wpdb;
        $repo = new ProfileRepository($wpdb);
        
        $profile = $repo->findById($siteId);
        if (!$profile) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        // Check ownership (v1: using created_by as owner surrogate)
        if ($profile->createdBy !== (int) $currentUser->ID) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        // TODO: Handle version-specific preview when versions table is implemented
        // For now, always show current siteJson data
        
        // Use the existing TimberRenderer to render the profile
        if (class_exists('Timber\\Timber') && $this->renderer) {
            $this->renderer->render($profile);
            return;
        }

        // Fallback: render using existing profile template
        if (class_exists('Timber\\Timber')) {
            $base = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/v2025';
            \Timber\Timber::$locations = array_values(array_unique(array_merge(\Timber\Timber::$locations ?? [], [$base])));
            
            \Timber\Timber::render('profile.twig', [
                'profile' => $profile,
            ]);
            return;
        }

        // Final fallback
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><h1>Preview: ' . htmlspecialchars($profile->title) . '</h1>';
        echo '<p>Preview not available (Timber required).</p>';
    }

    private function buildSiteJsonFromForm(array $postData): array
    {
        // Build siteJson structure from form data
        return [
            'seo' => [
                'title' => sanitize_text_field($postData['seo_title'] ?? ''),
                'description' => sanitize_textarea_field($postData['seo_description'] ?? ''),
                'keywords' => sanitize_text_field($postData['seo_keywords'] ?? ''),
                'favicon' => esc_url_raw($postData['seo_favicon'] ?? ''),
            ],
            'brand' => [
                'logo' => esc_url_raw($postData['brand_logo'] ?? ''),
                'industry' => sanitize_text_field($postData['brand_industry'] ?? ''),
                'palette' => sanitize_text_field($postData['brand_palette'] ?? 'blue'),
            ],
            'hero' => [
                'badge' => sanitize_text_field($postData['hero_badge'] ?? ''),
                'heading' => sanitize_text_field($postData['hero_heading'] ?? ''),
                'subheading' => sanitize_textarea_field($postData['hero_subheading'] ?? ''),
                'image' => esc_url_raw($postData['hero_image'] ?? ''),
                'imageAlt' => sanitize_text_field($postData['hero_image_alt'] ?? ''),
                'ctas' => [
                    [
                        'text' => sanitize_text_field($postData['hero_cta1_text'] ?? ''),
                        'url' => esc_url_raw($postData['hero_cta1_url'] ?? ''),
                    ],
                    [
                        'text' => sanitize_text_field($postData['hero_cta2_text'] ?? ''),
                        'url' => esc_url_raw($postData['hero_cta2_url'] ?? ''),
                    ],
                ],
                'rating' => [
                    'value' => sanitize_text_field($postData['hero_rating_value'] ?? ''),
                    'count' => sanitize_text_field($postData['hero_rating_count'] ?? ''),
                ],
            ],
            'whyUs' => [
                'title' => sanitize_text_field($postData['whyus_title'] ?? ''),
                'html' => wp_kses_post($postData['whyus_html'] ?? ''),
                'image' => esc_url_raw($postData['whyus_image'] ?? ''),
            ],
            'about' => [
                'html' => wp_kses_post($postData['about_html'] ?? ''),
            ],
            'contact' => [
                'phone' => [
                    'text' => sanitize_text_field($postData['contact_phone_text'] ?? ''),
                    'link' => sanitize_text_field($postData['contact_phone_link'] ?? ''),
                ],
                'whatsapp' => [
                    'text' => sanitize_text_field($postData['contact_whatsapp_text'] ?? ''),
                    'link' => sanitize_text_field($postData['contact_whatsapp_link'] ?? ''),
                ],
                'email' => sanitize_email($postData['contact_email'] ?? ''),
                'website' => [
                    'text' => sanitize_text_field($postData['contact_website_text'] ?? ''),
                    'link' => esc_url_raw($postData['contact_website_link'] ?? ''),
                ],
                'address_line1' => sanitize_text_field($postData['contact_address1'] ?? ''),
                'address_line2' => sanitize_text_field($postData['contact_address2'] ?? ''),
                'address_line3' => sanitize_text_field($postData['contact_address3'] ?? ''),
                'address_line4' => sanitize_text_field($postData['contact_address4'] ?? ''),
                'plusCode' => sanitize_text_field($postData['contact_pluscode'] ?? ''),
                'hours' => $this->buildHoursFromForm($postData),
            ],
            'services' => $this->buildServicesFromForm($postData),
            'social' => $this->buildSocialFromForm($postData),
            'gallery' => $this->buildGalleryFromForm($postData),
        ];
    }

    private function buildServicesFromForm(array $postData): array
    {
        $services = [];
        $serviceCount = (int) ($postData['product_count'] ?? 0);
        
        for ($i = 0; $i < $serviceCount; $i++) {
            $services[] = [
                'title' => sanitize_text_field($postData["product_{$i}_title"] ?? ''),
                'image' => esc_url_raw($postData["product_{$i}_image"] ?? ''),
                'description' => sanitize_textarea_field($postData["product_{$i}_description"] ?? ''),
                'price' => sanitize_text_field($postData["product_{$i}_price"] ?? ''),
                'icon' => sanitize_text_field($postData["product_{$i}_icon"] ?? ''),
                'cta' => sanitize_text_field($postData["product_{$i}_cta_text"] ?? ''),
                'url' => esc_url_raw($postData["product_{$i}_cta_url"] ?? ''),
            ];
        }
        
        return [
            'title' => sanitize_text_field($postData['products_section_title'] ?? 'Products & Services'),
            'listing' => $services,
        ];
    }

    private function buildSocialFromForm(array $postData): array
    {
        $networks = ['facebook', 'instagram', 'x', 'youtube', 'linkedin', 'tiktok'];
        $social = [];
        
        foreach ($networks as $network) {
            $url = esc_url_raw($postData["social_{$network}"] ?? '');
            if (!empty($url)) {
                $social[] = [
                    'network' => $network,
                    'url' => $url,
                ];
            }
        }
        
        return $social;
    }

    private function buildHoursFromForm(array $postData): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $hours = [];
        
        foreach ($days as $day) {
            $isClosed = !empty($postData["hours_{$day}_closed"]);
            $openTime = sanitize_text_field($postData["hours_{$day}_open"] ?? '');
            $closeTime = sanitize_text_field($postData["hours_{$day}_close"] ?? '');
            
            if ($isClosed) {
                $hours[] = [
                    'day' => ucfirst($day),
                    'closed' => true,
                ];
            } elseif (!empty($openTime) && !empty($closeTime)) {
                // Convert 24-hour format to 12-hour format for display
                $openFormatted = $this->formatTime24To12($openTime);
                $closeFormatted = $this->formatTime24To12($closeTime);
                
                $hours[] = [
                    'day' => ucfirst($day),
                    'open' => $openFormatted,
                    'close' => $closeFormatted,
                ];
            }
        }
        
        return $hours;
    }

    private function formatTime24To12(string $time24): string
    {
        if (empty($time24)) return '';
        
        $time = explode(':', $time24);
        $hour = (int) $time[0];
        $minute = $time[1] ?? '00';
        
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12;
        if ($hour12 === 0) $hour12 = 12;
        
        return sprintf('%d:%s %s', $hour12, $minute, $ampm);
    }

    private function buildGalleryFromForm(array $postData): array
    {
        $gallery = [];
        $imageCount = (int) ($postData['gallery_count'] ?? 0);
        
        for ($i = 0; $i < $imageCount; $i++) {
            $imageUrl = esc_url_raw($postData["gallery_{$i}_image"] ?? '');
            $imageAlt = sanitize_text_field($postData["gallery_{$i}_alt"] ?? '');
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
}



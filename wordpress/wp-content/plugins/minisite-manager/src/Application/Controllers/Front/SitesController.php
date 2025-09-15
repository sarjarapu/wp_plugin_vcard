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
}



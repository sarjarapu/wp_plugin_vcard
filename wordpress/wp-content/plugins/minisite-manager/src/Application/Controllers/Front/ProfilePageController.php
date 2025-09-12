<?php
namespace Minisite\Application\Controllers\Front;

use Minisite\Infrastructure\Persistence\Repositories\ProfileRepository;
use Minisite\Domain\ValueObjects\SlugPair;

final class ProfilePageController
{
    public function __construct(private object $renderer) {}

    public function handle(string $businessSlug, string $locationSlug): void
    {
        global $wpdb;

        $repo = new ProfileRepository($wpdb);
        $profile = $repo->findBySlugs(new SlugPair($businessSlug, $locationSlug));

        if (!$profile) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            echo '<!doctype html><meta charset="utf-8"><h1>Minisite not found</h1>';
            return;
        }

        // Delegate to the renderer (PhpRenderer or TimberRenderer)
        if (method_exists($this->renderer, 'render')) {
            $this->renderer->render($profile);
            return;
        }

        // If renderer is missing, show minimal details
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><h1>' . htmlspecialchars($profile->name) . '</h1>';
    }
}


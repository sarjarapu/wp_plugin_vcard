<?php
namespace Minisite\Application\Controllers\Front;

use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Domain\ValueObjects\SlugPair;

final class MinisitePageController
{
    public function __construct(private object $renderer) {}

    public function handle(string $businessSlug, string $locationSlug): void
    {
        global $wpdb;

        $repo = new MinisiteRepository($wpdb);
        $minisite = $repo->findBySlugs(new SlugPair($businessSlug, $locationSlug));

        if (!$minisite) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            echo '<!doctype html><meta charset="utf-8"><h1>Minisite not found</h1>';
            return;
        }

        // Delegate to the Timber renderer
        if (method_exists($this->renderer, 'render')) {
            $this->renderer->render($minisite);
            return;
        }

        // If renderer is missing, show minimal details
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><h1>' . htmlspecialchars($minisite->name) . '</h1>';
    }
}

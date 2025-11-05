<?php

namespace Minisite\Features\MinisiteListing\Rendering;

/**
 * Listing Renderer
 *
 * SINGLE RESPONSIBILITY: Handle template rendering with Timber for listing functionality
 * - Manages Timber template rendering
 * - Handles template context
 */
class ListingRenderer
{
    /**
     * Render list page using Timber
     */
    public function renderListPage(array $data): void
    {
        if (! class_exists('Timber\\Timber')) {
            $this->renderFallback($data);

            return;
        }

        $this->registerTimberLocations();
        \Timber\Timber::render('account-sites.twig', $data);
    }

    /**
     * Register Timber template locations
     */
    private function registerTimberLocations(): void
    {
        $viewsBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
        \Timber\Timber::$locations = array_values(
            array_unique(
                array_merge(
                    \Timber\Timber::$locations ?? array(),
                    array($viewsBase)
                )
            )
        );
    }

    /**
     * Fallback rendering if Timber is not available
     */
    private function renderFallback(array $data): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">';
        echo '<title>' . esc_html($data['page_title'] ?? 'My Minisites') . '</title>';
        echo '<h1>' . esc_html($data['page_title'] ?? 'My Minisites') . '</h1>';

        if (! empty($data['error'])) {
            echo '<p style="color: red;">Error: ' . esc_html($data['error']) . '</p>';
        }

        if (! empty($data['sites'])) {
            echo '<ul>';
            foreach ($data['sites'] as $site) {
                echo '<li>' . esc_html($site['title'] ?? $site['name']) . ' - ' . esc_html($site['status']) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No minisites found.</p>';
        }
    }
}

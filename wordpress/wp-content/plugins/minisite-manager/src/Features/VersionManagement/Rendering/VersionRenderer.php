<?php

namespace Minisite\Features\VersionManagement\Rendering;

use Minisite\Application\Rendering\TimberRenderer;

/**
 * Handles rendering for version management
 */
class VersionRenderer
{
    public function __construct(
        private TimberRenderer $timberRenderer
    ) {
    }

    /**
     * Render version history page
     */
    public function renderVersionHistory(array $data): void
    {
        if (class_exists('Timber\\Timber')) {
            $base = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
            \Timber\Timber::$locations = array_values(
                array_unique(
                    array_merge(
                        \Timber\Timber::$locations ?? array(),
                        array($base)
                    )
                )
            );

            \Timber\Timber::render('account-sites-versions.twig', $data);

            return;
        }

        // Fallback rendering
        $this->renderFallbackVersionHistory($data);
    }

    /**
     * Fallback rendering when Timber is not available
     */
    private function renderFallbackVersionHistory(array $data): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">';
        echo '<h1>Version History: ' . esc_html($data['profile']->title ?? 'Unknown') . '</h1>';
        echo '<p>Version history not available (Timber required).</p>';
    }
}

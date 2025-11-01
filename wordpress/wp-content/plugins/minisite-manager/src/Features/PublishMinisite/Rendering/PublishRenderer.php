<?php

namespace Minisite\Features\PublishMinisite\Rendering;

use Minisite\Application\Rendering\TimberRenderer;

/**
 * Publish Renderer
 *
 * SINGLE RESPONSIBILITY: Handle template rendering for publish page
 * - Manages publish page template rendering
 * - Handles error page rendering
 * - Coordinates with Timber renderer
 */
class PublishRenderer
{
    public function __construct(
        private ?TimberRenderer $timberRenderer = null
    ) {
    }

    /**
     * Render publish page
     */
    public function renderPublishPage(object $publishData): void
    {
        if (!$this->timberRenderer) {
            $this->renderFallbackPublishPage($publishData);
            return;
        }

        // Set up Timber locations
        $this->setupTimberLocations();

        // Prepare template data
        $templateData = $this->prepareTemplateData($publishData);

        // Render using existing publish template
        if (class_exists('Timber\\Timber')) {
            \Timber\Timber::render('account-sites-publish.twig', $templateData);
        } else {
            $this->renderFallbackPublishPage($publishData);
        }
    }

    /**
     * Set up Timber locations
     */
    private function setupTimberLocations(): void
    {
        if (!class_exists('Timber\\Timber')) {
            return;
        }

        $timberBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber';
        $viewsBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
        $componentsBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/components';

        \Timber\Timber::$locations = array_values(
            array_unique(
                array_merge(
                    \Timber\Timber::$locations ?? [],
                    [$timberBase, $viewsBase, $componentsBase]
                )
            )
        );
    }

    /**
     * Prepare template data
     */
    private function prepareTemplateData(object $publishData): array
    {
        $minisite = $publishData->minisite;
        $currentUser = wp_get_current_user();

        return [
            'page_title' => 'Publish Your Minisite',
            'minisite_id' => $minisite->id,
            'minisite_name' => $minisite->name ?? 'Untitled Minisite',
            'current_business_slug' => $publishData->currentSlugs['business'] ?? '',
            'current_location_slug' => $publishData->currentSlugs['location'] ?? '',
            'user' => $currentUser,
            // Nonces for AJAX operations
            'nonce_check_availability' => wp_create_nonce('check_slug_availability'),
            'nonce_reserve_slug' => wp_create_nonce('reserve_slug'),
            'nonce_cancel_reservation' => wp_create_nonce('cancel_reservation'),
            'nonce_create_order' => wp_create_nonce('create_minisite_order'),
            'ajax_url' => admin_url('admin-ajax.php'),
        ];
    }

    /**
     * Render fallback publish page (when Timber is not available)
     */
    private function renderFallbackPublishPage(object $publishData): void
    {
        $minisite = $publishData->minisite;
        
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Publish Your Minisite</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { 
            background: #0073aa; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
    </style>
</head>
<body>
    <h1>Publish Your Minisite</h1>
    <p>Minisite: ' . esc_html($minisite->name ?? 'Untitled') . '</p>
    <p>Publish functionality is not available without Timber template engine.</p>
</body>
</html>';
    }
}


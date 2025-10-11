<?php

namespace Minisite\Features\MinisiteViewer\Rendering;

/**
 * View Renderer
 *
 * SINGLE RESPONSIBILITY: Handle template rendering for minisite view
 * - Manages template rendering logic
 * - Handles fallback rendering
 * - Provides clean interface for view output
 */
final class ViewRenderer
{
    private object $renderer;

    public function __construct(object $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Render minisite template
     *
     * @param object $minisite
     * @return void
     */
    public function renderMinisite(object $minisite): void
    {
        // Delegate to the Timber renderer if available
        if (method_exists($this->renderer, 'render')) {
            $this->renderer->render($minisite);
            return;
        }

        // Fallback rendering if renderer is missing
        $this->renderFallback($minisite);
    }

    /**
     * Render template with context
     *
     * @param string $template
     * @param array $context
     * @return void
     */
    public function render(string $template, array $context): void
    {
        if (method_exists($this->renderer, 'render')) {
            $this->renderer->render($template, $context);
            return;
        }

        // Fallback rendering
        $this->renderFallbackFromContext($context);
    }

    /**
     * Render 404 page
     *
     * @param string $errorMessage
     * @return void
     */
    public function render404(string $errorMessage = 'Minisite not found'): void
    {
        echo '<!doctype html><meta charset="utf-8"><h1>' . esc_html($errorMessage) . '</h1>';
    }

    /**
     * Fallback rendering for minisite
     *
     * @param object $minisite
     * @return void
     */
    private function renderFallback(object $minisite): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><h1>' . esc_html($minisite->name ?? 'Minisite') . '</h1>';
    }

    /**
     * Fallback rendering from context
     *
     * @param array $context
     * @return void
     */
    private function renderFallbackFromContext(array $context): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $title = $context['page_title'] ?? 'Page';
        echo '<!doctype html><meta charset="utf-8"><h1>' . esc_html($title) . '</h1>';
    }

    /**
     * Render version-specific preview (authenticated)
     *
     * @param object $previewData
     * @return void
     */
    public function renderVersionSpecificPreview(object $previewData): void
    {
        if (!$this->renderer) {
            $this->renderFallbackVersionSpecificPreview($previewData);
            return;
        }

        // Set up Timber locations
        $this->setupTimberLocations();

        // Prepare template data for version-specific preview
        $templateData = $this->prepareVersionSpecificPreviewTemplateData($previewData);
        
        // Render the preview template using Timber directly
        if (class_exists('Timber\\Timber')) {
            try {
                \Timber\Timber::render('v2025/minisite.twig', $templateData);
            } catch (\Exception $e) {
                error_log('Template rendering error: ' . $e->getMessage());
                $this->renderFallbackVersionSpecificPreview($previewData);
            }
        } else {
            $this->renderFallbackVersionSpecificPreview($previewData);
        }
    }

    /**
     * Prepare template data for version-specific preview
     *
     * @param object $previewData
     * @return array
     */
    private function prepareVersionSpecificPreviewTemplateData(object $previewData): array
    {
        $minisite = $previewData->minisite;
        $version = $previewData->version;
        
        // Fetch reviews for the minisite (same as regular minisite view)
        $reviews = $this->fetchReviews($minisite->id);
        
        // Use the same data structure as public minisite view
        return [
            'minisite' => $minisite,
            'reviews' => $reviews, // Fetch actual reviews for preview
            // Additional version-specific preview data
            'version' => $version,
            'versionId' => $previewData->versionId,
            'isVersionSpecificPreview' => true,
            'previewTitle' => $version ? "Preview: {$version->label}" : 'Preview: Current Version'
        ];
    }

    /**
     * Render fallback version-specific preview
     *
     * @param object $previewData
     * @return void
     */
    private function renderFallbackVersionSpecificPreview(object $previewData): void
    {
        $minisite = $previewData->minisite;
        $version = $previewData->version;
        $versionLabel = $version ? $version->label : 'Current Version';
        
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Preview: ' . esc_html($minisite->name ?? 'Minisite') . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .preview-header { background: #f0f0f0; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .preview-content { border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
        .version-info { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="preview-header">
        <h1>Preview: ' . esc_html($minisite->name ?? 'Minisite') . '</h1>
        <div class="version-info">Version: ' . esc_html($versionLabel) . '</div>
    </div>
    
    <div class="preview-content">
        <h2>Minisite Content</h2>
        <p><strong>Name:</strong> ' . esc_html($minisite->name ?? '') . '</p>
        <p><strong>City:</strong> ' . esc_html($minisite->city ?? '') . '</p>
        <p><strong>Title:</strong> ' . esc_html($minisite->title ?? '') . '</p>
        
        <h3>Site JSON Data</h3>
        <pre>' . esc_html(json_encode($previewData->siteJson, JSON_PRETTY_PRINT)) . '</pre>
    </div>
</body>
</html>';
    }

    /**
     * Set up Timber locations for version-specific preview
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
     * Fetch reviews for a minisite (same implementation as TimberRenderer)
     *
     * @param string $minisiteId
     * @return array
     */
    private function fetchReviews(string $minisiteId): array
    {
        global $wpdb;
        $reviewRepo = new \Minisite\Infrastructure\Persistence\Repositories\ReviewRepository($wpdb);
        $reviews = $reviewRepo->listApprovedForMinisite($minisiteId);
        
        // Log review fetching for debugging
        error_log('MINISITE_VIEWER_DEBUG: Fetched ' . count($reviews) . ' reviews for minisite ' . $minisiteId);
        
        return $reviews;
    }
}

<?php

namespace Minisite\Features\NewMinisite\Rendering;

use Minisite\Application\Rendering\TimberRenderer;

/**
 * New Minisite Renderer
 *
 * SINGLE RESPONSIBILITY: Handle template rendering for new minisite forms
 * - Manages new minisite form template rendering
 * - Handles error page rendering
 * - Coordinates with Timber renderer
 */
class NewMinisiteRenderer
{
    public function __construct(
        private ?TimberRenderer $timberRenderer = null
    ) {
    }

    /**
     * Render new minisite form
     */
    public function renderNewMinisiteForm(object $newMinisiteData): void
    {
        if (!$this->timberRenderer) {
            $this->renderFallbackNewMinisiteForm($newMinisiteData);
            return;
        }

        // Set up Timber locations
        $this->setupTimberLocations();

        // Prepare template data
        $templateData = $this->prepareTemplateData($newMinisiteData);

        // Render the new minisite form template using Timber directly
        if (class_exists('Timber\\Timber')) {
            \Timber\Timber::render('account-sites-new.twig', $templateData);
        } else {
            $this->renderFallbackNewMinisiteForm($newMinisiteData);
        }
    }

    /**
     * Render error page
     */
    public function renderError(string $errorMessage): void
    {
        if (!$this->timberRenderer) {
            $this->renderFallbackError($errorMessage);
            return;
        }

        if (class_exists('Timber\\Timber')) {
            \Timber\Timber::render('error.twig', [
                'error_message' => $errorMessage,
                'page_title' => 'Error'
            ]);
        } else {
            $this->renderFallbackError($errorMessage);
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
    private function prepareTemplateData(object $newMinisiteData): array
    {
        $formData = $newMinisiteData->formData;

        return [
            'page_title' => 'Create New Minisite',
            'page_subtitle' => 'Create a new minisite for your business',
            'user_minisite_count' => $newMinisiteData->userMinisiteCount,
            'success_message' => $newMinisiteData->successMessage,
            'error_message' => $newMinisiteData->errorMessage,
            'form_nonce' => wp_create_nonce('minisite_new'),
            'form_action' => '',
            'form_method' => 'POST',
            // Form field values (empty for new minisite)
            'business_name' => $formData['business']['name'] ?? '',
            'business_city' => $formData['business']['city'] ?? '',
            'business_region' => $formData['business']['region'] ?? '',
            'business_country' => $formData['business']['country'] ?? '',
            'business_postal' => $formData['business']['postal'] ?? '',
            'seo_title' => $formData['seo']['title'] ?? '',
            'site_template' => $formData['settings']['template'] ?? '',
            'brand_palette' => $formData['brand']['palette'] ?? '',
            'brand_industry' => $formData['brand']['industry'] ?? '',
            'default_locale' => $formData['settings']['locale'] ?? '',
            'search_terms' => $formData['seo']['searchTerms'] ?? '',
            'contact_lat' => $formData['contact']['lat'] ?? '',
            'contact_lng' => $formData['contact']['lng'] ?? '',
            'version_label' => 'Initial Draft',
            'version_comment' => 'First draft of the new minisite',
        ];
    }

    /**
     * Render fallback new minisite form (when Timber is not available)
     */
    private function renderFallbackNewMinisiteForm(object $newMinisiteData): void
    {
        $formData = $newMinisiteData->formData;

        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Create New Minisite</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .error { color: red; margin-bottom: 20px; }
        .success { color: green; margin-bottom: 20px; }
        .info { color: blue; margin-bottom: 20px; }
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
    <h1>Create New Minisite</h1>
    <div class="info">You currently have ' . esc_html($newMinisiteData->userMinisiteCount) . ' minisite(s).</div>';

        if ($newMinisiteData->errorMessage) {
            echo '<div class="error">' . esc_html($newMinisiteData->errorMessage) . '</div>';
        }

        if ($newMinisiteData->successMessage) {
            echo '<div class="success">' . esc_html($newMinisiteData->successMessage) . '</div>';
        }

        echo '<form method="POST">
        <input type="hidden" name="minisite_new_nonce" 
            value="' . esc_attr(wp_create_nonce('minisite_new')) . '">
        
        <div class="form-group">
            <label for="business_name">Business Name:</label>
            <input type="text" id="business_name" name="business_name" 
                value="' . esc_attr($formData['business']['name'] ?? '') . '" required>
        </div>
        
        <div class="form-group">
            <label for="business_city">City:</label>
            <input type="text" id="business_city" name="business_city" 
                value="' . esc_attr($formData['business']['city'] ?? '') . '" required>
        </div>
        
        <div class="form-group">
            <label for="seo_title">SEO Title:</label>
            <input type="text" id="seo_title" name="seo_title" 
                value="' . esc_attr($formData['seo']['title'] ?? '') . '">
        </div>
        
        <div class="form-group">
            <label for="version_label">Version Label:</label>
            <input type="text" id="version_label" name="version_label" 
                   value="Initial Draft">
        </div>
        
        <div class="form-group">
            <label for="version_comment">Version Comment:</label>
            <textarea id="version_comment" name="version_comment">First draft of the new minisite</textarea>
        </div>
        
        <button type="submit">Create Draft</button>
    </form>
</body>
</html>';
    }

    /**
     * Render fallback error page
     */
    private function renderFallbackError(string $errorMessage): void
    {
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Error</h1>
    <div class="error">' . esc_html($errorMessage) . '</div>
</body>
</html>';
    }
}

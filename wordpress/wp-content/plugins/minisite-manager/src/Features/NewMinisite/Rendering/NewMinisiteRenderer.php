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

        // Prepare template data (reuse same structure as EditRenderer)
        $templateData = $this->prepareTemplateData($newMinisiteData);

        // Render using the SAME template as edit form (account-sites-edit.twig)
        // This ensures UI consistency and reuses existing templates
        if (class_exists('Timber\\Timber')) {
            \Timber\Timber::render('account-sites-edit.twig', $templateData);
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
     * Prepare template data (reuse same structure as EditRenderer)
     */
    private function prepareTemplateData(object $newMinisiteData): array
    {
        $formData = $newMinisiteData->formData;

        // Create mock objects to match EditRenderer's expected structure
        $mockMinisite = (object) [
            'id' => 'new',
            'status' => 'draft'
        ];

        $mockProfile = (object) [
            'name' => $formData['business']['name'] ?? '',
            'city' => $formData['business']['city'] ?? '',
            'region' => $formData['business']['region'] ?? '',
            'countryCode' => $formData['business']['country'] ?? '',
            'postalCode' => $formData['business']['postal'] ?? '',
            'title' => $formData['seo']['title'] ?? '',
            'siteTemplate' => $formData['settings']['template'] ?? '',
            'palette' => $formData['brand']['palette'] ?? '',
            'industry' => $formData['brand']['industry'] ?? '',
            'defaultLocale' => $formData['settings']['locale'] ?? '',
            'searchTerms' => $formData['seo']['searchTerms'] ?? '',
            'geo' => null
        ];

        // Add geo data if available
        if (!empty($formData['contact']['lat']) && !empty($formData['contact']['lng'])) {
            $mockProfile->geo = (object) [
                'getLat' => fn() => $formData['contact']['lat'],
                'getLng' => fn() => $formData['contact']['lng']
            ];
        }

        $mockEditingVersion = (object) [
            'label' => 'Initial Draft',
            'comment' => 'First draft of the new minisite'
        ];

        // Return same structure as EditRenderer for template compatibility
        return [
            'page_title' => 'Create New Minisite',
            'page_subtitle' => 'Create a new minisite for your business',
            'minisite' => $mockMinisite,
            'editing_version' => $mockEditingVersion,
            'latest_draft' => null, // No existing draft for new minisite
            'profile' => $mockProfile,
            'site_json' => [], // Empty site JSON for new minisite
            'success_message' => $newMinisiteData->successMessage,
            'error_message' => $newMinisiteData->errorMessage,
            'form_nonce' => wp_create_nonce('minisite_edit'),
            'form_action' => '',
            'form_method' => 'POST',
            // Preview and navigation URLs (disabled for new minisite)
            'preview_url' => null, // No preview available for new minisite
            'versions_url' => null, // No versions available for new minisite
            'edit_latest_url' => null, // No edit available for new minisite
            'minisite_id' => 'new',
            'minisite_status' => 'draft',
            // Form field values (same as EditRenderer structure)
            'business_name' => $mockProfile->name,
            'business_city' => $mockProfile->city,
            'business_region' => $mockProfile->region,
            'business_country' => $mockProfile->countryCode,
            'business_postal' => $mockProfile->postalCode,
            'seo_title' => $mockProfile->title,
            'site_template' => $mockProfile->siteTemplate,
            'brand_palette' => $mockProfile->palette,
            'brand_industry' => $mockProfile->industry,
            'default_locale' => $mockProfile->defaultLocale,
            'search_terms' => $mockProfile->searchTerms,
            'contact_lat' => $mockProfile->geo && method_exists($mockProfile->geo, 'getLat') ? $mockProfile->geo->getLat() : '',
            'contact_lng' => $mockProfile->geo && method_exists($mockProfile->geo, 'getLng') ? $mockProfile->geo->getLng() : '',
            'version_label' => $mockEditingVersion->label,
            'version_comment' => $mockEditingVersion->comment,
        ];
    }

    /**
     * Render fallback new minisite form (when Timber is not available)
     * Reuse same structure as EditRenderer's fallback form
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
    <h1>Create New Minisite</h1>';

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

<?php

namespace Minisite\Features\MinisiteEdit\Rendering;

use Minisite\Application\Rendering\TimberRenderer;

/**
 * Edit Renderer
 *
 * SINGLE RESPONSIBILITY: Handle template rendering for edit forms
 * - Manages edit form template rendering
 * - Handles error page rendering
 * - Coordinates with Timber renderer
 */
class EditRenderer
{
    public function __construct(
        private ?TimberRenderer $timberRenderer = null
    ) {
    }

    /**
     * Render edit form
     */
    public function renderEditForm(object $editData): void
    {
        if (!$this->timberRenderer) {
            $this->renderFallbackEditForm($editData);
            return;
        }

        // Set up Timber locations
        $this->setupTimberLocations();

        // Prepare template data
        $templateData = $this->prepareTemplateData($editData);

        // Render the edit form template using Timber directly
        if (class_exists('Timber\\Timber')) {
            \Timber\Timber::render('account-sites-edit.twig', $templateData);
        } else {
            $this->renderFallbackEditForm($editData);
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
    private function prepareTemplateData(object $editData): array
    {
        $profile = $editData->profileForForm;
        $siteJson = $editData->siteJson;

        return [
            'page_title' => 'Edit Minisite',
            'page_subtitle' => 'Update your minisite information',
            'minisite' => $editData->minisite,
            'editing_version' => $editData->editingVersion,
            'latest_draft' => $editData->latestDraft,
            'profile' => $profile,
            'site_json' => $siteJson,
            'success_message' => $editData->successMessage,
            'error_message' => $editData->errorMessage,
            'form_nonce' => wp_create_nonce('minisite_edit'),
            'form_action' => '',
            'form_method' => 'POST',
            // Preview and navigation URLs (same as old implementation)
            'preview_url' => $editData->editingVersion ?
                home_url('/account/sites/' . $editData->minisite->id . '/preview/' . $editData->editingVersion->id) :
                home_url('/account/sites/' . $editData->minisite->id . '/preview/current'),
            'versions_url' => home_url('/account/sites/' . $editData->minisite->id . '/versions'),
            'edit_latest_url' => home_url('/account/sites/' . $editData->minisite->id . '/edit/latest'),
            'minisite_id' => $editData->minisite->id,
            'minisite_status' => $editData->minisite->status,
            // Form field values
            'business_name' => $profile->name ?? '',
            'business_city' => $profile->city ?? '',
            'business_region' => $profile->region ?? '',
            'business_country' => $profile->countryCode ?? '',
            'business_postal' => $profile->postalCode ?? '',
            'seo_title' => $profile->title ?? '',
            'site_template' => $profile->siteTemplate ?? '',
            'brand_palette' => $profile->palette ?? '',
            'brand_industry' => $profile->industry ?? '',
            'default_locale' => $profile->defaultLocale ?? '',
            'search_terms' => $profile->searchTerms ?? '',
            'contact_lat' => $profile->geo && method_exists($profile->geo, 'getLat') ? $profile->geo->getLat() : '',
            'contact_lng' => $profile->geo && method_exists($profile->geo, 'getLng') ? $profile->geo->getLng() : '',
            'version_label' => $editData->editingVersion?->label ?? '',
            'version_comment' => $editData->editingVersion?->comment ?? '',
        ];
    }

    /**
     * Render fallback edit form (when Timber is not available)
     */
    private function renderFallbackEditForm(object $editData): void
    {
        $profile = $editData->profileForForm;

        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit Minisite</title>
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
    <h1>Edit Minisite</h1>';

        if ($editData->errorMessage) {
            echo '<div class="error">' . esc_html($editData->errorMessage) . '</div>';
        }

        if ($editData->successMessage) {
            echo '<div class="success">' . esc_html($editData->successMessage) . '</div>';
        }

        echo '<form method="POST">
        <input type="hidden" name="minisite_edit_nonce" 
            value="' . esc_attr(wp_create_nonce('minisite_edit')) . '">
        
        <div class="form-group">
            <label for="business_name">Business Name:</label>
            <input type="text" id="business_name" name="business_name" 
                value="' . esc_attr($profile->name ?? '') . '" required>
        </div>
        
        <div class="form-group">
            <label for="business_city">City:</label>
            <input type="text" id="business_city" name="business_city" 
                value="' . esc_attr($profile->city ?? '') . '" required>
        </div>
        
        <div class="form-group">
            <label for="seo_title">SEO Title:</label>
            <input type="text" id="seo_title" name="seo_title" value="' . esc_attr($profile->title ?? '') . '">
        </div>
        
        <div class="form-group">
            <label for="version_label">Version Label:</label>
            <input type="text" id="version_label" name="version_label" 
                   value="' . esc_attr($editData->editingVersion?->label ?? '') . '">
        </div>
        
        <div class="form-group">
            <label for="version_comment">Version Comment:</label>
            <textarea id="version_comment" name="version_comment">' .
                esc_textarea($editData->editingVersion?->comment ?? '') . '</textarea>
        </div>
        
        <button type="submit">Save Draft</button>
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

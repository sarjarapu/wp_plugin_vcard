<?php

namespace Minisite\Features\MinisiteEditor\Http;

use Minisite\Features\MinisiteEditor\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteEditor\Commands\CreateMinisiteCommand;
use Minisite\Features\MinisiteEditor\Commands\EditMinisiteCommand;
use Minisite\Features\MinisiteEditor\Commands\PreviewMinisiteCommand;

/**
 * Editor Request Handler
 *
 * SINGLE RESPONSIBILITY: Handle HTTP requests and extract data
 * - Validates HTTP method
 * - Extracts and sanitizes form data
 * - Creates command objects
 * - Handles nonce verification
 */
final class EditorRequestHandler
{
    /**
     * Parse list minisites request
     */
    public function parseListMinisitesRequest(): ?ListMinisitesCommand
    {
        $currentUser = wp_get_current_user();
        
        if (!$currentUser || !$currentUser->ID) {
            return null;
        }

        return new ListMinisitesCommand(
            userId: (int) $currentUser->ID,
            limit: 50,
            offset: 0
        );
    }

    /**
     * Parse create minisite request
     */
    public function parseCreateMinisiteRequest(): ?CreateMinisiteCommand
    {
        if (!$this->isPostRequest()) {
            return null;
        }

        if (!$this->isValidNonce('minisite_create')) {
            throw new \InvalidArgumentException('Invalid nonce');
        }

        $currentUser = wp_get_current_user();
        
        if (!$currentUser || !$currentUser->ID) {
            return null;
        }

        return new CreateMinisiteCommand(
            userId: (int) $currentUser->ID,
            businessSlug: $this->sanitizeInput($_POST['business_slug'] ?? ''),
            locationSlug: $this->sanitizeInput($_POST['location_slug'] ?? ''),
            businessName: $this->sanitizeInput($_POST['business_name'] ?? ''),
            businessCity: $this->sanitizeInput($_POST['business_city'] ?? ''),
            businessRegion: $this->sanitizeInput($_POST['business_region'] ?? ''),
            businessCountry: $this->sanitizeInput($_POST['business_country'] ?? ''),
            businessPostal: $this->sanitizeInput($_POST['business_postal'] ?? ''),
            latitude: $this->sanitizeFloat($_POST['latitude'] ?? null),
            longitude: $this->sanitizeFloat($_POST['longitude'] ?? null)
        );
    }

    /**
     * Parse edit minisite request
     */
    public function parseEditMinisiteRequest(): ?EditMinisiteCommand
    {
        $siteId = get_query_var('minisite_site_id');
        
        if (!$siteId) {
            return null;
        }

        $currentUser = wp_get_current_user();
        
        if (!$currentUser || !$currentUser->ID) {
            return null;
        }

        // If it's a POST request, validate nonce
        if ($this->isPostRequest() && !$this->isValidNonce('minisite_edit')) {
            throw new \InvalidArgumentException('Invalid nonce');
        }

        return new EditMinisiteCommand(
            siteId: $siteId,
            userId: (int) $currentUser->ID,
            businessName: $this->sanitizeInput($_POST['business_name'] ?? ''),
            businessCity: $this->sanitizeInput($_POST['business_city'] ?? ''),
            businessRegion: $this->sanitizeInput($_POST['business_region'] ?? ''),
            businessCountry: $this->sanitizeInput($_POST['business_country'] ?? ''),
            businessPostal: $this->sanitizeInput($_POST['business_postal'] ?? ''),
            seoTitle: $this->sanitizeInput($_POST['seo_title'] ?? ''),
            siteTemplate: $this->sanitizeInput($_POST['site_template'] ?? ''),
            brandPalette: $this->sanitizeInput($_POST['brand_palette'] ?? ''),
            brandIndustry: $this->sanitizeInput($_POST['brand_industry'] ?? ''),
            defaultLocale: $this->sanitizeInput($_POST['default_locale'] ?? ''),
            searchTerms: $this->sanitizeInput($_POST['search_terms'] ?? ''),
            latitude: $this->sanitizeFloat($_POST['latitude'] ?? null),
            longitude: $this->sanitizeFloat($_POST['longitude'] ?? null),
            versionLabel: $this->sanitizeInput($_POST['version_label'] ?? ''),
            versionComment: $this->sanitizeTextarea($_POST['version_comment'] ?? '')
        );
    }

    /**
     * Parse preview minisite request
     */
    public function parsePreviewMinisiteRequest(): ?PreviewMinisiteCommand
    {
        $siteId = get_query_var('minisite_site_id');
        $versionId = get_query_var('minisite_version_id');
        
        if (!$siteId || !$versionId) {
            return null;
        }

        $currentUser = wp_get_current_user();
        
        if (!$currentUser || !$currentUser->ID) {
            return null;
        }

        return new PreviewMinisiteCommand(
            siteId: $siteId,
            userId: (int) $currentUser->ID,
            versionId: $versionId
        );
    }

    /**
     * Check if request is POST
     */
    private function isPostRequest(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Validate nonce
     */
    private function isValidNonce(string $action): bool
    {
        if (!isset($_POST['minisite_nonce'])) {
            return false;
        }

        return wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['minisite_nonce'])),
            $action
        ) !== false;
    }

    /**
     * Sanitize input
     */
    private function sanitizeInput(string $input): string
    {
        return sanitize_text_field(wp_unslash($input));
    }

    /**
     * Sanitize textarea
     */
    private function sanitizeTextarea(string $input): string
    {
        return sanitize_textarea_field(wp_unslash($input));
    }

    /**
     * Sanitize float
     */
    private function sanitizeFloat(?string $input): ?float
    {
        if ($input === null || $input === '') {
            return null;
        }

        $float = (float) $input;
        return $float === 0.0 ? null : $float;
    }
}

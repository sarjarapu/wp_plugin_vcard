<?php

namespace Minisite\Features\VersionManagement\Http;

use Minisite\Features\VersionManagement\Commands\CreateDraftCommand;
use Minisite\Features\VersionManagement\Commands\ListVersionsCommand;
use Minisite\Features\VersionManagement\Commands\PublishVersionCommand;
use Minisite\Features\VersionManagement\Commands\RollbackVersionCommand;
use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;

/**
 * Handles HTTP requests for version management
 */
class VersionRequestHandler
{
    public function __construct(
        private WordPressVersionManager $wordPressManager
    ) {
    }

    /**
     * Parse request for listing versions
     */
    public function parseListVersionsRequest(): ?ListVersionsCommand
    {
        $siteId = $this->wordPressManager->getQueryVar('minisite_site_id');
        
        if (!$siteId) {
            return null;
        }

        $currentUser = $this->wordPressManager->getCurrentUser();
        
        if (!$currentUser || !$currentUser->ID) {
            return null;
        }

        return new ListVersionsCommand($siteId, (int) $currentUser->ID);
    }

    /**
     * Parse request for creating draft
     */
    public function parseCreateDraftRequest(): ?CreateDraftCommand
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }

        if (!$this->wordPressManager->verifyNonce($this->wordPressManager->sanitizeTextField($this->wordPressManager->unslash($_POST['nonce'] ?? '')), 'minisite_version')) {
            return null;
        }

        $siteId = $this->wordPressManager->sanitizeTextField($this->wordPressManager->unslash($_POST['site_id'] ?? ''));
        if (!$siteId) {
            return null;
        }

        $currentUser = $this->wordPressManager->getCurrentUser();
        if (!$currentUser || !$currentUser->ID) {
            return null;
        }

        $label = $this->wordPressManager->sanitizeTextField($this->wordPressManager->unslash($_POST['label'] ?? ''));
        $comment = $this->wordPressManager->sanitizeTextareaField($this->wordPressManager->unslash($_POST['version_comment'] ?? ''));
        $siteJson = $this->buildSiteJsonFromForm($_POST);

        return new CreateDraftCommand(
            $siteId,
            (int) $currentUser->ID,
            $label,
            $comment,
            $siteJson
        );
    }

    /**
     * Parse request for publishing version
     */
    public function parsePublishVersionRequest(): ?PublishVersionCommand
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }

        if (!$this->wordPressManager->verifyNonce($this->wordPressManager->sanitizeTextField($this->wordPressManager->unslash($_POST['nonce'] ?? '')), 'minisite_version')) {
            return null;
        }

        $siteId = $this->wordPressManager->sanitizeTextField($this->wordPressManager->unslash($_POST['site_id'] ?? ''));
        $versionId = (int) ($this->wordPressManager->sanitizeTextField($this->wordPressManager->unslash($_POST['version_id'] ?? 0)));

        if (!$siteId || !$versionId) {
            return null;
        }

        $currentUser = $this->wordPressManager->getCurrentUser();
        if (!$currentUser || !$currentUser->ID) {
            return null;
        }

        return new PublishVersionCommand($siteId, $versionId, (int) $currentUser->ID);
    }

    /**
     * Parse request for rollback version
     */
    public function parseRollbackVersionRequest(): ?RollbackVersionCommand
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }

        if (!$this->wordPressManager->verifyNonce($this->wordPressManager->sanitizeTextField($this->wordPressManager->unslash($_POST['nonce'] ?? '')), 'minisite_version')) {
            return null;
        }

        $siteId = $this->wordPressManager->sanitizeTextField($this->wordPressManager->unslash($_POST['site_id'] ?? ''));
        $sourceVersionId = (int) ($this->wordPressManager->sanitizeTextField($this->wordPressManager->unslash($_POST['source_version_id'] ?? 0)));

        if (!$siteId || !$sourceVersionId) {
            return null;
        }

        $currentUser = $this->wordPressManager->getCurrentUser();
        if (!$currentUser || !$currentUser->ID) {
            return null;
        }

        return new RollbackVersionCommand($siteId, $sourceVersionId, (int) $currentUser->ID);
    }

    /**
     * Build site JSON from form data
     */
    private function buildSiteJsonFromForm(array $formData): array
    {
        return array(
            'seo' => array(
                'title' => sanitize_text_field($formData['seo_title'] ?? ''),
                'description' => sanitize_textarea_field($formData['seo_description'] ?? ''),
                'keywords' => sanitize_text_field($formData['seo_keywords'] ?? ''),
            ),
            'brand' => array(
                'name' => sanitize_text_field($formData['brand_name'] ?? ''),
                'logo' => esc_url_raw($formData['brand_logo'] ?? ''),
                'palette' => sanitize_text_field($formData['brand_palette'] ?? 'blue'),
                'industry' => sanitize_text_field($formData['brand_industry'] ?? 'services'),
            ),
            'hero' => array(
                'heading' => sanitize_text_field($formData['hero_heading'] ?? ''),
                'subheading' => sanitize_textarea_field($formData['hero_subheading'] ?? ''),
                'image' => esc_url_raw($formData['hero_image'] ?? ''),
            ),
        );
    }
}

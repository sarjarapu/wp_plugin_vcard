<?php

namespace Minisite\Features\VersionManagement\Http;

use Minisite\Features\VersionManagement\Commands\CreateDraftCommand;
use Minisite\Features\VersionManagement\Commands\ListVersionsCommand;
use Minisite\Features\VersionManagement\Commands\PublishVersionCommand;
use Minisite\Features\VersionManagement\Commands\RollbackVersionCommand;
use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;
use Minisite\Infrastructure\Security\FormSecurityHelper;

/**
 * Handles HTTP requests for version management
 */
class VersionRequestHandler
{
    public function __construct(
        private WordPressVersionManager $wordPressManager,
        private FormSecurityHelper $formSecurityHelper
    ) {
    }

    /**
     * Parse request for listing versions
     */
    public function parseListVersionsRequest(): ?ListVersionsCommand
    {
        $siteId = $this->wordPressManager->getQueryVar('minisite_id');

        if (! $siteId) {
            return null;
        }

        $currentUser = $this->wordPressManager->getCurrentUser();

        if (! $currentUser || ! $currentUser->ID) {
            return null;
        }

        return new ListVersionsCommand($siteId, (int) $currentUser->ID);
    }

    /**
     * Safely get and sanitize POST data
     */
    private function getPostData(string $key, string $default = ''): string
    {
        return $this->formSecurityHelper->getPostData($key, $default);
    }

    /**
     * Safely get and sanitize POST data as integer
     */
    private function getPostDataInt(string $key, int $default = 0): int
    {
        return $this->formSecurityHelper->getPostDataInt($key, $default);
    }

    /**
     * Verify nonce for form submissions
     */
    private function verifyNonce(): bool
    {
        return $this->formSecurityHelper->verifyNonce('minisite_version', 'nonce');
    }

    /**
     * Parse request for creating draft
     */
    public function parseCreateDraftRequest(): ?CreateDraftCommand
    {
        if (! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }

        // Verify nonce first before processing any form data
        if (! $this->verifyNonce()) {
            return null;
        }

        $siteId = $this->getPostData('site_id');
        if (! $siteId) {
            return null;
        }

        $currentUser = $this->wordPressManager->getCurrentUser();
        if (! $currentUser || ! $currentUser->ID) {
            return null;
        }

        $label = $this->getPostData('label');
        $comment = $this->getPostData('version_comment');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
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
        if (! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }

        // Verify nonce first before processing any form data
        if (! $this->verifyNonce()) {
            return null;
        }

        $siteId = $this->getPostData('site_id');
        $versionId = $this->getPostDataInt('version_id');

        if (! $siteId || ! $versionId) {
            return null;
        }

        $currentUser = $this->wordPressManager->getCurrentUser();
        if (! $currentUser || ! $currentUser->ID) {
            return null;
        }

        return new PublishVersionCommand($siteId, $versionId, (int) $currentUser->ID);
    }

    /**
     * Parse request for rollback version
     */
    public function parseRollbackVersionRequest(): ?RollbackVersionCommand
    {
        if (! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }

        // Verify nonce first before processing any form data
        if (! $this->verifyNonce()) {
            return null;
        }

        $siteId = $this->getPostData('site_id');
        $sourceVersionId = $this->getPostDataInt('source_version_id');

        if (! $siteId || ! $sourceVersionId) {
            return null;
        }

        $currentUser = $this->wordPressManager->getCurrentUser();
        if (! $currentUser || ! $currentUser->ID) {
            return null;
        }

        return new RollbackVersionCommand($siteId, $sourceVersionId, (int) $currentUser->ID);
    }

    /**
     * Safely get form data with default value
     */
    private function getFormData(array $formData, string $key, string $default = ''): string
    {
        $value = sanitize_text_field(wp_unslash($formData[$key] ?? $default));

        return $value ?? $default;
    }

    /**
     * Safely get form data as URL
     */
    private function getFormDataUrl(array $formData, string $key, string $default = ''): string
    {
        $value = esc_url_raw(wp_unslash($formData[$key] ?? $default));

        return $value ?? $default;
    }

    /**
     * Safely get form data as textarea
     */
    private function getFormDataTextarea(array $formData, string $key, string $default = ''): string
    {
        $value = sanitize_textarea_field(wp_unslash($formData[$key] ?? $default));

        return $value ?? $default;
    }

    /**
     * Build site JSON from form data
     */
    private function buildSiteJsonFromForm(array $formData): array
    {
        return array(
            'seo' => array(
                'title' => $this->getFormData($formData, 'seo_title'),
                'description' => $this->getFormDataTextarea($formData, 'seo_description'),
                'keywords' => $this->getFormData($formData, 'seo_keywords'),
            ),
            'brand' => array(
                'name' => $this->getFormData($formData, 'brand_name'),
                'logo' => $this->getFormDataUrl($formData, 'brand_logo'),
                'palette' => $this->getFormData($formData, 'brand_palette', 'blue'),
                'industry' => $this->getFormData($formData, 'brand_industry', 'services'),
            ),
            'hero' => array(
                'heading' => $this->getFormData($formData, 'hero_heading'),
                'subheading' => $this->getFormDataTextarea($formData, 'hero_subheading'),
                'image' => $this->getFormDataUrl($formData, 'hero_image'),
            ),
        );
    }
}

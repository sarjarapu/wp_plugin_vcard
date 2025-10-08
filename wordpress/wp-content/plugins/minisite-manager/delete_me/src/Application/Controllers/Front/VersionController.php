<?php

namespace Minisite\Application\Controllers\Front;

use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

class VersionController
{
    public function __construct(
        private MinisiteRepository $minisiteRepository,
        private VersionRepository $versionRepository
    ) {
    }

    /**
     * Get all versions for a minisite
     */
    public function handleListVersions(): void
    {
        if (! is_user_logged_in()) {
            $redirect_url = home_url(
                '/account/login?redirect_to=' . urlencode(
                    isset($_SERVER['REQUEST_URI']) ?
                    sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : ''
                )
            );
            wp_redirect($redirect_url);
            exit;
        }

        $siteId = get_query_var('minisite_site_id');
        if (! $siteId) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        $currentUser = wp_get_current_user();
        $minisite    = $this->minisiteRepository->findById($siteId);

        if (! $minisite) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        // Check ownership
        if ($minisite->createdBy !== (int) $currentUser->ID) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        $versions = $this->versionRepository->findByMinisiteId($siteId);

        // Render version history page
        if (class_exists('Timber\\Timber')) {
            $base                      = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
            \Timber\Timber::$locations = array_values(
                array_unique(
                    array_merge(
                        \Timber\Timber::$locations ?? array(),
                        array( $base )
                    )
                )
            );

            \Timber\Timber::render(
                'account-sites-versions.twig',
                array(
                    'page_title' => 'Version History: ' . $minisite->title,
                    'profile'    => $minisite,
                    'versions'   => $versions,
                )
            );
            return;
        }

        // Fallback
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><h1>Version History: ' .
             esc_html($minisite->title) . '</h1>';
        echo '<p>Version history not available (Timber required).</p>';
    }

    /**
     * Create a new draft version
     */
    public function handleCreateDraft(): void
    {
        if (! is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if (! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'minisite_version')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $siteId = sanitize_text_field(wp_unslash($_POST['site_id'] ?? ''));
        if (! $siteId) {
            wp_send_json_error('Invalid site ID', 400);
            return;
        }

        $currentUser = wp_get_current_user();
        $minisite    = $this->minisiteRepository->findById($siteId);

        if (! $minisite) {
            wp_send_json_error('Site not found', 404);
            return;
        }

        // Check ownership
        if ($minisite->createdBy !== (int) $currentUser->ID) {
            wp_send_json_error('Access denied', 403);
            return;
        }

        try {
            $nextVersion = $this->versionRepository->getNextVersionNumber($siteId);

            error_log('Form data: ' . print_r($_POST, true));

            $version = new \Minisite\Domain\Entities\Version(
                id: null,
                minisiteId: $siteId,
                versionNumber: $nextVersion,
                status: 'draft',
                label: sanitize_text_field(wp_unslash($_POST['label'] ?? "Version {$nextVersion}")),
                comment: sanitize_textarea_field(wp_unslash($_POST['version_comment'] ?? '')),
                createdBy: (int) $currentUser->ID,
                createdAt: null,
                publishedAt: null,
                sourceVersionId: null,
                siteJson: $this->buildSiteJsonFromForm($_POST)
            );

            $savedVersion = $this->versionRepository->save($version);

            wp_send_json_success(
                array(
                    'id'             => $savedVersion->id,
                    'version_number' => $savedVersion->versionNumber,
                    'status'         => $savedVersion->status,
                    'message'        => 'Draft created successfully',
                )
            );
        } catch (\Exception $e) {
            wp_send_json_error('Failed to create draft: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Publish a draft version
     */
    public function handlePublishVersion(): void
    {
        if (! is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if (! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'minisite_version')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $siteId    = sanitize_text_field(wp_unslash($_POST['site_id'] ?? ''));
        $versionId = (int) ( sanitize_text_field(wp_unslash($_POST['version_id'] ?? 0)) );

        if (! $siteId || ! $versionId) {
            wp_send_json_error('Invalid parameters', 400);
            return;
        }

        $currentUser = wp_get_current_user();
        $minisite    = $this->minisiteRepository->findById($siteId);

        if (! $minisite) {
            wp_send_json_error('Site not found', 404);
            return;
        }

        // Check ownership
        if ($minisite->createdBy !== (int) $currentUser->ID) {
            wp_send_json_error('Access denied', 403);
            return;
        }

        $version = $this->versionRepository->findById($versionId);
        if (! $version || $version->minisiteId !== $siteId) {
            wp_send_json_error('Version not found', 404);
            return;
        }

        if ($version->status !== 'draft') {
            wp_send_json_error('Only draft versions can be published', 400);
            return;
        }

        try {
            $this->publishVersion($siteId, $versionId);

            wp_send_json_success(
                array(
                    'message'              => 'Version published successfully',
                    'published_version_id' => $versionId,
                )
            );
        } catch (\Exception $e) {
            wp_send_json_error('Failed to publish version: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a rollback draft from a previous version
     */
    public function handleRollbackVersion(): void
    {
        if (! is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if (! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'minisite_version')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $siteId          = sanitize_text_field(wp_unslash($_POST['site_id'] ?? ''));
        $sourceVersionId = (int) ( sanitize_text_field(wp_unslash($_POST['source_version_id'] ?? 0)) );

        if (! $siteId || ! $sourceVersionId) {
            wp_send_json_error('Invalid parameters', 400);
            return;
        }

        $currentUser = wp_get_current_user();
        $minisite    = $this->minisiteRepository->findById($siteId);

        if (! $minisite) {
            wp_send_json_error('Site not found', 404);
            return;
        }

        // Check ownership
        if ($minisite->createdBy !== (int) $currentUser->ID) {
            wp_send_json_error('Access denied', 403);
            return;
        }

        $sourceVersion = $this->versionRepository->findById($sourceVersionId);
        if (! $sourceVersion || $sourceVersion->minisiteId !== $siteId) {
            wp_send_json_error('Source version not found', 404);
            return;
        }

        try {
            $rollbackVersion = $this->createRollbackVersion($siteId, $sourceVersionId, (int) $currentUser->ID);

            wp_send_json_success(
                array(
                    'id'             => $rollbackVersion->id,
                    'version_number' => $rollbackVersion->versionNumber,
                    'status'         => $rollbackVersion->status,
                    'message'        => 'Rollback draft created',
                )
            );
        } catch (\Exception $e) {
            wp_send_json_error('Failed to create rollback: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Publish a version (atomic operation)
     */
    private function publishVersion(string $minisiteId, int $versionId): void
    {
        global $wpdb;

        // Get the version to publish
        $version = $this->versionRepository->findById($versionId);
        if (! $version) {
            throw new \Exception('Version not found');
        }

        db::query('START TRANSACTION');

        try {
            // Move current published version to draft
            db::query(
                "UPDATE {$wpdb->prefix}minisite_versions 
                 SET status = 'draft' 
                 WHERE minisite_id = %s AND status = 'published'",
                [$minisiteId]
            );

            // Publish new version
            db::query(
                "UPDATE {$wpdb->prefix}minisite_versions SET status = 'published', published_at = NOW() WHERE id = %d",
                [$versionId]
            );

            // Update profile with published version data and current version ID
            db::query(
                "UPDATE {$wpdb->prefix}minisites 
                 SET site_json = %s, title = %s, name = %s, city = %s, region = %s, 
                     country_code = %s, postal_code = %s, site_template = %s, palette = %s, 
                     industry = %s, default_locale = %s, schema_version = %d, site_version = %d, 
                     search_terms = %s, _minisite_current_version_id = %d, updated_at = NOW() 
                 WHERE id = %s",
                [
                    wp_json_encode($version->siteJson),
                    $version->title,
                    $version->name,
                    $version->city,
                    $version->region,
                    $version->countryCode,
                    $version->postalCode,
                    $version->siteTemplate,
                    $version->palette,
                    $version->industry,
                    $version->defaultLocale,
                    $version->schemaVersion,
                    $version->siteVersion,
                    $version->searchTerms,
                    $versionId,
                    $minisiteId
                ]
            );

            // Update location_point if geo data exists
            if ($version->geo && $version->geo->lat && $version->geo->lng) {
                db::query(
                    "UPDATE {$wpdb->prefix}minisites SET location_point = POINT(%f, %f) WHERE id = %s",
                    [$version->geo->lng, $version->geo->lat, $minisiteId]
                );
            }

            db::query('COMMIT');
        } catch (\Exception $e) {
            db::query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Create a rollback draft from a source version
     */
    private function createRollbackVersion(
        string $minisiteId,
        int $sourceVersionId,
        int $userId
    ): \Minisite\Domain\Entities\Version {
        $sourceVersion = $this->versionRepository->findById($sourceVersionId);
        $nextVersion   = $this->versionRepository->getNextVersionNumber($minisiteId);

        $rollbackVersion = new \Minisite\Domain\Entities\Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: $nextVersion,
            status: 'draft',
            label: "Rollback to v{$sourceVersion->versionNumber}",
            comment: "Rollback from version {$sourceVersion->versionNumber}",
            createdBy: $userId,
            createdAt: null,
            publishedAt: null,
            sourceVersionId: $sourceVersionId,
            siteJson: $sourceVersion->siteJson,
            // Copy all profile fields from source version to ensure preview works correctly
            slugs: $sourceVersion->slugs,
            title: $sourceVersion->title,
            name: $sourceVersion->name,
            city: $sourceVersion->city,
            region: $sourceVersion->region,
            countryCode: $sourceVersion->countryCode,
            postalCode: $sourceVersion->postalCode,
            geo: $sourceVersion->geo,
            siteTemplate: $sourceVersion->siteTemplate,
            palette: $sourceVersion->palette,
            industry: $sourceVersion->industry,
            defaultLocale: $sourceVersion->defaultLocale,
            schemaVersion: $sourceVersion->schemaVersion,
            siteVersion: $sourceVersion->siteVersion,
            searchTerms: $sourceVersion->searchTerms
        );

        return $this->versionRepository->save($rollbackVersion);
    }

    /**
     * Build site JSON from form data (copied from SitesController)
     */
    private function buildSiteJsonFromForm(array $formData): array
    {
        // This is a simplified version - you may want to expand this based on your form structure
        return array(
            'seo'   => array(
                'title'       => sanitize_text_field($formData['seo_title'] ?? ''),
                'description' => sanitize_textarea_field($formData['seo_description'] ?? ''),
                'keywords'    => sanitize_text_field($formData['seo_keywords'] ?? ''),
            ),
            'brand' => array(
                'name'     => sanitize_text_field($formData['brand_name'] ?? ''),
                'logo'     => esc_url_raw($formData['brand_logo'] ?? ''),
                'palette'  => sanitize_text_field($formData['brand_palette'] ?? 'blue'),
                'industry' => sanitize_text_field($formData['brand_industry'] ?? 'services'),
            ),
            'hero'  => array(
                'heading'    => sanitize_text_field($formData['hero_heading'] ?? ''),
                'subheading' => sanitize_textarea_field($formData['hero_subheading'] ?? ''),
                'image'      => esc_url_raw($formData['hero_image'] ?? ''),
            ),
            // Add more sections as needed
        );
    }
}

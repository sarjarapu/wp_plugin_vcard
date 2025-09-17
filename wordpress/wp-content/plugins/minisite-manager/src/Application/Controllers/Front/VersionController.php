<?php
namespace Minisite\Application\Controllers\Front;

use Minisite\Infrastructure\Persistence\Repositories\ProfileRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;

class VersionController
{
    public function __construct(
        private ProfileRepository $profileRepository,
        private VersionRepository $versionRepository
    ) {}

    /**
     * Get all versions for a minisite
     */
    public function handleListVersions(): void
    {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/account/login?redirect_to=' . urlencode($_SERVER['REQUEST_URI'])));
            exit;
        }

        $siteId = (int) get_query_var('minisite_site_id');
        if (!$siteId) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        $currentUser = wp_get_current_user();
        $profile = $this->profileRepository->findById($siteId);
        
        if (!$profile) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        // Check ownership
        if ($profile->createdBy !== (int) $currentUser->ID) {
            wp_redirect(home_url('/account/sites'));
            exit;
        }

        $versions = $this->versionRepository->findByMinisiteId($siteId);

        // Render version history page
        if (class_exists('Timber\\Timber')) {
            $base = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/views';
            \Timber\Timber::$locations = array_values(array_unique(array_merge(\Timber\Timber::$locations ?? [], [$base])));

            \Timber\Timber::render('account-sites-versions.twig', [
                'page_title' => 'Version History: ' . $profile->title,
                'profile' => $profile,
                'versions' => $versions,
            ]);
            return;
        }

        // Fallback
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><h1>Version History: ' . htmlspecialchars($profile->title) . '</h1>';
        echo '<p>Version history not available (Timber required).</p>';
    }

    /**
     * Create a new draft version
     */
    public function handleCreateDraft(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'minisite_version')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $siteId = (int) ($_POST['site_id'] ?? 0);
        if (!$siteId) {
            wp_send_json_error('Invalid site ID', 400);
            return;
        }

        $currentUser = wp_get_current_user();
        $profile = $this->profileRepository->findById($siteId);
        
        if (!$profile) {
            wp_send_json_error('Site not found', 404);
            return;
        }

        // Check ownership
        if ($profile->createdBy !== (int) $currentUser->ID) {
            wp_send_json_error('Access denied', 403);
            return;
        }

        try {
            $nextVersion = $this->versionRepository->getNextVersionNumber($siteId);
            
            $version = new \Minisite\Domain\Entities\Version(
                id: null,
                minisiteId: $siteId,
                versionNumber: $nextVersion,
                status: 'draft',
                label: sanitize_text_field($_POST['label'] ?? "Version {$nextVersion}"),
                comment: sanitize_textarea_field($_POST['comment'] ?? ''),
                dataJson: $this->buildSiteJsonFromForm($_POST),
                createdBy: (int) $currentUser->ID,
                createdAt: null,
                publishedAt: null,
                sourceVersionId: null
            );

            $savedVersion = $this->versionRepository->save($version);

            wp_send_json_success([
                'id' => $savedVersion->id,
                'version_number' => $savedVersion->versionNumber,
                'status' => $savedVersion->status,
                'message' => 'Draft created successfully'
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Failed to create draft: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Publish a draft version
     */
    public function handlePublishVersion(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'minisite_version')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $siteId = (int) ($_POST['site_id'] ?? 0);
        $versionId = (int) ($_POST['version_id'] ?? 0);
        
        if (!$siteId || !$versionId) {
            wp_send_json_error('Invalid parameters', 400);
            return;
        }

        $currentUser = wp_get_current_user();
        $profile = $this->profileRepository->findById($siteId);
        
        if (!$profile) {
            wp_send_json_error('Site not found', 404);
            return;
        }

        // Check ownership
        if ($profile->createdBy !== (int) $currentUser->ID) {
            wp_send_json_error('Access denied', 403);
            return;
        }

        $version = $this->versionRepository->findById($versionId);
        if (!$version || $version->minisiteId !== $siteId) {
            wp_send_json_error('Version not found', 404);
            return;
        }

        if ($version->status !== 'draft') {
            wp_send_json_error('Only draft versions can be published', 400);
            return;
        }

        try {
            $this->publishVersion($siteId, $versionId);
            
            wp_send_json_success([
                'message' => 'Version published successfully',
                'published_version_id' => $versionId
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Failed to publish version: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a rollback draft from a previous version
     */
    public function handleRollbackVersion(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not authenticated', 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'minisite_version')) {
            wp_send_json_error('Security check failed', 403);
            return;
        }

        $siteId = (int) ($_POST['site_id'] ?? 0);
        $sourceVersionId = (int) ($_POST['source_version_id'] ?? 0);
        
        if (!$siteId || !$sourceVersionId) {
            wp_send_json_error('Invalid parameters', 400);
            return;
        }

        $currentUser = wp_get_current_user();
        $profile = $this->profileRepository->findById($siteId);
        
        if (!$profile) {
            wp_send_json_error('Site not found', 404);
            return;
        }

        // Check ownership
        if ($profile->createdBy !== (int) $currentUser->ID) {
            wp_send_json_error('Access denied', 403);
            return;
        }

        $sourceVersion = $this->versionRepository->findById($sourceVersionId);
        if (!$sourceVersion || $sourceVersion->minisiteId !== $siteId) {
            wp_send_json_error('Source version not found', 404);
            return;
        }

        try {
            $rollbackVersion = $this->createRollbackVersion($siteId, $sourceVersionId, (int) $currentUser->ID);
            
            wp_send_json_success([
                'id' => $rollbackVersion->id,
                'version_number' => $rollbackVersion->versionNumber,
                'status' => $rollbackVersion->status,
                'message' => 'Rollback draft created'
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Failed to create rollback: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Publish a version (atomic operation)
     */
    private function publishVersion(int $minisiteId, int $versionId): void
    {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Move current published version to draft
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}minisite_versions 
                 SET status = 'draft' 
                 WHERE minisite_id = %d AND status = 'published'",
                $minisiteId
            ));
            
            // Publish new version
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}minisite_versions 
                 SET status = 'published', published_at = NOW() 
                 WHERE id = %d",
                $versionId
            ));
            
            // Update profile with published version data and current version ID
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}minisite_profiles 
                 SET site_json = %s, _minisite_current_version_id = %d, updated_at = NOW() 
                 WHERE id = %d",
                wp_json_encode($version->dataJson), $versionId, $minisiteId
            ));
            
            $wpdb->query('COMMIT');
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Create a rollback draft from a source version
     */
    private function createRollbackVersion(int $minisiteId, int $sourceVersionId, int $userId): \Minisite\Domain\Entities\Version
    {
        $sourceVersion = $this->versionRepository->findById($sourceVersionId);
        $nextVersion = $this->versionRepository->getNextVersionNumber($minisiteId);
        
        $rollbackVersion = new \Minisite\Domain\Entities\Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: $nextVersion,
            status: 'draft',
            label: "Rollback to v{$sourceVersion->versionNumber}",
            comment: "Rollback from version {$sourceVersion->versionNumber}",
            dataJson: $sourceVersion->dataJson,
            createdBy: $userId,
            createdAt: null,
            publishedAt: null,
            sourceVersionId: $sourceVersionId
        );
        
        return $this->versionRepository->save($rollbackVersion);
    }

    /**
     * Build site JSON from form data (copied from SitesController)
     */
    private function buildSiteJsonFromForm(array $formData): array
    {
        // This is a simplified version - you may want to expand this based on your form structure
        return [
            'seo' => [
                'title' => sanitize_text_field($formData['seo_title'] ?? ''),
                'description' => sanitize_textarea_field($formData['seo_description'] ?? ''),
                'keywords' => sanitize_text_field($formData['seo_keywords'] ?? ''),
            ],
            'brand' => [
                'name' => sanitize_text_field($formData['brand_name'] ?? ''),
                'logo' => esc_url_raw($formData['brand_logo'] ?? ''),
                'palette' => sanitize_text_field($formData['brand_palette'] ?? 'blue'),
                'industry' => sanitize_text_field($formData['brand_industry'] ?? 'services'),
            ],
            'hero' => [
                'heading' => sanitize_text_field($formData['hero_heading'] ?? ''),
                'subheading' => sanitize_textarea_field($formData['hero_subheading'] ?? ''),
                'image' => esc_url_raw($formData['hero_image'] ?? ''),
            ],
            // Add more sections as needed
        ];
    }
}

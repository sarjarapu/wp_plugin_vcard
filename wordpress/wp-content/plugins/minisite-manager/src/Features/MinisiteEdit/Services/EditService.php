<?php

namespace Minisite\Features\MinisiteEdit\Services;

use Minisite\Domain\Entities\Version;
use Minisite\Domain\Services\MinisiteDatabaseCoordinator;
use Minisite\Domain\Services\MinisiteFormProcessor;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Edit Service
 *
 * SINGLE RESPONSIBILITY: Handle business logic for minisite editing
 * - Manages edit form data processing
 * - Handles version creation and updates
 * - Coordinates between repositories and WordPress functions
 */
class EditService
{
    private LoggerInterface $logger;

    public function __construct(
        private WordPressEditManager $wordPressManager
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('minisite-edit-service');
    }

    /**
     * Get minisite data for editing
     */
    public function getMinisiteForEditing(string $siteId, ?string $versionId = null): object
    {
        $minisite = $this->wordPressManager->findMinisiteById($siteId);
        if (! $minisite) {
            throw new \RuntimeException('Minisite not found');
        }

        // Check ownership
        $currentUser = $this->wordPressManager->getCurrentUser();
        if (! $this->wordPressManager->userOwnsMinisite($minisite, (int) $currentUser->ID)) {
            throw new \RuntimeException('Access denied');
        }

        // Get version to edit
        $editingVersion = $this->getEditingVersion($siteId, $versionId);
        $latestDraft = $this->wordPressManager->findLatestDraft($siteId);

        // Create profile object for form
        $profileForForm = $this->createProfileForForm($minisite, $editingVersion);

        return (object) array(
            'minisite' => $minisite,
            'editingVersion' => $editingVersion,
            'latestDraft' => $latestDraft,
            'profileForForm' => $profileForForm,
            'siteJson' => $editingVersion ? $editingVersion->siteJson : $minisite->siteJson,
            'successMessage' => $this->getSuccessMessage(),
            'errorMessage' => '',
        );
    }


    /**
     * Save draft version
     */
    public function saveDraft(string $siteId, array $formData): object
    {
        // Log detailed form data for debugging
        $this->logger->debug('EditService::saveDraft() called', array(
            'site_id' => $siteId,
            'form_data_count' => count($formData),
            'business_name' => $formData['business_name'] ?? 'NOT_SET',
            'business_city' => $formData['business_city'] ?? 'NOT_SET',
            'business_region' => $formData['business_region'] ?? 'NOT_SET',
            'business_country' => $formData['business_country'] ?? 'NOT_SET',
            'seo_title' => $formData['seo_title'] ?? 'NOT_SET',
            'seo_description' => $formData['seo_description'] ?? 'NOT_SET',
            'seo_keywords' => $formData['seo_keywords'] ?? 'NOT_SET',
            'seo_favicon' => $formData['seo_favicon'] ?? 'NOT_SET',
            'brand_name' => $formData['brand_name'] ?? 'NOT_SET',
            'brand_logo' => $formData['brand_logo'] ?? 'NOT_SET',
            'brand_industry' => $formData['brand_industry'] ?? 'NOT_SET',
            'brand_palette' => $formData['brand_palette'] ?? 'NOT_SET',
            'hero_heading' => $formData['hero_heading'] ?? 'NOT_SET',
            'hero_subheading' => $formData['hero_subheading'] ?? 'NOT_SET',
            'hero_image' => $formData['hero_image'] ?? 'NOT_SET',
            'about_html' => $formData['about_html'] ?? 'NOT_SET',
            'whyus_title' => $formData['whyus_title'] ?? 'NOT_SET',
            'whyus_html' => $formData['whyus_html'] ?? 'NOT_SET',
            'contact_email' => $formData['contact_email'] ?? 'NOT_SET',
            'contact_phone_text' => $formData['contact_phone_text'] ?? 'NOT_SET',
            'contact_phone_link' => $formData['contact_phone_link'] ?? 'NOT_SET',
            'contact_lat' => $formData['contact_lat'] ?? 'NOT_SET',
            'contact_lng' => $formData['contact_lng'] ?? 'NOT_SET',
            'site_template' => $formData['site_template'] ?? 'NOT_SET',
            'default_locale' => $formData['default_locale'] ?? 'NOT_SET',
            'search_terms' => $formData['search_terms'] ?? 'NOT_SET',
            'version_label' => $formData['version_label'] ?? 'NOT_SET',
            'version_comment' => $formData['version_comment'] ?? 'NOT_SET',
            'has_nonce' => isset($formData['minisite_edit_nonce']),
            'nonce_value' => $formData['minisite_edit_nonce'] ?? 'MISSING',
        ));

        try {
            // Create shared components
            $formProcessor = new MinisiteFormProcessor($this->wordPressManager);
            $dbCoordinator = new MinisiteDatabaseCoordinator($this->wordPressManager);

            // Validate form data
            $errors = $formProcessor->validateFormData($formData);
            if (! empty($errors)) {
                return (object) array('success' => false, 'errors' => $errors);
            }

            // Verify nonce
            if (
                ! $this->wordPressManager->verifyNonce(
                    $this->wordPressManager->sanitizeTextField($formData['minisite_edit_nonce']),
                    'minisite_edit'
                )
            ) {
                return (object) array('success' => false, 'errors' => array('Security check failed. Please try again.'));
            }

            $minisite = $this->wordPressManager->findMinisiteById($siteId);
            $currentUser = $this->wordPressManager->getCurrentUser();

            // Determine operation type based on minisite status
            $hasBeenPublished = $this->wordPressManager->hasBeenPublished($siteId);
            $operationType = $hasBeenPublished ? 'edit_published' : 'edit_draft';

            // Use shared database coordinator
            $this->logger->info('Calling MinisiteDatabaseCoordinator::saveMinisiteData', array(
                'site_id' => $siteId,
                'form_data_count' => count($formData),
                'operation_type' => $operationType,
                'has_been_published' => $hasBeenPublished,
                'minisite_status' => $minisite->status ?? 'unknown',
                'call_type' => 'call_database_coordinator',
            ));

            $result = $dbCoordinator->saveMinisiteData(
                $siteId,
                $formData,
                $operationType,
                $minisite,
                $currentUser,
                $hasBeenPublished
            );

            $this->logger->info('MinisiteDatabaseCoordinator::saveMinisiteData completed', array(
                'site_id' => $siteId,
                'success' => $result->success ?? false,
                'has_errors' => ! empty($result->errors ?? array()),
                'error_count' => count($result->errors ?? array()),
                'has_redirect_url' => ! empty($result->redirectUrl ?? ''),
                'operation_type' => 'database_coordinator_result',
            ));

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('EditService::saveDraft() - Exception caught', array(
                'site_id' => $siteId,
                'form_data_count' => count($formData),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'operation_type' => 'edit_service_exception',
            ));

            return (object) array(
                'success' => false,
                'errors' => array('Failed to save draft: ' . $e->getMessage()),
            );
        }
    }

    /**
     * Get editing version
     */
    private function getEditingVersion(string $siteId, ?string $versionId): ?object
    {
        if ($versionId === 'latest' || ! $versionId) {
            return $this->wordPressManager->getLatestDraftForEditing($siteId);
        }

        $version = $this->wordPressManager->findVersionById((int) $versionId);
        if (! $version || $version->minisiteId !== $siteId) {
            $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl("/account/sites/{$siteId}/edit"));
        }

        return $version;
    }

    /**
     * Create profile object for form
     */
    private function createProfileForForm(object $minisite, ?object $editingVersion): object
    {
        $profileForForm = clone $minisite;

        if ($editingVersion) {
            $profileForForm->title = $editingVersion->title ?? $minisite->title;
            $profileForForm->name = $editingVersion->name ?? $minisite->name;
            $profileForForm->city = $editingVersion->city ?? $minisite->city;
            $profileForForm->region = $editingVersion->region ?? $minisite->region;
            $profileForForm->countryCode = $editingVersion->countryCode ?? $minisite->countryCode;
            $profileForForm->postalCode = $editingVersion->postalCode ?? $minisite->postalCode;
            $profileForForm->siteTemplate = $editingVersion->siteTemplate ?? $minisite->siteTemplate;
            $profileForForm->palette = $editingVersion->palette ?? $minisite->palette;
            $profileForForm->industry = $editingVersion->industry ?? $minisite->industry;
            $profileForForm->defaultLocale = $editingVersion->defaultLocale ?? $minisite->defaultLocale;
            $profileForForm->searchTerms = $editingVersion->searchTerms ?? $minisite->searchTerms;
        }

        return $profileForForm;
    }

    /**
     * Get success message
     */
    private function getSuccessMessage(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for success message doesn't require nonce verification
        if (isset($_GET['draft_saved']) && $_GET['draft_saved'] === '1') {
            return 'Draft saved successfully!';
        }

        return '';
    }
}

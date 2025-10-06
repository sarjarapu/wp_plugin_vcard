<?php

namespace Minisite\Features\MinisiteEditor\Services;

use Minisite\Features\MinisiteEditor\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteEditor\Commands\CreateMinisiteCommand;
use Minisite\Features\MinisiteEditor\Commands\EditMinisiteCommand;
use Minisite\Features\MinisiteEditor\Commands\PreviewMinisiteCommand;
use Minisite\Features\MinisiteEditor\WordPress\WordPressMinisiteManager;

/**
 * Minisite Editor Service
 *
 * Handles all minisite editing business logic including listing, creating,
 * editing, and previewing minisites.
 */
final class MinisiteEditorService
{
    public function __construct(
        private WordPressMinisiteManager $wordPressManager
    ) {
    }

    /**
     * List user's minisites
     *
     * @param ListMinisitesCommand $command
     * @return array{success: bool, minisites?: array, error?: string}
     */
    public function listMinisites(ListMinisitesCommand $command): array
    {
        try {
            $minisites = $this->wordPressManager->listMinisitesByOwner(
                $command->userId,
                $command->limit,
                $command->offset
            );

            return [
                'success' => true,
                'minisites' => $minisites
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve minisites: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a new minisite
     *
     * @param CreateMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function createMinisite(CreateMinisiteCommand $command): array
    {
        try {
            // Validate required fields
            if (!$this->validateCreateMinisiteData($command)) {
                return [
                    'success' => false,
                    'error' => 'Please provide all required fields.'
                ];
            }

            // Check if slugs are available
            if (!$this->wordPressManager->areSlugsAvailable($command->businessSlug, $command->locationSlug)) {
                return [
                    'success' => false,
                    'error' => 'The business and location combination is already taken.'
                ];
            }

            $minisite = $this->wordPressManager->createMinisite($command);

            return [
                'success' => true,
                'minisite' => $minisite
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to create minisite: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Edit an existing minisite
     *
     * @param EditMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function editMinisite(EditMinisiteCommand $command): array
    {
        try {
            // Validate required fields
            if (!$this->validateEditMinisiteData($command)) {
                return [
                    'success' => false,
                    'error' => 'Please provide all required fields.'
                ];
            }

            // Check if user has access to this minisite
            if (!$this->wordPressManager->hasUserAccess($command->siteId, $command->userId)) {
                return [
                    'success' => false,
                    'error' => 'You do not have permission to edit this minisite.'
                ];
            }

            $minisite = $this->wordPressManager->editMinisite($command);

            return [
                'success' => true,
                'minisite' => $minisite
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to edit minisite: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Preview a minisite
     *
     * @param PreviewMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function previewMinisite(PreviewMinisiteCommand $command): array
    {
        try {
            // Check if user has access to this minisite
            if (!$this->wordPressManager->hasUserAccess($command->siteId, $command->userId)) {
                return [
                    'success' => false,
                    'error' => 'You do not have permission to preview this minisite.'
                ];
            }

            $minisite = $this->wordPressManager->getMinisiteForPreview($command->siteId, $command->versionId);

            if (!$minisite) {
                return [
                    'success' => false,
                    'error' => 'Minisite not found.'
                ];
            }

            return [
                'success' => true,
                'minisite' => $minisite
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to preview minisite: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate create minisite data
     */
    private function validateCreateMinisiteData(CreateMinisiteCommand $command): bool
    {
        return !empty($command->businessSlug) &&
               !empty($command->locationSlug) &&
               !empty($command->businessName) &&
               !empty($command->businessCity) &&
               !empty($command->businessRegion) &&
               !empty($command->businessCountry);
    }

    /**
     * Validate edit minisite data
     */
    private function validateEditMinisiteData(EditMinisiteCommand $command): bool
    {
        return !empty($command->siteId) &&
               !empty($command->businessName) &&
               !empty($command->businessCity) &&
               !empty($command->businessRegion) &&
               !empty($command->businessCountry);
    }
}

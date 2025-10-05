<?php

namespace Minisite\Features\MinisiteDisplay\Services;

use Minisite\Features\MinisiteDisplay\Commands\DisplayMinisiteCommand;
use Minisite\Features\MinisiteDisplay\WordPress\WordPressMinisiteManager;

/**
 * Minisite Display Service
 *
 * SINGLE RESPONSIBILITY: Handle minisite display business logic
 * - Manages minisite data retrieval and validation
 * - Handles display logic and error conditions
 * - Provides clean interface for display operations
 */
final class MinisiteDisplayService
{
    public function __construct(
        private WordPressMinisiteManager $wordPressManager
    ) {
    }

    /**
     * Get minisite for display
     *
     * @param DisplayMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function getMinisiteForDisplay(DisplayMinisiteCommand $command): array
    {
        try {
            $minisite = $this->wordPressManager->findMinisiteBySlugs(
                $command->businessSlug,
                $command->locationSlug
            );

            if (!$minisite) {
                return [
                    'success' => false,
                    'error' => 'Minisite not found'
                ];
            }

            return [
                'success' => true,
                'minisite' => $minisite
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error retrieving minisite: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if minisite exists
     *
     * @param DisplayMinisiteCommand $command
     * @return bool
     */
    public function minisiteExists(DisplayMinisiteCommand $command): bool
    {
        return $this->wordPressManager->minisiteExists(
            $command->businessSlug,
            $command->locationSlug
        );
    }
}

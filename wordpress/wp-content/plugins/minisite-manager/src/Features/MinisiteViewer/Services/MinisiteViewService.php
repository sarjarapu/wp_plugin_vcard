<?php

namespace Minisite\Features\MinisiteViewer\Services;

use Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;

/**
 * Minisite View Service
 *
 * SINGLE RESPONSIBILITY: Handle minisite view business logic
 * - Manages minisite data retrieval and validation
 * - Handles view logic and error conditions
 * - Provides clean interface for view operations
 */
final class MinisiteViewService
{
    public function __construct(
        private WordPressMinisiteManager $wordPressManager
    ) {
    }

    /**
     * Get minisite for view
     *
     * @param ViewMinisiteCommand $command
     * @return array{success: bool, minisite?: object, error?: string}
     */
    public function getMinisiteForView(ViewMinisiteCommand $command): array
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
     * @param ViewMinisiteCommand $command
     * @return bool
     */
    public function minisiteExists(ViewMinisiteCommand $command): bool
    {
        return $this->wordPressManager->minisiteExists(
            $command->businessSlug,
            $command->locationSlug
        );
    }
}

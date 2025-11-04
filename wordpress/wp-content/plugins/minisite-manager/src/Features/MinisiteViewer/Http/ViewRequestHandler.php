<?php

namespace Minisite\Features\MinisiteViewer\Http;

use Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;

/**
 * View Request Handler
 *
 * SINGLE RESPONSIBILITY: Handle HTTP requests for minisite view
 * - Extracts and validates request data
 * - Creates command objects from HTTP requests
 * - Handles request validation and sanitization
 */
class ViewRequestHandler
{
    public function __construct(
        private WordPressMinisiteManager $wordPressManager
    ) {
    }

    /**
     * Handle view request from URL parameters
     *
     * @return ViewMinisiteCommand|null
     */
    public function handleViewRequest(): ?ViewMinisiteCommand
    {
        $businessSlug = $this->wordPressManager->getQueryVar('minisite_biz');
        $locationSlug = $this->wordPressManager->getQueryVar('minisite_loc');

        if (!$businessSlug || !$locationSlug) {
            return null;
        }

        return new ViewMinisiteCommand(
            $this->sanitizeSlug($businessSlug),
            $this->sanitizeSlug($locationSlug)
        );
    }

    /**
     * Get business slug from query vars
     *
     * @return string|null
     */
    public function getBusinessSlug(): ?string
    {
        $slug = $this->wordPressManager->getQueryVar('minisite_biz');
        return $this->sanitizeSlug($slug);
    }

    /**
     * Get location slug from query vars
     *
     * @return string|null
     */
    public function getLocationSlug(): ?string
    {
        $slug = $this->wordPressManager->getQueryVar('minisite_loc');
        return $this->sanitizeSlug($slug);
    }

    /**
     * Helper function to sanitize slug values
     *
     * @param string|null $slug
     * @return string|null
     */
    private function sanitizeSlug(?string $slug): ?string
    {
        return $slug ? $this->wordPressManager->sanitizeTextField($slug) : null;
    }
}

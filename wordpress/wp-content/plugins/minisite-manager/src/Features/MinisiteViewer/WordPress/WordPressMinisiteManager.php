<?php

namespace Minisite\Features\MinisiteViewer\WordPress;

use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

/**
 * WordPress Minisite Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific minisite operations
 * - Manages minisite data retrieval
 * - Handles WordPress database interactions
 * - Provides clean interface for minisite operations
 */
final class WordPressMinisiteManager
{
    private ?MinisiteRepository $repository = null;

    /**
     * Get minisite repository instance
     */
    private function getRepository(): MinisiteRepository
    {
        if ($this->repository === null) {
            $this->repository = new MinisiteRepository(db::getWpdb());
        }
        return $this->repository;
    }

    /**
     * Find minisite by business and location slugs
     *
     * @param string $businessSlug
     * @param string $locationSlug
     * @return object|null
     */
    public function findMinisiteBySlugs(string $businessSlug, string $locationSlug): ?object
    {
        $slugPair = new SlugPair($businessSlug, $locationSlug);
        return $this->getRepository()->findBySlugs($slugPair);
    }

    /**
     * Check if minisite exists
     *
     * @param string $businessSlug
     * @param string $locationSlug
     * @return bool
     */
    public function minisiteExists(string $businessSlug, string $locationSlug): bool
    {
        return $this->findMinisiteBySlugs($businessSlug, $locationSlug) !== null;
    }

    /**
     * Get query variable
     *
     * @param string $var Variable name
     * @param mixed $default Default value
     * @return mixed Query variable value
     */
    public function getQueryVar(string $var, $default = '')
    {
        return get_query_var($var, $default);
    }

    /**
     * Sanitize text field
     *
     * @param string $text Text to sanitize
     * @return string Sanitized text
     */
    public function sanitizeTextField(string $text): string
    {
        return sanitize_text_field($text);
    }
}

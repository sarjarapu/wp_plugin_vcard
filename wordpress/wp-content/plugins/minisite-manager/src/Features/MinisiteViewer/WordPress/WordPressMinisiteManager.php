<?php

namespace Minisite\Features\MinisiteViewer\WordPress;

use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

/**
 * WordPress Minisite Manager
 *
 * SINGLE RESPONSIBILITY: Handle WordPress-specific minisite operations
 * - Manages minisite data retrieval
 * - Handles WordPress database interactions
 * - Provides clean interface for minisite operations
 */
class WordPressMinisiteManager extends BaseWordPressManager
{
    private ?MinisiteRepository $repository = null;

    /**
     * Constructor
     *
     * @param TerminationHandlerInterface $terminationHandler Handler for terminating script execution
     */
    public function __construct(TerminationHandlerInterface $terminationHandler)
    {
        parent::__construct($terminationHandler);
    }

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

    // ===== AUTHENTICATION METHODS =====

    /**
     * Check if user is logged in
     *
     * @return bool
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user
     *
     * @return object|null
     */
    public function getCurrentUser(): ?object
    {
        return wp_get_current_user();
    }

    /**
     * Redirect to URL
     * Uses base class redirect() method which handles termination
     */
    public function redirect(string $url, int $status = 302): void
    {
        parent::redirect($url, $status);
    }

    /**
     * Get home URL
     *
     * @param string $path
     * @return string
     */
    public function getHomeUrl(string $path = ''): string
    {
        return home_url($path);
    }

    /**
     * Get login redirect URL
     *
     * @return string
     */
    public function getLoginRedirectUrl(): string
    {
        return wp_login_url();
    }

    // ===== VERSION-SPECIFIC METHODS =====

    /**
     * Find minisite by ID
     *
     * @param string $siteId
     * @return object|null
     */
    public function findMinisiteById(string $siteId): ?object
    {
        return $this->getRepository()->findById($siteId);
    }

    /**
     * Get reviews for a minisite
     *
     * @param string $minisiteId
     * @return array
     */
    public function getReviewsForMinisite(string $minisiteId): array
    {
        // Use global ReviewRepository (initialized in PluginBootstrap)
        if (! isset($GLOBALS['minisite_review_repository'])) {
            return array();
        }

        /** @var \Minisite\Features\ReviewManagement\Repositories\ReviewRepository $reviewRepo */
        $reviewRepo = $GLOBALS['minisite_review_repository'];

        return $reviewRepo->listApprovedForMinisite($minisiteId);
    }
}

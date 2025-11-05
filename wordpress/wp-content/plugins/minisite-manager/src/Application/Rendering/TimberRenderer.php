<?php

namespace Minisite\Application\Rendering;

use Minisite\Domain\Entities\Minisite;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

class TimberRenderer
{
    public function __construct(private string $variant = 'v2025')
    {
    }

    public function render(Minisite $minisite): void
    {
        if (! class_exists('Timber\\Timber')) {
            $this->renderFallback($minisite);

            return;
        }

        $this->registerTimberLocations();
        $context = $this->getMinisiteData($minisite);

        \Timber\Timber::render(
            array(
                $this->variant . '/minisite.twig',
            ),
            $context
        );
    }

    protected function getMinisiteData(Minisite $minisite): array
    {
        $reviews = $this->fetchReviews($minisite->id);
        $minisiteWithUserData = $this->fetchMinisiteWithUserData($minisite);

        return array(
            'minisite' => $minisiteWithUserData,
            'reviews' => $reviews,
        );
    }

    protected function renderFallback(Minisite $minisite): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">';
        echo '<title>' . esc_html($minisite->title) . '</title>';
        echo '<h1>' . esc_html($minisite->name) . '</h1>';
    }

    protected function registerTimberLocations(): void
    {
        $base = trailingslashit(\MINISITE_PLUGIN_DIR) . 'templates/timber';
        \Timber\Timber::$locations = array_values(
            array_unique(
                array_merge(
                    \Timber\Timber::$locations ?? array(),
                    array( $base )
                )
            )
        );
    }

    protected function fetchReviews(string $minisiteId): array
    {
        // Use global ReviewRepository (initialized in PluginBootstrap)
        if (! isset($GLOBALS['minisite_review_repository'])) {
            return array();
        }

        /** @var \Minisite\Features\ReviewManagement\Repositories\ReviewRepository $reviewRepo */
        $reviewRepo = $GLOBALS['minisite_review_repository'];

        return $reviewRepo->listApprovedForMinisite($minisiteId);
    }

    protected function fetchMinisiteWithUserData(Minisite $minisite): Minisite
    {
        $isBookmarked = $this->checkIfBookmarked($minisite->id);
        $canEdit = $this->checkIfCanEdit($minisite->id);

        return new Minisite(
            id: $minisite->id,
            slug: $minisite->slug,
            slugs: $minisite->slugs,
            title: $minisite->title,
            name: $minisite->name,
            city: $minisite->city,
            region: $minisite->region,
            countryCode: $minisite->countryCode,
            postalCode: $minisite->postalCode,
            geo: $minisite->geo,
            siteTemplate: $minisite->siteTemplate,
            palette: $minisite->palette,
            industry: $minisite->industry,
            defaultLocale: $minisite->defaultLocale,
            schemaVersion: $minisite->schemaVersion,
            siteVersion: $minisite->siteVersion,
            siteJson: $minisite->siteJson,
            searchTerms: $minisite->searchTerms,
            status: $minisite->status,
            publishStatus: $minisite->publishStatus,
            createdAt: $minisite->createdAt,
            updatedAt: $minisite->updatedAt,
            publishedAt: $minisite->publishedAt,
            createdBy: $minisite->createdBy,
            updatedBy: $minisite->updatedBy,
            currentVersionId: $minisite->currentVersionId,
            isBookmarked: $isBookmarked,
            canEdit: $canEdit
        );
    }

    protected function checkIfBookmarked(string $minisiteId): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        global $wpdb;
        $userId = get_current_user_id();
        $bookmarkExists = db::get_var(
            "SELECT id FROM {$wpdb->prefix}minisite_bookmarks WHERE user_id = %d AND minisite_id = %d",
            array($userId, $minisiteId)
        );

        return (bool) $bookmarkExists;
    }

    protected function checkIfCanEdit(string $minisiteId): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        return current_user_can('minisite_edit_profile', $minisiteId);
    }
}

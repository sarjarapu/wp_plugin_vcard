<?php
namespace Minisite\Application\Rendering;

use Minisite\Domain\Entities\Minisite;

final class TimberRenderer
{
    public function __construct(private string $variant = 'v2025') {}

    public function render(Minisite $minisite): void
    {
        if (!class_exists('Timber\\Timber')) {
            // Fallback: minimal echo if Timber is not present
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8">';
            echo '<title>' . htmlspecialchars($minisite->title) . '</title>';
            echo '<h1>' . htmlspecialchars($minisite->name) . '</h1>';
            return;
        }

        // Register plugin templates location while allowing theme overrides
        $base = trailingslashit(\MINISITE_PLUGIN_DIR) . 'templates/timber';
        \Timber\Timber::$locations = array_values(array_unique(array_merge(\Timber\Timber::$locations ?? [], [$base])));

        // Fetch reviews for the profile
        global $wpdb;
        $reviewRepo = new \Minisite\Infrastructure\Persistence\Repositories\ReviewRepository($wpdb);
        $reviews = $reviewRepo->listApprovedForMinisite($minisite->id);

        // Check if current user has bookmarked this profile
        $isBookmarked = false;
        if (is_user_logged_in()) {
            $userId = get_current_user_id();
            $bookmarkExists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}minisite_bookmarks 
                 WHERE user_id = %d AND minisite_id = %d",
                $userId, $minisite->id
            ));
            $isBookmarked = (bool) $bookmarkExists;
        }

        // Check if current user can edit this profile
        $canEdit = false;
        if (is_user_logged_in()) {
            $canEdit = current_user_can('minisite_edit_profile', $minisite->id);
        }

        // Create a new Minisite object with the updated properties
        $minisite = new \Minisite\Domain\Entities\Minisite(
            id: $minisite->id,
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
            createdAt: $minisite->createdAt,
            updatedAt: $minisite->updatedAt,
            publishedAt: $minisite->publishedAt,
            createdBy: $minisite->createdBy,
            updatedBy: $minisite->updatedBy,
            currentVersionId: $minisite->currentVersionId,
            isBookmarked: $isBookmarked,
            canEdit: $canEdit
        );

        // Pass the entity directly; use properties in Twig (no additional mapping)
        $context = [
            'minisite' => $minisite,
            'reviews' => $reviews,
        ];

        \Timber\Timber::render([
            $this->variant . '/minisite.twig',
        ], $context);
    }
}


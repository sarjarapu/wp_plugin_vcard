<?php
namespace Minisite\Application\Rendering;

use Minisite\Domain\Entities\Profile;

final class TimberRenderer
{
    public function __construct(private string $variant = 'v2025') {}

    public function render(Profile $profile): void
    {
        if (!class_exists('Timber\\Timber')) {
            // Fallback: minimal echo if Timber is not present
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8">';
            echo '<title>' . htmlspecialchars($profile->title) . '</title>';
            echo '<h1>' . htmlspecialchars($profile->name) . '</h1>';
            return;
        }

        // Register plugin templates location while allowing theme overrides
        $base = trailingslashit(\MINISITE_PLUGIN_DIR) . 'templates/timber';
        \Timber\Timber::$locations = array_values(array_unique(array_merge(\Timber\Timber::$locations ?? [], [$base])));

        // Fetch reviews for the profile
        global $wpdb;
        $reviewRepo = new \Minisite\Infrastructure\Persistence\Repositories\ReviewRepository($wpdb);
        $reviews = $reviewRepo->listApprovedForProfile($profile->id);

        // Check if current user has bookmarked this profile
        $isBookmarked = false;
        if (is_user_logged_in()) {
            $userId = get_current_user_id();
            $bookmarkExists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}minisite_bookmarks 
                 WHERE user_id = %d AND profile_id = %d",
                $userId, $profile->id
            ));
            $isBookmarked = (bool) $bookmarkExists;
        }

        // Add bookmark status to profile object for template access
        $profile->isBookmarked = $isBookmarked;

        // Pass the entity directly; use properties in Twig (no additional mapping)
        $context = [
            'profile' => $profile,
            'reviews' => $reviews,
        ];

        \Timber\Timber::render([
            $this->variant . '/profile.twig',
            'default/profile.twig',
        ], $context);
    }
}


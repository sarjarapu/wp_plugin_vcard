<?php

namespace Minisite\Features\PublishMinisite\Services;

use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;
use Minisite\Infrastructure\Utils\ReservationCleanup;
use Psr\Log\LoggerInterface;

/**
 * Slug Availability Service
 *
 * SINGLE RESPONSIBILITY: Handle slug availability checking and validation
 * - Checks slug combination availability
 * - Validates slug format
 * - Queries database for existing minisites with same slugs
 * - Checks active reservations
 */
class SlugAvailabilityService
{
    private LoggerInterface $logger;

    public function __construct(
        private WordPressPublishManager $wordPressManager,
        private MinisiteRepositoryInterface $minisiteRepository
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('slug-availability-service');
    }

    /**
     * Check if slug format is valid
     */
    public function validateSlugFormat(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9-]+$/', $slug);
    }

    /**
     * Check availability of slug combination
     */
    public function checkAvailability(string $businessSlug, string $locationSlug): object
    {
        // Clean up expired reservations first
        ReservationCleanup::cleanupExpired();

        // Validate slug formats
        if (! $this->validateSlugFormat($businessSlug)) {
            return (object) array(
                'available' => false,
                'message' => 'Business slug can only contain lowercase letters, numbers, and hyphens',
            );
        }

        if (! empty($locationSlug) && ! $this->validateSlugFormat($locationSlug)) {
            return (object) array(
                'available' => false,
                'message' => 'Location slug can only contain lowercase letters, numbers, and hyphens',
            );
        }

        try {
            // Check if combination already exists in minisites table
            $existingMinisite = $this->minisiteRepository->findBySlugParams($businessSlug, $locationSlug);

            if ($existingMinisite) {
                return (object) array(
                    'available' => false,
                    'message' => 'This slug combination is already taken by an existing minisite',
                );
            }

            // Check if combination is currently reserved
            // Handle empty location slug (NULL in database)
            global $wpdb;
            $reservationsTable = $wpdb->prefix . 'minisite_reservations';
            $locationSlugForQuery = empty($locationSlug) ? null : $locationSlug;

            // Use proper NULL handling for location_slug
            if ($locationSlugForQuery === null) {
                $reservation = db::get_row(
                    "SELECT * FROM {$reservationsTable}
                     WHERE business_slug = %s AND (location_slug IS NULL OR location_slug = '') AND expires_at > NOW()",
                    array($businessSlug)
                );
            } else {
                $reservation = db::get_row(
                    "SELECT * FROM {$reservationsTable}
                     WHERE business_slug = %s AND location_slug = %s AND expires_at > NOW()",
                    array($businessSlug, $locationSlug)
                );
            }

            if ($reservation) {
                // DatabaseHelper::get_row returns array (ARRAY_A)
                $expiresAt = $reservation['expires_at'] ?? $reservation->expires_at ?? null;
                $expiresIn = $expiresAt ? (strtotime($expiresAt) - time()) : 0;
                $minutesLeft = max(0, ceil($expiresIn / 60));

                return (object) array(
                    'available' => false,
                    'message' => "This slug combination is currently reserved " .
                                "(expires in {$minutesLeft} minutes)",
                );
            }

            // Slug combination is available
            return (object) array(
                'available' => true,
                'message' => 'This slug combination is available',
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to check slug availability', array(
                'business_slug' => $businessSlug,
                'location_slug' => $locationSlug,
                'error' => $e->getMessage(),
            ));

            throw new \RuntimeException('Failed to check slug availability: ' . esc_html($e->getMessage()));
        }
    }
}

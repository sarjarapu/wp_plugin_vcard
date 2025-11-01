<?php

namespace Minisite\Features\PublishMinisite\Services;

use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
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
        private WordPressPublishManager $wordPressManager
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
        if (!$this->validateSlugFormat($businessSlug)) {
            return (object) [
                'available' => false,
                'message' => 'Business slug can only contain lowercase letters, numbers, and hyphens',
            ];
        }

        if (!empty($locationSlug) && !$this->validateSlugFormat($locationSlug)) {
            return (object) [
                'available' => false,
                'message' => 'Location slug can only contain lowercase letters, numbers, and hyphens',
            ];
        }

        // TODO: Implement actual availability checking
        // Check if combination exists in minisites table
        // Check if combination is currently reserved
        
        return (object) [
            'available' => true,
            'message' => 'This slug combination is available',
        ];
    }
}


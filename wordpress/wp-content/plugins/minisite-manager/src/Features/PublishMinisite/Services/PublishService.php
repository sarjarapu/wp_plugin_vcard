<?php

namespace Minisite\Features\PublishMinisite\Services;

use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Publish Service
 *
 * SINGLE RESPONSIBILITY: Handle business logic for minisite publishing
 * - Coordinates the publish workflow
 * - Validates minisite eligibility for publishing
 * - Orchestrates slug reservation and payment flow
 * - Handles direct publish for existing subscribers
 */
class PublishService
{
    private LoggerInterface $logger;

    public function __construct(
        private WordPressPublishManager $wordPressManager,
        private SlugAvailabilityService $slugAvailabilityService,
        private ReservationService $reservationService
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('publish-minisite-service');
    }

    /**
     * Get slug availability service (for controller access)
     */
    public function getSlugAvailabilityService(): SlugAvailabilityService
    {
        return $this->slugAvailabilityService;
    }

    /**
     * Get minisite data for publishing
     */
    public function getMinisiteForPublishing(string $siteId): object
    {
        $minisite = $this->wordPressManager->findMinisiteById($siteId);
        if (!$minisite) {
            throw new \RuntimeException('Minisite not found');
        }

        // Check ownership
        $currentUser = $this->wordPressManager->getCurrentUser();
        if ($minisite->createdBy !== (int) $currentUser->ID) {
            throw new \RuntimeException('Access denied');
        }

        return (object) [
            'minisite' => $minisite,
            'currentSlugs' => [
                'business' => $minisite->slugs->business ?? '',
                'location' => $minisite->slugs->location ?? '',
            ],
        ];
    }
}

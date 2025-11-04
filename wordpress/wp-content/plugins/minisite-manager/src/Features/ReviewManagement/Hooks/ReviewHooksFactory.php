<?php

namespace Minisite\Features\ReviewManagement\Hooks;

use Minisite\Features\ReviewManagement\Repositories\ReviewRepository;
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;

/**
 * ReviewHooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure ReviewHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and repositories
 * - Configures the complete review management system
 */
final class ReviewHooksFactory
{
    /**
     * Create and configure ReviewHooks
     */
    public static function create(): ReviewHooks
    {
        // Create repository (Doctrine)
        $em = DoctrineFactory::createEntityManager();
        $reviewRepository = new ReviewRepository(
            $em,
            $em->getClassMetadata(\Minisite\Features\ReviewManagement\Domain\Entities\Review::class)
        );

        // Create and return hooks
        // For now, this is a placeholder - when review management UI is added,
        // controllers, services, handlers will be created here
        return new ReviewHooks($reviewRepository);
    }
}

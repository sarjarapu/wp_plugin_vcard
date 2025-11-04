<?php

declare(strict_types=1);

namespace Minisite\Features\ReviewManagement\Repositories;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Minisite\Features\ReviewManagement\Domain\Entities\Review;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Review Repository using Doctrine ORM
 *
 * Note: Naming is agnostic (not "DoctrineReviewRepository") since we have
 * only one implementation. If multiple implementations are needed in future
 * (e.g., for testing, caching, or alternative storage), rename to distinguish.
 */
class ReviewRepository extends EntityRepository implements ReviewRepositoryInterface
{
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->logger = LoggingServiceProvider::getFeatureLogger('review-repository');
    }

    /**
     * Save review (insert or update)
     */
    public function save(Review $review): Review
    {
        $this->logger->debug("save() entry", [
            'review_id' => $review->id,
            'minisite_id' => $review->minisiteId,
            'status' => $review->status,
        ]);

        try {
            $review->touch();
            $this->getEntityManager()->persist($review);
            $this->getEntityManager()->flush();

            $this->logger->debug("save() exit", [
                'review_id' => $review->id,
                'minisite_id' => $review->minisiteId,
            ]);

            return $review;
        } catch (\Exception $e) {
            $this->logger->error("save() failed", [
                'review_id' => $review->id,
                'minisite_id' => $review->minisiteId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Find review by ID
     *
     * Note: This method signature matches Doctrine's EntityRepository::find() to avoid conflicts.
     * For type-safe usage, use findById() which is defined in the interface.
     */
    public function find(
        mixed $id,
        \Doctrine\DBAL\LockMode|int|null $lockMode = null,
        ?int $lockVersion = null
    ): ?Review {
        if (!is_int($id)) {
            throw new \InvalidArgumentException('Review ID must be an integer');
        }

        $this->logger->debug("find() entry", [
            'review_id' => $id,
        ]);

        try {
            // Call parent::find() to use Doctrine's implementation with locking support
            $result = parent::find($id, $lockMode, $lockVersion);

            $this->logger->debug("find() exit", [
                'review_id' => $id,
                'found' => $result !== null,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("find() failed", [
                'review_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Type-safe find by ID (delegates to find())
     */
    public function findById(int $id): ?Review
    {
        return $this->find($id);
    }

    /**
     * Find review by ID, throw if not found
     */
    public function findOrFail(int $id): Review
    {
        $review = $this->findById($id);
        if ($review === null) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \RuntimeException(sprintf('Review with ID %d not found', $id));
        }
        return $review;
    }

    /**
     * Delete review
     */
    public function delete(Review $review): void
    {
        $this->logger->debug("delete() entry", [
            'review_id' => $review->id,
            'minisite_id' => $review->minisiteId,
        ]);

        try {
            $this->getEntityManager()->remove($review);
            $this->getEntityManager()->flush();

            $this->logger->debug("delete() exit", [
                'review_id' => $review->id,
                'deleted' => true,
            ]);
        } catch (\Exception $e) {
            $this->logger->error("delete() failed", [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * List approved reviews for a minisite
     *
     * @return Review[]
     */
    public function listApprovedForMinisite(string $minisiteId, int $limit = 20): array
    {
        return $this->listByStatusForMinisite($minisiteId, 'approved', $limit);
    }

    /**
     * List reviews by status for a minisite
     *
     * @return Review[]
     */
    public function listByStatusForMinisite(string $minisiteId, string $status, int $limit = 20): array
    {
        $this->logger->debug("listByStatusForMinisite() entry", [
            'minisite_id' => $minisiteId,
            'status' => $status,
            'limit' => $limit,
        ]);

        try {
            $qb = $this->createQueryBuilder('r')
                ->where('r.minisiteId = :minisiteId')
                ->andWhere('r.status = :status')
                ->setParameter('minisiteId', $minisiteId)
                ->setParameter('status', $status)
                ->orderBy('r.displayOrder', 'ASC')
                ->addOrderBy('r.publishedAt', 'DESC')
                ->addOrderBy('r.createdAt', 'DESC')
                ->setMaxResults($limit);

            $result = $qb->getQuery()->getResult();

            $this->logger->debug("listByStatusForMinisite() exit", [
                'minisite_id' => $minisiteId,
                'status' => $status,
                'count' => count($result),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("listByStatusForMinisite() failed", [
                'minisite_id' => $minisiteId,
                'status' => $status,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Count reviews by status for a minisite
     */
    public function countByStatusForMinisite(string $minisiteId, string $status): int
    {
        $this->logger->debug("countByStatusForMinisite() entry", [
            'minisite_id' => $minisiteId,
            'status' => $status,
        ]);

        try {
            $qb = $this->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.minisiteId = :minisiteId')
                ->andWhere('r.status = :status')
                ->setParameter('minisiteId', $minisiteId)
                ->setParameter('status', $status);

            $result = (int) $qb->getQuery()->getSingleScalarResult();

            $this->logger->debug("countByStatusForMinisite() exit", [
                'minisite_id' => $minisiteId,
                'status' => $status,
                'count' => $result,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("countByStatusForMinisite() failed", [
                'minisite_id' => $minisiteId,
                'status' => $status,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
}

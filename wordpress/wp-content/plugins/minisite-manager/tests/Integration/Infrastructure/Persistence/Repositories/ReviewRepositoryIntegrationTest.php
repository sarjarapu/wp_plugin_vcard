<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Minisite\Domain\Entities\Review;
use Minisite\Infrastructure\Persistence\Repositories\ReviewRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseTestHelper;

#[CoversClass(ReviewRepository::class)]
final class ReviewRepositoryIntegrationTest extends TestCase
{
    private ReviewRepository $repository;
    private DatabaseTestHelper $dbHelper;

    protected function setUp(): void
    {
        $this->dbHelper = new DatabaseTestHelper();
        $this->dbHelper->cleanupTestTables();
        $this->dbHelper->createMinisiteReviewsTable();

        $this->repository = new ReviewRepository($this->dbHelper->getWpdb());
    }

    protected function tearDown(): void
    {
        $this->dbHelper->cleanupTestTables();
    }


    public function testAddAndRetrieveReview(): void
    {
        $review = new Review(
            id: null,
            minisiteId: 123,
            authorName: 'Alice Johnson',
            authorUrl: 'https://alice.com',
            rating: 4.5,
            body: 'Excellent service and friendly staff!',
            locale: 'en-US',
            visitedMonth: '2025-01',
            source: 'google',
            sourceId: 'g-123',
            status: 'approved',
            createdAt: new DateTimeImmutable('2025-01-15T10:00:00Z'),
            updatedAt: new DateTimeImmutable('2025-01-15T10:30:00Z'),
            createdBy: 1
        );

        // Add the review
        $savedReview = $this->repository->add($review);

        $this->assertNotNull($savedReview->id);
        $this->assertSame(123, $savedReview->minisiteId);
        $this->assertSame('Alice Johnson', $savedReview->authorName);
        $this->assertSame('https://alice.com', $savedReview->authorUrl);
        $this->assertSame(4.5, $savedReview->rating);
        $this->assertSame('Excellent service and friendly staff!', $savedReview->body);
        $this->assertSame('en-US', $savedReview->locale);
        $this->assertSame('2025-01', $savedReview->visitedMonth);
        $this->assertSame('google', $savedReview->source);
        $this->assertSame('g-123', $savedReview->sourceId);
        $this->assertSame('approved', $savedReview->status);
        $this->assertSame(1, $savedReview->createdBy);

        // Retrieve the review
        $retrievedReviews = $this->repository->listApprovedForMinisite('123');

        $this->assertCount(1, $retrievedReviews);
        $retrievedReview = $retrievedReviews[0];

        $this->assertSame($savedReview->id, $retrievedReview->id);
        $this->assertSame(123, $retrievedReview->minisiteId);
        $this->assertSame('Alice Johnson', $retrievedReview->authorName);
        $this->assertSame('https://alice.com', $retrievedReview->authorUrl);
        $this->assertSame(4.5, $retrievedReview->rating);
        $this->assertSame('Excellent service and friendly staff!', $retrievedReview->body);
        $this->assertSame('en-US', $retrievedReview->locale);
        $this->assertSame('2025-01', $retrievedReview->visitedMonth);
        $this->assertSame('google', $retrievedReview->source);
        $this->assertSame('g-123', $retrievedReview->sourceId);
        $this->assertSame('approved', $retrievedReview->status);
        $this->assertSame(1, $retrievedReview->createdBy);
    }

    public function testAddReviewWithNullOptionalFields(): void
    {
        $review = new Review(
            id: null,
            minisiteId: 456,
            authorName: 'Bob Smith',
            authorUrl: null,
            rating: 5.0,
            body: 'Outstanding experience!',
            locale: null,
            visitedMonth: null,
            source: 'manual',
            sourceId: null,
            status: 'pending',
            createdAt: null,
            updatedAt: null,
            createdBy: null
        );

        $savedReview = $this->repository->add($review);

        $this->assertNotNull($savedReview->id);
        $this->assertSame(456, $savedReview->minisiteId);
        $this->assertSame('Bob Smith', $savedReview->authorName);
        $this->assertNull($savedReview->authorUrl);
        $this->assertSame(5.0, $savedReview->rating);
        $this->assertSame('Outstanding experience!', $savedReview->body);
        $this->assertNull($savedReview->locale);
        $this->assertNull($savedReview->visitedMonth);
        $this->assertSame('manual', $savedReview->source);
        $this->assertNull($savedReview->sourceId);
        $this->assertSame('pending', $savedReview->status);
        $this->assertNull($savedReview->createdBy);

        $retrievedReviews = $this->repository->listApprovedForMinisite('456');

        $this->assertCount(1, $retrievedReviews);
        $retrievedReview = $retrievedReviews[0];

        $this->assertSame($savedReview->id, $retrievedReview->id);
        $this->assertSame(456, $retrievedReview->minisiteId);
        $this->assertSame('Bob Smith', $retrievedReview->authorName);
        $this->assertNull($retrievedReview->authorUrl);
        $this->assertSame(5.0, $retrievedReview->rating);
        $this->assertSame('Outstanding experience!', $retrievedReview->body);
        $this->assertNull($retrievedReview->locale);
        $this->assertNull($retrievedReview->visitedMonth);
        $this->assertSame('manual', $retrievedReview->source);
        $this->assertNull($retrievedReview->sourceId);
        $this->assertSame('pending', $retrievedReview->status);
        $this->assertNull($retrievedReview->createdBy);
    }

    public function testListApprovedForMinisiteReturnsMultipleReviewsInCorrectOrder(): void
    {
        $minisiteId = '789';

        // Create multiple reviews with different timestamps
        $review1 = new Review(
            id: null,
            minisiteId: (int)$minisiteId,
            authorName: 'Charlie Brown',
            authorUrl: 'https://charlie.com',
            rating: 4.0,
            body: 'Good service',
            locale: 'en-US',
            visitedMonth: '2025-01',
            source: 'google',
            sourceId: 'g-001',
            status: 'approved',
            createdAt: new DateTimeImmutable('2025-01-15T10:00:00Z'),
            updatedAt: null,
            createdBy: 1
        );

        $review2 = new Review(
            id: null,
            minisiteId: (int)$minisiteId,
            authorName: 'Diana Prince',
            authorUrl: 'https://diana.com',
            rating: 5.0,
            body: 'Amazing experience!',
            locale: 'en-GB',
            visitedMonth: '2025-01',
            source: 'yelp',
            sourceId: 'y-002',
            status: 'approved',
            createdAt: new DateTimeImmutable('2025-01-16T14:00:00Z'),
            updatedAt: null,
            createdBy: 2
        );

        $review3 = new Review(
            id: null,
            minisiteId: (int)$minisiteId,
            authorName: 'Eve Wilson',
            authorUrl: null,
            rating: 3.5,
            body: 'Decent service',
            locale: 'fr-FR',
            visitedMonth: '2025-01',
            source: 'facebook',
            sourceId: 'f-003',
            status: 'approved',
            createdAt: new DateTimeImmutable('2025-01-17T09:00:00Z'),
            updatedAt: null,
            createdBy: 3
        );

        $this->repository->add($review1);
        $this->repository->add($review2);
        $this->repository->add($review3);

        $reviews = $this->repository->listApprovedForMinisite($minisiteId);

        $this->assertCount(3, $reviews);
        // Should be ordered by created_at DESC (newest first)
        $this->assertSame('Eve Wilson', $reviews[0]->authorName);
        $this->assertSame('Diana Prince', $reviews[1]->authorName);
        $this->assertSame('Charlie Brown', $reviews[2]->authorName);
    }

    public function testListApprovedForMinisiteWithCustomLimit(): void
    {
        $minisiteId = '999';

        // Create 5 reviews
        for ($i = 1; $i <= 5; $i++) {
            $review = new Review(
                id: null,
                minisiteId: (int)$minisiteId,
                authorName: "User $i",
                authorUrl: null,
                rating: 4.0,
                body: "Review $i",
                locale: 'en-US',
                visitedMonth: '2025-01',
                source: 'manual',
                sourceId: null,
                status: 'approved',
                createdAt: new DateTimeImmutable("2025-01-{$i}T10:00:00Z"),
                updatedAt: null,
                createdBy: $i
            );

            $this->repository->add($review);
        }

        // Test with limit of 3
        $reviews = $this->repository->listApprovedForMinisite($minisiteId, 3);

        $this->assertCount(3, $reviews);
        $this->assertSame('User 5', $reviews[0]->authorName);
        $this->assertSame('User 4', $reviews[1]->authorName);
        $this->assertSame('User 3', $reviews[2]->authorName);
    }

    public function testListApprovedForMinisiteReturnsEmptyArrayForNonExistentMinisite(): void
    {
        $reviews = $this->repository->listApprovedForMinisite('99999');

        $this->assertIsArray($reviews);
        $this->assertEmpty($reviews);
    }

    public function testAddMultipleReviewsForDifferentMinisites(): void
    {
        $minisite1 = '100';
        $minisite2 = '200';

        $review1 = new Review(
            id: null,
            minisiteId: (int)$minisite1,
            authorName: 'User 1',
            authorUrl: null,
            rating: 4.0,
            body: 'Review for minisite 1',
            locale: 'en-US',
            visitedMonth: '2025-01',
            source: 'google',
            sourceId: 'g-001',
            status: 'approved',
            createdAt: null,
            updatedAt: null,
            createdBy: 1
        );

        $review2 = new Review(
            id: null,
            minisiteId: (int)$minisite2,
            authorName: 'User 2',
            authorUrl: null,
            rating: 5.0,
            body: 'Review for minisite 2',
            locale: 'en-GB',
            visitedMonth: '2025-01',
            source: 'yelp',
            sourceId: 'y-002',
            status: 'approved',
            createdAt: null,
            updatedAt: null,
            createdBy: 2
        );

        $savedReview1 = $this->repository->add($review1);
        $savedReview2 = $this->repository->add($review2);

        $reviews1 = $this->repository->listApprovedForMinisite($minisite1);
        $reviews2 = $this->repository->listApprovedForMinisite($minisite2);

        $this->assertCount(1, $reviews1);
        $this->assertCount(1, $reviews2);

        $this->assertSame($savedReview1->id, $reviews1[0]->id);
        $this->assertSame($savedReview2->id, $reviews2[0]->id);

        $this->assertSame('Review for minisite 1', $reviews1[0]->body);
        $this->assertSame('Review for minisite 2', $reviews2[0]->body);
    }

    public function testAddReviewWithDifferentSources(): void
    {
        $sources = ['manual', 'google', 'yelp', 'facebook', 'other'];
        $minisiteId = '300';

        foreach ($sources as $index => $source) {
            $review = new Review(
                id: null,
                minisiteId: (int)$minisiteId,
                authorName: "User for $source",
                authorUrl: null,
                rating: 4.0,
                body: "Review from $source",
                locale: 'en-US',
                visitedMonth: '2025-01',
                source: $source,
                sourceId: $source . '-123',
                status: 'approved',
                createdAt: null,
                updatedAt: null,
                createdBy: $index + 1
            );

            $savedReview = $this->repository->add($review);
            $this->assertSame($source, $savedReview->source);
            $this->assertSame($source . '-123', $savedReview->sourceId);
        }

        $reviews = $this->repository->listApprovedForMinisite($minisiteId);

        $this->assertCount(5, $reviews);

        $sourcesFromDb = array_map(fn($r) => $r->source, $reviews);
        $this->assertContains('manual', $sourcesFromDb);
        $this->assertContains('google', $sourcesFromDb);
        $this->assertContains('yelp', $sourcesFromDb);
        $this->assertContains('facebook', $sourcesFromDb);
        $this->assertContains('other', $sourcesFromDb);
    }

    public function testAddReviewWithDifferentStatuses(): void
    {
        $statuses = ['pending', 'approved', 'rejected'];
        $minisiteId = '400';

        foreach ($statuses as $index => $status) {
            $review = new Review(
                id: null,
                minisiteId: (int)$minisiteId,
                authorName: "User for $status",
                authorUrl: null,
                rating: 4.0,
                body: "Review with status $status",
                locale: 'en-US',
                visitedMonth: '2025-01',
                source: 'manual',
                sourceId: null,
                status: $status,
                createdAt: null,
                updatedAt: null,
                createdBy: $index + 1
            );

            $savedReview = $this->repository->add($review);
            $this->assertSame($status, $savedReview->status);
        }

        $reviews = $this->repository->listApprovedForMinisite($minisiteId);

        $this->assertCount(3, $reviews);

        $statusesFromDb = array_map(fn($r) => $r->status, $reviews);
        $this->assertContains('pending', $statusesFromDb);
        $this->assertContains('approved', $statusesFromDb);
        $this->assertContains('rejected', $statusesFromDb);
    }

    public function testAddReviewWithFloatRatings(): void
    {
        $ratings = [1.0, 2.5, 3.7, 4.2, 5.0];
        $minisiteId = '500';

        foreach ($ratings as $index => $rating) {
            $review = new Review(
                id: null,
                minisiteId: (int)$minisiteId,
                authorName: "User for rating $rating",
                authorUrl: null,
                rating: $rating,
                body: "Review with rating $rating",
                locale: 'en-US',
                visitedMonth: '2025-01',
                source: 'manual',
                sourceId: null,
                status: 'approved',
                createdAt: null,
                updatedAt: null,
                createdBy: $index + 1
            );

            $savedReview = $this->repository->add($review);
            $this->assertSame($rating, $savedReview->rating);
        }

        $reviews = $this->repository->listApprovedForMinisite($minisiteId);

        $this->assertCount(5, $reviews);

        $ratingsFromDb = array_map(fn($r) => $r->rating, $reviews);
        $this->assertContains(1.0, $ratingsFromDb);
        $this->assertContains(2.5, $ratingsFromDb);
        $this->assertContains(3.7, $ratingsFromDb);
        $this->assertContains(4.2, $ratingsFromDb);
        $this->assertContains(5.0, $ratingsFromDb);
    }

    public function testAddReviewWithSpecialCharacters(): void
    {
        $review = new Review(
            id: null,
            minisiteId: 600,
            authorName: 'José María García-López',
            authorUrl: 'https://example.com/café',
            rating: 4.5,
            body: '¡Excelente servicio! Muy recomendado. El personal es muy amable y profesional.',
            locale: 'es-ES',
            visitedMonth: '2025-01',
            source: 'google',
            sourceId: 'g-123',
            status: 'approved',
            createdAt: new DateTimeImmutable('2025-01-15T10:00:00Z'),
            updatedAt: new DateTimeImmutable('2025-01-15T10:30:00Z'),
            createdBy: 1
        );

        $savedReview = $this->repository->add($review);

        $this->assertSame('José María García-López', $savedReview->authorName);
        $this->assertSame('https://example.com/café', $savedReview->authorUrl);
        $this->assertSame('¡Excelente servicio! Muy recomendado. El personal es muy amable y profesional.', $savedReview->body);
        $this->assertSame('es-ES', $savedReview->locale);

        $retrievedReviews = $this->repository->listApprovedForMinisite('600');

        $this->assertCount(1, $retrievedReviews);
        $retrievedReview = $retrievedReviews[0];

        $this->assertSame('José María García-López', $retrievedReview->authorName);
        $this->assertSame('https://example.com/café', $retrievedReview->authorUrl);
        $this->assertSame('¡Excelente servicio! Muy recomendado. El personal es muy amable y profesional.', $retrievedReview->body);
        $this->assertSame('es-ES', $retrievedReview->locale);
    }

    public function testAddReviewWithLongText(): void
    {
        $longBody = str_repeat('This is a very long review text. ', 100);

        $review = new Review(
            id: null,
            minisiteId: 700,
            authorName: 'Long Review Author',
            authorUrl: null,
            rating: 4.0,
            body: $longBody,
            locale: 'en-US',
            visitedMonth: '2025-01',
            source: 'manual',
            sourceId: null,
            status: 'approved',
            createdAt: null,
            updatedAt: null,
            createdBy: 1
        );

        $savedReview = $this->repository->add($review);

        $this->assertSame($longBody, $savedReview->body);

        $retrievedReviews = $this->repository->listApprovedForMinisite('700');

        $this->assertCount(1, $retrievedReviews);
        $retrievedReview = $retrievedReviews[0];

        $this->assertSame($longBody, $retrievedReview->body);
    }

    public function testAddReviewWithDateTimeHandling(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-15T10:30:45Z');
        $updatedAt = new DateTimeImmutable('2025-01-15T11:00:00Z');

        $review = new Review(
            id: null,
            minisiteId: 800,
            authorName: 'DateTime Test User',
            authorUrl: null,
            rating: 4.5,
            body: 'Test review with specific timestamps',
            locale: 'en-US',
            visitedMonth: '2025-01',
            source: 'manual',
            sourceId: null,
            status: 'approved',
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            createdBy: 1
        );

        $savedReview = $this->repository->add($review);

        $this->assertSame($createdAt, $savedReview->createdAt);
        $this->assertSame($updatedAt, $savedReview->updatedAt);

        $retrievedReviews = $this->repository->listApprovedForMinisite('800');

        $this->assertCount(1, $retrievedReviews);
        $retrievedReview = $retrievedReviews[0];

        $this->assertInstanceOf(DateTimeImmutable::class, $retrievedReview->createdAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $retrievedReview->updatedAt);
        $this->assertSame($createdAt->format('Y-m-d H:i:s'), $retrievedReview->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame($updatedAt->format('Y-m-d H:i:s'), $retrievedReview->updatedAt->format('Y-m-d H:i:s'));
    }
}

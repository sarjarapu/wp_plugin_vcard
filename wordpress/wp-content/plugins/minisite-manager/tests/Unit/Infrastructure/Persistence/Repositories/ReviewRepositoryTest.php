<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Minisite\Domain\Entities\Review;
use Minisite\Infrastructure\Persistence\Repositories\ReviewRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

#[CoversClass(ReviewRepository::class)]
final class ReviewRepositoryTest extends TestCase
{
    private ReviewRepository $repository;
    private FakeWpdb $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(FakeWpdb::class);
        $this->mockDb->prefix = 'wp_';
        $this->repository = new ReviewRepository($this->mockDb);
    }

    public function testTableReturnsCorrectTableName(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('table');
        $method->setAccessible(true);

        $result = $method->invoke($this->repository);

        $this->assertSame('wp_minisite_reviews', $result);
    }

    public function testAddInsertsNewReview(): void
    {
        $review = $this->createTestReview();
        $review->id = null; // New review

        $this->mockDb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_minisite_reviews',
                $this->callback(function ($data) {
                    return $data['minisite_id'] === 123 &&
                           $data['author_name'] === 'John Doe' &&
                           $data['author_url'] === 'https://example.com' &&
                           $data['rating'] === 4.5 &&
                           $data['body'] === 'Great service!' &&
                           $data['locale'] === 'en-US' &&
                           $data['visited_month'] === '2025-01' &&
                           $data['source'] === 'google' &&
                           $data['source_id'] === 'g-123' &&
                           $data['status'] === 'approved' &&
                           $data['created_at'] === '2025-01-15 10:00:00' &&
                           $data['updated_at'] === '2025-01-15 10:30:00' &&
                           $data['created_by'] === 1;
                }),
                $this->callback(function ($formats) {
                    // Should have 13 format specifiers: 11 base + 2 timestamps
                    return count($formats) === 13 &&
                           $formats[0] === '%d' && // minisite_id
                           $formats[10] === '%d' && // created_by
                           $formats[11] === '%s' && // created_at
                           $formats[12] === '%s';   // updated_at
                })
            )
            ->willReturn(1);

        $this->mockDb->insert_id = 456;

        $result = $this->repository->add($review);

        $this->assertSame(456, $result->id);
        $this->assertSame(123, $result->minisiteId);
        $this->assertSame('John Doe', $result->authorName);
    }

    public function testAddWithNullOptionalFields(): void
    {
        $review = new Review(
            id: null,
            minisiteId: 123,
            authorName: 'Jane Doe',
            authorUrl: null,
            rating: 5.0,
            body: 'Excellent!',
            locale: null,
            visitedMonth: null,
            source: 'manual',
            sourceId: null,
            status: 'pending',
            createdAt: null,
            updatedAt: null,
            createdBy: null
        );

        $this->mockDb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_minisite_reviews',
                $this->callback(function ($data) {
                    return $data['minisite_id'] === 123 &&
                           $data['author_name'] === 'Jane Doe' &&
                           $data['author_url'] === null &&
                           $data['rating'] === 5.0 &&
                           $data['body'] === 'Excellent!' &&
                           $data['locale'] === null &&
                           $data['visited_month'] === null &&
                           $data['source'] === 'manual' &&
                           $data['source_id'] === null &&
                           $data['status'] === 'pending' &&
                           $data['created_by'] === null &&
                           !isset($data['created_at']) && // Should not be in data array
                           !isset($data['updated_at']);   // Should not be in data array
                }),
                $this->callback(function ($formats) {
                    // Should have 11 format specifiers: base fields only (no timestamps)
                    return count($formats) === 11 &&
                           $formats[0] === '%d' && // minisite_id
                           $formats[10] === '%d';  // created_by
                })
            )
            ->willReturn(1);

        $this->mockDb->insert_id = 789;

        $result = $this->repository->add($review);

        $this->assertSame(789, $result->id);
        $this->assertSame('Jane Doe', $result->authorName);
        $this->assertNull($result->authorUrl);
        $this->assertNull($result->locale);
    }

    public function testListApprovedForMinisiteReturnsArrayOfReviews(): void
    {
        $rows = [
            [
                'id' => '1',
                'minisite_id' => '123',
                'author_name' => 'Alice Smith',
                'author_url' => 'https://alice.com',
                'rating' => '4.5',
                'body' => 'Great experience!',
                'locale' => 'en-US',
                'visited_month' => '2025-01',
                'source' => 'google',
                'source_id' => 'g-456',
                'status' => 'approved',
                'created_at' => '2025-01-15 10:00:00',
                'updated_at' => '2025-01-15 10:30:00',
                'created_by' => '2'
            ],
            [
                'id' => '2',
                'minisite_id' => '123',
                'author_name' => 'Bob Johnson',
                'author_url' => null,
                'rating' => '5.0',
                'body' => 'Amazing service!',
                'locale' => 'en-GB',
                'visited_month' => '2025-01',
                'source' => 'yelp',
                'source_id' => 'y-789',
                'status' => 'approved',
                'created_at' => '2025-01-16 14:00:00',
                'updated_at' => null,
                'created_by' => null
            ]
        ];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function ($query) {
                    return strpos($query, 'SELECT * FROM wp_minisite_reviews') !== false &&
                           strpos($query, 'WHERE minisite_id=%s') !== false &&
                           strpos($query, 'ORDER BY created_at DESC') !== false &&
                           strpos($query, 'LIMIT %d') !== false;
                }),
                '123',
                20
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_results')
            ->with('prepared query', \ARRAY_A)
            ->willReturn($rows);

        $result = $this->repository->listApprovedForMinisite('123');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Review::class, $result[0]);
        $this->assertInstanceOf(Review::class, $result[1]);

        // Check first review
        $this->assertSame(1, $result[0]->id);
        $this->assertSame(123, $result[0]->minisiteId);
        $this->assertSame('Alice Smith', $result[0]->authorName);
        $this->assertSame('https://alice.com', $result[0]->authorUrl);
        $this->assertSame(4.5, $result[0]->rating);
        $this->assertSame('Great experience!', $result[0]->body);
        $this->assertSame('en-US', $result[0]->locale);
        $this->assertSame('2025-01', $result[0]->visitedMonth);
        $this->assertSame('google', $result[0]->source);
        $this->assertSame('g-456', $result[0]->sourceId);
        $this->assertSame('approved', $result[0]->status);
        $this->assertInstanceOf(DateTimeImmutable::class, $result[0]->createdAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $result[0]->updatedAt);
        $this->assertSame(2, $result[0]->createdBy);

        // Check second review
        $this->assertSame(2, $result[1]->id);
        $this->assertSame(123, $result[1]->minisiteId);
        $this->assertSame('Bob Johnson', $result[1]->authorName);
        $this->assertNull($result[1]->authorUrl);
        $this->assertSame(5.0, $result[1]->rating);
        $this->assertSame('Amazing service!', $result[1]->body);
        $this->assertSame('en-GB', $result[1]->locale);
        $this->assertSame('2025-01', $result[1]->visitedMonth);
        $this->assertSame('yelp', $result[1]->source);
        $this->assertSame('y-789', $result[1]->sourceId);
        $this->assertSame('approved', $result[1]->status);
        $this->assertInstanceOf(DateTimeImmutable::class, $result[1]->createdAt);
        $this->assertNull($result[1]->updatedAt);
        $this->assertNull($result[1]->createdBy);
    }

    public function testListApprovedForMinisiteWithCustomLimit(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function ($query) {
                    return strpos($query, 'SELECT * FROM wp_minisite_reviews') !== false &&
                           strpos($query, 'WHERE minisite_id=%s') !== false &&
                           strpos($query, 'ORDER BY created_at DESC') !== false &&
                           strpos($query, 'LIMIT %d') !== false;
                }),
                '456',
                10
            )
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_results')
            ->with('prepared query', \ARRAY_A)
            ->willReturn([]);

        $result = $this->repository->listApprovedForMinisite('456', 10);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testListApprovedForMinisiteReturnsEmptyArrayWhenNoResults(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_results')
            ->with('prepared query', \ARRAY_A)
            ->willReturn(null);

        $result = $this->repository->listApprovedForMinisite('999');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testListApprovedForMinisiteHandlesNullValuesCorrectly(): void
    {
        $rows = [
            [
                'id' => '1',
                'minisite_id' => '123',
                'author_name' => 'Test User',
                'author_url' => '',
                'rating' => '3.0',
                'body' => 'Average service',
                'locale' => '',
                'visited_month' => '',
                'source' => 'manual',
                'source_id' => '',
                'status' => 'approved',
                'created_at' => '',
                'updated_at' => '',
                'created_by' => ''
            ]
        ];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_results')
            ->with('prepared query', \ARRAY_A)
            ->willReturn($rows);

        $result = $this->repository->listApprovedForMinisite('123');

        $this->assertCount(1, $result);
        $review = $result[0];

        $this->assertSame(1, $review->id);
        $this->assertSame(123, $review->minisiteId);
        $this->assertSame('Test User', $review->authorName);
        $this->assertNull($review->authorUrl); // Empty string becomes null
        $this->assertSame(3.0, $review->rating);
        $this->assertSame('Average service', $review->body);
        $this->assertNull($review->locale); // Empty string becomes null
        $this->assertNull($review->visitedMonth); // Empty string becomes null
        $this->assertSame('manual', $review->source);
        $this->assertNull($review->sourceId); // Empty string becomes null
        $this->assertSame('approved', $review->status);
        $this->assertNull($review->createdAt); // Empty string becomes null
        $this->assertNull($review->updatedAt); // Empty string becomes null
        $this->assertNull($review->createdBy); // Empty string becomes null
    }

    #[DataProvider('dpValidSources')]
    public function testAddWithValidSources(string $source): void
    {
        $review = $this->createTestReview();
        $review->id = null;
        $review->source = $source;

        $this->mockDb->expects($this->once())
            ->method('insert')
            ->willReturn(1);

        $this->mockDb->insert_id = 100;

        $result = $this->repository->add($review);

        $this->assertSame($source, $result->source);
    }

    public static function dpValidSources(): array
    {
        return [
            ['manual'],
            ['google'],
            ['yelp'],
            ['facebook'],
            ['other'],
        ];
    }

    #[DataProvider('dpValidStatuses')]
    public function testAddWithValidStatuses(string $status): void
    {
        $review = $this->createTestReview();
        $review->id = null;
        $review->status = $status;

        $this->mockDb->expects($this->once())
            ->method('insert')
            ->willReturn(1);

        $this->mockDb->insert_id = 100;

        $result = $this->repository->add($review);

        $this->assertSame($status, $result->status);
    }

    public static function dpValidStatuses(): array
    {
        return [
            ['pending'],
            ['approved'],
            ['rejected'],
        ];
    }

    public function testAddWithFloatRating(): void
    {
        $review = $this->createTestReview();
        $review->id = null;
        $review->rating = 3.7;

        $this->mockDb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_minisite_reviews',
                $this->callback(function ($data) {
                    return $data['rating'] === 3.7;
                }),
                $this->callback(function ($formats) {
                    // Should have 13 format specifiers: 11 base + 2 timestamps
                    return count($formats) === 13;
                })
            )
            ->willReturn(1);

        $this->mockDb->insert_id = 100;

        $result = $this->repository->add($review);

        $this->assertSame(3.7, $result->rating);
    }

    public function testAddWithIntegerMinisiteId(): void
    {
        $review = $this->createTestReview();
        $review->id = null;
        $review->minisiteId = 999;

        $this->mockDb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_minisite_reviews',
                $this->callback(function ($data) {
                    return $data['minisite_id'] === 999;
                }),
                $this->callback(function ($formats) {
                    // Should have 13 format specifiers: 11 base + 2 timestamps
                    return count($formats) === 13;
                })
            )
            ->willReturn(1);

        $this->mockDb->insert_id = 100;

        $result = $this->repository->add($review);

        $this->assertSame(999, $result->minisiteId);
    }

    public function testListApprovedForMinisiteWithSpecialCharacters(): void
    {
        $rows = [
            [
                'id' => '1',
                'minisite_id' => '123',
                'author_name' => 'José María',
                'author_url' => 'https://example.com/café',
                'rating' => '4.5',
                'body' => '¡Excelente servicio! Muy recomendado.',
                'locale' => 'es-ES',
                'visited_month' => '2025-01',
                'source' => 'google',
                'source_id' => 'g-123',
                'status' => 'approved',
                'created_at' => '2025-01-15 10:00:00',
                'updated_at' => '2025-01-15 10:30:00',
                'created_by' => '2'
            ]
        ];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn('prepared query');

        $this->mockDb->expects($this->once())
            ->method('get_results')
            ->with('prepared query', \ARRAY_A)
            ->willReturn($rows);

        $result = $this->repository->listApprovedForMinisite('123');

        $this->assertCount(1, $result);
        $review = $result[0];

        $this->assertSame('José María', $review->authorName);
        $this->assertSame('https://example.com/café', $review->authorUrl);
        $this->assertSame('¡Excelente servicio! Muy recomendado.', $review->body);
        $this->assertSame('es-ES', $review->locale);
    }

    private function createTestReview(): Review
    {
        return new Review(
            id: 1,
            minisiteId: 123,
            authorName: 'John Doe',
            authorUrl: 'https://example.com',
            rating: 4.5,
            body: 'Great service!',
            locale: 'en-US',
            visitedMonth: '2025-01',
            source: 'google',
            sourceId: 'g-123',
            status: 'approved',
            createdAt: new DateTimeImmutable('2025-01-15T10:00:00Z'),
            updatedAt: new DateTimeImmutable('2025-01-15T10:30:00Z'),
            createdBy: 1
        );
    }
}

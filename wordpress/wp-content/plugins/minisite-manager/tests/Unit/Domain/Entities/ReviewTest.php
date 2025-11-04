<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Minisite\Domain\Entities\Review;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Review::class)]
final class ReviewTest extends TestCase
{
    public function testConstructorSetsDefaultValues(): void
    {
        $review = new Review();

        $this->assertNull($review->id);
        $this->assertInstanceOf(DateTimeImmutable::class, $review->createdAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $review->updatedAt);
        $this->assertSame('manual', $review->source);
        $this->assertSame('approved', $review->status);
        $this->assertFalse($review->isEmailVerified);
        $this->assertFalse($review->isPhoneVerified);
        $this->assertSame(0, $review->helpfulCount);
    }

    public function testCanSetAllFields(): void
    {
        $now = new DateTimeImmutable('2025-01-01T00:00:00Z');
        $published = new DateTimeImmutable('2025-01-02T00:00:00Z');

        $review = new Review();
        $review->id = 1;
        $review->minisiteId = 'minisite-123';
        $review->authorName = 'Alice Smith';
        $review->authorEmail = 'alice@example.com';
        $review->authorPhone = '+1234567890';
        $review->authorUrl = 'https://example.com/alice';
        $review->rating = 4.5;
        $review->body = 'Great service!';
        $review->language = 'en';
        $review->locale = 'en-US';
        $review->visitedMonth = '2025-01';
        $review->source = 'google';
        $review->sourceId = 'g-123';
        $review->status = 'approved';
        $review->isEmailVerified = true;
        $review->isPhoneVerified = false;
        $review->helpfulCount = 5;
        $review->spamScore = 0.1;
        $review->sentimentScore = 0.8;
        $review->displayOrder = 1;
        $review->publishedAt = $published;
        $review->moderationReason = null;
        $review->moderatedBy = null;
        $review->createdAt = $now;
        $review->updatedAt = $now;
        $review->createdBy = 10;

        $this->assertSame(1, $review->id);
        $this->assertSame('minisite-123', $review->minisiteId);
        $this->assertSame('Alice Smith', $review->authorName);
        $this->assertSame('alice@example.com', $review->authorEmail);
        $this->assertSame('+1234567890', $review->authorPhone);
        $this->assertSame('https://example.com/alice', $review->authorUrl);
        $this->assertSame(4.5, $review->rating);
        $this->assertSame('Great service!', $review->body);
        $this->assertSame('en', $review->language);
        $this->assertSame('en-US', $review->locale);
        $this->assertSame('2025-01', $review->visitedMonth);
        $this->assertSame('google', $review->source);
        $this->assertSame('g-123', $review->sourceId);
        $this->assertSame('approved', $review->status);
        $this->assertTrue($review->isEmailVerified);
        $this->assertFalse($review->isPhoneVerified);
        $this->assertSame(5, $review->helpfulCount);
        $this->assertSame(0.1, $review->spamScore);
        $this->assertSame(0.8, $review->sentimentScore);
        $this->assertSame(1, $review->displayOrder);
        $this->assertSame($published, $review->publishedAt);
        $this->assertSame($now, $review->createdAt);
        $this->assertSame($now, $review->updatedAt);
        $this->assertSame(10, $review->createdBy);
    }

    public function testOptionalFieldsCanBeNull(): void
    {
        $review = new Review();
        $review->minisiteId = 'minisite-456';
        $review->authorName = 'Bob';
        $review->rating = 5.0;
        $review->body = 'Excellent!';
        
        // All optional fields should be nullable
        $this->assertNull($review->authorEmail);
        $this->assertNull($review->authorPhone);
        $this->assertNull($review->authorUrl);
        $this->assertNull($review->language);
        $this->assertNull($review->locale);
        $this->assertNull($review->visitedMonth);
        $this->assertNull($review->sourceId);
        $this->assertNull($review->spamScore);
        $this->assertNull($review->sentimentScore);
        $this->assertNull($review->displayOrder);
        $this->assertNull($review->publishedAt);
        $this->assertNull($review->moderationReason);
        $this->assertNull($review->moderatedBy);
        $this->assertNull($review->createdBy);
    }

    public function testMarkAsPublished(): void
    {
        $before = new DateTimeImmutable();
        
        $review = new Review();
        $review->status = 'pending';
        $review->markAsPublished();
        
        usleep(1000); // Small delay to ensure timestamp is different
        $after = new DateTimeImmutable();
        
        $this->assertSame('approved', $review->status);
        $this->assertNotNull($review->publishedAt);
        $this->assertGreaterThanOrEqual($before, $review->publishedAt);
        $this->assertLessThanOrEqual($after, $review->publishedAt);
        $this->assertGreaterThanOrEqual($before, $review->updatedAt);
    }

    public function testMarkAsPublishedWithModerator(): void
    {
        $review = new Review();
        $review->status = 'pending';
        $review->markAsPublished(42);
        
        $this->assertSame('approved', $review->status);
        $this->assertNotNull($review->publishedAt);
        $this->assertSame(42, $review->moderatedBy);
    }

    public function testMarkAsRejected(): void
    {
        $before = new DateTimeImmutable();
        
        $review = new Review();
        $review->status = 'pending';
        $review->markAsRejected('Spam content');
        
        usleep(1000);
        $after = new DateTimeImmutable();
        
        $this->assertSame('rejected', $review->status);
        $this->assertSame('Spam content', $review->moderationReason);
        $this->assertGreaterThanOrEqual($before, $review->updatedAt);
        $this->assertLessThanOrEqual($after, $review->updatedAt);
    }

    public function testMarkAsRejectedWithModerator(): void
    {
        $review = new Review();
        $review->status = 'pending';
        $review->markAsRejected('Inappropriate', 99);
        
        $this->assertSame('rejected', $review->status);
        $this->assertSame('Inappropriate', $review->moderationReason);
        $this->assertSame(99, $review->moderatedBy);
    }

    public function testMarkAsFlagged(): void
    {
        $before = new DateTimeImmutable();
        
        $review = new Review();
        $review->status = 'approved';
        $review->markAsFlagged('Needs review');
        
        usleep(1000);
        $after = new DateTimeImmutable();
        
        $this->assertSame('flagged', $review->status);
        $this->assertSame('Needs review', $review->moderationReason);
        $this->assertGreaterThanOrEqual($before, $review->updatedAt);
        $this->assertLessThanOrEqual($after, $review->updatedAt);
    }

    public function testMarkAsFlaggedWithModerator(): void
    {
        $review = new Review();
        $review->status = 'approved';
        $review->markAsFlagged('Suspicious activity', 88);
        
        $this->assertSame('flagged', $review->status);
        $this->assertSame('Suspicious activity', $review->moderationReason);
        $this->assertSame(88, $review->moderatedBy);
    }

    public function testTouchUpdatesTimestamp(): void
    {
        $review = new Review();
        $originalUpdatedAt = $review->updatedAt;
        
        usleep(1000); // Small delay
        $review->touch();
        
        $this->assertGreaterThan($originalUpdatedAt, $review->updatedAt);
    }

    #[DataProvider('dpValidStatuses')]
    public function testStoresValidStatuses(string $status): void
    {
        $review = new Review();
        $review->status = $status;
        
        $this->assertSame($status, $review->status);
    }

    public static function dpValidStatuses(): array
    {
        return [
            ['pending'],
            ['approved'],
            ['rejected'],
            ['flagged'],
        ];
    }

    #[DataProvider('dpValidSources')]
    public function testStoresValidSources(string $source): void
    {
        $review = new Review();
        $review->source = $source;
        
        $this->assertSame($source, $review->source);
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

    public function testVerificationFlagsWorkIndependently(): void
    {
        $review = new Review();
        
        // Initially both false
        $this->assertFalse($review->isEmailVerified);
        $this->assertFalse($review->isPhoneVerified);
        
        // Set email verified
        $review->isEmailVerified = true;
        $this->assertTrue($review->isEmailVerified);
        $this->assertFalse($review->isPhoneVerified);
        
        // Set phone verified
        $review->isPhoneVerified = true;
        $this->assertTrue($review->isEmailVerified);
        $this->assertTrue($review->isPhoneVerified);
        
        // Unset email
        $review->isEmailVerified = false;
        $this->assertFalse($review->isEmailVerified);
        $this->assertTrue($review->isPhoneVerified);
    }

    public function testScoresCanBeNull(): void
    {
        $review = new Review();
        
        $this->assertNull($review->spamScore);
        $this->assertNull($review->sentimentScore);
        
        // Set values
        $review->spamScore = 0.5;
        $review->sentimentScore = 0.7;
        
        $this->assertSame(0.5, $review->spamScore);
        $this->assertSame(0.7, $review->sentimentScore);
        
        // Can be set back to null
        $review->spamScore = null;
        $review->sentimentScore = null;
        
        $this->assertNull($review->spamScore);
        $this->assertNull($review->sentimentScore);
    }
}

<?php

declare(strict_types=1);

namespace Minisite\Features\ReviewManagement\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Minisite\Features\ReviewManagement\Repositories\ReviewRepository::class)]
#[ORM\Table(name: 'minisite_reviews')]
#[ORM\Index(columns: array('minisite_id'), name: 'idx_minisite')]
#[ORM\Index(columns: array('status', 'created_at'), name: 'idx_status_date')]
#[ORM\Index(columns: array('rating'), name: 'idx_rating')]
final class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: array('unsigned' => true))]
    public ?int $id = null;

    #[ORM\Column(name: 'minisite_id', type: 'string', length: 32)]
    public string $minisiteId;

    #[ORM\Column(name: 'author_name', type: 'string', length: 160)]
    public string $authorName;

    #[ORM\Column(name: 'author_email', type: 'string', length: 255, nullable: true)]
    public ?string $authorEmail = null;

    #[ORM\Column(name: 'author_phone', type: 'string', length: 20, nullable: true)]
    public ?string $authorPhone = null;

    #[ORM\Column(name: 'author_url', type: 'string', length: 300, nullable: true)]
    public ?string $authorUrl = null;

    #[ORM\Column(type: 'decimal', precision: 2, scale: 1)]
    public float $rating;

    #[ORM\Column(type: 'text')]
    public string $body;

    #[ORM\Column(name: 'language', type: 'string', length: 10, nullable: true)]
    public ?string $language = null; // Auto-detected: 'en', 'hi', 'mr', 'gu', etc.

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    public ?string $locale = null; // Keep for compatibility

    #[ORM\Column(name: 'visited_month', type: 'string', length: 7, nullable: true)]
    public ?string $visitedMonth = null; // YYYY-MM

    #[ORM\Column(type: 'string', length: 20)]
    public string $source = 'manual'; // 'manual'|'google'|'yelp'|'facebook'|'other'

    #[ORM\Column(name: 'source_id', type: 'string', length: 160, nullable: true)]
    public ?string $sourceId = null;

    #[ORM\Column(type: 'string', length: 20)]
    public string $status = 'approved'; // 'pending'|'approved'|'rejected'|'flagged'

    #[ORM\Column(name: 'is_email_verified', type: 'boolean', options: array('default' => false))]
    public bool $isEmailVerified = false;

    #[ORM\Column(name: 'is_phone_verified', type: 'boolean', options: array('default' => false))]
    public bool $isPhoneVerified = false;

    #[ORM\Column(name: 'helpful_count', type: 'integer', options: array('default' => 0))]
    public int $helpfulCount = 0;

    #[ORM\Column(name: 'spam_score', type: 'decimal', precision: 3, scale: 2, nullable: true)]
    public ?float $spamScore = null; // Auto-calculated spam probability (0-1)

    #[ORM\Column(name: 'sentiment_score', type: 'decimal', precision: 3, scale: 2, nullable: true)]
    public ?float $sentimentScore = null; // Auto-calculated sentiment (-1 to +1)

    #[ORM\Column(name: 'display_order', type: 'integer', nullable: true)]
    public ?int $displayOrder = null; // Manual sorting for featured reviews

    #[ORM\Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $publishedAt = null; // When review was approved/published

    #[ORM\Column(name: 'moderation_reason', type: 'string', length: 200, nullable: true)]
    public ?string $moderationReason = null; // Why rejected/flagged (for transparency)

    #[ORM\Column(name: 'moderated_by', type: 'bigint', nullable: true, options: array('unsigned' => true))]
    public ?int $moderatedBy = null; // User ID who moderated this

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'created_by', type: 'bigint', nullable: true, options: array('unsigned' => true))]
    public ?int $createdBy = null; // NULL for anonymous, user_id if registered

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Mark review as published (approved)
     */
    public function markAsPublished(?int $moderatedByUserId = null): void
    {
        $this->status = 'approved';
        $this->publishedAt = new \DateTimeImmutable();
        if ($moderatedByUserId !== null) {
            $this->moderatedBy = $moderatedByUserId;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Mark review as rejected
     */
    public function markAsRejected(?string $reason = null, ?int $moderatedByUserId = null): void
    {
        $this->status = 'rejected';
        if ($reason !== null) {
            $this->moderationReason = $reason;
        }
        if ($moderatedByUserId !== null) {
            $this->moderatedBy = $moderatedByUserId;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Mark review as flagged
     */
    public function markAsFlagged(?string $reason = null, ?int $moderatedByUserId = null): void
    {
        $this->status = 'flagged';
        if ($reason !== null) {
            $this->moderationReason = $reason;
        }
        if ($moderatedByUserId !== null) {
            $this->moderatedBy = $moderatedByUserId;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Update timestamp on modification
     */
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

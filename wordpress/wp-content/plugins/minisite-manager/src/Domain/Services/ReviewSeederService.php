<?php

declare(strict_types=1);

namespace Minisite\Domain\Services;

use Minisite\Domain\Entities\Review;
use Minisite\Infrastructure\Persistence\Repositories\ReviewRepositoryInterface;

/**
 * Service for seeding sample review data using Doctrine
 *
 * This replaces the old wpdb-based review insertion in _1_0_0_CreateBase.
 * All review operations (creation, editing, seeding) should use ReviewRepository
 * through this service or directly.
 */
class ReviewSeederService
{
    public function __construct(
        private ReviewRepositoryInterface $reviewRepository
    ) {
    }

    /**
     * Insert a review into the database using Doctrine
     * Sets all 24 MVP fields explicitly
     */
    public function insertReview(
        string $minisiteId,
        string $authorName,
        float $rating,
        string $body,
        ?string $locale = 'en-US',
        ?string $authorEmail = null,
        ?string $authorPhone = null,
        ?string $authorUrl = null,
        ?int $displayOrder = null
    ): Review {
        $nowUser = get_current_user_id() ?: null;

        // Auto-detect language from locale (e.g., 'en-US' -> 'en', 'en-GB' -> 'en', 'en-IN' -> 'en')
        $language = $locale ? substr($locale, 0, 2) : null;

        // Create Review entity using Doctrine
        $review = new Review();

        // Core required fields
        $review->minisiteId = $minisiteId;
        $review->authorName = $authorName;
        $review->rating = $rating;
        $review->body = $body;

        // Optional author fields
        $review->authorEmail = $authorEmail;
        $review->authorPhone = $authorPhone;
        $review->authorUrl = $authorUrl;

        // Language and locale
        $review->language = $language;
        $review->locale = $locale;

        // Visit tracking
        $review->visitedMonth = date('Y-m');

        // Source tracking
        $review->source = 'manual';
        $review->sourceId = null; // Manual reviews don't have source_id

        // Verification flags (explicitly set to false for manual reviews)
        $review->isEmailVerified = false;
        $review->isPhoneVerified = false;

        // Engagement metrics (defaults)
        $review->helpfulCount = 0;
        $review->spamScore = null; // Will be auto-calculated later
        $review->sentimentScore = null; // Will be auto-calculated later

        // Display and sorting
        $review->displayOrder = $displayOrder;

        // Status and moderation
        $review->status = 'approved'; // String status: 'pending'|'approved'|'rejected'|'flagged'
        $review->moderationReason = null;
        $review->moderatedBy = null; // Will be set by markAsPublished if user is logged in

        // Timestamps
        $review->createdAt = new \DateTimeImmutable();
        $review->updatedAt = new \DateTimeImmutable();
        $review->createdBy = $nowUser;

        // Mark as published (sets publishedAt timestamp and optionally moderatedBy)
        $review->markAsPublished($nowUser);

        // Save using repository
        return $this->reviewRepository->save($review);
    }

    /**
     * Create a Review entity from JSON data array
     *
     * Populates all fields from JSON data, with sensible defaults for missing fields.
     *
     * @param string $minisiteId The minisite ID
     * @param array $reviewData Review data from JSON
     * @return Review The created review entity
     */
    public function createReviewFromJsonData(string $minisiteId, array $reviewData): Review
    {
        $nowUser = get_current_user_id() ?: null;

        // Create Review entity
        $review = new Review();

        // Core required fields
        $review->minisiteId = $minisiteId;
        $review->authorName = $reviewData['authorName'] ?? '';
        $review->rating = isset($reviewData['rating']) ? (float) $reviewData['rating'] : 5.0;
        $review->body = $reviewData['body'] ?? '';

        // Optional author fields
        $review->authorEmail = $reviewData['authorEmail'] ?? null;
        $review->authorPhone = $reviewData['authorPhone'] ?? null;
        $review->authorUrl = $reviewData['authorUrl'] ?? null;

        // Language and locale
        $locale = $reviewData['locale'] ?? 'en-US';
        $review->locale = $locale;
        // Use explicit language from JSON, or auto-detect from locale
        $review->language = $reviewData['language'] ?? ($locale ? substr($locale, 0, 2) : null);

        // Visit tracking
        $review->visitedMonth = $reviewData['visitedMonth'] ?? date('Y-m');

        // Source tracking
        $review->source = $reviewData['source'] ?? 'manual';
        $review->sourceId = $reviewData['sourceId'] ?? null;

        // Verification flags
        $review->isEmailVerified = isset($reviewData['isEmailVerified']) ? (bool) $reviewData['isEmailVerified'] : false;
        $review->isPhoneVerified = isset($reviewData['isPhoneVerified']) ? (bool) $reviewData['isPhoneVerified'] : false;

        // Engagement metrics
        $review->helpfulCount = isset($reviewData['helpfulCount']) ? (int) $reviewData['helpfulCount'] : 0;
        $review->spamScore = isset($reviewData['spamScore']) && $reviewData['spamScore'] !== null ? (float) $reviewData['spamScore'] : null;
        $review->sentimentScore = isset($reviewData['sentimentScore']) && $reviewData['sentimentScore'] !== null ? (float) $reviewData['sentimentScore'] : null;

        // Display and sorting
        $review->displayOrder = isset($reviewData['displayOrder']) && $reviewData['displayOrder'] !== null ? (int) $reviewData['displayOrder'] : null;

        // Status and moderation
        $review->status = $reviewData['status'] ?? 'approved';
        $review->moderationReason = $reviewData['moderationReason'] ?? null;
        $review->moderatedBy = isset($reviewData['moderatedBy']) && $reviewData['moderatedBy'] !== null ? (int) $reviewData['moderatedBy'] : null;

        // Timestamps - parse from JSON or use current time
        if (isset($reviewData['createdAt']) && $reviewData['createdAt']) {
            $review->createdAt = new \DateTimeImmutable($reviewData['createdAt']);
        } else {
            $review->createdAt = new \DateTimeImmutable();
        }

        if (isset($reviewData['updatedAt']) && $reviewData['updatedAt']) {
            $review->updatedAt = new \DateTimeImmutable($reviewData['updatedAt']);
        } else {
            $review->updatedAt = new \DateTimeImmutable();
        }

        $review->createdBy = isset($reviewData['createdBy']) && $reviewData['createdBy'] !== null ? (int) $reviewData['createdBy'] : $nowUser;

        // PublishedAt - parse from JSON or set via markAsPublished if approved
        if (isset($reviewData['publishedAt']) && $reviewData['publishedAt']) {
            $review->publishedAt = new \DateTimeImmutable($reviewData['publishedAt']);
        } elseif ($review->status === 'approved') {
            // If status is approved but no publishedAt, mark as published
            $review->markAsPublished($review->moderatedBy ?? $nowUser);
        }

        return $review;
    }

    /**
     * Seed sample reviews for a minisite
     *
     * This is the Doctrine-based replacement for the old review seeding in CreateBase.
     * Now loads all fields from JSON data.
     *
     * @param string $minisiteId The minisite ID to seed reviews for
     * @param array $reviews Array of review data from JSON (all fields supported)
     */
    public function seedReviewsForMinisite(string $minisiteId, array $reviews): void
    {
        foreach ($reviews as $reviewData) {
            $review = $this->createReviewFromJsonData($minisiteId, $reviewData);
            $this->reviewRepository->save($review);
        }
    }

    /**
     * Load reviews from JSON file
     *
     * @param string $jsonFile JSON filename (e.g., 'acme-dental-reviews.json')
     * @return array Array of review data
     * @throws \RuntimeException If file not found or invalid JSON
     */
    protected function loadReviewsFromJson(string $jsonFile): array
    {
        $jsonPath = MINISITE_PLUGIN_DIR . 'data/json/reviews/' . $jsonFile;

        if (!file_exists($jsonPath)) {
            throw new \RuntimeException('JSON file not found: ' . esc_html($jsonPath));
        }

        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Invalid JSON in file: ' . esc_html($jsonFile) . '. Error: ' . esc_html(json_last_error_msg())
            );
        }

        if (!isset($data['reviews']) || !is_array($data['reviews'])) {
            throw new \RuntimeException(
                'Invalid JSON structure in file: ' . esc_html($jsonFile) . '. Missing \'reviews\' array.'
            );
        }

        return $data['reviews'];
    }

    /**
     * Seed all sample reviews for the standard test minisites
     *
     * This seeds reviews for:
     * - ACME Dental (Dallas)
     * - Lotus Textiles (Mumbai)
     * - Green Bites (London)
     * - Swift Transit (Sydney)
     *
     * Reviews are loaded from JSON files in data/json/reviews/
     *
     * @param array $minisiteIds Array with keys: 'ACME', 'LOTUS', 'GREEN', 'SWIFT'
     */
    public function seedAllTestReviews(array $minisiteIds): void
    {
        // Map of minisite keys to their JSON review files
        $reviewFiles = [
            'ACME' => 'acme-dental-reviews.json',
            'LOTUS' => 'lotus-textiles-reviews.json',
            'GREEN' => 'green-bites-reviews.json',
            'SWIFT' => 'swift-transit-reviews.json',
        ];

        foreach ($reviewFiles as $key => $jsonFile) {
            if (!empty($minisiteIds[$key])) {
                try {
                    $reviews = $this->loadReviewsFromJson($jsonFile);
                    $this->seedReviewsForMinisite($minisiteIds[$key], $reviews);
                } catch (\RuntimeException $e) {
                    // Log error but continue with other minisites
                    error_log('Failed to load reviews from ' . $jsonFile . ': ' . $e->getMessage());
                }
            }
        }
    }
}

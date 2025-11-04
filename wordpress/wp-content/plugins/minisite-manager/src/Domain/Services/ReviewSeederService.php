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
     * Seed sample reviews for a minisite
     *
     * This is the Doctrine-based replacement for the old review seeding in CreateBase.
     *
     * @param string $minisiteId The minisite ID to seed reviews for
     * @param array $reviews Array of review data:
     *                      ['authorName' => string, 'rating' => float, 'body' => string, 'locale' => string?]
     */
    public function seedReviewsForMinisite(string $minisiteId, array $reviews): void
    {
        foreach ($reviews as $reviewData) {
            $this->insertReview(
                $minisiteId,
                $reviewData['authorName'],
                $reviewData['rating'],
                $reviewData['body'],
                $reviewData['locale'] ?? 'en-US',
                $reviewData['authorEmail'] ?? null,
                $reviewData['authorPhone'] ?? null
            );
        }
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
     * @param array $minisiteIds Array with keys: 'ACME', 'LOTUS', 'GREEN', 'SWIFT'
     */
    public function seedAllTestReviews(array $minisiteIds): void
    {
        // ACME Dental reviews (5 total)
        if (!empty($minisiteIds['ACME'])) {
            $this->seedReviewsForMinisite($minisiteIds['ACME'], [
                [
                    'authorName' => 'Jane Doe',
                    'rating' => 5.0,
                    'body' => 'The hygienist was incredibly gentle and explained every step before she started. ' .
                             'The clinic is spotless and the equipment looks brand new. ' .
                             'I left feeling well cared for and finally not dreading my next visit.',
                    'locale' => 'en-US',
                ],
                [
                    'authorName' => 'Mark T.',
                    'rating' => 4.5,
                    'body' => 'Booked a last‑minute appointment for a chipped tooth and they fit me in the same day. ' .
                             'The repair was quick and painless, and the billing was clear. ' .
                             'Parking was easy which is a bonus in Dallas.',
                    'locale' => 'en-US',
                ],
                [
                    'authorName' => 'Priya S.',
                    'rating' => 4.8,
                    'body' => 'I had whitening done here and the results were immediate. ' .
                             'The dentist checked sensitivity throughout and gave me clear ' .
                             'aftercare instructions. Front desk followed up the next day to see how I was doing.',
                    'locale' => 'en-US',
                ],
                [
                    'authorName' => 'Daniel K.',
                    'rating' => 4.9,
                    'body' => 'Super organized practice with on‑time appointments. ' .
                             'They walked me through options for a crown and never pushed extras. ' .
                             'Waiting area is calm and the coffee machine is a nice touch.',
                    'locale' => 'en-US',
                ],
                [
                    'authorName' => 'Alicia M.',
                    'rating' => 5.0,
                    'body' => 'Brought my teen for Invisalign and the consultation was thorough ' .
                             'without being overwhelming. Clear timeline, fair pricing, and they ' .
                             'answered all our questions. We feel confident continuing care here.',
                    'locale' => 'en-US',
                ],
            ]);
        }

        // Lotus Textiles reviews (5 total)
        if (!empty($minisiteIds['LOTUS'])) {
            $this->seedReviewsForMinisite($minisiteIds['LOTUS'], [
                [
                    'authorName' => 'Asha P.',
                    'rating' => 5.0,
                    'body' => 'Beautiful fabric selection and honest pricing. ' .
                             'The team helped me pick the right silk and arranged quick alterations. ' .
                             'I received so many compliments at the event.',
                    'locale' => 'en-IN',
                ],
                [
                    'authorName' => 'Rohit K.',
                    'rating' => 4.6,
                    'body' => 'Quality linens and attentive staff. ' .
                             'Turnaround for tailoring was faster than expected and the fit was perfect.',
                    'locale' => 'en-IN',
                ],
                [
                    'authorName' => 'Neha S.',
                    'rating' => 4.8,
                    'body' => 'They sourced a specific shade of chiffon for me within two days. ' .
                             'Great communication throughout and careful packaging.',
                    'locale' => 'en-IN',
                ],
                [
                    'authorName' => 'Imran V.',
                    'rating' => 4.7,
                    'body' => 'Got a sherwani tailored here. ' .
                             'Professional fittings and precise embroidery work. ' .
                             'Delivery was on the promised date.',
                    'locale' => 'en-IN',
                ],
                [
                    'authorName' => 'Kavita D.',
                    'rating' => 4.9,
                    'body' => 'Staff were patient while I compared several silks. ' .
                             'They suggested blouse lining and care tips that really helped.',
                    'locale' => 'en-IN',
                ],
            ]);
        }

        // Green Bites reviews (5 total)
        if (!empty($minisiteIds['GREEN'])) {
            $this->seedReviewsForMinisite($minisiteIds['GREEN'], [
                [
                    'authorName' => 'Alex P.',
                    'rating' => 5.0,
                    'body' => 'Best sourdough in the City. ' .
                             'The crust has real depth of flavor and the bowls are generous. ' .
                             'Staff remembered my usual after two visits.',
                    'locale' => 'en-GB',
                ],
                [
                    'authorName' => 'Maria G.',
                    'rating' => 4.7,
                    'body' => 'Delicious bowls and quick service at lunch. ' .
                             'Great coffee with oat milk, and I love the rotating specials.',
                    'locale' => 'en-GB',
                ],
                [
                    'authorName' => 'Tom H.',
                    'rating' => 4.6,
                    'body' => 'Great place for a quick, healthy lunch. ' .
                             'Seating fills up at noon but the line moves fast.',
                    'locale' => 'en-GB',
                ],
                [
                    'authorName' => 'Ella R.',
                    'rating' => 4.8,
                    'body' => 'Excellent espresso and friendly baristas. ' .
                             'The vegan bowl had great textures and bright flavors.',
                    'locale' => 'en-GB',
                ],
                [
                    'authorName' => 'Ben S.',
                    'rating' => 4.9,
                    'body' => 'Love the seasonal menu changes and the sourdough loaves on Fridays. ' .
                             'Consistently great quality.',
                    'locale' => 'en-GB',
                ],
            ]);
        }

        // Swift Transit reviews (5 total)
        if (!empty($minisiteIds['SWIFT'])) {
            $this->seedReviewsForMinisite($minisiteIds['SWIFT'], [
                [
                    'authorName' => 'Zoe L.',
                    'rating' => 5.0,
                    'body' => 'Super fast and careful with fragile items. ' .
                             'They handled our clinic samples with documented chain-of-custody ' .
                             'and delivered earlier than promised.',
                    'locale' => 'en-AU',
                ],
                [
                    'authorName' => 'Nick R.',
                    'rating' => 4.8,
                    'body' => 'Great communication and tracking. ' .
                             'Dispatch answered within seconds, and the driver called ahead for loading dock access.',
                    'locale' => 'en-AU',
                ],
                [
                    'authorName' => 'Sam D.',
                    'rating' => 4.7,
                    'body' => 'Booked an urgent pickup at 4 pm and it reached the CBD in under an hour. ' .
                             'Clear proof‑of‑delivery emailed instantly.',
                    'locale' => 'en-AU',
                ],
                [
                    'authorName' => 'Priya V.',
                    'rating' => 4.9,
                    'body' => 'Courteous drivers and clean vehicles. ' .
                             'Our bulk transfers were secured properly and arrived without damage.',
                    'locale' => 'en-AU',
                ],
                [
                    'authorName' => 'Owen C.',
                    'rating' => 4.8,
                    'body' => 'We use their scheduled routes daily. ' .
                             'Reliable timings and proactive updates whenever traffic is heavy.',
                    'locale' => 'en-AU',
                ],
            ]);
        }
    }
}

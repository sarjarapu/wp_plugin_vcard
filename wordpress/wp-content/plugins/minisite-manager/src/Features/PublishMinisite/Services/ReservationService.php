<?php

namespace Minisite\Features\PublishMinisite\Services;

use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepositoryInterface;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;
use Minisite\Infrastructure\Utils\ReservationCleanup;
use Psr\Log\LoggerInterface;

/**
 * Reservation Service
 *
 * SINGLE RESPONSIBILITY: Handle slug reservation management
 * - Create slug reservations (5-minute window)
 * - Cancel reservations
 * - Validate reservation ownership
 * - Handle reservation expiration
 * - Auto-renew expired reservations if slug still available (for checkout scenario)
 * - Enforce single active reservation per user
 */
class ReservationService
{
    private LoggerInterface $logger;

    public function __construct(
        private WordPressPublishManager $wordPressManager,
        private MinisiteRepositoryInterface $minisiteRepository
    ) {
        $this->logger = LoggingServiceProvider::getFeatureLogger('reservation-service');
    }

    /**
     * Check if user has active reservation
     */
    public function hasActiveReservation(int $userId): bool
    {
        // TODO: Implement check for active reservations
        return false;
    }

    /**
     * Reserve slug combination
     */
    public function reserveSlug(string $businessSlug, string $locationSlug, int $userId): object
    {
        global $wpdb;
        $reservationsTable = $wpdb->prefix . 'minisite_reservations';

        // Clean up expired reservations first
        ReservationCleanup::cleanupExpired();

        db::query('START TRANSACTION');

        try {
            // Check if combination already exists in minisites
            $existingMinisite = $this->minisiteRepository->findBySlugParams($businessSlug, $locationSlug);
            if ($existingMinisite) {
                // Check if this minisite has an active subscription or is in grace period
                // A slug is protected if: expires_at > NOW() (active) OR grace_period_ends_at > NOW() (grace period)
                $paymentsTable = $wpdb->prefix . 'minisite_payments';
                $activePayment = db::get_row(
                    "SELECT * FROM {$paymentsTable}
                     WHERE minisite_id = %s
                     AND (expires_at > NOW() OR grace_period_ends_at > NOW())
                     LIMIT 1",
                    array($existingMinisite->id)
                );

                if ($activePayment) {
                    // Minisite has active subscription or is in grace period - slug is protected
                    db::query('ROLLBACK');

                    throw new \RuntimeException(
                        'This slug combination is already taken by an existing minisite with an active subscription'
                    );
                }
                // If no active payment found, minisite exists but subscription has fully expired (beyond grace period)
                // Allow reservation to proceed - the slug can be reassigned
            }

            // Check if combination is currently reserved by another user
            $locationSlugForQuery = empty($locationSlug) ? null : $locationSlug;

            if ($locationSlugForQuery === null) {
                $existingReservation = db::get_row(
                    "SELECT * FROM {$reservationsTable}
                     WHERE business_slug = %s AND (location_slug IS NULL OR location_slug = '')
                     AND expires_at > NOW() AND user_id != %d",
                    array($businessSlug, $userId)
                );
            } else {
                $existingReservation = db::get_row(
                    "SELECT * FROM {$reservationsTable}
                     WHERE business_slug = %s AND location_slug = %s
                     AND expires_at > NOW() AND user_id != %d",
                    array($businessSlug, $locationSlug, $userId)
                );
            }

            if ($existingReservation) {
                db::query('ROLLBACK');

                throw new \RuntimeException('This slug combination is currently reserved by another user');
            }

            // If user already has a reservation for this slug, extend it
            if ($locationSlugForQuery === null) {
                $userReservation = db::get_row(
                    "SELECT * FROM {$reservationsTable}
                     WHERE business_slug = %s AND (location_slug IS NULL OR location_slug = '')
                     AND user_id = %d AND expires_at > NOW()",
                    array($businessSlug, $userId)
                );
            } else {
                $userReservation = db::get_row(
                    "SELECT * FROM {$reservationsTable}
                     WHERE business_slug = %s AND location_slug = %s
                     AND user_id = %d AND expires_at > NOW()",
                    array($businessSlug, $locationSlug, $userId)
                );
            }

            if ($userReservation) {
                // Extend existing reservation
                $newExpiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                $reservationId = $userReservation['id'] ?? $userReservation->id ?? null;

                if ($reservationId) {
                    db::query(
                        "UPDATE {$reservationsTable}
                         SET expires_at = %s, created_at = NOW()
                         WHERE id = %d",
                        array($newExpiresAt, $reservationId)
                    );

                    db::query('COMMIT');

                    return (object) array(
                        'reservation_id' => (int) $reservationId,
                        'expires_at' => $newExpiresAt,
                        'expires_in_seconds' => 300,
                        'message' => 'Slug reservation extended for 5 minutes. Complete payment to secure it.',
                    );
                }
            }

            // Create new reservation record
            $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $result = db::insert(
                $reservationsTable,
                array(
                    'business_slug' => $businessSlug,
                    'location_slug' => empty($locationSlug) ? null : $locationSlug,
                    'user_id' => $userId,
                    'minisite_id' => null, // Will be set when payment completes
                    'expires_at' => $expiresAt,
                ),
                array('%s', '%s', '%d', '%s', '%s')
            );

            if ($result === false) {
                db::query('ROLLBACK');

                throw new \RuntimeException('Failed to create reservation');
            }

            $reservationId = (int) db::get_insert_id();
            db::query('COMMIT');

            $this->logger->info('Created slug reservation', array(
                'reservation_id' => $reservationId,
                'business_slug' => $businessSlug,
                'location_slug' => $locationSlug,
                'user_id' => $userId,
            ));

            return (object) array(
                'reservation_id' => $reservationId,
                'expires_at' => $expiresAt,
                'expires_in_seconds' => 300,
                'message' => 'Slug reserved for 5 minutes. Complete payment to secure it.',
            );
        } catch (\Exception $e) {
            db::query('ROLLBACK');
            $this->logger->error('Failed to reserve slug', array(
                'business_slug' => $businessSlug,
                'location_slug' => $locationSlug,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ));

            throw $e;
        }
    }

    /**
     * Cancel reservation
     */
    public function cancelReservation(int $reservationId, int $userId): void
    {
        // TODO: Implement reservation cancellation
        // Validate ownership
        // Delete reservation
    }

    /**
     * Check if reservation is valid
     */
    public function isReservationValid(int $reservationId): bool
    {
        // TODO: Implement reservation validation
        return false;
    }

    /**
     * Try to auto-renew expired reservation (for checkout scenario)
     */
    public function tryAutoRenewExpiredReservation(
        int $reservationId,
        string $businessSlug,
        string $locationSlug
    ): ?object {
        // TODO: Implement auto-renewal logic
        // Only during checkout completion
        // Check if slug still available
        // Create new reservation if available
        return null;
    }
}

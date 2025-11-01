<?php

namespace Minisite\Features\PublishMinisite\Services;

/**
 * Payment Constants
 *
 * SINGLE RESPONSIBILITY: Define constants for payment and subscription calculations
 * - Subscription duration
 * - Grace period duration
 * - Other payment-related constants
 */
final class PaymentConstants
{
    /**
     * Subscription duration in months (default: 12 months = 1 year)
     */
    public const SUBSCRIPTION_DURATION_MONTHS = 12;

    /**
     * Grace period duration in days (default: 7 days = 1 week)
     * After subscription expires, users have this long to renew before URL becomes available to others
     */
    public const GRACE_PERIOD_DAYS = 7;

    /**
     * Calculate grace period end date from expiration date
     */
    public static function calculateGracePeriodEnd(string $expiresAt): string
    {
        return date('Y-m-d H:i:s', strtotime($expiresAt . ' +' . self::GRACE_PERIOD_DAYS . ' days'));
    }

    /**
     * Calculate expiration date from base date (typically 1 year from now)
     */
    public static function calculateExpirationDate(?string $baseDate = null): string
    {
        $base = $baseDate ?: current_time('mysql');
        return date('Y-m-d H:i:s', strtotime($base . ' +' . self::SUBSCRIPTION_DURATION_MONTHS . ' months'));
    }
}


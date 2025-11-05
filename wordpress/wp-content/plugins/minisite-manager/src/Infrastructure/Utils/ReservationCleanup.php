<?php

namespace Minisite\Infrastructure\Utils;

use Minisite\Infrastructure\Utils\DatabaseHelper as db;

final class ReservationCleanup
{
    /**
     * Clean up expired reservations
     */
    public static function cleanupExpired(): int
    {
        global $wpdb;
        $reservationsTable = $wpdb->prefix . 'minisite_reservations';

        return db::query("DELETE FROM {$reservationsTable} WHERE expires_at <= NOW()");
    }
}

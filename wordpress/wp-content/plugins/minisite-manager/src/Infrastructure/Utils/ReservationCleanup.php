<?php

namespace Minisite\Infrastructure\Utils;

final class ReservationCleanup {

	/**
	 * Clean up expired reservations
	 */
	public static function cleanupExpired(): int {
		global $wpdb;
		$reservationsTable = $wpdb->prefix . 'minisite_reservations';
		return $wpdb->query( "DELETE FROM {$reservationsTable} WHERE expires_at <= NOW()" );
	}
}

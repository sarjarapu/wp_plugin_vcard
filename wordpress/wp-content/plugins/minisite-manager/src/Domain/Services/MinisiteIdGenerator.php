<?php

namespace Minisite\Domain\Services;

/**
 * Service for generating unique IDs and managing slug generation for minisites
 */
final class MinisiteIdGenerator {

	/**
	 * Generate a unique 12-byte hex ID for minisites
	 *
	 * @return string 24-character hex string (e.g., "a1b2c3d4e5f6789012345678")
	 */
	public static function generate(): string {
		return bin2hex( random_bytes( 12 ) );
	}

	/**
	 * Generate a temporary slug for draft minisites
	 *
	 * @param string $id The minisite ID
	 * @return string Temporary slug (e.g., "draft-a1b2c3d4")
	 */
	public static function generateTempSlug( string $id ): string {
		return 'draft-' . substr( $id, 0, 8 );
	}
}

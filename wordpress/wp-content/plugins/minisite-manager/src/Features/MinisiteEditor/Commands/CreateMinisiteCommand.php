<?php

namespace Minisite\Features\MinisiteEditor\Commands;

/**
 * Create Minisite Command
 *
 * Represents a request to create a new minisite.
 */
final class CreateMinisiteCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly string $businessSlug,
        public readonly string $locationSlug,
        public readonly string $businessName,
        public readonly string $businessCity,
        public readonly string $businessRegion,
        public readonly string $businessCountry,
        public readonly string $businessPostal,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null
    ) {
    }
}

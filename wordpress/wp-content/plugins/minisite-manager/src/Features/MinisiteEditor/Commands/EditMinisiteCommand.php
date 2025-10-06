<?php

namespace Minisite\Features\MinisiteEditor\Commands;

/**
 * Edit Minisite Command
 *
 * Represents a request to edit an existing minisite.
 */
final class EditMinisiteCommand
{
    public function __construct(
        public readonly string $siteId,
        public readonly int $userId,
        public readonly string $businessName,
        public readonly string $businessCity,
        public readonly string $businessRegion,
        public readonly string $businessCountry,
        public readonly string $businessPostal,
        public readonly string $seoTitle,
        public readonly string $siteTemplate,
        public readonly string $brandPalette,
        public readonly string $brandIndustry,
        public readonly string $defaultLocale,
        public readonly string $searchTerms,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly string $versionLabel = '',
        public readonly string $versionComment = ''
    ) {
    }
}

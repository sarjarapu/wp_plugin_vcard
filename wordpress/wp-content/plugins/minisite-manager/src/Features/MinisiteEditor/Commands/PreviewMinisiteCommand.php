<?php

namespace Minisite\Features\MinisiteEditor\Commands;

/**
 * Preview Minisite Command
 *
 * Represents a request to preview a minisite.
 */
final class PreviewMinisiteCommand
{
    public function __construct(
        public readonly string $siteId,
        public readonly int $userId,
        public readonly string $versionId
    ) {
    }
}

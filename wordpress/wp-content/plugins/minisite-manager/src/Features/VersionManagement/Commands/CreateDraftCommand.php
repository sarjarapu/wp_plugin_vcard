<?php

namespace Minisite\Features\VersionManagement\Commands;

/**
 * Command for creating a new draft version
 */
class CreateDraftCommand
{
    public function __construct(
        public readonly string $siteId,
        public readonly int $userId,
        public readonly string $label,
        public readonly string $comment,
        public readonly array $siteJson
    ) {
    }
}

<?php

namespace Minisite\Features\VersionManagement\Commands;

/**
 * Command for listing versions of a minisite
 */
class ListVersionsCommand
{
    public function __construct(
        public readonly string $siteId,
        public readonly int $userId
    ) {
    }
}

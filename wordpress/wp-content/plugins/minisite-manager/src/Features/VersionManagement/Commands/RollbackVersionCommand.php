<?php

namespace Minisite\Features\VersionManagement\Commands;

/**
 * Command for creating a rollback version
 */
class RollbackVersionCommand
{
    public function __construct(
        public readonly string $siteId,
        public readonly int $sourceVersionId,
        public readonly int $userId
    ) {
    }
}

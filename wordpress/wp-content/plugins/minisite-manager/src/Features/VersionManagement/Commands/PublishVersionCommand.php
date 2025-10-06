<?php

namespace Minisite\Features\VersionManagement\Commands;

/**
 * Command for publishing a version
 */
class PublishVersionCommand
{
    public function __construct(
        public readonly string $siteId,
        public readonly int $versionId,
        public readonly int $userId
    ) {
    }
}

<?php

namespace Minisite\Features\MinisiteListing\Commands;

/**
 * List Minisites Command
 *
 * Represents a request to list user's minisites.
 */
final class ListMinisitesCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly int $limit = 50,
        public readonly int $offset = 0
    ) {
    }
}

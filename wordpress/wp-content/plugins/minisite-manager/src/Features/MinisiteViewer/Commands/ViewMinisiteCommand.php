<?php

namespace Minisite\Features\MinisiteViewer\Commands;

/**
 * View Minisite Command
 *
 * SINGLE RESPONSIBILITY: Encapsulate data for viewing a minisite
 * - Contains business slug and location slug
 * - Immutable data transfer object
 * - Used by handlers to process view requests
 */
final class ViewMinisiteCommand
{
    public function __construct(
        public readonly string $businessSlug,
        public readonly string $locationSlug
    ) {
    }
}

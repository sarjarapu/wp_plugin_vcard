<?php

namespace Minisite\Features\MinisiteViewer\Commands;

/**
 * Display Minisite Command
 *
 * SINGLE RESPONSIBILITY: Encapsulate data for displaying a minisite
 * - Contains business slug and location slug
 * - Immutable data transfer object
 * - Used by handlers to process display requests
 */
final class DisplayMinisiteCommand
{
    public function __construct(
        public readonly string $businessSlug,
        public readonly string $locationSlug
    ) {
    }
}

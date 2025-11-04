<?php

namespace Minisite\Features\ConfigurationManagement\Commands;

/**
 * SaveConfigCommand
 *
 * SINGLE RESPONSIBILITY: Data transfer object for saving configuration
 */
final class SaveConfigCommand
{
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $type,
        public readonly ?string $description = null
    ) {
    }
}

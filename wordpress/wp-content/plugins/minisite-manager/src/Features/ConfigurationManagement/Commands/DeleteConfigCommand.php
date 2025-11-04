<?php

namespace Minisite\Features\ConfigurationManagement\Commands;

/**
 * DeleteConfigCommand
 *
 * SINGLE RESPONSIBILITY: Data transfer object for deleting configuration
 */
final class DeleteConfigCommand
{
    public function __construct(
        public readonly string $key
    ) {
    }
}

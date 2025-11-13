<?php

declare(strict_types=1);

namespace Tests\Support;

use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface;

/**
 * Helper trait to ensure globals-based repository dependencies are available.
 */
trait MinisiteRepositoryGlobals
{
    protected function setUpMinisiteRepositoryGlobals(): void
    {
        $GLOBALS['minisite_repository'] = $this->createMock(MinisiteRepositoryInterface::class);
        $GLOBALS['minisite_version_repository'] = $this->createMock(VersionRepositoryInterface::class);
    }

    protected function tearDownMinisiteRepositoryGlobals(): void
    {
        unset($GLOBALS['minisite_repository'], $GLOBALS['minisite_version_repository']);
    }
}



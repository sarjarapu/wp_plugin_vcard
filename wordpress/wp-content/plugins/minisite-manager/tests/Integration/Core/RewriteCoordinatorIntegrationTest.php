<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Minisite\Core\RewriteCoordinator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CoreTestCase;

#[Group('integration')]
#[CoversClass(RewriteCoordinator::class)]
final class RewriteCoordinatorIntegrationTest extends CoreTestCase
{
    public function testAddQueryVarsReturnsExpectedList(): void
    {
        $vars = RewriteCoordinator::addQueryVars(array());

        $this->assertContains('minisite_version_id', $vars);
        $this->assertCount(7, $vars);
    }
}

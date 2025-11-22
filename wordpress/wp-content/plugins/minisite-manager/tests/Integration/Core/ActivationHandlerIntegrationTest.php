<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Minisite\Core\ActivationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CoreTestCase;

#[Group('integration')]
#[CoversClass(ActivationHandler::class)]
final class ActivationHandlerIntegrationTest extends CoreTestCase
{
    public function testHandleSetsRewriteFlagAndRegistersSeederHook(): void
    {
        ActivationHandler::setMigrationRunnerFactory(static fn () => new class {
            public function migrate(): void
            {
            }
        });
        ActivationHandler::setRoleSyncCallback(static function (): void {
        });

        ActivationHandler::handle();

        $this->assertSame(1, $GLOBALS['_test_options']['minisite_flush_rewrites']);
        $initCallbacks = $GLOBALS['wp_filter']->callbacks['init'][15] ?? array();
        $this->assertNotEmpty($initCallbacks);
    }
}

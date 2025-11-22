<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Brain\Monkey\Functions;
use Minisite\Core\DeactivationHandler;
use Tests\Support\CoreTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(DeactivationHandler::class)]
final class DeactivationHandlerTest extends CoreTestCase
{
    public function testHandleFlushesRewritesAndCleansUpNonProduction(): void
    {
        DeactivationHandler::setProductionOverride(false);
        Functions\expect('flush_rewrite_rules')->once()->andReturnNull();

        // Seed roles/options
        update_option(MINISITE_DB_OPTION, '1.0.0');
        add_role('minisite_user', 'User');

        DeactivationHandler::handle();

        $this->assertArrayNotHasKey(MINISITE_DB_OPTION, $GLOBALS['_test_options'] ?? array());
        $this->assertArrayNotHasKey('minisite_user', $GLOBALS['_test_roles'] ?? array());
    }

    public function testHandleSkipsCleanupInProduction(): void
    {
        DeactivationHandler::setProductionOverride(true);
        Functions\expect('flush_rewrite_rules')->once()->andReturnNull();

        add_role('minisite_member', 'Member');
        update_option(MINISITE_DB_OPTION, '1.0.0');

        DeactivationHandler::handle();

        $this->assertArrayHasKey('minisite_member', $GLOBALS['_test_roles']);
        $this->assertSame('1.0.0', $GLOBALS['_test_options'][MINISITE_DB_OPTION]);
    }

    public function testCleanupRemovesAllCustomRoles(): void
    {
        DeactivationHandler::setProductionOverride(false);
        Functions\expect('flush_rewrite_rules')->once()->andReturnNull();

        add_role('minisite_user', 'User');
        add_role('minisite_member', 'Member');
        add_role('minisite_power', 'Power');
        add_role('minisite_admin', 'Admin');

        DeactivationHandler::handle();

        foreach (array('minisite_user', 'minisite_member', 'minisite_power', 'minisite_admin') as $role) {
            $this->assertArrayNotHasKey($role, $GLOBALS['_test_roles']);
        }
    }
}

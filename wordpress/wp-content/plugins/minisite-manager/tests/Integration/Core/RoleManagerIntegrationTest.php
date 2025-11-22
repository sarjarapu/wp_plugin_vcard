<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Minisite\Core\RoleManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CoreTestCase;

#[Group('integration')]
#[CoversClass(RoleManager::class)]
final class RoleManagerIntegrationTest extends CoreTestCase
{
    public function testSyncRolesCreatesCustomRoles(): void
    {
        add_role('administrator', 'Administrator');

        RoleManager::syncRolesAndCapabilities();

        $this->assertArrayHasKey('minisite_admin', $GLOBALS['_test_roles']);
        $adminRole = get_role('administrator');
        $this->assertTrue($adminRole->has_cap('minisite_manage_plugin'));
    }
}

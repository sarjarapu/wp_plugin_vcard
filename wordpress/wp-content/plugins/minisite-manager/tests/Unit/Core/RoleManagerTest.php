<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Brain\Monkey\Functions;
use Minisite\Core\RoleManager;
use Tests\Support\CoreTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(RoleManager::class)]
final class RoleManagerTest extends CoreTestCase
{
    public function testInitializeRegistersSyncHook(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('init', array(RoleManager::class, 'syncRolesAndCapabilities'), 20)
            ->andReturnNull();

        RoleManager::initialize();
    }

    public function testSyncRolesRegistersRolesAndGrantsAdministrator(): void
    {
        add_role('administrator', 'Admin');

        RoleManager::syncRolesAndCapabilities();

        $roles = $GLOBALS['_test_roles'];
        $this->assertArrayHasKey('minisite_user', $roles);
        $this->assertArrayHasKey('minisite_admin', $roles);

        $adminRole = get_role('administrator');
        $this->assertTrue($adminRole->has_cap('minisite_manage_plugin'));
    }

    public function testAddOrUpdateRoleAddsCapabilitiesToExistingRole(): void
    {
        add_role('custom_role', 'Custom', array('read' => true));

        $method = $this->getPrivateMethod('addOrUpdateRole');
        $method->invoke(null, 'custom_role', 'Custom', array('new_cap' => true));

        $role = get_role('custom_role');
        $this->assertTrue($role->has_cap('new_cap'));
    }

    public function testGrantAdminCapabilitiesHandlesMissingRole(): void
    {
        $method = $this->getPrivateMethod('grantAdminCapabilities');
        $method->invoke(null, array('minisite_read'));

        $this->assertTrue(true, 'Method should complete without errors when admin role is absent');
    }

    private function getPrivateMethod(string $name): \ReflectionMethod
    {
        $reflection = new \ReflectionClass(RoleManager::class);
        $method = $reflection->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}

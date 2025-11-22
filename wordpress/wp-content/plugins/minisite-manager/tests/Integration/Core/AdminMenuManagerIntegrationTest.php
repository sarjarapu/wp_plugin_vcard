<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Minisite\Core\AdminMenuManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CoreTestCase;

#[Group('integration')]
#[CoversClass(AdminMenuManager::class)]
final class AdminMenuManagerIntegrationTest extends CoreTestCase
{
    public function testRegisterPopulatesMenuArrays(): void
    {
        $manager = new AdminMenuManager();
        $manager->register();

        $this->assertNotEmpty($GLOBALS['_test_admin_menus']['menu']);
        $this->assertNotEmpty($GLOBALS['_test_admin_menus']['submenu']);
    }
}

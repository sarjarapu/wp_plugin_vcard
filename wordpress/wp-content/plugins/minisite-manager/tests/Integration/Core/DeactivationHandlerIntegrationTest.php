<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Minisite\Core\DeactivationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CoreTestCase;

#[Group('integration')]
#[CoversClass(DeactivationHandler::class)]
final class DeactivationHandlerIntegrationTest extends CoreTestCase
{
    public function testHandleRemovesRolesAndDeletesOption(): void
    {
        add_role('administrator', 'Admin');
        add_role('minisite_user', 'User');
        update_option(MINISITE_DB_OPTION, '1.0.0');

        DeactivationHandler::setProductionOverride(false);
        DeactivationHandler::handle();

        $this->assertArrayNotHasKey('minisite_user', $GLOBALS['_test_roles']);
        $this->assertArrayNotHasKey(MINISITE_DB_OPTION, $GLOBALS['_test_options']);
    }
}

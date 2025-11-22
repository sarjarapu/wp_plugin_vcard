<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Brain\Monkey\Functions;
use Minisite\Core\RewriteCoordinator;
use Tests\Support\CoreTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(RewriteCoordinator::class)]
final class RewriteCoordinatorTest extends CoreTestCase
{
    public function testInitializeRegistersQueryVarsFilterAndFlushesWhenFlagSet(): void
    {
        update_option('minisite_flush_rewrites', 1);
        Functions\expect('flush_rewrite_rules')->once()->andReturnNull();
        Functions\expect('delete_option')->once()->with('minisite_flush_rewrites')->andReturnTrue();

        RewriteCoordinator::initialize();

        $callbacks = $GLOBALS['wp_filter']->callbacks['query_vars'] ?? array();
        $this->assertNotEmpty($callbacks);
    }

    public function testInitializeDoesNotFlushWhenFlagMissing(): void
    {
        Functions\expect('flush_rewrite_rules')->never();

        RewriteCoordinator::initialize();
    }

    public function testAddQueryVarsAppendsExpectedVariables(): void
    {
        $result = RewriteCoordinator::addQueryVars(array('existing'));

        $this->assertEquals(
            array(
                'existing',
                'minisite',
                'minisite_biz',
                'minisite_loc',
                'minisite_account',
                'minisite_account_action',
                'minisite_id',
                'minisite_version_id',
            ),
            $result
        );
    }
}

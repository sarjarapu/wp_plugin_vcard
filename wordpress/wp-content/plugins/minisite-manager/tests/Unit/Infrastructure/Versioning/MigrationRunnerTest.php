<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\Contracts\Migration;
use Minisite\Infrastructure\Versioning\MigrationRunner;
use Minisite\Infrastructure\Versioning\MigrationLocator;
use PHPUnit\Framework\TestCase;

// wpdb stub & get_option/update_option are provided in tests/bootstrap.php

final class MigrationRunnerTest extends TestCase
{
    /** Create a Migration impl whose up/down invoke closures (receiving $wpdb) */
    private function fakeMigration(string $version, string $desc, \Closure $onUp, \Closure $onDown): Migration
    {
        return new class($version, $desc, $onUp, $onDown) implements Migration {
            public function __construct(
                private string $v,
                private string $d,
                private \Closure $u,
                private \Closure $w
            ) {}
            public function version(): string { return $this->v; }
            public function description(): string { return $this->d; }
            public function up(\wpdb $wpdb): void { ($this->u)($wpdb); }
            public function down(\wpdb $wpdb): void { ($this->w)($wpdb); }
        };
    }

    /** @return MigrationLocator PHPUnit mock that returns given migrations */
    private function mockLocator(array $migrations): MigrationLocator
    {
        $locator = $this->getMockBuilder(MigrationLocator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['all'])
            ->getMock();
        $locator->method('all')->willReturn($migrations);
        return $locator;
    }

    public function testUpgradeRunsPendingOnlyAndUpdatesOption(): void
    {
        $called = [];
        $m1 = $this->fakeMigration(
            '1.0.0',
            'base',
            function(\wpdb $db) use (&$called) { $called[] = 'up-1.0.0'; },
            function(\wpdb $db) use (&$called) { $called[] = 'down-1.0.0'; }
        );
        $m2 = $this->fakeMigration(
            '1.1.0',
            'feature',
            function(\wpdb $db) use (&$called) { $called[] = 'up-1.1.0'; },
            function(\wpdb $db) use (&$called) { $called[] = 'down-1.1.0'; }
        );

        // Start at 1.0.0 → only 1.1.0 should run
        update_option('minisite_db_version', '1.0.0');

        $runner = new MigrationRunner('1.1.0', 'minisite_db_version', $this->mockLocator([$m1, $m2]));
        $runner->upgradeTo(new \wpdb(), static function ($msg) {});

        $this->assertSame(['up-1.1.0'], $called);
        $this->assertSame('1.1.0', get_option('minisite_db_version'));
    }

    public function testUpgradeIsIdempotentWhenAtTarget(): void
    {
        $called = [];
        $m1 = $this->fakeMigration(
            '1.0.0',
            'base',
            function(\wpdb $db) use (&$called) { $called[] = 'up-1.0.0'; },
            function(\wpdb $db) use (&$called) { $called[] = 'down-1.0.0'; }
        );

        update_option('minisite_db_version', '1.0.0');

        $runner = new MigrationRunner('1.0.0', 'minisite_db_version', $this->mockLocator([$m1]));
        $runner->upgradeTo(new \wpdb(), static function ($msg) {});

        $this->assertSame([], $called, 'no migrations should run when already at target');
        $this->assertSame('1.0.0', get_option('minisite_db_version'));
    }
}

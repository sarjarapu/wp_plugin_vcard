<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\Contracts\Migration;
use Minisite\Infrastructure\Versioning\MigrationRunner;
use PHPUnit\Framework\TestCase;

// wpdb stub & get_option/update_option are provided in tests/bootstrap.php

final class MigrationRunnerTest extends TestCase
{
    /** @return object with ->all(): array<int,Migration> */
    private function fakeLocator(array $migrations)
    {
        return new class($migrations) {
            /** @var array<int,Migration> */
            private array $migrations;
            public function __construct(array $m) { $this->migrations = $m; }
            /** @return array<int,Migration> */
            public function all(): array { return $this->migrations; }
        };
    }

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

    public function testUpgradeRunsPendingOnlyAndUpdatesOption(): void
    {
        $called = [];
        $m1 = $this->fakeMigration('1.0.0', 'base',    fn(\wpdb $db) => $called[] = 'up-1.0.0',   fn(\wpdb $db) => $called[] = 'down-1.0.0');
        $m2 = $this->fakeMigration('1.1.0', 'feature', fn(\wpdb $db) => $called[] = 'up-1.1.0',   fn(\wpdb $db) => $called[] = 'down-1.1.0');

        // Start at 1.0.0 → only 1.1.0 should run
        update_option('minisite_db_version', '1.0.0');

        $runner = new MigrationRunner('1.1.0', 'minisite_db_version', $this->fakeLocator([$m1, $m2]));
        $runner->upgradeTo(new \wpdb(), static function ($msg) {});

        $this->assertSame(['up-1.1.0'], $called);
        $this->assertSame('1.1.0', get_option('minisite_db_version'));
    }

    public function testUpgradeIsIdempotentWhenAtTarget(): void
    {
        $called = [];
        $m1 = $this->fakeMigration('1.0.0', 'base', fn(\wpdb $db) => $called[] = 'up-1.0.0', fn(\wpdb $db) => $called[] = 'down-1.0.0');

        update_option('minisite_db_version', '1.0.0');

        $runner = new MigrationRunner('1.0.0', 'minisite_db_version', $this->fakeLocator([$m1]));
        $runner->upgradeTo(new \wpdb(), static function ($msg) {});

        $this->assertSame([], $called, 'no migrations should run when already at target');
        $this->assertSame('1.0.0', get_option('minisite_db_version'));
    }
}

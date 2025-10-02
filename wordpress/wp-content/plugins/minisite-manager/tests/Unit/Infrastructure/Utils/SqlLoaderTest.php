<?php

namespace Tests\Unit\Infrastructure\Utils;

use Minisite\Infrastructure\Utils\SqlLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class SqlLoaderTest extends TestCase
{
    private string $testSqlPath;
    private string $pluginRoot;

    protected function setUp(): void
    {
        $this->pluginRoot = dirname(__DIR__, 4); // Go up from tests/Unit/Infrastructure/Utils to plugin root
        $this->testSqlPath = 'test_table.sql'; // Just the filename, SqlLoader will handle the path
    }

    public function test_loadAndProcess_replaces_variables_correctly(): void
    {
        $variables = [
            'prefix' => 'wp_',
            'charset' => 'utf8mb4_unicode_ci'
        ];

        $result = SqlLoader::loadAndProcess($this->testSqlPath, $variables);

        $this->assertStringNotContainsString('{$prefix}', $result);
        $this->assertStringNotContainsString('{$charset}', $result);
        $this->assertStringContainsString('CREATE TABLE wp_test_table', $result);
        $this->assertStringContainsString('utf8mb4_unicode_ci', $result);
    }

    public function test_loadAndProcess_with_custom_variables(): void
    {
        $variables = [
            'prefix' => 'test_',
            'charset' => 'utf8mb4_general_ci'
        ];

        $result = SqlLoader::loadAndProcess($this->testSqlPath, $variables);

        $this->assertStringContainsString('CREATE TABLE test_test_table', $result);
        $this->assertStringContainsString('utf8mb4_general_ci', $result);
    }

    public function test_loadAndProcess_with_partial_variables(): void
    {
        $variables = [
            'prefix' => 'wp_'
            // charset not provided
        ];

        $result = SqlLoader::loadAndProcess($this->testSqlPath, $variables);

        $this->assertStringContainsString('CREATE TABLE wp_test_table', $result);
        $this->assertStringContainsString('{$charset}', $result); // Should remain unreplaced
    }

    public function test_loadAndProcess_with_empty_variables(): void
    {
        $result = SqlLoader::loadAndProcess($this->testSqlPath, []);

        $this->assertStringContainsString('{$prefix}', $result); // Should remain unreplaced
        $this->assertStringContainsString('{$charset}', $result); // Should remain unreplaced
    }

    public function test_loadAndProcess_throws_exception_for_nonexistent_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL file not found');

        SqlLoader::loadAndProcess('nonexistent/file.sql', []);
    }

    public function test_createStandardVariables_with_mock_wpdb(): void
    {
        $mockWpdb = new class {
            public $prefix = 'wp_';
            public function get_charset_collate()
            {
                return 'utf8mb4_unicode_ci';
            }
        };

        $result = SqlLoader::createStandardVariables($mockWpdb);

        $this->assertEquals([
            'prefix' => 'wp_',
            'charset' => 'utf8mb4_unicode_ci'
        ], $result);
    }

    public function test_createStandardVariables_with_different_values(): void
    {
        $mockWpdb = new class {
            public $prefix = 'test_';
            public function get_charset_collate()
            {
                return 'utf8mb4_general_ci';
            }
        };

        $result = SqlLoader::createStandardVariables($mockWpdb);

        $this->assertEquals([
            'prefix' => 'test_',
            'charset' => 'utf8mb4_general_ci'
        ], $result);
    }

    public function test_getFullPath_handles_relative_paths(): void
    {
        // Test that the method correctly resolves paths
        $reflection = new \ReflectionClass(SqlLoader::class);
        $method = $reflection->getMethod('getFullPath');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'minisites.sql');
        $expected = $this->pluginRoot . '/data/db/tables/minisites.sql';
        $this->assertEquals($expected, $result);
    }

    public function test_getFullPath_handles_absolute_paths(): void
    {
        $reflection = new \ReflectionClass(SqlLoader::class);
        $method = $reflection->getMethod('getFullPath');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'data/db/tables/minisites.sql');
        $expected = $this->pluginRoot . '/data/db/tables/minisites.sql';
        $this->assertEquals($expected, $result);
    }

    public function test_replaceVariables_handles_complex_sql(): void
    {
        $reflection = new \ReflectionClass(SqlLoader::class);
        $method = $reflection->getMethod('replaceVariables');
        $method->setAccessible(true);

        $sql = "CREATE TABLE {\$prefix}users (id INT, name VARCHAR(100)) ENGINE=InnoDB {\$charset};";
        $variables = [
            'prefix' => 'wp_',
            'charset' => 'utf8mb4_unicode_ci'
        ];

        $result = $method->invoke(null, $sql, $variables);

        $expected = "CREATE TABLE wp_users (id INT, name VARCHAR(100)) ENGINE=InnoDB utf8mb4_unicode_ci;";
        $this->assertEquals($expected, $result);
    }

    public function test_replaceVariables_handles_missing_variables(): void
    {
        $reflection = new \ReflectionClass(SqlLoader::class);
        $method = $reflection->getMethod('replaceVariables');
        $method->setAccessible(true);

        $sql = "CREATE TABLE {\$prefix}users (id INT) ENGINE=InnoDB {\$charset};";
        $variables = [
            'prefix' => 'wp_'
            // charset missing
        ];

        $result = $method->invoke(null, $sql, $variables);

        $expected = "CREATE TABLE wp_users (id INT) ENGINE=InnoDB {\$charset};";
        $this->assertEquals($expected, $result);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_loadAndExecute_calls_loadAndProcess_and_dbDelta(): void
    {
        // Create a mock wpdb object
        $mockWpdb = new class {
            public $prefix = 'wp_';
            public function get_charset_collate()
            {
                return 'utf8mb4_unicode_ci';
            }
        };

        // Mock DbDelta::run to verify it's called
        $this->mockDbDeltaFunction();

        // Test that loadAndExecute calls loadAndProcess and then DbDelta::run
        SqlLoader::loadAndExecute($this->testSqlPath, [
            'prefix' => 'wp_',
            'charset' => 'utf8mb4_unicode_ci'
        ]);

        // Verify DbDelta was called
        $this->assertTrue(isset($GLOBALS['__mock_dbDelta_called']));
        $this->assertTrue($GLOBALS['__mock_dbDelta_called']);

        // Verify the SQL passed to DbDelta contains processed variables
        $this->assertStringContainsString('CREATE TABLE wp_test_table', $GLOBALS['__mock_dbDelta_sql']);
        $this->assertStringContainsString('utf8mb4_unicode_ci', $GLOBALS['__mock_dbDelta_sql']);
        $this->assertStringNotContainsString('{$prefix}', $GLOBALS['__mock_dbDelta_sql']);
        $this->assertStringNotContainsString('{$charset}', $GLOBALS['__mock_dbDelta_sql']);

        // Clean up global state
        unset($GLOBALS['__mock_dbDelta_called']);
        unset($GLOBALS['__mock_dbDelta_sql']);
    }

    public function test_loadAndExecute_throws_exception_for_nonexistent_file(): void
    {
        $mockWpdb = new class {
            public $prefix = 'wp_';
            public function get_charset_collate()
            {
                return 'utf8mb4_unicode_ci';
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL file not found');

        SqlLoader::loadAndExecute('nonexistent_file.sql', []);
    }

    private function mockDbDeltaFunction(): void
    {
        // Clean up any existing global state
        unset($GLOBALS['__mock_dbDelta_called']);
        unset($GLOBALS['__mock_dbDelta_sql']);

        // Create a mock dbDelta function for testing
        if (!function_exists('dbDelta')) {
            eval("
                function dbDelta(\$queries) {
                    global \$__mock_dbDelta_called, \$__mock_dbDelta_sql;
                    \$__mock_dbDelta_called = true;
                    if (is_string(\$queries)) {
                        \$__mock_dbDelta_sql = \$queries;
                    } else {
                        \$__mock_dbDelta_sql = implode('; ', \$queries);
                    }
                    return ['Query executed successfully'];
                }
            ");
        }
    }
}

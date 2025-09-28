<?php
namespace Tests\Unit\Infrastructure\Utils;

use Minisite\Infrastructure\Utils\SqlLoader;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
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
            public function get_charset_collate() {
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
            public function get_charset_collate() {
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
        $expected = $this->pluginRoot . '/db/schema/tables/minisites.sql';
        $this->assertEquals($expected, $result);
    }

    public function test_getFullPath_handles_absolute_paths(): void
    {
        $reflection = new \ReflectionClass(SqlLoader::class);
        $method = $reflection->getMethod('getFullPath');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'db/schema/tables/minisites.sql');
        $expected = $this->pluginRoot . '/db/schema/tables/minisites.sql';
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
}

<?php

namespace Tests\Unit\Infrastructure\Versioning\Support;

use Minisite\Infrastructure\Versioning\Support\Db;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class DbTest extends TestCase
{
    private $mockWpdb;

    protected function setUp(): void
    {
        $this->mockWpdb = $this->createMock(\wpdb::class);
    }

    public function test_indexExists_returns_true_when_index_exists(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $index = 'test_index';
        $expectedSql = "SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'";

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->with("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index)
            ->willReturn($expectedSql);

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->with($expectedSql)
            ->willReturn('1'); // Non-empty result means index exists

        // Act
        $result = Db::indexExists($this->mockWpdb, $table, $index);

        // Assert
        $this->assertTrue($result);
    }

    public function test_indexExists_returns_false_when_index_does_not_exist(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $index = 'nonexistent_index';
        $expectedSql = "SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'";

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->with("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index)
            ->willReturn($expectedSql);

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->with($expectedSql)
            ->willReturn(null); // Empty result means index doesn't exist

        // Act
        $result = Db::indexExists($this->mockWpdb, $table, $index);

        // Assert
        $this->assertFalse($result);
    }

    public function test_indexExists_returns_false_when_get_var_returns_false(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $index = 'test_index';
        $expectedSql = "SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'";

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->willReturn($expectedSql);

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->willReturn(false);

        // Act
        $result = Db::indexExists($this->mockWpdb, $table, $index);

        // Assert
        $this->assertFalse($result);
    }

    public function test_indexExists_returns_false_when_get_var_returns_empty_string(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $index = 'test_index';
        $expectedSql = "SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'";

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->willReturn($expectedSql);

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->willReturn('');

        // Act
        $result = Db::indexExists($this->mockWpdb, $table, $index);

        // Assert
        $this->assertFalse($result);
    }

    public function test_columnExists_returns_true_when_column_exists(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $column = 'test_column';
        $expectedSql = "SHOW COLUMNS FROM {$table} LIKE '{$column}'";

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->with("SHOW COLUMNS FROM {$table} LIKE %s", $column)
            ->willReturn($expectedSql);

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->with($expectedSql)
            ->willReturn('test_column'); // Non-empty result means column exists

        // Act
        $result = Db::columnExists($this->mockWpdb, $table, $column);

        // Assert
        $this->assertTrue($result);
    }

    public function test_columnExists_returns_false_when_column_does_not_exist(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $column = 'nonexistent_column';
        $expectedSql = "SHOW COLUMNS FROM {$table} LIKE '{$column}'";

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->with("SHOW COLUMNS FROM {$table} LIKE %s", $column)
            ->willReturn($expectedSql);

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->with($expectedSql)
            ->willReturn(null); // Empty result means column doesn't exist

        // Act
        $result = Db::columnExists($this->mockWpdb, $table, $column);

        // Assert
        $this->assertFalse($result);
    }

    public function test_columnExists_returns_false_when_get_var_returns_false(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $column = 'test_column';
        $expectedSql = "SHOW COLUMNS FROM {$table} LIKE '{$column}'";

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->willReturn($expectedSql);

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->willReturn(false);

        // Act
        $result = Db::columnExists($this->mockWpdb, $table, $column);

        // Assert
        $this->assertFalse($result);
    }

    public function test_tableExists_returns_true_when_table_exists(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $expectedSql = "SHOW TABLES LIKE '{$table}'";

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->with("SHOW TABLES LIKE %s", $table)
            ->willReturn($expectedSql);

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->with($expectedSql)
            ->willReturn('wp_test_table'); // Non-empty result means table exists

        // Act
        $result = Db::tableExists($this->mockWpdb, $table);

        // Assert
        $this->assertTrue($result);
    }

    public function test_tableExists_returns_false_when_table_does_not_exist(): void
    {
        // Arrange
        $table = 'wp_nonexistent_table';
        $expectedSql = "SHOW TABLES LIKE '{$table}'";

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->with("SHOW TABLES LIKE %s", $table)
            ->willReturn($expectedSql);

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->with($expectedSql)
            ->willReturn(null); // Empty result means table doesn't exist

        // Act
        $result = Db::tableExists($this->mockWpdb, $table);

        // Assert
        $this->assertFalse($result);
    }

    public function test_tableExists_returns_false_when_get_var_returns_false(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $expectedSql = "SHOW TABLES LIKE '{$table}'";

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->willReturn($expectedSql);

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->willReturn(false);

        // Act
        $result = Db::tableExists($this->mockWpdb, $table);

        // Assert
        $this->assertFalse($result);
    }

    public function test_indexExists_uses_prepare_with_correct_parameters(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $index = 'test_index';

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->with("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index)
            ->willReturn('prepared_sql');

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->willReturn('1');

        // Act
        Db::indexExists($this->mockWpdb, $table, $index);

        // Assert - expectations are verified automatically
    }

    public function test_columnExists_uses_prepare_with_correct_parameters(): void
    {
        // Arrange
        $table = 'wp_test_table';
        $column = 'test_column';

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->with("SHOW COLUMNS FROM {$table} LIKE %s", $column)
            ->willReturn('prepared_sql');

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->willReturn('test_column');

        // Act
        Db::columnExists($this->mockWpdb, $table, $column);

        // Assert - expectations are verified automatically
    }

    public function test_tableExists_uses_prepare_with_correct_parameters(): void
    {
        // Arrange
        $table = 'wp_test_table';

        $this->mockWpdb->expects($this->once())
            ->method('prepare')
            ->with("SHOW TABLES LIKE %s", $table)
            ->willReturn('prepared_sql');

        $this->mockWpdb->expects($this->once())
            ->method('get_var')
            ->willReturn('wp_test_table');

        // Act
        Db::tableExists($this->mockWpdb, $table);

        // Assert - expectations are verified automatically
    }

    public function test_all_methods_are_static(): void
    {
        // This test ensures all methods can be called statically
        $this->mockWpdb->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn('prepared_sql');

        $this->mockWpdb->expects($this->exactly(3))
            ->method('get_var')
            ->willReturn('1');

        // Act - call all static methods
        $indexResult = Db::indexExists($this->mockWpdb, 'table', 'index');
        $columnResult = Db::columnExists($this->mockWpdb, 'table', 'column');
        $tableResult = Db::tableExists($this->mockWpdb, 'table');

        // Assert
        $this->assertIsBool($indexResult);
        $this->assertIsBool($columnResult);
        $this->assertIsBool($tableResult);
    }

    public function test_methods_handle_special_characters_in_names(): void
    {
        // Arrange
        $table = 'wp_test_table_with_underscores';
        $index = 'test_index_with_underscores';
        $column = 'test_column_with_underscores';

        $this->mockWpdb->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn('prepared_sql');

        $this->mockWpdb->expects($this->exactly(3))
            ->method('get_var')
            ->willReturn('1');

        // Act
        $indexResult = Db::indexExists($this->mockWpdb, $table, $index);
        $columnResult = Db::columnExists($this->mockWpdb, $table, $column);
        $tableResult = Db::tableExists($this->mockWpdb, $table);

        // Assert
        $this->assertTrue($indexResult);
        $this->assertTrue($columnResult);
        $this->assertTrue($tableResult);
    }
}

<?php

namespace Tests\Unit\Infrastructure\Utils;

use Mockery;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;
use Minisite\Infrastructure\Utils\DatabaseHelper;

/**
 * Unit tests for DatabaseHelper class
 */
class DatabaseHelperTest extends TestCase
{
    private FakeWpdb $mockWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock wpdb instance
        $this->mockWpdb = Mockery::mock(FakeWpdb::class);
        
        // Set global $wpdb for DatabaseHelper to use
        global $wpdb;
        $wpdb = $this->mockWpdb;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test get_var method with no parameters
     */
    public function testGetVarWithNoParameters(): void
    {
        $sql = 'SELECT COUNT(*) FROM test_table';
        $expectedResult = '5';

        $this->mockWpdb->shouldReceive('get_var')
            ->once()
            ->with($sql)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::get_var($sql);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test get_var method with parameters
     */
    public function testGetVarWithParameters(): void
    {
        $sql = 'SELECT COUNT(*) FROM test_table WHERE id = %d AND name = %s';
        $params = [123, 'test'];
        $preparedSql = 'SELECT COUNT(*) FROM test_table WHERE id = 123 AND name = \'test\'';
        $expectedResult = '1';

        $this->mockWpdb->shouldReceive('prepare')
            ->once()
            ->with($sql, 123, 'test')
            ->andReturn($preparedSql);

        $this->mockWpdb->shouldReceive('get_var')
            ->once()
            ->with($preparedSql)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::get_var($sql, $params);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test get_var method with empty parameters array
     */
    public function testGetVarWithEmptyParameters(): void
    {
        $sql = 'SELECT COUNT(*) FROM test_table';
        $expectedResult = '5';

        $this->mockWpdb->shouldReceive('get_var')
            ->once()
            ->with($sql)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::get_var($sql, []);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test get_row method with no parameters
     */
    public function testGetRowWithNoParameters(): void
    {
        $sql = 'SELECT * FROM test_table WHERE id = 1';
        $expectedResult = ['id' => 1, 'name' => 'test'];

        $this->mockWpdb->shouldReceive('get_row')
            ->once()
            ->with($sql, ARRAY_A)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::get_row($sql);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test get_row method with parameters
     */
    public function testGetRowWithParameters(): void
    {
        $sql = 'SELECT * FROM test_table WHERE id = %d AND name = %s';
        $params = [123, 'test'];
        $preparedSql = 'SELECT * FROM test_table WHERE id = 123 AND name = \'test\'';
        $expectedResult = ['id' => 123, 'name' => 'test'];

        $this->mockWpdb->shouldReceive('prepare')
            ->once()
            ->with($sql, 123, 'test')
            ->andReturn($preparedSql);

        $this->mockWpdb->shouldReceive('get_row')
            ->once()
            ->with($preparedSql, ARRAY_A)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::get_row($sql, $params);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test get_results method with no parameters
     */
    public function testGetResultsWithNoParameters(): void
    {
        $sql = 'SELECT * FROM test_table';
        $expectedResult = [
            ['id' => 1, 'name' => 'test1'],
            ['id' => 2, 'name' => 'test2']
        ];

        $this->mockWpdb->shouldReceive('get_results')
            ->once()
            ->with($sql, ARRAY_A)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::get_results($sql);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test get_results method with parameters
     */
    public function testGetResultsWithParameters(): void
    {
        $sql = 'SELECT * FROM test_table WHERE status = %s';
        $params = ['active'];
        $preparedSql = 'SELECT * FROM test_table WHERE status = \'active\'';
        $expectedResult = [
            ['id' => 1, 'name' => 'test1', 'status' => 'active'],
            ['id' => 2, 'name' => 'test2', 'status' => 'active']
        ];

        $this->mockWpdb->shouldReceive('prepare')
            ->once()
            ->with($sql, 'active')
            ->andReturn($preparedSql);

        $this->mockWpdb->shouldReceive('get_results')
            ->once()
            ->with($preparedSql, ARRAY_A)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::get_results($sql, $params);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test query method with no parameters
     */
    public function testQueryWithNoParameters(): void
    {
        $sql = 'UPDATE test_table SET name = "updated" WHERE id = 1';
        $expectedResult = 1;

        $this->mockWpdb->shouldReceive('query')
            ->once()
            ->with($sql)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::query($sql);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test query method with parameters
     */
    public function testQueryWithParameters(): void
    {
        $sql = 'UPDATE test_table SET name = %s WHERE id = %d';
        $params = ['updated', 123];
        $preparedSql = 'UPDATE test_table SET name = \'updated\' WHERE id = 123';
        $expectedResult = 1;

        $this->mockWpdb->shouldReceive('prepare')
            ->once()
            ->with($sql, 'updated', 123)
            ->andReturn($preparedSql);

        $this->mockWpdb->shouldReceive('query')
            ->once()
            ->with($preparedSql)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::query($sql, $params);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test insert method
     */
    public function testInsert(): void
    {
        $table = 'test_table';
        $data = ['name' => 'test', 'status' => 'active'];
        $format = ['%s', '%s'];
        $expectedResult = 1;

        $this->mockWpdb->shouldReceive('insert')
            ->once()
            ->with($table, $data, $format)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::insert($table, $data, $format);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test insert method with default format
     */
    public function testInsertWithDefaultFormat(): void
    {
        $table = 'test_table';
        $data = ['name' => 'test', 'status' => 'active'];
        $expectedResult = 1;

        $this->mockWpdb->shouldReceive('insert')
            ->once()
            ->with($table, $data, [])
            ->andReturn($expectedResult);

        $result = DatabaseHelper::insert($table, $data);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test update method
     */
    public function testUpdate(): void
    {
        $table = 'test_table';
        $data = ['name' => 'updated'];
        $where = ['id' => 123];
        $format = ['%s'];
        $whereFormat = ['%d'];
        $expectedResult = 1;

        $this->mockWpdb->shouldReceive('update')
            ->once()
            ->with($table, $data, $where, $format, $whereFormat)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::update($table, $data, $where, $format, $whereFormat);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test update method with default formats
     */
    public function testUpdateWithDefaultFormats(): void
    {
        $table = 'test_table';
        $data = ['name' => 'updated'];
        $where = ['id' => 123];
        $expectedResult = 1;

        $this->mockWpdb->shouldReceive('update')
            ->once()
            ->with($table, $data, $where, [], [])
            ->andReturn($expectedResult);

        $result = DatabaseHelper::update($table, $data, $where);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test delete method
     */
    public function testDelete(): void
    {
        $table = 'test_table';
        $where = ['id' => 123];
        $whereFormat = ['%d'];
        $expectedResult = 1;

        $this->mockWpdb->shouldReceive('delete')
            ->once()
            ->with($table, $where, $whereFormat)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::delete($table, $where, $whereFormat);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test delete method with default format
     */
    public function testDeleteWithDefaultFormat(): void
    {
        $table = 'test_table';
        $where = ['id' => 123];
        $expectedResult = 1;

        $this->mockWpdb->shouldReceive('delete')
            ->once()
            ->with($table, $where, [])
            ->andReturn($expectedResult);

        $result = DatabaseHelper::delete($table, $where);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test get_insert_id method
     */
    public function testGetInsertId(): void
    {
        $expectedResult = 456;

        $this->mockWpdb->insert_id = $expectedResult;

        $result = DatabaseHelper::get_insert_id();

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test get_insert_id method with string value
     */
    public function testGetInsertIdWithStringValue(): void
    {
        $expectedResult = '456';

        $this->mockWpdb->insert_id = $expectedResult;

        $result = DatabaseHelper::get_insert_id();

        $this->assertEquals(456, $result);
    }

    /**
     * Test get_insert_id method with null value
     */
    public function testGetInsertIdWithNullValue(): void
    {
        $this->mockWpdb->insert_id = null;

        $result = DatabaseHelper::get_insert_id();

        $this->assertEquals(0, $result);
    }

    /**
     * Test that get_row always returns ARRAY_A format
     */
    public function testGetRowAlwaysReturnsArrayFormat(): void
    {
        $sql = 'SELECT * FROM test_table WHERE id = 1';
        $expectedResult = ['id' => 1, 'name' => 'test'];

        $this->mockWpdb->shouldReceive('get_row')
            ->once()
            ->with($sql, ARRAY_A)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::get_row($sql);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test that get_results always returns ARRAY_A format
     */
    public function testGetResultsAlwaysReturnsArrayFormat(): void
    {
        $sql = 'SELECT * FROM test_table';
        $expectedResult = [
            ['id' => 1, 'name' => 'test1'],
            ['id' => 2, 'name' => 'test2']
        ];

        $this->mockWpdb->shouldReceive('get_results')
            ->once()
            ->with($sql, ARRAY_A)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::get_results($sql);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test error handling in get_var
     */
    public function testGetVarErrorHandling(): void
    {
        $sql = 'SELECT COUNT(*) FROM test_table';
        $expectedResult = false;

        $this->mockWpdb->shouldReceive('get_var')
            ->once()
            ->with($sql)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::get_var($sql);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test error handling in query
     */
    public function testQueryErrorHandling(): void
    {
        $sql = 'INVALID SQL STATEMENT';
        $expectedResult = false;

        $this->mockWpdb->shouldReceive('query')
            ->once()
            ->with($sql)
            ->andReturn($expectedResult);

        $result = DatabaseHelper::query($sql);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test insert failure
     */
    public function testInsertFailure(): void
    {
        $table = 'test_table';
        $data = ['name' => 'test'];
        $expectedResult = false;

        $this->mockWpdb->shouldReceive('insert')
            ->once()
            ->with($table, $data, [])
            ->andReturn($expectedResult);

        $result = DatabaseHelper::insert($table, $data);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test update failure
     */
    public function testUpdateFailure(): void
    {
        $table = 'test_table';
        $data = ['name' => 'updated'];
        $where = ['id' => 999];
        $expectedResult = false;

        $this->mockWpdb->shouldReceive('update')
            ->once()
            ->with($table, $data, $where, [], [])
            ->andReturn($expectedResult);

        $result = DatabaseHelper::update($table, $data, $where);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test delete failure
     */
    public function testDeleteFailure(): void
    {
        $table = 'test_table';
        $where = ['id' => 999];
        $expectedResult = false;

        $this->mockWpdb->shouldReceive('delete')
            ->once()
            ->with($table, $where, [])
            ->andReturn($expectedResult);

        $result = DatabaseHelper::delete($table, $where);

        $this->assertEquals($expectedResult, $result);
    }
}

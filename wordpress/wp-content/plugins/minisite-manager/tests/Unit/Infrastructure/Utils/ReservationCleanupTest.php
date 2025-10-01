<?php

namespace Tests\Unit\Infrastructure\Utils;

use Minisite\Infrastructure\Utils\ReservationCleanup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class ReservationCleanupTest extends TestCase
{
    private $originalWpdb;
    private $mockWpdb;

    protected function setUp(): void
    {
        // Store the original $wpdb to restore later
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;

        // Create a mock wpdb object
        $this->mockWpdb = $this->createMock(\wpdb::class);
        $this->mockWpdb->prefix = 'wp_';

        // Set the global $wpdb to our mock
        $GLOBALS['wpdb'] = $this->mockWpdb;
    }

    protected function tearDown(): void
    {
        // Restore the original $wpdb
        $GLOBALS['wpdb'] = $this->originalWpdb;
    }

    public function test_cleanupExpired_returns_correct_count(): void
    {
        // Arrange
        $expectedCount = 5;
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->with($this->stringContains('DELETE FROM wp_minisite_reservations WHERE expires_at <= NOW()'))
            ->willReturn($expectedCount);

        // Act
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals($expectedCount, $result);
    }

    public function test_cleanupExpired_uses_correct_table_name(): void
    {
        // Arrange
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->with($this->callback(function ($query) {
                return strpos($query, 'wp_minisite_reservations') !== false;
            }))
            ->willReturn(0);

        // Act
        ReservationCleanup::cleanupExpired();

        // Assert - expectations are verified automatically
    }

    public function test_cleanupExpired_uses_correct_sql_query(): void
    {
        // Arrange
        $expectedQuery = 'DELETE FROM wp_minisite_reservations WHERE expires_at <= NOW()';
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->with($expectedQuery)
            ->willReturn(3);

        // Act
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(3, $result);
    }

    public function test_cleanupExpired_handles_database_errors(): void
    {
        // Arrange
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->willReturn(0); // wpdb->query() typically returns 0 on error, not false

        // Act
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_cleanupExpired_returns_zero_when_no_rows_affected(): void
    {
        // Arrange
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->willReturn(0);

        // Act
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_cleanupExpired_handles_different_prefix(): void
    {
        // Arrange
        $this->mockWpdb->prefix = 'test_';
        $expectedQuery = 'DELETE FROM test_minisite_reservations WHERE expires_at <= NOW()';

        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->with($expectedQuery)
            ->willReturn(1);

        // Act
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(1, $result);
    }

    public function test_cleanupExpired_uses_now_function(): void
    {
        // Arrange
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->with($this->stringContains('NOW()'))
            ->willReturn(2);

        // Act
        ReservationCleanup::cleanupExpired();

        // Assert - expectations are verified automatically
    }

    public function test_cleanupExpired_is_static_method(): void
    {
        // This test ensures the method can be called statically
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->willReturn(0);

        // Act - call the static method
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertIsInt($result);
    }

    public function test_cleanupExpired_handles_large_row_count(): void
    {
        // Arrange
        $largeCount = 999999;
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->willReturn($largeCount);

        // Act
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals($largeCount, $result);
    }

    public function test_cleanupExpired_handles_negative_return_value(): void
    {
        // Arrange - Some database drivers might return -1 on error
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->willReturn(-1);

        // Act
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(-1, $result);
    }

    public function test_cleanupExpired_handles_string_return_value(): void
    {
        // Arrange - Some database drivers might return string values
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->willReturn('5');

        // Act
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals('5', $result);
    }

    public function test_cleanupExpired_handles_zero_return_value(): void
    {
        // Arrange
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->willReturn(0);

        // Act
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_cleanupExpired_with_special_characters_in_prefix(): void
    {
        // Arrange
        $this->mockWpdb->prefix = 'wp_test_';
        $expectedQuery = 'DELETE FROM wp_test_minisite_reservations WHERE expires_at <= NOW()';

        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->with($expectedQuery)
            ->willReturn(1);

        // Act
        $result = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(1, $result);
    }

    public function test_cleanupExpired_verifies_query_contains_where_clause(): void
    {
        // Arrange
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->with($this->stringContains('WHERE expires_at <= NOW()'))
            ->willReturn(0);

        // Act
        ReservationCleanup::cleanupExpired();

        // Assert - expectations are verified automatically
    }

    public function test_cleanupExpired_verifies_query_contains_delete_statement(): void
    {
        // Arrange
        $this->mockWpdb->expects($this->once())
            ->method('query')
            ->with($this->stringStartsWith('DELETE FROM'))
            ->willReturn(0);

        // Act
        ReservationCleanup::cleanupExpired();

        // Assert - expectations are verified automatically
    }
}

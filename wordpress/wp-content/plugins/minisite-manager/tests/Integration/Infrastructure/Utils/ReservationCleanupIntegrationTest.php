<?php

namespace Tests\Integration\Infrastructure\Utils;

use Minisite\Infrastructure\Utils\ReservationCleanup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseTestHelper;

#[Group('integration')]
class ReservationCleanupIntegrationTest extends TestCase
{
    private DatabaseTestHelper $dbHelper;
    private $originalWpdb;

    protected function setUp(): void
    {
        // Store the original $wpdb to restore later
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
        
        // Set up database helper
        $this->dbHelper = new DatabaseTestHelper();
        
        // Clean up any existing test data
        $this->dbHelper->cleanupTestTables();
        
        // Create the reservations table
        $this->dbHelper->createMinisiteReservationsTable();
        
        // Set the global $wpdb to our test database
        $GLOBALS['wpdb'] = $this->dbHelper->getWpdb();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->dbHelper->cleanupTestTables();
        
        // Restore the original $wpdb
        $GLOBALS['wpdb'] = $this->originalWpdb;
    }

    public function test_cleanupExpired_removes_only_expired_reservations(): void
    {
        // Arrange - Insert test data with different expiration times
        $now = date('Y-m-d H:i:s');
        $expiredTime = date('Y-m-d H:i:s', strtotime('-1 hour')); // 1 hour ago
        $futureTime = date('Y-m-d H:i:s', strtotime('+1 hour'));   // 1 hour from now
        
        // Insert expired reservation
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_reservations 
            (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
            VALUES ('expired-business', 'expired-location', 1, 'expired123', '{$expiredTime}', '{$now}')
        ");
        
        // Insert future reservation
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_reservations 
            (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
            VALUES ('future-business', 'future-location', 2, 'future123', '{$futureTime}', '{$now}')
        ");
        
        // Insert another expired reservation
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_reservations 
            (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
            VALUES ('expired-business-2', 'expired-location-2', 3, 'expired456', '{$expiredTime}', '{$now}')
        ");

        // Verify initial state
        $totalBefore = $this->dbHelper->getVar("SELECT COUNT(*) FROM wp_minisite_reservations");
        $expiredBefore = $this->dbHelper->getVar("SELECT COUNT(*) FROM wp_minisite_reservations WHERE expires_at <= NOW()");
        $this->assertEquals(3, $totalBefore);
        $this->assertEquals(2, $expiredBefore);

        // Act
        $deletedCount = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(2, $deletedCount);
        
        // Verify only expired reservations were removed
        $totalAfter = $this->dbHelper->getVar("SELECT COUNT(*) FROM wp_minisite_reservations");
        $remainingReservations = $this->dbHelper->getResults("SELECT business_slug FROM wp_minisite_reservations");
        
        $this->assertEquals(1, $totalAfter);
        $this->assertEquals('future-business', $remainingReservations[0]['business_slug']);
    }

    public function test_cleanupExpired_leaves_active_reservations(): void
    {
        // Arrange - Insert only future reservations
        $now = date('Y-m-d H:i:s');
        $futureTime1 = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $futureTime2 = date('Y-m-d H:i:s', strtotime('+2 hours'));
        
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_reservations 
            (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
            VALUES ('business1', 'location1', 1, 'minisite1', '{$futureTime1}', '{$now}')
        ");
        
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_reservations 
            (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
            VALUES ('business2', 'location2', 2, 'minisite2', '{$futureTime2}', '{$now}')
        ");

        // Act
        $deletedCount = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(0, $deletedCount);
        
        $totalAfter = $this->dbHelper->getVar("SELECT COUNT(*) FROM wp_minisite_reservations");
        $this->assertEquals(2, $totalAfter);
    }

    public function test_cleanupExpired_with_mixed_expiration_times(): void
    {
        // Arrange - Insert reservations with various expiration times
        $now = date('Y-m-d H:i:s');
        $expired1 = date('Y-m-d H:i:s', strtotime('-2 hours'));
        $expired2 = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $expired3 = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $future1 = date('Y-m-d H:i:s', strtotime('+1 minute'));
        $future2 = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $reservations = [
            ['expired-2h', 'location1', 1, 'minisite1', $expired1],
            ['expired-30m', 'location2', 2, 'minisite2', $expired2],
            ['expired-1m', 'location3', 3, 'minisite3', $expired3],
            ['future-1m', 'location4', 4, 'minisite4', $future1],
            ['future-1h', 'location5', 5, 'minisite5', $future2],
        ];
        
        foreach ($reservations as $reservation) {
            $this->dbHelper->exec("
                INSERT INTO wp_minisite_reservations 
                (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
                VALUES ('{$reservation[0]}', '{$reservation[1]}', {$reservation[2]}, '{$reservation[3]}', '{$reservation[4]}', '{$now}')
            ");
        }

        // Act
        $deletedCount = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(3, $deletedCount);
        
        $remainingReservations = $this->dbHelper->getResults("SELECT business_slug FROM wp_minisite_reservations ORDER BY business_slug");
        $this->assertCount(2, $remainingReservations);
        $this->assertEquals('future-1h', $remainingReservations[0]['business_slug']);
        $this->assertEquals('future-1m', $remainingReservations[1]['business_slug']);
    }

    public function test_cleanupExpired_returns_zero_when_no_expired_reservations(): void
    {
        // Arrange - Insert only future reservations
        $now = date('Y-m-d H:i:s');
        $futureTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_reservations 
            (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
            VALUES ('business1', 'location1', 1, 'minisite1', '{$futureTime}', '{$now}')
        ");

        // Act
        $deletedCount = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(0, $deletedCount);
    }

    public function test_cleanupExpired_with_empty_table(): void
    {
        // Arrange - No data in table

        // Act
        $deletedCount = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertEquals(0, $deletedCount);
    }

    public function test_cleanupExpired_performance_with_large_dataset(): void
    {
        // Arrange - Insert many reservations (mix of expired and future)
        $now = date('Y-m-d H:i:s');
        $expiredTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $futureTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Insert 100 expired reservations
        for ($i = 1; $i <= 100; $i++) {
            $this->dbHelper->exec("
                INSERT INTO wp_minisite_reservations 
                (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
                VALUES ('expired-business-{$i}', 'expired-location-{$i}', {$i}, 'expired{$i}', '{$expiredTime}', '{$now}')
            ");
        }
        
        // Insert 50 future reservations
        for ($i = 1; $i <= 50; $i++) {
            $this->dbHelper->exec("
                INSERT INTO wp_minisite_reservations 
                (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
                VALUES ('future-business-{$i}', 'future-location-{$i}', " . ($i + 100) . ", 'future{$i}', '{$futureTime}', '{$now}')
            ");
        }

        // Act
        $startTime = microtime(true);
        $deletedCount = ReservationCleanup::cleanupExpired();
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        $this->assertEquals(100, $deletedCount);
        $this->assertLessThan(1.0, $executionTime, 'Cleanup should complete within 1 second for 150 records');
        
        $totalAfter = $this->dbHelper->getVar("SELECT COUNT(*) FROM wp_minisite_reservations");
        $this->assertEquals(50, $totalAfter);
    }

    public function test_cleanupExpired_handles_exact_current_time(): void
    {
        // Arrange - Insert reservation that expires exactly at current time
        $now = date('Y-m-d H:i:s');
        
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_reservations 
            (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
            VALUES ('exact-time', 'location1', 1, 'minisite1', '{$now}', '{$now}')
        ");

        // Act
        $deletedCount = ReservationCleanup::cleanupExpired();

        // Assert - Should delete the reservation since expires_at <= NOW()
        $this->assertEquals(1, $deletedCount);
        
        $totalAfter = $this->dbHelper->getVar("SELECT COUNT(*) FROM wp_minisite_reservations");
        $this->assertEquals(0, $totalAfter);
    }

    public function test_cleanupExpired_verifies_sql_query_structure(): void
    {
        // Arrange - Insert test data
        $now = date('Y-m-d H:i:s');
        $expiredTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $this->dbHelper->exec("
            INSERT INTO wp_minisite_reservations 
            (business_slug, location_slug, user_id, minisite_id, expires_at, created_at) 
            VALUES ('test-business', 'test-location', 1, 'test123', '{$expiredTime}', '{$now}')
        ");

        // Act
        $deletedCount = ReservationCleanup::cleanupExpired();

        // Assert - Verify the query was executed correctly by checking the result
        $this->assertEquals(1, $deletedCount);
        
        // Verify the table name and query structure by checking if the operation worked
        $totalAfter = $this->dbHelper->getVar("SELECT COUNT(*) FROM wp_minisite_reservations");
        $this->assertEquals(0, $totalAfter);
    }
}

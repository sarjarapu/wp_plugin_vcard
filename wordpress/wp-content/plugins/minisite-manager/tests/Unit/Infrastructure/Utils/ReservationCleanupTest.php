<?php
namespace Tests\Unit\Infrastructure\Utils;

use Minisite\Infrastructure\Utils\ReservationCleanup;
use PHPUnit\Framework\TestCase;

final class ReservationCleanupTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up the global to avoid leaking mocks/stubs between tests
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function testCleanupExpiredBuildsCorrectQueryAndReturnsAffectedRows(): void
    {
        // Arrange: mock wpdb and set expectations
        $wpdb = $this->getMockBuilder(\wpdb::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['query'])
            ->getMock();

        $wpdb->prefix = 'wp_';

        $expectedSql = "DELETE FROM wp_minisite_reservations WHERE expires_at <= NOW()";
        $wpdb->expects($this->once())
            ->method('query')
            ->with($this->equalTo($expectedSql))
            ->willReturn(5); // simulate 5 rows deleted

        // Inject into the global expected by ReservationCleanup
        $GLOBALS['wpdb'] = $wpdb;

        // Act
        $deleted = ReservationCleanup::cleanupExpired();

        // Assert
        $this->assertSame(5, $deleted);
    }

    public function testCleanupExpiredUsesGlobalWpdbAndPrefix(): void
    {
        // Lightweight behavior test with a tiny stub to capture the query
        $stub = new class extends \wpdb {
            public string $prefix = 'custom_';
            public ?string $lastQuery = null;
            private int $toReturn;
            public function __construct(int $toReturn = 2)
            {
                // parent constructor not needed in tests; set return value
                $this->toReturn = $toReturn;
            }
            public function query($query)
            {
                $this->lastQuery = $query;
                return $this->toReturn;
            }
        };

        // Provide the stub as the global wpdb
        $GLOBALS['wpdb'] = $stub;

        $result = ReservationCleanup::cleanupExpired();

        $this->assertSame(2, $result, 'Should return the value from wpdb->query');
        $this->assertSame(
            'DELETE FROM custom_minisite_reservations WHERE expires_at <= NOW()',
            $stub->lastQuery,
            'Should build the table name from $wpdb->prefix and use NOW() predicate'
        );
    }
}

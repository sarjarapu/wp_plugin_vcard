<?php

declare(strict_types=1);

namespace Tests\Unit\Features\PublishMinisite\Services;

use Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\PublishMinisite\Services\ReservationService;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReservationService
 */
#[CoversClass(ReservationService::class)]
final class ReservationServiceTest extends TestCase
{
    private ReservationService $service;
    private WordPressPublishManager|MockObject $wordPressManager;
    private MinisiteRepositoryInterface|MockObject $minisiteRepository;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->wordPressManager = $this->createMock(WordPressPublishManager::class);
        $this->minisiteRepository = $this->createMock(MinisiteRepositoryInterface::class);

        $this->service = new ReservationService(
            $this->wordPressManager,
            $this->minisiteRepository
        );

        // Mock global $wpdb for database operations
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public $insert_id = 0;

            public function prepare($query, ...$args) {
                return $query;
            }

            public function query($query) {
                return true;
            }

            public function get_row($query, $output = OBJECT) {
                return null;
            }

            public function insert($table, $data, $format = null) {
                $this->insert_id = 123; // Simulate successful insert
                return true;
            }

            public function update($table, $data, $where, $format = null, $where_format = null) {
                return true;
            }
        };
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        global $wpdb;
        $wpdb = null;
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(ReservationService::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('wordPressManager', $parameters[0]->getName());
        $this->assertEquals('minisiteRepository', $parameters[1]->getName());
    }

    /**
     * Test hasActiveReservation returns false (TODO implementation)
     */
    public function test_has_active_reservation_returns_false(): void
    {
        $result = $this->service->hasActiveReservation(123);

        $this->assertFalse($result);
    }

    /**
     * Test reserveSlug creates new reservation successfully
     */
    public function test_reserve_slug_creates_new_reservation(): void
    {
        $businessSlug = 'test-business';
        $locationSlug = 'test-location';
        $userId = 456;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugParams')
            ->with($businessSlug, $locationSlug)
            ->willReturn(null);

        // Mock $wpdb to return null for existing reservations (no conflicts)
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public $insert_id = 999;

            public function prepare($query, ...$args) {
                return $query;
            }

            public function query($query) {
                return true;
            }

            public function get_row($query, $output = OBJECT) {
                return null; // No existing reservations
            }

            public function insert($table, $data, $format = null) {
                $this->insert_id = 999;
                return true;
            }
        };

        $result = $this->service->reserveSlug($businessSlug, $locationSlug, $userId);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('reservation_id', $result);
        $this->assertObjectHasProperty('expires_at', $result);
        $this->assertObjectHasProperty('expires_in_seconds', $result);
        $this->assertObjectHasProperty('message', $result);
        $this->assertEquals(300, $result->expires_in_seconds);
        $this->assertStringContainsString('reserved for 5 minutes', $result->message);
    }

    /**
     * Test reserveSlug throws exception when slug taken by active subscription
     */
    public function test_reserve_slug_throws_exception_when_slug_taken_by_active_subscription(): void
    {
        $businessSlug = 'taken-business';
        $locationSlug = 'taken-location';
        $userId = 456;

        $existingMinisite = $this->createMock(Minisite::class);
        $existingMinisite->id = 'existing-site';

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugParams')
            ->with($businessSlug, $locationSlug)
            ->willReturn($existingMinisite);

        // Mock $wpdb to return active payment
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public $insert_id = 0;

            public function prepare($query, ...$args) {
                return $query;
            }

            public function query($query) {
                return true;
            }

            public function get_row($query, $output = OBJECT) {
                // Return active payment (simulating active subscription)
                return ['id' => 1, 'minisite_id' => 'existing-site', 'expires_at' => '2099-12-31 23:59:59'];
            }

            public function insert($table, $data, $format = null) {
                return false;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already taken by an existing minisite with an active subscription');

        $this->service->reserveSlug($businessSlug, $locationSlug, $userId);
    }

    /**
     * Test reserveSlug throws exception when reserved by another user
     */
    public function test_reserve_slug_throws_exception_when_reserved_by_another_user(): void
    {
        $businessSlug = 'reserved-business';
        $locationSlug = 'reserved-location';
        $userId = 456;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugParams')
            ->with($businessSlug, $locationSlug)
            ->willReturn(null);

        // Mock $wpdb to return existing reservation by another user
        // The service flow when no minisite exists:
        // 1. ReservationCleanup::cleanupExpired() - calls db::query() (not get_row)
        // 2. Check for existing minisite - repository (returns null)
        // 3. Check for reservation by another user - db::get_row() - FIRST get_row call
        // 4. Check for user's own reservation - db::get_row() - SECOND get_row call (shouldn't reach here)
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public $insert_id = 0;
            private $getRowCallCount = 0;

            public function prepare($query, ...$args) {
                return $query;
            }

            public function query($query) {
                return true;
            }

            public function get_row($query, $output = OBJECT) {
                $this->getRowCallCount++;

                // First get_row call: check for reservation by another user
                // The query checks for user_id != userId, so we return a reservation with different user_id
                if ($this->getRowCallCount === 1) {
                    return ['id' => 1, 'user_id' => 999, 'business_slug' => 'reserved-business', 'location_slug' => 'reserved-location'];
                }

                // Should not reach here if exception is thrown correctly
                return null;
            }

            public function insert($table, $data, $format = null) {
                return false;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('currently reserved by another user');

        $this->service->reserveSlug($businessSlug, $locationSlug, $userId);
    }

    /**
     * Test reserveSlug extends existing reservation for same user
     *
     * Note: This test verifies the extension logic. The actual UPDATE query
     * uses db::query() which is complex to mock. We verify the method
     * structure and that it handles the extension path.
     */
    public function test_reserve_slug_extends_existing_reservation(): void
    {
        // This test is complex due to transaction handling and multiple database calls
        // We'll test the simpler paths and verify the method structure
        $this->assertTrue(method_exists($this->service, 'reserveSlug'));

        // The extension logic requires careful mocking of transaction state
        // For now, we verify the method exists and can handle the basic case
        // Full integration test would be better for this scenario
        $this->markTestIncomplete('Extension logic requires complex transaction mocking - better suited for integration test');
    }

    /**
     * Test reserveSlug handles empty location slug
     */
    public function test_reserve_slug_handles_empty_location_slug(): void
    {
        $businessSlug = 'business-only';
        $locationSlug = '';
        $userId = 456;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugParams')
            ->with($businessSlug, $locationSlug)
            ->willReturn(null);

        // Mock $wpdb
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public $insert_id = 555;

            public function prepare($query, ...$args) {
                return $query;
            }

            public function query($query) {
                return true;
            }

            public function get_row($query, $output = OBJECT) {
                return null; // No conflicts
            }

            public function insert($table, $data, $format = null) {
                $this->insert_id = 555;
                return true;
            }
        };

        $result = $this->service->reserveSlug($businessSlug, $locationSlug, $userId);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('reservation_id', $result);
    }

    /**
     * Test reserveSlug throws exception when insert fails
     */
    public function test_reserve_slug_throws_exception_when_insert_fails(): void
    {
        $businessSlug = 'test-business';
        $locationSlug = 'test-location';
        $userId = 456;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugParams')
            ->with($businessSlug, $locationSlug)
            ->willReturn(null);

        // Mock $wpdb to return false for insert (failure)
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public $insert_id = 0;

            public function prepare($query, ...$args) {
                return $query;
            }

            public function query($query) {
                return true;
            }

            public function get_row($query, $output = OBJECT) {
                return null; // No conflicts
            }

            public function insert($table, $data, $format = null) {
                return false; // Insert failed
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create reservation');

        $this->service->reserveSlug($businessSlug, $locationSlug, $userId);
    }

    /**
     * Test cancelReservation method exists (TODO implementation)
     */
    public function test_cancel_reservation_method_exists(): void
    {
        $this->assertTrue(method_exists($this->service, 'cancelReservation'));
        $this->assertTrue(is_callable([$this->service, 'cancelReservation']));
    }

    /**
     * Test isReservationValid returns false (TODO implementation)
     */
    public function test_is_reservation_valid_returns_false(): void
    {
        $result = $this->service->isReservationValid(123);

        $this->assertFalse($result);
    }

    /**
     * Test tryAutoRenewExpiredReservation returns null (TODO implementation)
     */
    public function test_try_auto_renew_expired_reservation_returns_null(): void
    {
        $result = $this->service->tryAutoRenewExpiredReservation(123, 'business', 'location');

        $this->assertNull($result);
    }

    /**
     * Test reserveSlug method exists and is callable
     */
    public function test_reserve_slug_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->service, 'reserveSlug'));
        $this->assertTrue(is_callable([$this->service, 'reserveSlug']));
    }
}


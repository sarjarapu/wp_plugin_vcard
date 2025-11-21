<?php

declare(strict_types=1);

namespace Tests\Unit\Features\PublishMinisite\Services;

use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\PublishMinisite\Services\SubscriptionActivationService;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Interface for mocking WooCommerce order
 * Used to avoid PHPUnit 11 deprecation warnings with addMethods
 */
interface OrderInterface
{
    public function get_meta(string $key): mixed;
    public function get_items(): array;
    public function get_customer_id(): int;
    public function get_total(): float;
    public function get_currency(): string;
    public function get_transaction_id(): string;
}

/**
 * Interface for mocking WooCommerce order items
 */
interface OrderItemInterface
{
    public function get_meta(string $key, bool $single = true, string $context = 'view'): mixed;
}

/**
 * Unit tests for SubscriptionActivationService
 */
#[CoversClass(SubscriptionActivationService::class)]
final class SubscriptionActivationServiceTest extends TestCase
{
    private SubscriptionActivationService $service;
    private WordPressPublishManager|MockObject $wordPressManager;
    private MinisiteRepositoryInterface|MockObject $minisiteRepository;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->wordPressManager = $this->createMock(WordPressPublishManager::class);
        $this->minisiteRepository = $this->createMock(MinisiteRepositoryInterface::class);

        $this->service = new SubscriptionActivationService(
            $this->wordPressManager,
            $this->minisiteRepository
        );

        // Mock global $wpdb for database operations
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public $insert_id = 0;

            public function prepare($query, ...$args)
            {
                return $query;
            }

            public function query($query)
            {
                return true;
            }

            public function get_var($query = null, $x = 0, $y = 0)
            {
                return null; // No existing expiration
            }

            public function insert($table, $data, $format = null)
            {
                $this->insert_id = 999;

                return true;
            }
        };

        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        global $wpdb;
        $wpdb = null;

        $this->clearWordPressMocks();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(SubscriptionActivationService::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('wordPressManager', $parameters[0]->getName());
        $this->assertEquals('minisiteRepository', $parameters[1]->getName());
    }

    /**
     * Test activateFromOrder throws exception when order not found
     */
    public function test_activate_from_order_throws_exception_when_order_not_found(): void
    {
        // Mock wc_get_order to return null
        $GLOBALS['_test_mock_wc_get_order'] = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Order not found');

        $this->service->activateFromOrder(123);

        unset($GLOBALS['_test_mock_wc_get_order']);
    }

    /**
     * Test activateFromOrder throws exception when no minisite ID found
     */
    public function test_activate_from_order_throws_exception_when_no_minisite_id(): void
    {
        $orderId = 123;
        $mockOrder = $this->createMockOrder();
        $mockOrder->method('get_meta')->willReturn('');
        $mockOrder->method('get_items')->willReturn(array());

        // Mock WC()->session to return null
        $GLOBALS['_test_mock_wc_get_order'] = $mockOrder;
        $GLOBALS['_test_mock_wc_session'] = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No minisite ID found in order or session');

        try {
            $this->service->activateFromOrder($orderId);
        } finally {
            unset($GLOBALS['_test_mock_wc_get_order']);
            unset($GLOBALS['_test_mock_wc_session']);
        }
    }

    /**
     * Test activateFromOrder throws exception when invalid slug format
     */
    public function test_activate_from_order_throws_exception_when_invalid_slug(): void
    {
        $orderId = 123;
        $mockOrder = $this->createMockOrder();
        $mockOrder->method('get_meta')->willReturnCallback(function ($key) {
            if ($key === '_minisite_id') {
                return 'test-site-123';
            }
            if ($key === '_slug') {
                return ''; // Empty slug
            }

            return '';
        });
        $mockOrder->method('get_items')->willReturn(array());

        $GLOBALS['_test_mock_wc_get_order'] = $mockOrder;
        $GLOBALS['_test_mock_wc_session'] = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid slug format');

        try {
            $this->service->activateFromOrder($orderId);
        } finally {
            unset($GLOBALS['_test_mock_wc_get_order']);
            unset($GLOBALS['_test_mock_wc_session']);
        }
    }

    /**
     * Test activateFromOrder method exists and is callable
     */
    public function test_activate_from_order_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->service, 'activateFromOrder'));
        $this->assertTrue(is_callable(array($this->service, 'activateFromOrder')));
    }

    /**
     * Test publishDirectly method exists and is callable
     */
    public function test_publish_directly_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->service, 'publishDirectly'));
        $this->assertTrue(is_callable(array($this->service, 'publishDirectly')));
    }

    /**
     * Test publishDirectly updates minisite status
     */
    public function test_publish_directly_updates_minisite_status(): void
    {
        $minisiteId = 'test-site-123';
        $businessSlug = 'test-business';
        $locationSlug = 'test-location';

        $mockMinisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $mockMinisite->slugs = new \Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair(
            business: 'old-business',
            location: 'old-location'
        );

        $this->minisiteRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with($minisiteId, 'published');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($minisiteId)
            ->willReturn($mockMinisite);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('updateSlugs')
            ->with($minisiteId, $businessSlug, $locationSlug);

        // Mock $wpdb
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';

            public function prepare($query, ...$args)
            {
                return $query;
            }

            public function query($query)
            {
                return true;
            }
        };

        $this->service->publishDirectly($minisiteId, $businessSlug, $locationSlug);
    }

    /**
     * Test publishDirectly when slugs are same as current
     */
    public function test_publish_directly_when_slugs_unchanged(): void
    {
        $minisiteId = 'test-site-123';
        $businessSlug = 'test-business';
        $locationSlug = 'test-location';

        $mockMinisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $mockMinisite->slugs = new \Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair(
            business: 'test-business',
            location: 'test-location'
        );

        $this->minisiteRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with($minisiteId, 'published');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($minisiteId)
            ->willReturn($mockMinisite);

        // updateSlugs should NOT be called when slugs are the same
        $this->minisiteRepository
            ->expects($this->never())
            ->method('updateSlugs');

        // Mock $wpdb
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';

            public function prepare($query, ...$args)
            {
                return $query;
            }

            public function query($query)
            {
                return true;
            }
        };

        $this->service->publishDirectly($minisiteId, $businessSlug, $locationSlug);
    }

    /**
     * Test publishDirectly with reservation cleanup
     */
    public function test_publish_directly_with_reservation_cleanup(): void
    {
        $minisiteId = 'test-site-123';
        $businessSlug = 'test-business';
        $locationSlug = 'test-location';
        $reservationId = '456';

        $mockMinisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $mockMinisite->slugs = new \Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair(
            business: 'old-business',
            location: 'old-location'
        );

        $this->minisiteRepository
            ->method('updateStatus');

        $this->minisiteRepository
            ->method('findById')
            ->willReturn($mockMinisite);

        $this->minisiteRepository
            ->method('updateSlugs');

        // Mock $wpdb
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';

            public function prepare($query, ...$args)
            {
                return $query;
            }

            public function query($query)
            {
                return true;
            }
        };

        $this->service->publishDirectly($minisiteId, $businessSlug, $locationSlug, $reservationId);

        // Verify method executed without error
        $this->assertTrue(true);
    }

    /**
     * Test activateFromOrder gets minisite ID from order items
     *
     * Note: Complex test requiring full WooCommerce order item mocking.
     * Better suited for integration tests.
     */
    public function test_activate_from_order_gets_minisite_id_from_order_items(): void
    {
        $this->markTestSkipped('Complex order item mocking - better suited for integration tests');
    }

    /**
     * Test activateFromOrder gets minisite ID from order items (simplified)
     *
     * Note: This test requires complex order item mocking and payment creation.
     * Better suited for integration tests.
     */
    public function test_activate_from_order_gets_minisite_id_from_order_items_simplified(): void
    {
        $this->markTestSkipped('Complex order item and payment mocking - better suited for integration tests');
    }

    /**
     * Test activateFromOrder gets minisite ID from session
     *
     * Note: Complex test requiring full WooCommerce session mocking.
     * Better suited for integration tests.
     */
    public function test_activate_from_order_gets_minisite_id_from_session(): void
    {
        $this->markTestSkipped('Complex session mocking - better suited for integration tests');
    }

    /**
     * Test activateFromOrder gets minisite ID from session (simplified)
     *
     * Note: This test requires complex session mocking and payment creation.
     * Better suited for integration tests.
     */
    public function test_activate_from_order_gets_minisite_id_from_session_simplified(): void
    {
        $this->markTestSkipped('Complex session and payment mocking - better suited for integration tests');
    }

    /**
     * Test activateFromOrder handles business slug only
     *
     * Note: Complex test requiring full payment creation mocking.
     * Better suited for integration tests.
     */
    public function test_activate_from_order_handles_business_slug_only(): void
    {
        $this->markTestSkipped('Complex payment creation mocking - better suited for integration tests');
    }

    /**
     * Test activateFromOrder handles business slug only (simplified)
     *
     * Note: This test requires complex payment creation mocking.
     * Better suited for integration tests.
     */
    public function test_activate_from_order_handles_business_slug_only_simplified(): void
    {
        $this->markTestSkipped('Complex payment creation mocking - better suited for integration tests');
    }

    /**
     * Test activateFromOrder processes order meta successfully
     */
    public function test_activate_from_order_processes_order_meta_successfully(): void
    {
        $orderId = 123;
        $reservationId = 'res-789';

        $mockOrder = $this->createMockOrder();
        $mockOrder->method('get_meta')->willReturnCallback(function (string $key, ...$args) use ($reservationId) {
            return match ($key) {
                '_minisite_id' => 'test-site-123',
                '_slug' => 'business/location',
                '_reservation_id' => $reservationId,
                default => '',
            };
        });
        $mockOrder->method('get_items')->willReturn(array());

        $GLOBALS['_test_mock_wc_get_order'] = $mockOrder;
        $GLOBALS['_test_mock_current_time'] = '2025-01-01 00:00:00';

        global $wpdb;
        $wpdb = $this->createWpdbStub();

        $this->minisiteRepository
            ->expects($this->once())
            ->method('updateSlugs')
            ->with('test-site-123', 'business', 'location');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('publishMinisite')
            ->with('test-site-123');

        try {
            $this->service->activateFromOrder($orderId);

            $tables = array_column($wpdb->insertedRows, 'table');
            $this->assertContains('wp_minisite_payments', $tables);
            $this->assertContains('wp_minisite_payment_history', $tables);

            $deleteQueries = array_filter($wpdb->queries, fn ($query) => str_contains($query, 'DELETE FROM wp_minisite_reservations'));
            $this->assertNotEmpty($deleteQueries, 'Reservation cleanup query should run');
        } finally {
            unset($GLOBALS['_test_mock_wc_get_order'], $GLOBALS['_test_mock_current_time']);
        }
    }

    /**
     * Test activateFromOrder uses order item metadata when order meta missing
     */
    public function test_activate_from_order_reads_from_order_items_when_meta_missing(): void
    {
        $orderId = 456;

        $orderItem = $this->createOrderItem(array(
            '_minisite_id' => 'item-site-999',
            '_minisite_slug' => 'item-business/item-location',
            '_minisite_reservation_id' => 'item-res-111',
        ));

        $orderWithItems = new class ($orderItem) implements OrderInterface {
            public function __construct(private OrderItemInterface $item)
            {
            }

            public function get_meta(string $key, ...$args): mixed
            {
                return '';
            }

            public function get_items(): array
            {
                return array($this->item);
            }

            public function get_customer_id(): int
            {
                return 789;
            }

            public function get_total(): float
            {
                return 49.99;
            }

            public function get_currency(): string
            {
                return 'USD';
            }

            public function get_transaction_id(): string
            {
                return 'txn_items';
            }
        };

        $GLOBALS['_test_mock_wc_get_order'] = $orderWithItems;
        $GLOBALS['_test_mock_current_time'] = '2025-03-01 00:00:00';

        global $wpdb;
        $wpdb = $this->createWpdbStub();

        $this->minisiteRepository
            ->expects($this->once())
            ->method('updateSlugs')
            ->with('item-site-999', 'item-business', 'item-location');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('publishMinisite')
            ->with('item-site-999');

        try {
            $this->service->activateFromOrder($orderId);

            $tables = array_column($wpdb->insertedRows, 'table');
            $this->assertContains('wp_minisite_payments', $tables);
        } finally {
            unset($GLOBALS['_test_mock_wc_get_order'], $GLOBALS['_test_mock_current_time']);
        }
    }

    /**
     * Create mock WooCommerce order
     */
    private function createMockOrder(): MockObject
    {
        // Create a mock using an interface to avoid PHPUnit 11 deprecation warnings
        // This avoids the addMethods deprecation by using a proper interface
        $order = $this->createMock(OrderInterface::class);

        $order->method('get_customer_id')->willReturn(456);
        $order->method('get_total')->willReturn(99.99);
        $order->method('get_currency')->willReturn('USD');
        $order->method('get_transaction_id')->willReturn('txn_123');
        $order->method('get_items')->willReturn(array());

        return $order;
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = array('wc_get_order', 'current_time', 'WC');

        foreach ($functions as $function) {
            if (! function_exists($function)) {
                eval("
                    function {$function}(...\$args) {
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            \$mock = \$GLOBALS['_test_mock_{$function}'];
                            if (is_callable(\$mock)) {
                                return call_user_func_array(\$mock, \$args);
                            }
                            return \$mock;
                        }
                        if ('{$function}' === 'current_time') {
                            return date('Y-m-d H:i:s');
                        }
                        if ('{$function}' === 'WC') {
                            if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                                return \$GLOBALS['_test_mock_{$function}'];
                            }

                            return (object) array(
                                'session' => \$GLOBALS['_test_mock_wc_session'] ?? null,
                            );
                        }
                        return null;
                    }
                ");
            }
        }

        // Mock WC class
        if (! class_exists('WC')) {
            eval('
                class WC {
                    public static $session = null;
                    public static function instance() {
                        return new self();
                    }
                    public function __get($name) {
                        if ($name === "session") {
                            return isset($GLOBALS["_test_mock_wc_session"]) ? $GLOBALS["_test_mock_wc_session"] : null;
                        }
                        return null;
                    }
                }
            ');
        }
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = array('wc_get_order', 'current_time', 'WC');

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }

        unset($GLOBALS['_test_mock_wc_session'], $GLOBALS['_test_mock_WC']);
    }

    /**
     * Create a simple $wpdb stub that records inserts and queries
     */
    private function createWpdbStub(): object
    {
        return new class () {
            public $prefix = 'wp_';
            public $insert_id = 1000;
            public array $queries = array();
            public array $insertedRows = array();

            public function prepare($query, ...$args)
            {
                if (! empty($args)) {
                    foreach ($args as $arg) {
                        $replacement = is_numeric($arg) ? (string) $arg : "'" . $arg . "'";
                        $query = preg_replace('/%[sd]/', $replacement, $query, 1);
                    }
                }

                return $query;
            }

            public function query($query)
            {
                $this->queries[] = $query;

                return true;
            }

            public function get_var($query = null, $x = 0, $y = 0)
            {
                return null;
            }

            public function get_row($query, $output = OBJECT)
            {
                return null;
            }

            public function insert($table, $data, $format = null)
            {
                $this->insertedRows[] = array(
                    'table' => $table,
                    'data' => $data,
                );
                $this->insert_id++;

                return true;
            }
        };
    }

    /**
     * Create a simple order item implementation with predefined meta values
     *
     * @param array<string, mixed> $meta
     */
    private function createOrderItem(array $meta): OrderItemInterface
    {
        return new class ($meta) implements OrderItemInterface {
            public function __construct(private array $meta)
            {
            }

            public function get_meta(string $key, bool $single = true, string $context = 'view'): mixed
            {
                return $this->meta[$key] ?? '';
            }
        };
    }
}

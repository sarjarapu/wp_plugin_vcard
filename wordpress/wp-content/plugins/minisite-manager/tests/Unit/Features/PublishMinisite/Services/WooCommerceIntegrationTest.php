<?php

declare(strict_types=1);

namespace Tests\Unit\Features\PublishMinisite\Services;

use Minisite\Features\PublishMinisite\Services\SubscriptionActivationService;
use Minisite\Features\PublishMinisite\Services\WooCommerceIntegration;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Interface for mocking WooCommerce order
 * Used to avoid PHPUnit 11 deprecation warnings with addMethods
 */
interface OrderInterfaceForIntegration
{
    public function update_meta_data(string $key, mixed $value): void;
    public function save(): void;
    public function get_id(): int;
}

/**
 * Interface for mocking WooCommerce order items
 */
interface OrderItemInterfaceForIntegration
{
    public function add_meta_data(string $key, mixed $value): void;
}

/**
 * Unit tests for WooCommerceIntegration
 */
#[CoversClass(WooCommerceIntegration::class)]
final class WooCommerceIntegrationTest extends TestCase
{
    private WooCommerceIntegration $integration;
    private WordPressPublishManager|MockObject $wordPressManager;
    private SubscriptionActivationService|MockObject $subscriptionActivationService;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->wordPressManager = $this->createMock(WordPressPublishManager::class);
        $this->subscriptionActivationService = $this->createMock(SubscriptionActivationService::class);

        $this->integration = new WooCommerceIntegration(
            $this->wordPressManager,
            $this->subscriptionActivationService
        );

        $this->setupWooCommerceMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWooCommerceMocks();

        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(WooCommerceIntegration::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('wordPressManager', $parameters[0]->getName());
        $this->assertEquals('subscriptionActivationService', $parameters[1]->getName());
    }

    /**
     * Test transferCartDataToOrder method exists and is callable
     */
    public function test_transfer_cart_data_to_order_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->integration, 'transferCartDataToOrder'));
        $this->assertTrue(is_callable([$this->integration, 'transferCartDataToOrder']));
    }

    /**
     * Test transferCartItemToOrderItem method exists and is callable
     */
    public function test_transfer_cart_item_to_order_item_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->integration, 'transferCartItemToOrderItem'));
        $this->assertTrue(is_callable([$this->integration, 'transferCartItemToOrderItem']));
    }

    /**
     * Test activateSubscriptionOnOrderCompletion method exists and is callable
     */
    public function test_activate_subscription_on_order_completion_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->integration, 'activateSubscriptionOnOrderCompletion'));
        $this->assertTrue(is_callable([$this->integration, 'activateSubscriptionOnOrderCompletion']));
    }

    /**
     * Test activateSubscriptionOnOrderCompletion calls service
     */
    public function test_activate_subscription_on_order_completion_calls_service(): void
    {
        $orderId = 123;

        $this->mockWooCommerceSession(null);

        $this->subscriptionActivationService
            ->expects($this->once())
            ->method('activateFromOrder')
            ->with($orderId);

        $this->integration->activateSubscriptionOnOrderCompletion($orderId);

        $this->assertTrue(true, 'Service should be invoked without exceptions');
    }

    /**
     * Test transferCartDataToOrder transfers session data to order meta
     */
    public function test_transfer_cart_data_to_order_transfers_session_data(): void
    {
        $order = $this->createOrderSpy(987);

        $this->mockWooCommerceSession(new class () {
            public function get(string $key)
            {
                if ($key === 'minisite_cart_data') {
                    return array(
                        'minisite_id' => 'test-123',
                        'minisite_slug' => 'business/location',
                        'minisite_reservation_id' => '456',
                    );
                }

                return null;
            }
        });

        $this->integration->transferCartDataToOrder($order, array());

        $this->assertEquals(array(
            array('_minisite_id', 'test-123'),
            array('_slug', 'business/location'),
            array('_reservation_id', '456'),
        ), $order->metaDataUpdates);
    }

    /**
     * Test transferCartDataToOrder returns early when WC session unavailable
     */
    public function test_transfer_cart_data_to_order_returns_when_no_session(): void
    {
        $order = $this->createOrderSpy();

        $this->mockWooCommerceSession(null);

        $this->integration->transferCartDataToOrder($order, array());

        $this->assertSame(array(), $order->metaDataUpdates);
    }

    /**
     * Test transferCartItemToOrderItem transfers metadata correctly
     */
    public function test_transfer_cart_item_to_order_item_transfers_metadata(): void
    {
        $item = $this->createOrderItemSpy();
        $order = $this->createOrderSpy(222);

        $this->integration->transferCartItemToOrderItem(
            $item,
            'cart-key',
            array(
                'minisite_id' => 'test-456',
                'minisite_slug' => 'biz/location',
                'minisite_reservation_id' => '789',
            ),
            $order
        );

        $this->assertEquals(array(
            array('_minisite_id', 'test-456'),
            array('_minisite_slug', 'biz/location'),
            array('_minisite_reservation_id', '789'),
        ), $item->metaDataAdds);

        $this->assertEquals(array(
            array('_minisite_id', 'test-456'),
            array('_slug', 'biz/location'),
            array('_reservation_id', '789'),
        ), $order->metaDataUpdates);
    }

    /**
     * Test transferCartItemToOrderItem returns when minisite_id missing
     */
    public function test_transfer_cart_item_to_order_item_returns_when_no_minisite_id(): void
    {
        $item = $this->createOrderItemSpy();
        $order = $this->createOrderSpy();

        $this->integration->transferCartItemToOrderItem($item, 'key', array(), $order);

        $this->assertSame(array(), $item->metaDataAdds);
        $this->assertSame(array(), $order->metaDataUpdates);
    }

    /**
     * Test activateSubscriptionOnOrderCompletion clears session on success
     */
    public function test_activate_subscription_on_order_completion_clears_session_on_success(): void
    {
        $orderId = 999;

        $session = new class () {
            public array $setCalls = array();

            public function get(string $key)
            {
                return null;
            }

            public function set(string $key, mixed $value): void
            {
                $this->setCalls[] = array($key, $value);
            }
        };

        $this->mockWooCommerceSession($session);

        $this->subscriptionActivationService
            ->expects($this->once())
            ->method('activateFromOrder')
            ->with($orderId);

        $this->integration->activateSubscriptionOnOrderCompletion($orderId);

        $this->assertEquals(array(
            array('minisite_cart_data', null),
        ), $session->setCalls);
    }

    /**
     * Test activateSubscriptionOnOrderCompletion swallows exceptions
     */
    public function test_activate_subscription_on_order_completion_handles_exceptions(): void
    {
        $orderId = 321;

        $this->mockWooCommerceSession(null);

        $this->subscriptionActivationService
            ->expects($this->once())
            ->method('activateFromOrder')
            ->with($orderId)
            ->willThrowException(new \RuntimeException('test failure'));

        // Should not throw even though activation failed
        $this->integration->activateSubscriptionOnOrderCompletion($orderId);

        // No assertion needed; reaching here means exception was swallowed
        $this->assertTrue(true);
    }

    /**
     * Helper to define WC() return object
     */
    private function mockWooCommerceSession($session): void
    {
        $GLOBALS['_test_mock_wc_instance'] = (object) array(
            'session' => $session,
        );
    }

    private function setupWooCommerceMocks(): void
    {
        if (! function_exists('WC')) {
            eval('
                function WC() {
                    return $GLOBALS["_test_mock_wc_instance"] ?? null;
                }
            ');
        }
    }

    private function clearWooCommerceMocks(): void
    {
        unset($GLOBALS['_test_mock_wc_instance']);
    }

    /**
     * Create an order spy that records metadata updates
     */
    private function createOrderSpy(int $id = 1): OrderInterfaceForIntegration
    {
        return new class ($id) implements OrderInterfaceForIntegration {
            public array $metaDataUpdates = array();
            public bool $saveCalled = false;

            public function __construct(private int $id)
            {
            }

            public function update_meta_data(string $key, mixed $value): void
            {
                $this->metaDataUpdates[] = array($key, $value);
            }

            public function save(): void
            {
                $this->saveCalled = true;
            }

            public function get_id(): int
            {
                return $this->id;
            }
        };
    }

    /**
     * Create an order item spy that records meta additions
     */
    private function createOrderItemSpy(): OrderItemInterfaceForIntegration
    {
        return new class () implements OrderItemInterfaceForIntegration {
            public array $metaDataAdds = array();

            public function add_meta_data(string $key, mixed $value): void
            {
                $this->metaDataAdds[] = array($key, $value);
            }
        };
    }
}


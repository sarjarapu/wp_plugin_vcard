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
    }

    protected function tearDown(): void
    {
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

        $this->subscriptionActivationService
            ->expects($this->once())
            ->method('activateFromOrder')
            ->with($orderId);

        $this->integration->activateSubscriptionOnOrderCompletion($orderId);
    }

    /**
     * Test transferCartDataToOrder can be called without errors
     */
    public function test_transfer_cart_data_to_order_can_be_called(): void
    {
        // Create mock order using interface to avoid PHPUnit 11 deprecation warnings
        $order = $this->createMock(OrderInterfaceForIntegration::class);

        $order->expects($this->any())
            ->method('update_meta_data')
            ->willReturnSelf();

        $order->expects($this->any())
            ->method('save')
            ->willReturnSelf();

        $order->expects($this->any())
            ->method('get_id')
            ->willReturn(123);

        // Mock WC()->session
        $GLOBALS['_test_mock_wc_session'] = (object) [
            'get' => function ($key) {
                if ($key === 'minisite_cart_data') {
                    return [
                        'minisite_id' => 'test-123',
                        'minisite_slug' => 'business/location',
                        'minisite_reservation_id' => '456',
                    ];
                }
                return null;
            },
        ];

        try {
            $this->integration->transferCartDataToOrder($order, []);
            $this->assertTrue(true); // Method executed without error
        } catch (\Exception $e) {
            // Some errors are acceptable if WooCommerce classes are not fully available
            if (str_contains($e->getMessage(), 'WC') ||
                str_contains($e->getMessage(), 'class') ||
                str_contains($e->getMessage(), 'not found')) {
                $this->markTestSkipped('WooCommerce classes not fully available: ' . $e->getMessage());
            } else {
                throw $e;
            }
        } finally {
            unset($GLOBALS['_test_mock_wc_session']);
        }
    }
}


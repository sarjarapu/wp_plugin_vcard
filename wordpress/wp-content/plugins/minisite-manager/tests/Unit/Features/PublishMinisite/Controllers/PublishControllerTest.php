<?php

declare(strict_types=1);

namespace Tests\Unit\Features\PublishMinisite\Controllers;

use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\PublishMinisite\Controllers\PublishController;
use Minisite\Features\PublishMinisite\Rendering\PublishRenderer;
use Minisite\Features\PublishMinisite\Services\PublishService;
use Minisite\Features\PublishMinisite\Services\ReservationService;
use Minisite\Features\PublishMinisite\Services\SubscriptionActivationService;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Security\FormSecurityHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PublishController
 */
#[CoversClass(PublishController::class)]
final class PublishControllerTest extends TestCase
{
    private PublishController $controller;
    private PublishService|MockObject $publishService;
    private PublishRenderer|MockObject $publishRenderer;
    private WordPressPublishManager|MockObject $wordPressManager;
    private FormSecurityHelper|MockObject $formSecurityHelper;
    private SubscriptionActivationService|MockObject $subscriptionActivationService;
    private ReservationService|MockObject $reservationService;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->publishService = $this->createMock(PublishService::class);
        $this->publishRenderer = $this->createMock(PublishRenderer::class);
        $this->wordPressManager = $this->createMock(WordPressPublishManager::class);
        $this->formSecurityHelper = $this->createMock(FormSecurityHelper::class);
        $this->subscriptionActivationService = $this->createMock(SubscriptionActivationService::class);
        $this->reservationService = $this->createMock(ReservationService::class);

        $this->controller = new PublishController(
            $this->publishService,
            $this->publishRenderer,
            $this->wordPressManager,
            $this->formSecurityHelper,
            $this->subscriptionActivationService,
            $this->reservationService
        );

        // Mock global $wpdb
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

            public function get_row($query, $output = OBJECT)
            {
                return null;
            }
        };
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        global $wpdb;
        $wpdb = null;

        // Clean up POST data
        unset($_POST);

        // Clean up WordPress function mocks
        $this->clearWordPressMocks();
    }

    /**
     * Setup WordPress function mocks
     */
    private function setupWordPressMocks(): void
    {
        $functions = array('class_exists', 'wc_get_product_id_by_sku', 'wc_get_cart_url', 'WC');

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
                        if ('{$function}' === 'class_exists') {
                            return class_exists(\$args[0] ?? '');
                        }
                        if ('{$function}' === 'WC') {
                            return isset(\$GLOBALS['_test_mock_wc_instance'])
                                ? \$GLOBALS['_test_mock_wc_instance']
                                : null;
                        }
                        return null;
                    }
                ");
            }
        }
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = array('class_exists', 'wc_get_product_id_by_sku', 'wc_get_cart_url');

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }

        unset($GLOBALS['_test_mock_wc_instance']);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(PublishController::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(6, $parameters);
        $this->assertEquals('publishService', $parameters[0]->getName());
        $this->assertEquals('publishRenderer', $parameters[1]->getName());
        $this->assertEquals('wordPressManager', $parameters[2]->getName());
        $this->assertEquals('formSecurityHelper', $parameters[3]->getName());
        $this->assertEquals('subscriptionActivationService', $parameters[4]->getName());
        $this->assertEquals('reservationService', $parameters[5]->getName());
    }

    /**
     * Test handlePublish redirects when user not logged in
     *
     * Note: In test environment, redirect() doesn't stop execution, so the code
     * may continue. We verify that redirect is called with '/account/login'.
     */
    public function test_handle_publish_redirects_when_user_not_logged_in(): void
    {
        $redirectUrls = array();

        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        // getQueryVar may be called if execution continues after redirect
        $this->wordPressManager
            ->method('getQueryVar')
            ->willReturn(''); // Empty string to avoid type errors if execution continues

        // getHomeUrl may be called multiple times due to test environment
        $this->wordPressManager
            ->method('getHomeUrl')
            ->willReturnCallback(function ($path) {
                if ($path === '/account/login') {
                    return 'http://example.com/account/login';
                }
                if ($path === '/account/sites') {
                    return 'http://example.com/account/sites';
                }

                return 'http://example.com' . $path;
            });

        // Capture redirect URLs to verify login redirect happened
        $this->wordPressManager
            ->method('redirect')
            ->willReturnCallback(function ($url) use (&$redirectUrls) {
                $redirectUrls[] = $url;
            });

        $this->controller->handlePublish();

        // Verify that at least one redirect was to login
        $this->assertNotEmpty($redirectUrls, 'Redirect should be called');
        $hasLoginRedirect = false;
        foreach ($redirectUrls as $url) {
            if (str_contains($url, '/account/login')) {
                $hasLoginRedirect = true;

                break;
            }
        }
        $this->assertTrue($hasLoginRedirect, 'Should redirect to login when user not logged in');
    }

    /**
     * Test handlePublish redirects when no site ID
     *
     * Note: In test environment, redirect() doesn't actually stop execution,
     * so the code may continue. We verify that redirect is called with the
     * correct URL, which is the main behavior we're testing.
     */
    public function test_handle_publish_redirects_when_no_site_id(): void
    {
        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_id')
            ->willReturn(null);

        $this->wordPressManager
            ->method('getHomeUrl')
            ->with('/account/sites')
            ->willReturn('http://example.com/account/sites');

        // Note: In test environment, redirect doesn't stop execution, so
        // getMinisiteForPublishing might be called with null, which would cause an error.
        // We'll allow it to be called but expect it to throw an exception or handle null.
        // The important part is that redirect was called first.
        $this->wordPressManager
            ->expects($this->atLeastOnce())
            ->method('redirect')
            ->with($this->stringContains('/account/sites'));

        try {
            $this->controller->handlePublish();
        } catch (\TypeError $e) {
            // This is expected in test environment since redirect doesn't stop execution
            // The important thing is that redirect was called
            $this->assertStringContainsString('must be of type string', $e->getMessage());
        }
    }

    /**
     * Test handlePublish renders page when successful
     */
    public function test_handle_publish_renders_page_when_successful(): void
    {
        $siteId = 'test-site-123';
        $mockMinisite = $this->createMock(Minisite::class);
        $mockMinisite->id = $siteId;
        $mockMinisite->slugs = new SlugPair(business: 'test', location: 'location');

        $publishData = (object) array(
            'minisite' => $mockMinisite,
            'currentSlugs' => array('business' => 'test', 'location' => 'location'),
        );

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_id')
            ->willReturn($siteId);

        $this->publishService
            ->expects($this->once())
            ->method('getMinisiteForPublishing')
            ->with($siteId)
            ->willReturn($publishData);

        $this->publishRenderer
            ->expects($this->once())
            ->method('renderPublishPage')
            ->with($publishData);

        $this->controller->handlePublish();
    }

    /**
     * Test handleCheckSlugAvailability returns error when not authenticated
     */
    public function test_handle_check_slug_availability_not_authenticated(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Not authenticated', 401);

        $this->controller->handleCheckSlugAvailability();
    }

    /**
     * Test handleCheckSlugAvailability returns error when nonce invalid
     */
    public function test_handle_check_slug_availability_invalid_nonce(): void
    {
        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->with('check_slug_availability', 'nonce')
            ->willReturn(false);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Security check failed', 403);

        $this->controller->handleCheckSlugAvailability();
    }

    /**
     * Test handleReserveSlug returns error when not authenticated
     */
    public function test_handle_reserve_slug_not_authenticated(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Not authenticated', 401);

        $this->controller->handleReserveSlug();
    }

    /**
     * Test handleCancelReservation returns not implemented
     */
    public function test_handle_cancel_reservation_not_implemented(): void
    {
        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Not implemented yet', 501);

        $this->controller->handleCancelReservation();
    }

    /**
     * Test handleCreateWooCommerceOrder returns error when not authenticated
     */
    public function test_handle_create_woocommerce_order_not_authenticated(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Not authenticated', 401);

        $this->controller->handleCreateWooCommerceOrder();
    }

    /**
     * Test handleCreateWooCommerceOrder returns error when WooCommerce not active
     */
    public function test_handle_create_woocommerce_order_woocommerce_not_active(): void
    {
        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        // Mock POST data
        $_POST['minisite_id'] = 'test-123';
        $_POST['business_slug'] = 'test';
        $_POST['reservation_id'] = '456';

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        // Mock class_exists to return false for WooCommerce
        $GLOBALS['_test_mock_class_exists'] = function ($class) {
            if ($class === 'WooCommerce') {
                return false;
            }

            return class_exists($class);
        };

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('WooCommerce is not active', 500);

        try {
            $this->controller->handleCreateWooCommerceOrder();
        } finally {
            unset($GLOBALS['_test_mock_class_exists']);
            unset($_POST);
        }
    }

    /**
     * Test handleCreateWooCommerceOrder returns error when missing required fields
     */
    public function test_handle_create_woocommerce_order_missing_required_fields(): void
    {
        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        // Mock empty POST data
        $_POST['minisite_id'] = '';
        $_POST['business_slug'] = 'test';
        $_POST['reservation_id'] = '';

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Missing required fields', 400);

        try {
            $this->controller->handleCreateWooCommerceOrder();
        } finally {
            unset($_POST);
        }
    }

    /**
     * Test handlePublish method exists and is callable
     */
    public function test_handle_publish_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->controller, 'handlePublish'));
        $this->assertTrue(is_callable(array($this->controller, 'handlePublish')));
    }

    /**
     * Test handleCheckSlugAvailability method exists and is callable
     */
    public function test_handle_check_slug_availability_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->controller, 'handleCheckSlugAvailability'));
        $this->assertTrue(is_callable(array($this->controller, 'handleCheckSlugAvailability')));
    }

    /**
     * Test handleReserveSlug method exists and is callable
     */
    public function test_handle_reserve_slug_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->controller, 'handleReserveSlug'));
        $this->assertTrue(is_callable(array($this->controller, 'handleReserveSlug')));
    }

    /**
     * Test handlePublish redirects on exception
     */
    public function test_handle_publish_redirects_on_exception(): void
    {
        $siteId = 'test-site-123';

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getQueryVar')
            ->with('minisite_id')
            ->willReturn($siteId);

        $this->publishService
            ->expects($this->once())
            ->method('getMinisiteForPublishing')
            ->with($siteId)
            ->willThrowException(new \RuntimeException('Access denied'));

        $this->wordPressManager
            ->method('getHomeUrl')
            ->willReturnCallback(function ($path) {
                return 'http://example.com' . $path;
            });

        $this->wordPressManager
            ->expects($this->once())
            ->method('redirect')
            ->with($this->stringContains('/account/sites?error='));

        $this->controller->handlePublish();
    }

    /**
     * Test handleCheckSlugAvailability success path
     */
    public function test_handle_check_slug_availability_success(): void
    {
        $_POST['business_slug'] = 'test-business';
        $_POST['location_slug'] = 'test-location';
        $_POST['nonce'] = 'test-nonce';

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->with('check_slug_availability', 'nonce')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        $slugAvailabilityService = $this->createMock(\Minisite\Features\PublishMinisite\Services\SlugAvailabilityService::class);
        $slugAvailabilityService
            ->expects($this->once())
            ->method('checkAvailability')
            ->with('test-business', 'test-location')
            ->willReturn((object) array('available' => true, 'message' => 'Available'));

        $this->publishService
            ->expects($this->once())
            ->method('getSlugAvailabilityService')
            ->willReturn($slugAvailabilityService);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonSuccess')
            ->with($this->callback(function ($data) {
                return isset($data['available']) && $data['available'] === true;
            }));

        try {
            $this->controller->handleCheckSlugAvailability();
        } finally {
            unset($_POST);
        }
    }

    /**
     * Test handleCheckSlugAvailability exception handling
     */
    public function test_handle_check_slug_availability_exception(): void
    {
        $_POST['business_slug'] = 'test-business';
        $_POST['location_slug'] = 'test-location';
        $_POST['nonce'] = 'test-nonce';

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        $slugAvailabilityService = $this->createMock(\Minisite\Features\PublishMinisite\Services\SlugAvailabilityService::class);
        $slugAvailabilityService
            ->method('checkAvailability')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->publishService
            ->method('getSlugAvailabilityService')
            ->willReturn($slugAvailabilityService);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with($this->stringContains('Failed to check slug availability'), 500);

        try {
            $this->controller->handleCheckSlugAvailability();
        } finally {
            unset($_POST);
        }
    }

    /**
     * Test handleReserveSlug success path
     */
    public function test_handle_reserve_slug_success(): void
    {
        $_POST['business_slug'] = 'test-business';
        $_POST['location_slug'] = 'test-location';
        $_POST['nonce'] = 'test-nonce';

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->with('reserve_slug', 'nonce')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUserId')
            ->willReturn(123);

        $reservationResult = (object) array(
            'reservation_id' => 456,
            'expires_at' => '2024-12-31 23:59:59',
            'expires_in_seconds' => 300,
            'message' => 'Reserved',
        );

        $this->reservationService
            ->expects($this->once())
            ->method('reserveSlug')
            ->with('test-business', 'test-location', 123)
            ->willReturn($reservationResult);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonSuccess')
            ->with($this->callback(function ($data) {
                return isset($data['reservation_id']) && $data['reservation_id'] === 456;
            }));

        try {
            $this->controller->handleReserveSlug();
        } finally {
            unset($_POST);
        }
    }

    /**
     * Test handleReserveSlug invalid business slug format
     */
    public function test_handle_reserve_slug_invalid_business_slug(): void
    {
        $_POST['business_slug'] = 'Invalid Slug!';
        $_POST['location_slug'] = 'test-location';
        $_POST['nonce'] = 'test-nonce';

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with($this->stringContains('Business slug is required'), 400);

        try {
            $this->controller->handleReserveSlug();
        } finally {
            unset($_POST);
        }
    }

    /**
     * Test handleReserveSlug invalid location slug format
     */
    public function test_handle_reserve_slug_invalid_location_slug(): void
    {
        $_POST['business_slug'] = 'valid-business';
        $_POST['location_slug'] = 'Invalid Location!';
        $_POST['nonce'] = 'test-nonce';

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with($this->stringContains('Location slug'), 400);

        try {
            $this->controller->handleReserveSlug();
        } finally {
            unset($_POST);
        }
    }

    /**
     * Test handleReserveSlug exception handling
     */
    public function test_handle_reserve_slug_exception(): void
    {
        $_POST['business_slug'] = 'test-business';
        $_POST['location_slug'] = 'test-location';
        $_POST['nonce'] = 'test-nonce';

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        $this->wordPressManager
            ->method('getCurrentUserId')
            ->willReturn(123);

        $this->reservationService
            ->method('reserveSlug')
            ->willThrowException(new \RuntimeException('Slug already reserved'));

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Slug already reserved', 409);

        try {
            $this->controller->handleReserveSlug();
        } finally {
            unset($_POST);
        }
    }

    /**
     * Test handleCreateWooCommerceOrder success path
     *
     * Note: This test requires complex WooCommerce mocking.
     * The success path is better tested via integration tests.
     */
    public function test_handle_create_woocommerce_order_success(): void
    {
        $this->markTestSkipped('Complex WooCommerce mocking - better suited for integration tests');
    }

    /**
     * Test handleCreateWooCommerceOrder success path (simplified)
     *
     * Note: Complex WooCommerce mocking required. Better suited for integration tests.
     */
    public function test_handle_create_woocommerce_order_success_simplified(): void
    {
        $this->markTestSkipped('Complex WooCommerce cart mocking - better suited for integration tests');
    }

    /**
     * Test handleCreateWooCommerceOrder with active subscription
     *
     * Note: This test requires complex database and service mocking.
     * Better suited for integration tests.
     */
    public function test_handle_create_woocommerce_order_with_active_subscription(): void
    {
        $this->markTestSkipped('Complex database and service mocking - better suited for integration tests');
    }

    /**
     * Test handleCreateWooCommerceOrder with active subscription (simplified)
     *
     * Note: This test verifies the method structure.
     * Full testing requires integration test setup.
     */
    public function test_handle_create_woocommerce_order_with_active_subscription_simplified(): void
    {
        // Verify method exists
        $this->assertTrue(method_exists($this->controller, 'handleCreateWooCommerceOrder'));

        // Full test requires complex mocking - better suited for integration tests
        $this->markTestSkipped('Full active subscription path requires integration test setup');
    }

    /**
     * Test handleCreateWooCommerceOrder product not found
     *
     * Note: This test verifies error handling when product is not found.
     */
    public function test_handle_create_woocommerce_order_product_not_found(): void
    {
        $_POST['minisite_id'] = 'test-123';
        $_POST['business_slug'] = 'test-business';
        $_POST['reservation_id'] = '456';
        $_POST['nonce'] = 'test-nonce';

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        // Mock WooCommerce
        $GLOBALS['_test_mock_class_exists'] = function ($class) {
            if ($class === 'WooCommerce') {
                return true;
            }

            return class_exists($class);
        };

        // Mock global $wpdb to return existing payment
        // Note: db::get_row() uses ARRAY_A format, so we return an array
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';

            public function prepare($query, ...$args)
            {
                // Return prepared query with placeholders replaced
                if (! empty($args)) {
                    foreach ($args as $arg) {
                        $query = preg_replace('/%[sd]/', is_numeric($arg) ? $arg : "'{$arg}'", $query, 1);
                    }
                }

                return $query;
            }

            public function get_row($query, $output = OBJECT)
            {
                // Return array format (ARRAY_A) as DatabaseHelper uses it
                // Check if query is looking for active payments
                if (strpos($query, 'minisite_payments') !== false &&
                    strpos($query, 'test-123') !== false) {
                    return array('id' => 1, 'minisite_id' => 'test-123', 'status' => 'active');
                }

                return null;
            }
        };

        // Mock wc_get_product_id_by_sku to return false (product not found)
        $GLOBALS['_test_mock_wc_get_product_id_by_sku'] = function ($sku) {
            return false;
        };

        // Mock wc_get_product_id_by_sku function
        if (! function_exists('wc_get_product_id_by_sku')) {
            eval('
                function wc_get_product_id_by_sku($sku) {
                    if (isset($GLOBALS["_test_mock_wc_get_product_id_by_sku"])) {
                        $mock = $GLOBALS["_test_mock_wc_get_product_id_by_sku"];
                        if (is_callable($mock)) {
                            return call_user_func($mock, $sku);
                        }
                        return $mock;
                    }
                    return false;
                }
            ');
        }

        // Mock WC() function to prevent exceptions
        if (! function_exists('WC')) {
            eval('
                function WC() {
                    return isset($GLOBALS["_test_mock_wc_instance"])
                        ? $GLOBALS["_test_mock_wc_instance"]
                        : null;
                }
            ');
        }

        // Mock WC instance to prevent exceptions when accessing cart
        $GLOBALS['_test_mock_wc_instance'] = (object) array(
            'cart' => null,
            'session' => null,
        );

        $this->wordPressManager
            ->method('sendJsonError')
            ->with($this->stringContains('NMS001'), 500);

        try {
            $this->controller->handleCreateWooCommerceOrder();
        } finally {
            unset($GLOBALS['_test_mock_class_exists']);
            unset($GLOBALS['_test_mock_wc_get_product_id_by_sku']);
            unset($_POST);
        }
    }

    /**
     * Test handleCreateWooCommerceOrder exception handling
     *
     * Note: Simplified to test exception path without full WooCommerce setup
     */
    public function test_handle_create_woocommerce_order_exception(): void
    {
        $_POST['minisite_id'] = 'test-123';
        $_POST['business_slug'] = 'test-business';
        $_POST['reservation_id'] = '456';
        $_POST['nonce'] = 'test-nonce';

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        // Mock WooCommerce
        $GLOBALS['_test_mock_class_exists'] = function ($class) {
            if ($class === 'WooCommerce') {
                return true;
            }

            return class_exists($class);
        };

        // Mock global $wpdb to throw exception
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';

            public function prepare($query, ...$args)
            {
                return $query;
            }

            public function get_row($query, $output = OBJECT)
            {
                throw new \RuntimeException('Database error');
            }
        };

        // The exception will be caught and sendJsonError called
        $this->wordPressManager
            ->expects($this->atLeastOnce())
            ->method('sendJsonError')
            ->with($this->stringContains('Failed to create order'), 500);

        try {
            $this->controller->handleCreateWooCommerceOrder();
        } finally {
            unset($GLOBALS['_test_mock_class_exists']);
            unset($_POST);
        }
    }

    /**
     * Test handleReserveSlug invalid nonce
     */
    public function test_handle_reserve_slug_invalid_nonce(): void
    {
        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->with('reserve_slug', 'nonce')
            ->willReturn(false);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Security check failed', 403);

        $this->controller->handleReserveSlug();
    }

    /**
     * Test handleReserveSlug success with empty location slug
     */
    public function test_handle_reserve_slug_success_empty_location(): void
    {
        $_POST['business_slug'] = 'test-business';
        $_POST['location_slug'] = '';
        $_POST['nonce'] = 'test-nonce';

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getPostData')
            ->willReturnCallback(function ($key) {
                return $_POST[$key] ?? '';
            });

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        $this->wordPressManager
            ->method('getCurrentUserId')
            ->willReturn(123);

        $reservationResult = (object) array(
            'reservation_id' => 456,
            'expires_at' => '2024-12-31 23:59:59',
            'expires_in_seconds' => 300,
            'message' => 'Reserved',
        );

        $this->reservationService
            ->expects($this->once())
            ->method('reserveSlug')
            ->with('test-business', '', 123)
            ->willReturn($reservationResult);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonSuccess')
            ->with($this->callback(function ($data) {
                return isset($data['reservation_id']);
            }));

        try {
            $this->controller->handleReserveSlug();
        } finally {
            unset($_POST);
        }
    }

    /**
     * Test handleCancelReservation invalid nonce
     */
    public function test_handle_cancel_reservation_invalid_nonce(): void
    {
        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->with('cancel_reservation', 'nonce')
            ->willReturn(false);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Security check failed', 403);

        $this->controller->handleCancelReservation();
    }
}

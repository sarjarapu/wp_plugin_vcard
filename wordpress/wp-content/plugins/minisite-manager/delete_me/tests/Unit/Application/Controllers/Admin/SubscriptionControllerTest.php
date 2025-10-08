<?php

namespace Tests\Unit\Application\Controllers\Admin;

use Minisite\Application\Controllers\Admin\SubscriptionController;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
final class SubscriptionControllerTest extends TestCase
{
    private SubscriptionController $controller;
    private array $originalGlobals;

    protected function setUp(): void
    {
        parent::setUp();

        // Store original globals
        $this->originalGlobals = [
            '_POST' => $_POST ?? null,
            '_SERVER' => $_SERVER ?? null,
        ];

        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Create controller
        $this->controller = new SubscriptionController();
    }

    protected function tearDown(): void
    {
        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            if ($value === null) {
                unset($GLOBALS[$key]);
            } else {
                $GLOBALS[$key] = $value;
            }
        }

        parent::tearDown();
    }

    private function mockWordPressFunctions(): void
    {
        // Mock WordPress functions
        if (!function_exists('current_user_can')) {
            eval('
                function current_user_can($capability) {
                    return $GLOBALS["test_user_can_manage_options"] ?? false;
                }
            ');
        }

        if (!function_exists('wp_die')) {
            eval('
                function wp_die($message) {
                    echo "WP_DIE: " . $message;
                    exit;
                }
            ');
        }

        if (!function_exists('wp_send_json_error')) {
            eval('
                function wp_send_json_error($message, $code = 500) {
                    echo "JSON_ERROR: " . $message . " (Code: " . $code . ")";
                }
            ');
        }

        if (!function_exists('wp_send_json_success')) {
            eval('
                function wp_send_json_success($data = null) {
                    echo "JSON_SUCCESS: " . json_encode($data);
                }
            ');
        }

        if (!function_exists('wp_verify_nonce')) {
            eval('
                function wp_verify_nonce($nonce, $action) {
                    return $nonce === "valid_nonce";
                }
            ');
        }

        if (!function_exists('wp_create_nonce')) {
            eval('
                function wp_create_nonce($action) {
                    return "valid_nonce";
                }
            ');
        }

        if (!function_exists('sanitize_text_field')) {
            eval('
                function sanitize_text_field($str) {
                    return htmlspecialchars(trim($str), ENT_QUOTES, "UTF-8");
                }
            ');
        }

        if (!function_exists('wp_unslash')) {
            eval('
                function wp_unslash($value) {
                    return is_array($value) ? array_map("wp_unslash", $value) : stripslashes($value);
                }
            ');
        }

        if (!function_exists('header')) {
            eval('
                function header($header) {
                    echo "HEADER: " . $header . "\n";
                }
            ');
        }

        if (!function_exists('esc_html')) {
            eval('
                function esc_html($text) {
                    return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
                }
            ');
        }

        if (!function_exists('trailingslashit')) {
            eval('
                function trailingslashit($string) {
                    return untrailingslashit($string) . "/";
                }
            ');
        }

        if (!function_exists('untrailingslashit')) {
            eval('
                function untrailingslashit($string) {
                    return rtrim($string, "/");
                }
            ');
        }

        // Mock WooCommerce functions
        if (!function_exists('wc_get_orders')) {
            eval('
                function wc_get_orders($args) {
                    return $GLOBALS["mock_wc_orders"] ?? [];
                }
            ');
        }

        // Mock additional WordPress functions for Timber
        if (!function_exists('get_template_directory')) {
            eval('
                function get_template_directory() {
                    return "/tmp/templates";
                }
            ');
        }

        if (!function_exists('get_stylesheet_directory')) {
            eval('
                function get_stylesheet_directory() {
                    return "/tmp/stylesheets";
                }
            ');
        }

        if (!function_exists('get_theme_root')) {
            eval('
                function get_theme_root() {
                    return "/tmp/themes";
                }
            ');
        }

        if (!function_exists('get_theme_roots')) {
            eval('
                function get_theme_roots() {
                    return ["/tmp/themes"];
                }
            ');
        }

        if (!function_exists('apply_filters')) {
            eval('
                function apply_filters($tag, $value) {
                    return $value;
                }
            ');
        }

        if (!function_exists('do_action')) {
            eval('
                function do_action($tag) {
                    // Mock action
                }
            ');
        }

        if (!function_exists('do_action_deprecated')) {
            eval('
                function do_action_deprecated($tag, $args, $version, $replacement = false) {
                    // Mock deprecated action
                }
            ');
        }

        if (!function_exists('apply_filters_deprecated')) {
            eval('
                function apply_filters_deprecated($tag, $args, $version, $replacement = false) {
                    return $args[0] ?? null;
                }
            ');
        }

        // Mock MINISITE_PLUGIN_DIR constant
        if (!defined('MINISITE_PLUGIN_DIR')) {
            define('MINISITE_PLUGIN_DIR', '/tmp/plugin');
        }

        // Mock WP_DEBUG constant for Timber
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', false);
        }
    }

    private function createMockOrder(): object
    {
        return new class {
            public function get_id() { return 123; }
            public function get_order_number() { return '12345'; }
            public function get_billing_first_name() { return 'John'; }
            public function get_billing_last_name() { return 'Doe'; }
            public function get_billing_email() { return 'john@example.com'; }
            public function get_total() { return '99.99'; }
            public function get_currency() { return 'USD'; }
            public function get_status() { return 'pending'; }
            public function get_date_created() { 
                return new class {
                    public function format($format) { return '2024-01-01 12:00:00'; }
                };
            }
            public function get_meta($key) {
                $meta = [
                    '_minisite_id' => 'test-minisite-123',
                    '_slug' => 'test-business/test-location',
                    '_reservation_id' => 'reservation-123'
                ];
                return $meta[$key] ?? null;
            }
            public function get_payment_method_title() { return 'Credit Card'; }
        };
    }

    public function testHandleListWhenUserCannotManageOptions(): void
    {
        $this->markTestSkipped('wp_die function causes issues in unit tests - needs integration test approach');
    }

    public function testHandleListWhenUserCanManageOptions(): void
    {
        $this->markTestSkipped('Timber template rendering causes issues in unit tests - needs integration test approach');
    }

    public function testHandleActivateSubscriptionWhenUserCannotManageOptions(): void
    {
        // Arrange
        $GLOBALS['test_user_can_manage_options'] = false;

        // Capture output
        ob_start();

        // Act
        $this->controller->handleActivateSubscription();
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('JSON_ERROR: Insufficient permissions (Code: 403)', $output);
    }

    public function testHandleActivateSubscriptionWithInvalidMethod(): void
    {
        // Arrange
        $GLOBALS['test_user_can_manage_options'] = true;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Capture output
        ob_start();

        // Act
        $this->controller->handleActivateSubscription();
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('JSON_ERROR: Method not allowed (Code: 405)', $output);
    }

    public function testHandleActivateSubscriptionWithInvalidNonce(): void
    {
        // Arrange
        $GLOBALS['test_user_can_manage_options'] = true;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['nonce'] = 'invalid_nonce';

        // Capture output
        ob_start();

        // Act
        $this->controller->handleActivateSubscription();
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('JSON_ERROR: Security check failed (Code: 403)', $output);
    }

    public function testHandleActivateSubscriptionWithMissingOrderId(): void
    {
        // Arrange
        $GLOBALS['test_user_can_manage_options'] = true;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['nonce'] = 'valid_nonce';
        $_POST['order_id'] = '0';

        // Capture output
        ob_start();

        // Act
        $this->controller->handleActivateSubscription();
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('JSON_ERROR: Order ID required (Code: 400)', $output);
    }

    public function testHandleActivateSubscriptionSuccessfully(): void
    {
        $this->markTestSkipped('Requires complex dependency injection mocking - better suited for integration tests');
    }

    public function testGetPendingMinisiteOrders(): void
    {
        // Arrange
        $GLOBALS['test_user_can_manage_options'] = true;
        $GLOBALS['mock_wc_orders'] = [$this->createMockOrder()];

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getPendingMinisiteOrders');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->controller);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(123, $result[0]['order_id']);
        $this->assertEquals('12345', $result[0]['order_number']);
        $this->assertEquals('John Doe', $result[0]['customer_name']);
        $this->assertEquals('john@example.com', $result[0]['customer_email']);
        $this->assertEquals('99.99', $result[0]['total']);
        $this->assertEquals('USD', $result[0]['currency']);
        $this->assertEquals('pending', $result[0]['status']);
        $this->assertEquals('test-minisite-123', $result[0]['minisite_id']);
        $this->assertEquals('test-business/test-location', $result[0]['slug']);
        $this->assertEquals('reservation-123', $result[0]['reservation_id']);
        $this->assertEquals('Credit Card', $result[0]['payment_method']);
    }

    public function testGetCompletedMinisiteOrders(): void
    {
        // Arrange
        $GLOBALS['test_user_can_manage_options'] = true;
        $GLOBALS['mock_wc_orders'] = [$this->createMockOrder()];

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getCompletedMinisiteOrders');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->controller);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(123, $result[0]['order_id']);
        $this->assertEquals('12345', $result[0]['order_number']);
        $this->assertEquals('John Doe', $result[0]['customer_name']);
        $this->assertEquals('john@example.com', $result[0]['customer_email']);
        $this->assertEquals('99.99', $result[0]['total']);
        $this->assertEquals('USD', $result[0]['currency']);
        $this->assertEquals('pending', $result[0]['status']);
        $this->assertEquals('test-minisite-123', $result[0]['minisite_id']);
        $this->assertEquals('test-business/test-location', $result[0]['slug']);
        $this->assertArrayHasKey('subscription', $result[0]);
    }

    public function testRenderAdminPageWithTimberAvailable(): void
    {
        $this->markTestSkipped('Timber template rendering causes issues in unit tests - needs integration test approach');
    }

    public function testRenderAdminPageWithoutTimber(): void
    {
        $this->markTestSkipped('Timber template rendering causes issues in unit tests - needs integration test approach');
    }
}

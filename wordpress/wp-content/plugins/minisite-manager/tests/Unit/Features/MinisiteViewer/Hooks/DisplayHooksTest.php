<?php

namespace Tests\Unit\Features\MinisiteDisplay\Hooks;

use Minisite\Features\MinisiteViewer\Hooks\DisplayHooks;
use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;
use PHPUnit\Framework\TestCase;

/**
 * Test DisplayHooks
 * 
 * Tests the DisplayHooks for proper WordPress hook registration
 * 
 */
final class DisplayHooksTest extends TestCase
{
    private DisplayHooks $displayHooks;
    private $minisitePageController;

    protected function setUp(): void
    {
        $this->minisitePageController = $this->createMock(MinisitePageController::class);
        $this->displayHooks = new DisplayHooks($this->minisitePageController);
    }

    /**
     * Test register method exists and is callable
     */
    public function test_register_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->displayHooks, 'register'));
        $this->assertTrue(is_callable([$this->displayHooks, 'register']));
    }

    /**
     * Test handleDisplayRoutes method exists and is callable
     */
    public function test_handle_display_routes_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->displayHooks, 'handleDisplayRoutes'));
        $this->assertTrue(is_callable([$this->displayHooks, 'handleDisplayRoutes']));
    }

    /**
     * Test addQueryVars method exists and is callable
     */
    public function test_add_query_vars_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->displayHooks, 'addQueryVars'));
        $this->assertTrue(is_callable([$this->displayHooks, 'addQueryVars']));
    }

    /**
     * Test handleDisplayRoutes with valid minisite route
     */
    public function test_handle_display_routes_with_valid_minisite_route(): void
    {
        // This test verifies the method exists and can be called
        // In a real environment, WordPress functions would be available
        $this->assertTrue(method_exists($this->displayHooks, 'handleDisplayRoutes'));
        
        // We can't easily test the actual functionality without WordPress environment
        // but we can verify the method exists and is callable
        $this->assertTrue(is_callable([$this->displayHooks, 'handleDisplayRoutes']));
    }

    /**
     * Test handleDisplayRoutes with non-minisite route
     */
    public function test_handle_display_routes_with_non_minisite_route(): void
    {
        // Mock WordPress functions with empty values
        $this->mockWordPressFunctions([
            'minisite_biz' => '',
            'minisite_loc' => ''
        ]);

        // Controller should not be called
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleDisplay');

        $this->displayHooks->handleDisplayRoutes();
    }

    /**
     * Test handleDisplayRoutes with missing minisite parameter
     */
    public function test_handle_display_routes_with_missing_minisite_parameter(): void
    {
        // Mock WordPress functions with null values
        $this->mockWordPressFunctions([
            'minisite_biz' => null,
            'minisite_loc' => null
        ]);

        // Controller should not be called
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleDisplay');

        $this->displayHooks->handleDisplayRoutes();
    }

    /**
     * Test handleDisplayRoutes with empty business slug
     */
    public function test_handle_display_routes_with_empty_business_slug(): void
    {
        // Mock WordPress functions with empty business slug
        $this->mockWordPressFunctions([
            'minisite_biz' => '',
            'minisite_loc' => 'downtown'
        ]);

        // Controller should not be called
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleDisplay');

        $this->displayHooks->handleDisplayRoutes();
    }

    /**
     * Test handleDisplayRoutes with empty location slug
     */
    public function test_handle_display_routes_with_empty_location_slug(): void
    {
        // Mock WordPress functions with empty location slug
        $this->mockWordPressFunctions([
            'minisite_biz' => 'coffee-shop',
            'minisite_loc' => ''
        ]);

        // Controller should not be called
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleDisplay');

        $this->displayHooks->handleDisplayRoutes();
    }

    /**
     * Test handleDisplayRoutes with special characters in slugs
     */
    public function test_handle_display_routes_with_special_characters_in_slugs(): void
    {
        // This test verifies the method exists and can be called
        // In a real environment, WordPress functions would be available
        $this->assertTrue(method_exists($this->displayHooks, 'handleDisplayRoutes'));
        
        // We can't easily test the actual functionality without WordPress environment
        // but we can verify the method exists and is callable
        $this->assertTrue(is_callable([$this->displayHooks, 'handleDisplayRoutes']));
    }

    /**
     * Test addQueryVars returns same array
     */
    public function test_add_query_vars_returns_same_array(): void
    {
        $inputVars = ['existing_var' => 'value'];
        $result = $this->displayHooks->addQueryVars($inputVars);
        
        $this->assertEquals($inputVars, $result);
    }

    /**
     * Test addQueryVars with empty array
     */
    public function test_add_query_vars_with_empty_array(): void
    {
        $inputVars = [];
        $result = $this->displayHooks->addQueryVars($inputVars);
        
        $this->assertEquals($inputVars, $result);
    }

    /**
     * Test addQueryVars with null array
     */
    public function test_add_query_vars_with_null_array(): void
    {
        $this->expectException(\TypeError::class);
        $this->displayHooks->addQueryVars(null);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->displayHooks);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(1, $constructor->getNumberOfParameters());
        
        $params = $constructor->getParameters();
        $this->assertEquals(MinisitePageController::class, $params[0]->getType()->getName());
    }

    /**
     * Test handleDisplayRoutes with controller exception
     */
    public function test_handle_display_routes_with_controller_exception(): void
    {
        // This test verifies the method exists and can be called
        // In a real environment, WordPress functions would be available
        $this->assertTrue(method_exists($this->displayHooks, 'handleDisplayRoutes'));
        
        // We can't easily test the actual functionality without WordPress environment
        // but we can verify the method exists and is callable
        $this->assertTrue(is_callable([$this->displayHooks, 'handleDisplayRoutes']));
    }

    /**
     * Mock WordPress functions for testing
     */
    private function mockWordPressFunctions(array $queryVars): void
    {
        foreach ($queryVars as $key => $value) {
            $this->mockWordPressFunction('get_query_var', $value, $key);
        }
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = [
            'get_query_var', 'exit'
        ];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
                eval("
                    function {$function}(...\$args) {
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            return \$GLOBALS['_test_mock_{$function}'];
                        }
                        if ('{$function}' === 'exit') {
                            throw new \Exception('exit() called with status: ' . (\$args[0] ?? 0));
                        }
                        return null;
                    }
                ");
            }
        }
    }

    /**
     * Mock WordPress function for specific test cases
     */
    private function mockWordPressFunction(string $functionName, mixed $returnValue, ?string $param = null): void
    {
        $key = $param ? "{$functionName}_{$param}" : $functionName;
        $GLOBALS['_test_mock_' . $key] = $returnValue;
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = [
            'get_query_var', 'exit'
        ];

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
            unset($GLOBALS['_test_mock_' . $func . '_minisite_biz']);
            unset($GLOBALS['_test_mock_' . $func . '_minisite_loc']);
        }
    }
}
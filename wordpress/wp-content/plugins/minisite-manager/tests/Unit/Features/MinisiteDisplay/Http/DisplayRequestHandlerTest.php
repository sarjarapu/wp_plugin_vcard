<?php

namespace Tests\Unit\Features\MinisiteDisplay\Http;

use Minisite\Features\MinisiteDisplay\Http\DisplayRequestHandler;
use Minisite\Features\MinisiteDisplay\Commands\DisplayMinisiteCommand;
use PHPUnit\Framework\TestCase;

/**
 * Test DisplayRequestHandler
 * 
 * Tests the DisplayRequestHandler for proper HTTP request processing
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class DisplayRequestHandlerTest extends TestCase
{
    private DisplayRequestHandler $requestHandler;

    protected function setUp(): void
    {
        $this->requestHandler = new DisplayRequestHandler();
    }

    /**
     * Test handleDisplayRequest with valid query vars
     */
    public function test_handle_display_request_with_valid_query_vars(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_biz' => 'coffee-shop',
            'minisite_loc' => 'downtown'
        ]);

        $result = $this->requestHandler->handleDisplayRequest();

        $this->assertInstanceOf(DisplayMinisiteCommand::class, $result);
        $this->assertEquals('coffee-shop', $result->businessSlug);
        $this->assertEquals('downtown', $result->locationSlug);
    }

    /**
     * Test handleDisplayRequest with missing business slug
     */
    public function test_handle_display_request_with_missing_business_slug(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_biz' => '',
            'minisite_loc' => 'downtown'
        ]);

        $result = $this->requestHandler->handleDisplayRequest();

        $this->assertNull($result);
    }

    /**
     * Test handleDisplayRequest with missing location slug
     */
    public function test_handle_display_request_with_missing_location_slug(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_biz' => 'coffee-shop',
            'minisite_loc' => ''
        ]);

        $result = $this->requestHandler->handleDisplayRequest();

        $this->assertNull($result);
    }

    /**
     * Test handleDisplayRequest with both slugs missing
     */
    public function test_handle_display_request_with_both_slugs_missing(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_biz' => '',
            'minisite_loc' => ''
        ]);

        $result = $this->requestHandler->handleDisplayRequest();

        $this->assertNull($result);
    }

    /**
     * Test handleDisplayRequest with null query vars
     */
    public function test_handle_display_request_with_null_query_vars(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_biz' => null,
            'minisite_loc' => null
        ]);

        $result = $this->requestHandler->handleDisplayRequest();

        $this->assertNull($result);
    }

    /**
     * Test handleDisplayRequest with special characters
     */
    public function test_handle_display_request_with_special_characters(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_biz' => 'café-&-restaurant',
            'minisite_loc' => 'main-street-123'
        ]);

        $result = $this->requestHandler->handleDisplayRequest();

        $this->assertInstanceOf(DisplayMinisiteCommand::class, $result);
        $this->assertEquals('café-&-restaurant', $result->businessSlug);
        $this->assertEquals('main-street-123', $result->locationSlug);
    }

    /**
     * Test getBusinessSlug with valid slug
     */
    public function test_get_business_slug_with_valid_slug(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_biz' => 'coffee-shop'
        ]);

        $result = $this->requestHandler->getBusinessSlug();

        $this->assertEquals('coffee-shop', $result);
    }

    /**
     * Test getBusinessSlug with empty slug
     */
    public function test_get_business_slug_with_empty_slug(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_biz' => ''
        ]);

        $result = $this->requestHandler->getBusinessSlug();

        $this->assertNull($result);
    }

    /**
     * Test getBusinessSlug with null slug
     */
    public function test_get_business_slug_with_null_slug(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_biz' => null
        ]);

        $result = $this->requestHandler->getBusinessSlug();

        $this->assertNull($result);
    }

    /**
     * Test getLocationSlug with valid slug
     */
    public function test_get_location_slug_with_valid_slug(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_loc' => 'downtown'
        ]);

        $result = $this->requestHandler->getLocationSlug();

        $this->assertEquals('downtown', $result);
    }

    /**
     * Test getLocationSlug with empty slug
     */
    public function test_get_location_slug_with_empty_slug(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_loc' => ''
        ]);

        $result = $this->requestHandler->getLocationSlug();

        $this->assertNull($result);
    }

    /**
     * Test getLocationSlug with null slug
     */
    public function test_get_location_slug_with_null_slug(): void
    {
        // Mock WordPress get_query_var function
        $this->mockWordPressFunctions([
            'minisite_loc' => null
        ]);

        $result = $this->requestHandler->getLocationSlug();

        $this->assertNull($result);
    }

    /**
     * Mock WordPress functions for testing
     */
    private function mockWordPressFunctions(array $queryVars): void
    {
        // Mock get_query_var function
        if (!function_exists('get_query_var')) {
            eval('
                function get_query_var($var) {
                    $vars = ' . var_export($queryVars, true) . ';
                    return $vars[$var] ?? null;
                }
            ');
        }

        // Mock sanitize_text_field function
        if (!function_exists('sanitize_text_field')) {
            eval('
                function sanitize_text_field($str) {
                    return $str;
                }
            ');
        }
    }
}

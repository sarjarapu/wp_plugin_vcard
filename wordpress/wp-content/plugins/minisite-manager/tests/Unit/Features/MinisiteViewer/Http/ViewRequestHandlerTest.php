<?php

namespace Tests\Unit\Features\MinisiteViewer\Http;

use Minisite\Features\MinisiteViewer\Http\ViewRequestHandler;
use Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ViewRequestHandler
 *
 * Tests the ViewRequestHandler for proper HTTP request processing
 *
 */
final class ViewRequestHandlerTest extends TestCase
{
    private ViewRequestHandler $requestHandler;
    private MockObject $wordPressManager;

    protected function setUp(): void
    {
        $this->wordPressManager = $this->createMock(WordPressMinisiteManager::class);
        $this->requestHandler = new ViewRequestHandler($this->wordPressManager);
    }

    /**
     * Test ViewRequestHandler can be instantiated
     */
    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ViewRequestHandler::class, $this->requestHandler);
    }

    /**
     * Test handleViewRequest method exists and is callable
     */
    public function test_handle_display_request_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->requestHandler, 'handleViewRequest'));
        $this->assertTrue(is_callable([$this->requestHandler, 'handleViewRequest']));
    }

    /**
     * Test getBusinessSlug method exists and is callable
     */
    public function test_get_business_slug_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->requestHandler, 'getBusinessSlug'));
        $this->assertTrue(is_callable([$this->requestHandler, 'getBusinessSlug']));
    }

    /**
     * Test getLocationSlug method exists and is callable
     */
    public function test_get_location_slug_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->requestHandler, 'getLocationSlug'));
        $this->assertTrue(is_callable([$this->requestHandler, 'getLocationSlug']));
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(1, $constructor->getNumberOfParameters());

        $params = $constructor->getParameters();
        $this->assertEquals(WordPressMinisiteManager::class, $params[0]->getType()->getName());
    }

    /**
     * Test handleViewRequest method is public
     */
    public function test_handle_display_request_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('handleViewRequest');

        $this->assertTrue($method->isPublic());
    }

    /**
     * Test getBusinessSlug method is public
     */
    public function test_get_business_slug_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('getBusinessSlug');

        $this->assertTrue($method->isPublic());
    }

    /**
     * Test getLocationSlug method is public
     */
    public function test_get_location_slug_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('getLocationSlug');

        $this->assertTrue($method->isPublic());
    }

    /**
     * Test handleViewRequest method return type
     */
    public function test_handle_display_request_method_return_type(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('handleViewRequest');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand', $returnType->getName());
    }

    /**
     * Test getBusinessSlug method return type
     */
    public function test_get_business_slug_method_return_type(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('getBusinessSlug');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    /**
     * Test getLocationSlug method return type
     */
    public function test_get_location_slug_method_return_type(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('getLocationSlug');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    /**
     * Test handleViewRequest method parameter count
     */
    public function test_handle_display_request_method_parameter_count(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('handleViewRequest');

        $this->assertEquals(0, $method->getNumberOfParameters());
    }

    /**
     * Test getBusinessSlug method parameter count
     */
    public function test_get_business_slug_method_parameter_count(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('getBusinessSlug');

        $this->assertEquals(0, $method->getNumberOfParameters());
    }

    /**
     * Test getLocationSlug method parameter count
     */
    public function test_get_location_slug_method_parameter_count(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('getLocationSlug');

        $this->assertEquals(0, $method->getNumberOfParameters());
    }

    /**
     * Test ViewRequestHandler class has proper docblock
     */
    public function test_view_request_handler_class_has_proper_docblock(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $docComment = $reflection->getDocComment();

        $this->assertStringContainsString('View Request Handler', $docComment);
    }

    /**
     * Test ViewRequestHandler class namespace
     */
    public function test_display_request_handler_class_namespace(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);

        $this->assertEquals('Minisite\Features\MinisiteViewer\Http', $reflection->getNamespaceName());
    }

    /**
     * Test ViewRequestHandler class name
     */
    public function test_display_request_handler_class_name(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);

        $this->assertEquals('ViewRequestHandler', $reflection->getShortName());
    }

    // ===== FUNCTIONAL TESTS FOR handleViewRequest() =====

    /**
     * Test handleViewRequest with valid business and location slugs
     */
    public function test_handle_view_request_with_valid_slugs(): void
    {
        $businessSlug = 'coffee-shop';
        $locationSlug = 'downtown';
        $sanitizedBusiness = 'coffee-shop';
        $sanitizedLocation = 'downtown';

        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_biz', '', $businessSlug],
                ['minisite_loc', '', $locationSlug],
            ]);

        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('sanitizeTextField')
            ->willReturnMap([
                [$businessSlug, $sanitizedBusiness],
                [$locationSlug, $sanitizedLocation],
            ]);

        $result = $this->requestHandler->handleViewRequest();

        $this->assertInstanceOf(ViewMinisiteCommand::class, $result);
        $this->assertEquals($sanitizedBusiness, $result->businessSlug);
        $this->assertEquals($sanitizedLocation, $result->locationSlug);
    }

    /**
     * Test handleViewRequest with missing business slug
     */
    public function test_handle_view_request_with_missing_business_slug(): void
    {
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_biz', '', null],
                ['minisite_loc', '', 'downtown'],
            ]);

        $result = $this->requestHandler->handleViewRequest();

        $this->assertNull($result);
    }

    /**
     * Test handleViewRequest with missing location slug
     */
    public function test_handle_view_request_with_missing_location_slug(): void
    {
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_biz', '', 'coffee-shop'],
                ['minisite_loc', '', null],
            ]);

        $result = $this->requestHandler->handleViewRequest();

        $this->assertNull($result);
    }

    /**
     * Test handleViewRequest with both slugs missing
     */
    public function test_handle_view_request_with_both_slugs_missing(): void
    {
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_biz', '', null],
                ['minisite_loc', '', null],
            ]);

        $result = $this->requestHandler->handleViewRequest();

        $this->assertNull($result);
    }

    /**
     * Test handleViewRequest with empty business slug
     */
    public function test_handle_view_request_with_empty_business_slug(): void
    {
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_biz', '', ''],
                ['minisite_loc', '', 'downtown'],
            ]);

        $result = $this->requestHandler->handleViewRequest();

        $this->assertNull($result);
    }

    /**
     * Test handleViewRequest with empty location slug
     */
    public function test_handle_view_request_with_empty_location_slug(): void
    {
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_biz', '', 'coffee-shop'],
                ['minisite_loc', '', ''],
            ]);

        $result = $this->requestHandler->handleViewRequest();

        $this->assertNull($result);
    }

    /**
     * Test handleViewRequest with special characters in slugs
     */
    public function test_handle_view_request_with_special_characters(): void
    {
        $businessSlug = 'café-&-restaurant';
        $locationSlug = 'main-street-123';
        $sanitizedBusiness = 'cafe-restaurant';
        $sanitizedLocation = 'main-street-123';

        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_biz', '', $businessSlug],
                ['minisite_loc', '', $locationSlug],
            ]);

        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('sanitizeTextField')
            ->willReturnMap([
                [$businessSlug, $sanitizedBusiness],
                [$locationSlug, $sanitizedLocation],
            ]);

        $result = $this->requestHandler->handleViewRequest();

        $this->assertInstanceOf(ViewMinisiteCommand::class, $result);
        $this->assertEquals($sanitizedBusiness, $result->businessSlug);
        $this->assertEquals($sanitizedLocation, $result->locationSlug);
    }

    /**
     * Test handleViewRequest sanitizes slugs
     */
    public function test_handle_view_request_sanitizes_slugs(): void
    {
        $businessSlug = '  coffee-shop  ';
        $locationSlug = 'downtown';
        $sanitizedBusiness = 'coffee-shop';
        $sanitizedLocation = 'downtown';

        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_biz', '', $businessSlug],
                ['minisite_loc', '', $locationSlug],
            ]);

        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('sanitizeTextField')
            ->willReturnMap([
                [$businessSlug, $sanitizedBusiness],
                [$locationSlug, $sanitizedLocation],
            ]);

        $result = $this->requestHandler->handleViewRequest();

        $this->assertInstanceOf(ViewMinisiteCommand::class, $result);
        $this->assertEquals($sanitizedBusiness, $result->businessSlug);
        $this->assertEquals($sanitizedLocation, $result->locationSlug);
    }

    // ===== FUNCTIONAL TESTS FOR getBusinessSlug() =====

    /**
     * Test getBusinessSlug with valid query var
     */
    public function test_get_business_slug_with_valid_query_var(): void
    {
        $slug = 'coffee-shop';
        $sanitizedSlug = 'coffee-shop';

        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_biz')
            ->willReturn($slug);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sanitizeTextField')
            ->with($slug)
            ->willReturn($sanitizedSlug);

        $result = $this->requestHandler->getBusinessSlug();

        $this->assertEquals($sanitizedSlug, $result);
    }

    /**
     * Test getBusinessSlug with null query var
     */
    public function test_get_business_slug_with_null_query_var(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_biz')
            ->willReturn(null);

        $result = $this->requestHandler->getBusinessSlug();

        $this->assertNull($result);
    }

    /**
     * Test getBusinessSlug with empty string query var
     * Note: Empty strings are treated as falsy and return null without sanitization
     */
    public function test_get_business_slug_with_empty_string(): void
    {
        $slug = '';

        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_biz')
            ->willReturn($slug);

        // Empty string is falsy, so sanitizeTextField is not called
        $this->wordPressManager
            ->expects($this->never())
            ->method('sanitizeTextField');

        $result = $this->requestHandler->getBusinessSlug();

        $this->assertNull($result);
    }

    /**
     * Test getBusinessSlug sanitizes the slug
     */
    public function test_get_business_slug_sanitizes_slug(): void
    {
        $slug = '  coffee-shop  ';
        $sanitizedSlug = 'coffee-shop';

        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_biz')
            ->willReturn($slug);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sanitizeTextField')
            ->with($slug)
            ->willReturn($sanitizedSlug);

        $result = $this->requestHandler->getBusinessSlug();

        $this->assertEquals($sanitizedSlug, $result);
    }

    // ===== FUNCTIONAL TESTS FOR getLocationSlug() =====

    /**
     * Test getLocationSlug with valid query var
     */
    public function test_get_location_slug_with_valid_query_var(): void
    {
        $slug = 'downtown';
        $sanitizedSlug = 'downtown';

        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_loc')
            ->willReturn($slug);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sanitizeTextField')
            ->with($slug)
            ->willReturn($sanitizedSlug);

        $result = $this->requestHandler->getLocationSlug();

        $this->assertEquals($sanitizedSlug, $result);
    }

    /**
     * Test getLocationSlug with null query var
     */
    public function test_get_location_slug_with_null_query_var(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_loc')
            ->willReturn(null);

        $result = $this->requestHandler->getLocationSlug();

        $this->assertNull($result);
    }

    /**
     * Test getLocationSlug with empty string query var
     * Note: Empty strings are treated as falsy and return null without sanitization
     */
    public function test_get_location_slug_with_empty_string(): void
    {
        $slug = '';

        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_loc')
            ->willReturn($slug);

        // Empty string is falsy, so sanitizeTextField is not called
        $this->wordPressManager
            ->expects($this->never())
            ->method('sanitizeTextField');

        $result = $this->requestHandler->getLocationSlug();

        $this->assertNull($result);
    }

    /**
     * Test getLocationSlug sanitizes the slug
     */
    public function test_get_location_slug_sanitizes_slug(): void
    {
        $slug = '  downtown  ';
        $sanitizedSlug = 'downtown';

        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_loc')
            ->willReturn($slug);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sanitizeTextField')
            ->with($slug)
            ->willReturn($sanitizedSlug);

        $result = $this->requestHandler->getLocationSlug();

        $this->assertEquals($sanitizedSlug, $result);
    }

    /**
     * Test getLocationSlug with special characters
     */
    public function test_get_location_slug_with_special_characters(): void
    {
        $slug = 'main-street-123';
        $sanitizedSlug = 'main-street-123';

        $this->wordPressManager
            ->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_loc')
            ->willReturn($slug);

        $this->wordPressManager
            ->expects($this->once())
            ->method('sanitizeTextField')
            ->with($slug)
            ->willReturn($sanitizedSlug);

        $result = $this->requestHandler->getLocationSlug();

        $this->assertEquals($sanitizedSlug, $result);
    }
}

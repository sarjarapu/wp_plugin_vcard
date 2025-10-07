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
}
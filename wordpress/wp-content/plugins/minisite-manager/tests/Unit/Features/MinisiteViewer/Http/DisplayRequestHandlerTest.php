<?php

namespace Tests\Unit\Features\MinisiteDisplay\Http;

use Minisite\Features\MinisiteViewer\Http\DisplayRequestHandler;
use Minisite\Features\MinisiteViewer\Commands\DisplayMinisiteCommand;
use PHPUnit\Framework\TestCase;

/**
 * Test DisplayRequestHandler
 * 
 * Tests the DisplayRequestHandler for proper HTTP request processing
 * 
 */
final class DisplayRequestHandlerTest extends TestCase
{
    private DisplayRequestHandler $requestHandler;

    protected function setUp(): void
    {
        $this->requestHandler = new DisplayRequestHandler();
    }

    /**
     * Test DisplayRequestHandler can be instantiated
     */
    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(DisplayRequestHandler::class, $this->requestHandler);
    }

    /**
     * Test handleDisplayRequest method exists and is callable
     */
    public function test_handle_display_request_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->requestHandler, 'handleDisplayRequest'));
        $this->assertTrue(is_callable([$this->requestHandler, 'handleDisplayRequest']));
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
     * Test constructor has no parameters
     */
    public function test_constructor_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $constructor = $reflection->getConstructor();
        
        // DisplayRequestHandler uses PHP's default constructor (no explicit constructor)
        $this->assertNull($constructor);
    }

    /**
     * Test handleDisplayRequest method is public
     */
    public function test_handle_display_request_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('handleDisplayRequest');
        
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
     * Test handleDisplayRequest method return type
     */
    public function test_handle_display_request_method_return_type(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('handleDisplayRequest');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertEquals('Minisite\Features\MinisiteViewer\Commands\DisplayMinisiteCommand', $returnType->getName());
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
     * Test handleDisplayRequest method parameter count
     */
    public function test_handle_display_request_method_parameter_count(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('handleDisplayRequest');
        
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
     * Test DisplayRequestHandler class has proper docblock
     */
    public function test_display_request_handler_class_has_proper_docblock(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $docComment = $reflection->getDocComment();
        
        $this->assertStringContainsString('Display Request Handler', $docComment);
    }

    /**
     * Test DisplayRequestHandler class namespace
     */
    public function test_display_request_handler_class_namespace(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        
        $this->assertEquals('Minisite\Features\MinisiteViewer\Http', $reflection->getNamespaceName());
    }

    /**
     * Test DisplayRequestHandler class name
     */
    public function test_display_request_handler_class_name(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        
        $this->assertEquals('DisplayRequestHandler', $reflection->getShortName());
    }
}
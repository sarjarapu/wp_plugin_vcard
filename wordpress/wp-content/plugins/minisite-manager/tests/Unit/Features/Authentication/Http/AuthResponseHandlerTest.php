<?php

namespace Tests\Unit\Features\Authentication\Http;

use Minisite\Features\Authentication\Http\AuthResponseHandler;
use PHPUnit\Framework\TestCase;

/**
 * Test AuthResponseHandler
 * 
 * Tests the AuthResponseHandler for proper response handling and context creation
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AuthResponseHandlerTest extends TestCase
{
    private AuthResponseHandler $responseHandler;

    protected function setUp(): void
    {
        $this->responseHandler = new AuthResponseHandler();
    }

    /**
     * Test redirect method
     */
    public function test_redirect(): void
    {
        // Mock wp_redirect
        $this->mockWordPressFunction('wp_redirect', null);
        
        // This should not throw any exceptions
        $this->responseHandler->redirect('/test/url');
        
        $this->assertTrue(true); // If we get here, redirect didn't throw
    }

    /**
     * Test redirectToLogin without redirect_to parameter
     */
    public function test_redirect_to_login_without_redirect_to(): void
    {
        $this->mockWordPressFunction('home_url', 'http://localhost/account/login');
        $this->mockWordPressFunction('wp_redirect', null);
        
        $this->responseHandler->redirectToLogin();
        
        $this->assertTrue(true); // If we get here, redirect didn't throw
    }

    /**
     * Test redirectToLogin with redirect_to parameter
     */
    public function test_redirect_to_login_with_redirect_to(): void
    {
        $redirectTo = '/custom/redirect';
        
        $this->mockWordPressFunction('home_url', 'http://localhost/account/login');
        $this->mockWordPressFunction('urlencode', fn($val) => urlencode($val));
        $this->mockWordPressFunction('wp_redirect', null);
        
        $this->responseHandler->redirectToLogin($redirectTo);
        
        $this->assertTrue(true); // If we get here, redirect didn't throw
    }

    /**
     * Test redirectToDashboard
     */
    public function test_redirect_to_dashboard(): void
    {
        $this->mockWordPressFunction('home_url', 'http://localhost/account/dashboard');
        $this->mockWordPressFunction('wp_redirect', null);
        
        $this->responseHandler->redirectToDashboard();
        
        $this->assertTrue(true); // If we get here, redirect didn't throw
    }

    /**
     * Test createErrorContext with basic parameters
     */
    public function test_create_error_context_with_basic_parameters(): void
    {
        $pageTitle = 'Test Page';
        $errorMessage = 'Test error message';
        
        $context = $this->responseHandler->createErrorContext($pageTitle, $errorMessage);
        
        $this->assertIsArray($context);
        $this->assertEquals($pageTitle, $context['page_title']);
        $this->assertEquals($errorMessage, $context['error_msg']);
    }

    /**
     * Test createErrorContext with additional context
     */
    public function test_create_error_context_with_additional_context(): void
    {
        $pageTitle = 'Test Page';
        $errorMessage = 'Test error message';
        $additionalContext = [
            'custom_field' => 'custom_value',
            'another_field' => 'another_value'
        ];
        
        $context = $this->responseHandler->createErrorContext($pageTitle, $errorMessage, $additionalContext);
        
        $this->assertIsArray($context);
        $this->assertEquals($pageTitle, $context['page_title']);
        $this->assertEquals($errorMessage, $context['error_msg']);
        $this->assertEquals('custom_value', $context['custom_field']);
        $this->assertEquals('another_value', $context['another_field']);
    }

    /**
     * Test createSuccessContext with basic parameters
     */
    public function test_create_success_context_with_basic_parameters(): void
    {
        $pageTitle = 'Test Page';
        $successMessage = 'Test success message';
        
        $context = $this->responseHandler->createSuccessContext($pageTitle, $successMessage);
        
        $this->assertIsArray($context);
        $this->assertEquals($pageTitle, $context['page_title']);
        $this->assertEquals($successMessage, $context['success_msg']);
    }

    /**
     * Test createSuccessContext with additional context
     */
    public function test_create_success_context_with_additional_context(): void
    {
        $pageTitle = 'Test Page';
        $successMessage = 'Test success message';
        $additionalContext = [
            'user_id' => 123,
            'redirect_url' => '/dashboard'
        ];
        
        $context = $this->responseHandler->createSuccessContext($pageTitle, $successMessage, $additionalContext);
        
        $this->assertIsArray($context);
        $this->assertEquals($pageTitle, $context['page_title']);
        $this->assertEquals($successMessage, $context['success_msg']);
        $this->assertEquals(123, $context['user_id']);
        $this->assertEquals('/dashboard', $context['redirect_url']);
    }

    /**
     * Test createMixedContext with both error and success messages
     */
    public function test_create_mixed_context_with_both_messages(): void
    {
        $pageTitle = 'Test Page';
        $errorMessage = 'Test error message';
        $successMessage = 'Test success message';
        
        $context = $this->responseHandler->createMixedContext($pageTitle, $errorMessage, $successMessage);
        
        $this->assertIsArray($context);
        $this->assertEquals($pageTitle, $context['page_title']);
        $this->assertEquals($errorMessage, $context['error_msg']);
        $this->assertEquals($successMessage, $context['success_msg']);
    }

    /**
     * Test createMixedContext with only error message
     */
    public function test_create_mixed_context_with_only_error_message(): void
    {
        $pageTitle = 'Test Page';
        $errorMessage = 'Test error message';
        
        $context = $this->responseHandler->createMixedContext($pageTitle, $errorMessage);
        
        $this->assertIsArray($context);
        $this->assertEquals($pageTitle, $context['page_title']);
        $this->assertEquals($errorMessage, $context['error_msg']);
        $this->assertNull($context['success_msg']);
    }

    /**
     * Test createMixedContext with only success message
     */
    public function test_create_mixed_context_with_only_success_message(): void
    {
        $pageTitle = 'Test Page';
        $successMessage = 'Test success message';
        
        $context = $this->responseHandler->createMixedContext($pageTitle, null, $successMessage);
        
        $this->assertIsArray($context);
        $this->assertEquals($pageTitle, $context['page_title']);
        $this->assertNull($context['error_msg']);
        $this->assertEquals($successMessage, $context['success_msg']);
    }

    /**
     * Test createMixedContext with additional context
     */
    public function test_create_mixed_context_with_additional_context(): void
    {
        $pageTitle = 'Test Page';
        $errorMessage = 'Test error message';
        $successMessage = 'Test success message';
        $additionalContext = [
            'form_data' => ['field1' => 'value1'],
            'timestamp' => '2023-01-01 12:00:00'
        ];
        
        $context = $this->responseHandler->createMixedContext($pageTitle, $errorMessage, $successMessage, $additionalContext);
        
        $this->assertIsArray($context);
        $this->assertEquals($pageTitle, $context['page_title']);
        $this->assertEquals($errorMessage, $context['error_msg']);
        $this->assertEquals($successMessage, $context['success_msg']);
        $this->assertEquals(['field1' => 'value1'], $context['form_data']);
        $this->assertEquals('2023-01-01 12:00:00', $context['timestamp']);
    }

    /**
     * Test context creation with empty strings
     */
    public function test_context_creation_with_empty_strings(): void
    {
        $context = $this->responseHandler->createErrorContext('', '');
        
        $this->assertEquals('', $context['page_title']);
        $this->assertEquals('', $context['error_msg']);
    }

    /**
     * Test context creation with null values
     */
    public function test_context_creation_with_null_values(): void
    {
        $context = $this->responseHandler->createMixedContext('Test', null, null);
        
        $this->assertEquals('Test', $context['page_title']);
        $this->assertNull($context['error_msg']);
        $this->assertNull($context['success_msg']);
    }

    /**
     * Mock WordPress function
     */
    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        if (!function_exists($functionName)) {
            if (is_callable($returnValue)) {
                eval("function {$functionName}(...\$args) { return call_user_func_array(" . var_export($returnValue, true) . ", \$args); }");
            } else {
                eval("function {$functionName}(...\$args) { return " . var_export($returnValue, true) . "; }");
            }
        }
    }
}

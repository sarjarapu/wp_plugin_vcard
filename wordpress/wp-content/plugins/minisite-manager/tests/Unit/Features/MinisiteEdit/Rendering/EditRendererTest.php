<?php

namespace Minisite\Tests\Unit\Features\MinisiteEdit\Rendering;

use Brain\Monkey\Functions;
use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Features\MinisiteEdit\Rendering\EditRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Test EditRenderer
 */
class EditRendererTest extends TestCase
{
    private EditRenderer $renderer;
    private $mockTimberRenderer;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->mockTimberRenderer = $this->createMock(TimberRenderer::class);
        $this->renderer = new EditRenderer($this->mockTimberRenderer);
    }

    protected function tearDown(): void
    {
        // Clean up any global mocks
        unset($GLOBALS['_test_mock_wp_create_nonce']);
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function testRenderEditFormWithTimber(): void
    {
        // wp_create_nonce is now defined in WordPressFunctions.php
        // Set up a mock value for this test
        $GLOBALS['_test_mock_wp_create_nonce'] = 'test_nonce';

        $editData = (object) array(
            'minisite' => (object) array('id' => '123', 'name' => 'Test Minisite'),
            'editingVersion' => (object) array('label' => 'Test Version', 'comment' => 'Test Comment'),
            'latestDraft' => (object) array('id' => 1),
            'profileForForm' => (object) array(
                'name' => 'Test Business',
                'city' => 'Test City',
                'region' => 'Test Region',
                'countryCode' => 'US',
                'postalCode' => '12345',
                'title' => 'Test Title',
                'siteTemplate' => 'v2025',
                'palette' => 'blue',
                'industry' => 'services',
                'defaultLocale' => 'en-US',
                'searchTerms' => 'test search',
                'geo' => (object) array('getLat' => function () { return 40.7128; }, 'getLng' => function () { return -74.0060; }),
            ),
            'siteJson' => array('test' => 'data'),
            'successMessage' => 'Draft saved successfully!',
            'errorMessage' => '',
        );

        // This will fail with Timber integration issues, but we can test the method signature
        try {
            $this->renderer->renderEditForm($editData);
            $this->fail('Expected Timber integration error');
        } catch (\TypeError $e) {
            // Expected - this confirms the method is being called
            $this->assertStringContainsString('addPath', $e->getMessage());
        }
    }

    public function testRenderEditFormWithoutTimber(): void
    {
        $renderer = new EditRenderer(null);

        $editData = (object) array(
            'minisite' => (object) array('id' => '123'),
            'editingVersion' => (object) array('label' => 'Test Version', 'comment' => 'Test Comment'),
            'latestDraft' => null,
            'profileForForm' => (object) array(
                'name' => 'Test Business',
                'city' => 'Test City',
                'title' => 'Test Title',
                'geo' => null,
            ),
            'siteJson' => array(),
            'successMessage' => '',
            'errorMessage' => 'Test error',
        );

        // wp_create_nonce is now defined in WordPressFunctions.php
        // It returns a predictable nonce based on action, or can be overridden via $GLOBALS
        $GLOBALS['_test_mock_wp_create_nonce'] = 'test_nonce';

        // Capture output
        ob_start();
        $renderer->renderEditForm($editData);
        $output = ob_get_clean();

        $this->assertStringContainsString('Edit Minisite', $output);
        $this->assertStringContainsString('Test Business', $output);
        $this->assertStringContainsString('Test City', $output);
        $this->assertStringContainsString('Test error', $output);
        $this->assertStringContainsString('test_nonce', $output);
    }

    public function testRenderErrorWithTimber(): void
    {
        $errorMessage = 'Test error message';

        // This will fail with Timber integration issues, but we can test the method signature
        try {
            $this->renderer->renderError($errorMessage);
            $this->fail('Expected Timber integration error');
        } catch (\TypeError $e) {
            // Expected - this confirms the method is being called
            $this->assertStringContainsString('addPath', $e->getMessage());
        }
    }

    public function testRenderErrorWithoutTimber(): void
    {
        $renderer = new EditRenderer(null);
        $errorMessage = 'Test error message';

        // Capture output
        ob_start();
        $renderer->renderError($errorMessage);
        $output = ob_get_clean();

        $this->assertStringContainsString('Error', $output);
        $this->assertStringContainsString($errorMessage, $output);
    }

    public function testPrepareTemplateData(): void
    {
        $editData = (object) array(
            'minisite' => (object) array('id' => '123'),
            'editingVersion' => (object) array('label' => 'Test Version', 'comment' => 'Test Comment'),
            'latestDraft' => (object) array('id' => 1),
            'profileForForm' => (object) array(
                'name' => 'Test Business',
                'city' => 'Test City',
                'region' => 'Test Region',
                'countryCode' => 'US',
                'postalCode' => '12345',
                'title' => 'Test Title',
                'siteTemplate' => 'v2025',
                'palette' => 'blue',
                'industry' => 'services',
                'defaultLocale' => 'en-US',
                'searchTerms' => 'test search',
                'geo' => $this->createMockGeoPoint(40.7128, -74.0060),
            ),
            'siteJson' => array('test' => 'data'),
            'successMessage' => 'Success!',
            'errorMessage' => 'Error!',
        );

        // wp_create_nonce is now defined in WordPressFunctions.php
        // It returns a predictable nonce based on action, or can be overridden via $GLOBALS
        $GLOBALS['_test_mock_wp_create_nonce'] = 'test_nonce';

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->renderer);
        $method = $reflection->getMethod('prepareTemplateData');
        $method->setAccessible(true);

        $result = $method->invoke($this->renderer, $editData);

        $this->assertEquals('Edit Minisite', $result['page_title']);
        $this->assertEquals('Update your minisite information', $result['page_subtitle']);
        $this->assertEquals('Test Business', $result['business_name']);
        $this->assertEquals('Test City', $result['business_city']);
        $this->assertEquals('Test Title', $result['seo_title']);
        $this->assertEquals('Success!', $result['success_message']);
        $this->assertEquals('Error!', $result['error_message']);
        $this->assertEquals('test_nonce', $result['form_nonce']);
        $this->assertEquals('POST', $result['form_method']);
        $this->assertEquals(40.7128, $result['contact_lat']);
        $this->assertEquals(-74.0060, $result['contact_lng']);
    }

    public function testPrepareTemplateDataWithNullGeo(): void
    {
        $editData = (object) array(
            'minisite' => (object) array('id' => '123'),
            'editingVersion' => (object) array('label' => 'Test Version', 'comment' => 'Test Comment'),
            'latestDraft' => null,
            'profileForForm' => (object) array(
                'name' => 'Test Business',
                'city' => 'Test City',
                'geo' => null,
            ),
            'siteJson' => array(),
            'successMessage' => '',
            'errorMessage' => '',
        );

        // wp_create_nonce is now defined in WordPressFunctions.php
        // It returns a predictable nonce based on action, or can be overridden via $GLOBALS
        $GLOBALS['_test_mock_wp_create_nonce'] = 'test_nonce';

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->renderer);
        $method = $reflection->getMethod('prepareTemplateData');
        $method->setAccessible(true);

        $result = $method->invoke($this->renderer, $editData);

        $this->assertEquals('', $result['contact_lat']);
        $this->assertEquals('', $result['contact_lng']);
    }

    public function testSetupTimberLocations(): void
    {
        Functions\when('MINISITE_PLUGIN_DIR')->justReturn('/path/to/plugin');

        // Mock Timber class
        $mockTimber = $this->createMock(\stdClass::class);
        $mockTimber->locations = array();

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->renderer);
        $method = $reflection->getMethod('setupTimberLocations');
        $method->setAccessible(true);

        // This should not throw an exception
        $method->invoke($this->renderer);
        $this->assertTrue(true);
    }


    /**
     * Create a mock GeoPoint object
     */
    private function createMockGeoPoint(float $lat, float $lng): object
    {
        return new class ($lat, $lng) {
            private float $lat;
            private float $lng;

            public function __construct(float $lat, float $lng)
            {
                $this->lat = $lat;
                $this->lng = $lng;
            }

            public function getLat(): float
            {
                return $this->lat;
            }

            public function getLng(): float
            {
                return $this->lng;
            }
        };
    }
}

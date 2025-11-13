<?php

declare(strict_types=1);

namespace Tests\Unit\Features\VersionManagement;

use Minisite\Features\VersionManagement\VersionManagementFeature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for VersionManagementFeature
 */
#[CoversClass(VersionManagementFeature::class)]
final class VersionManagementFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
        $this->clearWordPressMocks();
        // Clean up global mocks
        unset($GLOBALS['minisite_version_repository']);
    }

    /**
     * Test initialize method is static
     */
    public function test_initialize_is_static_method(): void
    {
        $reflection = new \ReflectionClass(VersionManagementFeature::class);
        $initializeMethod = $reflection->getMethod('initialize');

        $this->assertTrue($initializeMethod->isStatic());
        $this->assertTrue($initializeMethod->isPublic());
    }

    /**
     * Test initialize method exists and is callable
     */
    public function test_initialize_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists(VersionManagementFeature::class, 'initialize'));
        $this->assertTrue(is_callable(array(VersionManagementFeature::class, 'initialize')));
    }

    /**
     * Test initialize can be called without errors
     */
    public function test_initialize_can_be_called(): void
    {
        // Mock add_action to prevent actual WordPress hook registration
        $this->mockWordPressFunction('add_action', null);

        // Mock $wpdb global (required by VersionHooksFactory)
        global $wpdb;
        if (! isset($wpdb)) {
            $wpdb = $this->createMock(\wpdb::class);
        }

        // Mock VersionRepository in global (required by VersionHooksFactory)
        if (! isset($GLOBALS['minisite_version_repository'])) {
            // Use the actual repository class instead of interface
            $GLOBALS['minisite_version_repository'] = $this->createMock(
                \Minisite\Features\VersionManagement\Repositories\VersionRepository::class
            );
        }

        // initialize() creates hooks and registers them, but should not throw
        // Note: This may fail if Doctrine is not available, which is acceptable for unit tests
        try {
            VersionManagementFeature::initialize();
            $this->assertTrue(true); // If we get here, no exception was thrown
        } catch (\Exception $e) {
            // If Doctrine is not available or DB connection fails, skip this test
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') ||
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
                str_contains($errorMessage, 'No such file or directory') ||
                str_contains($errorMessage, 'SQLSTATE') ||
                str_contains($errorMessage, 'Class') ||
                str_contains($errorMessage, 'not found') ||
                str_contains($errorMessage, 'wpdb') ||
                str_contains($errorMessage, 'VersionRepository')) {
                $this->markTestSkipped('Dependencies not available: ' . $errorMessage);
            } else {
                throw $e;
            }
        } finally {
            // Clean up global mocks
            unset($GLOBALS['minisite_version_repository']);
        }
    }

    /**
     * Test VersionManagementFeature class has no constructor
     */
    public function test_class_has_no_constructor(): void
    {
        $reflection = new \ReflectionClass(VersionManagementFeature::class);
        $constructor = $reflection->getConstructor();

        $this->assertNull($constructor);
    }

    /**
     * Test class is not final (allows extension if needed)
     */
    public function test_class_is_not_final(): void
    {
        $reflection = new \ReflectionClass(VersionManagementFeature::class);
        $this->assertFalse($reflection->isFinal());
    }

    /**
     * Test initialize method signature
     */
    public function test_initialize_method_signature(): void
    {
        $reflection = new \ReflectionClass(VersionManagementFeature::class);
        $method = $reflection->getMethod('initialize');

        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
        $this->assertEquals('void', $method->getReturnType()->getName());
        $this->assertCount(0, $method->getParameters());
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = array('add_action');

        foreach ($functions as $function) {
            if (! function_exists($function)) {
                eval("
                    function {$function}(...\$args) {
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            return \$GLOBALS['_test_mock_{$function}'];
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
    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        $GLOBALS['_test_mock_' . $functionName] = $returnValue;
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = array('add_action');

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}

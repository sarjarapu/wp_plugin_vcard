<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ConfigurationManagement;

use Minisite\Features\ConfigurationManagement\ConfigurationManagementFeature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConfigurationManagementFeature
 */
#[CoversClass(ConfigurationManagementFeature::class)]
final class ConfigurationManagementFeatureTest extends TestCase
{
    /**
     * Test initialize method is static
     */
    public function test_initialize_is_static_method(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementFeature::class);
        $initializeMethod = $reflection->getMethod('initialize');

        $this->assertTrue($initializeMethod->isStatic());
        $this->assertTrue($initializeMethod->isPublic());
    }

    /**
     * Test initialize method exists and is callable
     */
    public function test_initialize_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists(ConfigurationManagementFeature::class, 'initialize'));
        $this->assertTrue(is_callable([ConfigurationManagementFeature::class, 'initialize']));
    }

    /**
     * Test initialize can be called without errors
     */
    public function test_initialize_can_be_called(): void
    {
        // Currently initialize() registers hooks, but it should not throw
        // Note: This may fail if Doctrine is not available, which is acceptable for unit tests
        try {
            ConfigurationManagementFeature::initialize();
            $this->assertTrue(true); // If we get here, no exception was thrown
        } catch (\Exception $e) {
            // If Doctrine is not available or DB connection fails, skip this test
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'DB_HOST') ||
                str_contains($errorMessage, 'Doctrine') ||
                str_contains($errorMessage, 'PDO') ||
                str_contains($errorMessage, 'Connection') ||
                str_contains($errorMessage, 'No such file or directory') ||
                str_contains($errorMessage, 'SQLSTATE')) {
                $this->markTestSkipped('Doctrine not available or DB connection failed: ' . $errorMessage);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Test ConfigurationManagementFeature class has no constructor
     */
    public function test_class_has_no_constructor(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementFeature::class);
        $constructor = $reflection->getConstructor();

        $this->assertNull($constructor);
    }

    /**
     * Test class is final
     */
    public function test_class_is_final(): void
    {
        $reflection = new \ReflectionClass(ConfigurationManagementFeature::class);
        $this->assertTrue($reflection->isFinal());
    }
}


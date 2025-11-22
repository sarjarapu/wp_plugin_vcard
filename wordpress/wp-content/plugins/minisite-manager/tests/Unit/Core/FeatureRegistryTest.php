<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Minisite\Core\FeatureRegistry;
use Tests\Support\CoreTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(FeatureRegistry::class)]
final class FeatureRegistryTest extends CoreTestCase
{
    protected function tearDown(): void
    {
        InitializableFeatureStub::reset();
        parent::tearDown();
    }

    public function testInitializeAllInvokesInitializeOnRegisteredFeatures(): void
    {
        $this->withFeatureList(array(
            InitializableFeatureStub::class,
            NonInitializableFeatureStub::class,
        ), function (): void {
            FeatureRegistry::initializeAll();
        });

        $this->assertSame(1, InitializableFeatureStub::$initializeCalls);
    }

    public function testRegisterFeatureAddsNewEntries(): void
    {
        $this->withFeatureList(array(), function (): void {
            FeatureRegistry::registerFeature(InitializableFeatureStub::class);
            FeatureRegistry::registerFeature(InitializableFeatureStub::class); // duplicate

            $features = FeatureRegistry::getFeatures();
            $this->assertCount(1, $features);
            $this->assertSame(InitializableFeatureStub::class, $features[0]);
        });
    }

    public function testGetFeaturesIncludesExistingAndNewOnes(): void
    {
        $this->withFeatureList(array('Foo'), function (): void {
            FeatureRegistry::registerFeature('Bar');
            $features = FeatureRegistry::getFeatures();

            $this->assertSame(array('Foo', 'Bar'), $features);
        });
    }

    /**
     * Temporarily override the registry feature list for isolated assertions.
     *
     * @param array<int, string> $features
     */
    private function withFeatureList(array $features, callable $callback): void
    {
        $reflection = new \ReflectionClass(FeatureRegistry::class);
        $property = $reflection->getProperty('features');
        $property->setAccessible(true);
        $original = $property->getValue();
        $property->setValue(null, $features);

        try {
            $callback();
        } finally {
            $property->setValue(null, $original);
        }
    }
}

final class InitializableFeatureStub
{
    public static int $initializeCalls = 0;

    public static function initialize(): void
    {
        self::$initializeCalls++;
    }

    public static function reset(): void
    {
        self::$initializeCalls = 0;
    }
}

final class NonInitializableFeatureStub
{
}

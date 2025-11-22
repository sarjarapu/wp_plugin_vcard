<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Minisite\Core\FeatureRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CoreTestCase;

#[Group('integration')]
#[CoversClass(FeatureRegistry::class)]
final class FeatureRegistryIntegrationTest extends CoreTestCase
{
    public function testInitializeAllLeavesFeatureListIntact(): void
    {
        $features = FeatureRegistry::getFeatures();
        $this->assertNotEmpty($features);

        FeatureRegistry::initializeAll();

        $this->assertSame($features, FeatureRegistry::getFeatures());
    }
}

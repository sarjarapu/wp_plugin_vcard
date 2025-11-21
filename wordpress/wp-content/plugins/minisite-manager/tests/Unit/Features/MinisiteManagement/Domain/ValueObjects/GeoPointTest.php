<?php

declare(strict_types=1);

namespace Tests\Unit\Features\MinisiteManagement\Domain\ValueObjects;

use Minisite\Features\MinisiteManagement\Domain\ValueObjects\GeoPoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GeoPointTest extends TestCase
{
    public function testConstructAndAccessors_IsSet(): void
    {
        $g = new GeoPoint(12.345, -98.765);
        $this->assertSame(12.345, $g->getLat());
        $this->assertSame(-98.765, $g->getLng());
        $this->assertTrue($g->isSet());
    }

    public function testAllowsNulls_IsSetFalse(): void
    {
        $g1 = new GeoPoint(null, null);
        $this->assertFalse($g1->isSet());

        $g2 = new GeoPoint(10.0, null);
        $this->assertFalse($g2->isSet());

        $g3 = new GeoPoint(null, 10.0);
        $this->assertFalse($g3->isSet());
    }

    public function testBoundaryValuesAccepted(): void
    {
        $this->assertTrue((new GeoPoint(-90.0, -180.0))->isSet());
        $this->assertTrue((new GeoPoint(90.0, 180.0))->isSet());
        $this->assertTrue((new GeoPoint(0.0, 0.0))->isSet());
    }

    #[DataProvider('dpInvalidLatitudes')]
    public function testThrowsOnInvalidLatitude(float $lat): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('latitude');
        new GeoPoint($lat, 0.0);
    }

    public static function dpInvalidLatitudes(): array
    {
        return [
            [-90.00001],
            [90.00001],
            [INF],
            [-INF],
            [NAN],
        ];
    }

    #[DataProvider('dpInvalidLongitudes')]
    public function testThrowsOnInvalidLongitude(float $lng): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('longitude');
        new GeoPoint(0.0, $lng);
    }

    public static function dpInvalidLongitudes(): array
    {
        return [
            [-180.00001],
            [180.00001],
            [INF],
            [-INF],
            [NAN],
        ];
    }

    #[DataProvider('dpTypeErrors')]
    public function testTypeErrorsOnNonFloatTypes(mixed $lat, mixed $lng): void
    {
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line */
        new GeoPoint($lat, $lng);
    }

    public static function dpTypeErrors(): array
    {
        return [
            ['10', 20.0],
            [10.0, '20'],
            ['10', '20'],
            [true, 0.0],
            [0.0, false],
            [[], 0.0],
            [0.0, (object)[]],
        ];
    }
}


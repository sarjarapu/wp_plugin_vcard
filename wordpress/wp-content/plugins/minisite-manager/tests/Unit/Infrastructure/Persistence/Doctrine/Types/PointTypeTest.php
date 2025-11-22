<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Minisite\Features\MinisiteManagement\Domain\ValueObjects\GeoPoint;
use Minisite\Infrastructure\Persistence\Doctrine\Types\PointType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PointType::class)]
final class PointTypeTest extends TestCase
{
    private PointType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new PointType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function test_get_name_returns_point(): void
    {
        $this->assertSame('point', $this->type->getName());
    }

    public function test_get_sql_declaration_returns_point(): void
    {
        $this->assertSame('POINT', $this->type->getSQLDeclaration(array(), $this->platform));
    }

    public function test_convert_to_php_value_handles_null(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function test_convert_to_php_value_parses_point_string(): void
    {
        $value = $this->type->convertToPHPValue('POINT(10 20)', $this->platform);
        $this->assertInstanceOf(GeoPoint::class, $value);
        $this->assertSame(20.0, $value->getLat());
        $this->assertSame(10.0, $value->getLng());
    }

    public function test_convert_to_php_value_handles_existing_geopoint(): void
    {
        $point = new GeoPoint(5.0, 6.0);
        $this->assertSame($point, $this->type->convertToPHPValue($point, $this->platform));
    }

    public function test_convert_to_database_value_handles_null(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function test_convert_to_database_value_creates_point_sql(): void
    {
        $point = new GeoPoint(40.0, -70.0);
        $this->assertSame('POINT(-70.000000 40.000000)', $this->type->convertToDatabaseValue($point, $this->platform));
    }

    public function test_convert_to_database_value_handles_unset_geopoint(): void
    {
        $point = new GeoPoint(null, 10.0);
        $this->assertNull($this->type->convertToDatabaseValue($point, $this->platform));
    }

    public function test_convert_to_php_value_sql_uses_st_astext(): void
    {
        $sql = $this->type->convertToPHPValueSQL('location', $this->platform);
        $this->assertSame('ST_AsText(location)', $sql);
    }

    public function test_convert_to_database_value_sql_uses_st_geomfromtext(): void
    {
        $sql = $this->type->convertToDatabaseValueSQL("'POINT(1 2)'", $this->platform);
        $this->assertSame('ST_GeomFromText(\'POINT(1 2)\', 4326)', $sql);
    }

    public function test_requires_sql_comment_hint_returns_true(): void
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}

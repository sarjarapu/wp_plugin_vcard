<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Persistence\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Minisite\Domain\ValueObjects\GeoPoint;

/**
 * Custom Doctrine type for MySQL POINT geometry type
 *
 * Handles conversion between GeoPoint value object and MySQL POINT type.
 * ⚠️ CRITICAL: POINT is stored as POINT(longitude, latitude) - longitude FIRST, latitude SECOND
 * See: docs/issues/location-point-lessons-learned.md
 *
 * Based on brick/geo-doctrine approach but adapted for our GeoPoint value object.
 */
final class PointType extends Type
{
    public const NAME = 'point';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Get SQL declaration for POINT type
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'POINT';
    }

    /**
     * Convert database value to PHP value (GeoPoint)
     *
     * MySQL returns POINT as binary WKB (Well-Known Binary) format.
     * We use ST_AsText() to convert to text format "POINT(lng lat)".
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?GeoPoint
    {
        if ($value === null || $value === '') {
            return null;
        }

        // If value is already a GeoPoint (from previous conversion), return as-is
        if ($value instanceof GeoPoint) {
            return $value;
        }

        // Handle WKB binary format - MySQL returns POINT as binary
        // We need to parse it. For now, if it's a string, try to parse it
        // In practice, this should be handled via SQL with ST_X() and ST_Y()
        if (is_string($value)) {
            // Try to parse "POINT(lng lat)" format
            if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/', $value, $matches)) {
                $lng = (float) $matches[1];
                $lat = (float) $matches[2];

                return new GeoPoint($lat, $lng);
            }
        }

        // If we can't parse it, return null
        return null;
    }

    /**
     * Convert PHP value (GeoPoint) to database value
     *
     * Returns SQL expression that creates POINT(longitude, latitude).
     * ⚠️ CRITICAL: longitude FIRST, latitude SECOND
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof GeoPoint) {
            throw new \InvalidArgumentException(
                'Expected GeoPoint value object, got: ' . gettype($value)
            );
        }

        if (! $value->isSet()) {
            return null;
        }

        // ⚠️ CRITICAL: POINT(longitude, latitude) - longitude FIRST, latitude SECOND
        // See: docs/issues/location-point-lessons-learned.md
        return sprintf('POINT(%F %F)', $value->getLng(), $value->getLat());
    }

    /**
     * Convert SQL expression for reading POINT from database
     *
     * Uses ST_AsText() to convert POINT to text format for parsing.
     * This is used when selecting POINT columns in queries.
     */
    public function convertToPHPValueSQL($sqlExpr, AbstractPlatform $platform): string
    {
        // Use ST_AsText() to convert POINT to text format "POINT(lng lat)"
        return sprintf('ST_AsText(%s)', $sqlExpr);
    }

    /**
     * Convert SQL expression for writing POINT to database
     *
     * Uses ST_GeomFromText() to convert text format to POINT geometry.
     * This is used when inserting/updating POINT columns.
     */
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform): string
    {
        // Use ST_GeomFromText() to convert text "POINT(lng lat)" to POINT geometry
        return sprintf('ST_GeomFromText(%s, 4326)', $sqlExpr);
    }

    /**
     * Whether this type requires SQL conversion
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Features\VersionManagement\Domain\Entities;

use DateTimeImmutable;
use Minisite\Features\MinisiteManagement\Domain\ValueObjects\GeoPoint;
use Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

#[CoversClass(Version::class)]
final class VersionTest extends TestCase
{
    public function testConstructorSignature_StrictContract(): void
    {
        $ctor = new ReflectionMethod(Version::class, '__construct');
        $params = $ctor->getParameters();

        $expected = array(
            array('id', 'int', true),
            array('minisiteId', 'string', true),
            array('versionNumber', 'int', true),
            array('status', 'string', true),
            array('label', 'string', true),
            array('comment', 'string', true),
            array('createdBy', 'int', true),
            array('createdAt', DateTimeImmutable::class, true),
            array('publishedAt', DateTimeImmutable::class, true),
            array('sourceVersionId', 'int', true),
            array('siteJson', 'array', true),
            // Optional minisite fields
            array('slugs', SlugPair::class, true),
            array('title', 'string', true),
            array('name', 'string', true),
            array('city', 'string', true),
            array('region', 'string', true),
            array('countryCode', 'string', true),
            array('postalCode', 'string', true),
            array('geo', GeoPoint::class, true),
            array('siteTemplate', 'string', true),
            array('palette', 'string', true),
            array('industry', 'string', true),
            array('defaultLocale', 'string', true),
            array('schemaVersion', 'int', true),
            array('siteVersion', 'int', true),
            array('searchTerms', 'string', true),
        );

        $this->assertSame(count($expected), count($params), 'Constructor parameter count changed');

        foreach ($params as $i => $param) {
            [$name, $typeName, $allowsNull] = $expected[$i];
            $this->assertSame($name, $param->getName(), "Param #$i name mismatch");
            $this->assertNotNull($param->getType(), "Param #$i type is missing");
            [$actualTypeName, $actualAllowsNull] = $this->normalizeType($param);
            $this->assertSame($typeName, $actualTypeName, "Param #$i type mismatch");
            $this->assertSame($allowsNull, $actualAllowsNull, "Param #$i nullability mismatch");
        }
    }

    public function testConstructSetsAllFieldsWithFullPayload(): void
    {
        $slugs = new SlugPair('biz', 'loc');
        $geo = new GeoPoint(12.34, 56.78);
        $now = new DateTimeImmutable('2025-01-01T00:00:00Z');
        $pub = new DateTimeImmutable('2025-01-02T00:00:00Z');

        $v = new Version(
            id: 7,
            minisiteId: 'ms-1',
            versionNumber: 3,
            status: 'published',
            label: 'v3',
            comment: 'release notes',
            createdBy: 9,
            createdAt: $now,
            publishedAt: $pub,
            sourceVersionId: 6,
            siteJson: array('a' => 1),
            slugs: $slugs,
            title: 'Title',
            name: 'Name',
            city: 'City',
            region: 'Region',
            countryCode: 'US',
            postalCode: '00000',
            geo: $geo,
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 2,
            siteVersion: 42,
            searchTerms: 'terms',
        );

        $this->assertSame(7, $v->id);
        $this->assertSame('ms-1', $v->minisiteId);
        $this->assertSame(3, $v->versionNumber);
        $this->assertSame('published', $v->status);
        $this->assertSame('v3', $v->label);
        $this->assertSame('release notes', $v->comment);
        $this->assertSame(9, $v->createdBy);
        $this->assertSame($now, $v->createdAt);
        $this->assertSame($pub, $v->publishedAt);
        $this->assertSame(6, $v->sourceVersionId);
        $this->assertSame(array('a' => 1), $v->getSiteJsonAsArray());
        $this->assertSame($slugs, $v->slugs);
        $this->assertSame('biz', $v->businessSlug);
        $this->assertSame('loc', $v->locationSlug);
        $this->assertSame('Title', $v->title);
        $this->assertSame('Name', $v->name);
        $this->assertSame('City', $v->city);
        $this->assertSame('Region', $v->region);
        $this->assertSame('US', $v->countryCode);
        $this->assertSame('00000', $v->postalCode);
        $this->assertSame($geo, $v->geo);
        $this->assertSame('v2025', $v->siteTemplate);
        $this->assertSame('blue', $v->palette);
        $this->assertSame('services', $v->industry);
        $this->assertSame('en-US', $v->defaultLocale);
        $this->assertSame(2, $v->schemaVersion);
        $this->assertSame(42, $v->siteVersion);
        $this->assertSame('terms', $v->searchTerms);
    }

    public function testOptionalFieldsCanBeNull(): void
    {
        $v = new Version(
            id: null,
            minisiteId: 'ms-1',
            versionNumber: 1,
            status: 'draft',
            label: null,
            comment: null,
            createdBy: 2,
            createdAt: null,
            publishedAt: null,
            sourceVersionId: null,
            siteJson: array(),
            slugs: null,
            title: null,
            name: null,
            city: null,
            region: null,
            countryCode: null,
            postalCode: null,
            geo: null,
            siteTemplate: null,
            palette: null,
            industry: null,
            defaultLocale: null,
            schemaVersion: null,
            siteVersion: null,
            searchTerms: null,
        );

        $this->assertNull($v->id);
        $this->assertNull($v->label);
        $this->assertNull($v->comment);
        $this->assertInstanceOf(DateTimeImmutable::class, $v->createdAt);
        $this->assertNull($v->publishedAt);
        $this->assertNull($v->sourceVersionId);
        $this->assertNull($v->slugs);
        $this->assertNull($v->title);
        $this->assertNull($v->name);
        $this->assertNull($v->city);
        $this->assertNull($v->region);
        $this->assertNull($v->countryCode);
        $this->assertNull($v->postalCode);
        $this->assertNull($v->geo);
        $this->assertNull($v->siteTemplate);
        $this->assertNull($v->palette);
        $this->assertNull($v->industry);
        $this->assertNull($v->defaultLocale);
        $this->assertNull($v->schemaVersion);
        $this->assertNull($v->siteVersion);
        $this->assertNull($v->searchTerms);
        $this->assertSame(array(), $v->getSiteJsonAsArray());
    }

    #[DataProvider('dpTypeErrorsOnInvalidTypes')]
    public function testTypeErrorsOnInvalidTypes(callable $factory): void
    {
        $this->expectException(\TypeError::class);
        $factory();
    }

    public static function dpTypeErrorsOnInvalidTypes(): array
    {
        return array(
            'minisiteId must be string' => array(function (): void {
                /** @phpstan-ignore-next-line */
                new Version(id: null, minisiteId: 1, versionNumber: 1, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: array());
            }),
            'versionNumber must be int' => array(function (): void {
                /** @phpstan-ignore-next-line */
                new Version(id: null, minisiteId: 'm', versionNumber: '1', status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: array());
            }),
            'siteJson must be array' => array(function (): void {
                /** @phpstan-ignore-next-line */
                new Version(id: null, minisiteId: 'm', versionNumber: 1, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: (object)array('a' => 1));
            }),
            'slugs must be SlugPair|null' => array(function (): void {
                /** @phpstan-ignore-next-line */
                new Version(id: null, minisiteId: 'm', versionNumber: 1, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: array(), slugs: 'bad');
            }),
            'geo must be GeoPoint|null' => array(function (): void {
                /** @phpstan-ignore-next-line */
                new Version(id: null, minisiteId: 'm', versionNumber: 1, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: array(), slugs: null, title: null, name: null, city: null, region: null, countryCode: null, postalCode: null, geo: 'bad');
            }),
        );
    }

    public function testHelperMethods(): void
    {
        $draft = new Version(id: null, minisiteId: 'm', versionNumber: 1, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: array());
        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isPublished());
        $this->assertFalse($draft->isRollback());

        $published = new Version(id: null, minisiteId: 'm', versionNumber: 2, status: 'published', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: new DateTimeImmutable('2025-01-01T00:00:00Z'), sourceVersionId: null, siteJson: array());
        $this->assertTrue($published->isPublished());
        $this->assertFalse($published->isDraft());

        $rollback = new Version(id: null, minisiteId: 'm', versionNumber: 3, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: 1, siteJson: array());
        $this->assertTrue($rollback->isRollback());
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function normalizeType(ReflectionParameter $param): array
    {
        $type = $param->getType();
        if (! $type instanceof ReflectionType) {
            return array('mixed', true);
        }
        $allowsNull = $type->allowsNull();
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            return array($name, $allowsNull);
        }

        return array('complex', $allowsNull);
    }
}

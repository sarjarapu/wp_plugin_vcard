<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Minisite\Domain\Entities\Version;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
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

        $expected = [
            ['id', 'int', true],
            ['minisiteId', 'string', false],
            ['versionNumber', 'int', false],
            ['status', 'string', false],
            ['label', 'string', true],
            ['comment', 'string', true],
            ['createdBy', 'int', false],
            ['createdAt', DateTimeImmutable::class, true],
            ['publishedAt', DateTimeImmutable::class, true],
            ['sourceVersionId', 'int', true],
            ['siteJson', 'array', false],
            // Optional minisite fields
            ['slugs', SlugPair::class, true],
            ['title', 'string', true],
            ['name', 'string', true],
            ['city', 'string', true],
            ['region', 'string', true],
            ['countryCode', 'string', true],
            ['postalCode', 'string', true],
            ['geo', GeoPoint::class, true],
            ['siteTemplate', 'string', true],
            ['palette', 'string', true],
            ['industry', 'string', true],
            ['defaultLocale', 'string', true],
            ['schemaVersion', 'int', true],
            ['siteVersion', 'int', true],
            ['searchTerms', 'string', true],
        ];

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
            siteJson: ['a' => 1],
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
        $this->assertSame(['a' => 1], $v->siteJson);
        $this->assertSame($slugs, $v->slugs);
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
            siteJson: [],
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
        $this->assertNull($v->createdAt);
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
    }

    #[DataProvider('dpTypeErrorsOnInvalidTypes')]
    public function testTypeErrorsOnInvalidTypes(callable $factory): void
    {
        $this->expectException(\TypeError::class);
        $factory();
    }

    public static function dpTypeErrorsOnInvalidTypes(): array
    {
        return [
            'minisiteId must be string' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Version(id: null, minisiteId: 1, versionNumber: 1, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: []);
            }],
            'versionNumber must be int' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Version(id: null, minisiteId: 'm', versionNumber: '1', status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: []);
            }],
            'siteJson must be array' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Version(id: null, minisiteId: 'm', versionNumber: 1, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: (object)['a' => 1]);
            }],
            'slugs must be SlugPair|null' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Version(id: null, minisiteId: 'm', versionNumber: 1, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: [], slugs: 'bad');
            }],
            'geo must be GeoPoint|null' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Version(id: null, minisiteId: 'm', versionNumber: 1, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: [], slugs: null, title: null, name: null, city: null, region: null, countryCode: null, postalCode: null, geo: 'bad');
            }],
        ];
    }

    public function testHelperMethods(): void
    {
        $draft = new Version(id: null, minisiteId: 'm', versionNumber: 1, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: null, siteJson: []);
        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isPublished());
        $this->assertFalse($draft->isRollback());

        $published = new Version(id: null, minisiteId: 'm', versionNumber: 2, status: 'published', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: new DateTimeImmutable('2025-01-01T00:00:00Z'), sourceVersionId: null, siteJson: []);
        $this->assertTrue($published->isPublished());
        $this->assertFalse($published->isDraft());

        $rollback = new Version(id: null, minisiteId: 'm', versionNumber: 3, status: 'draft', label: null, comment: null, createdBy: 1, createdAt: null, publishedAt: null, sourceVersionId: 1, siteJson: []);
        $this->assertTrue($rollback->isRollback());
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function normalizeType(ReflectionParameter $param): array
    {
        $type = $param->getType();
        if (!$type instanceof ReflectionType) {
            return ['mixed', true];
        }
        $allowsNull = $type->allowsNull();
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            return [$name, $allowsNull];
        }
        return ['complex', $allowsNull];
    }
}

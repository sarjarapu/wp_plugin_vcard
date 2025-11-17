<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use delete_me\Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

#[CoversClass(Minisite::class)]
final class MinisiteTest extends TestCase
{
    public function testConstructorSignature_StrictContract(): void
    {
        $ctor = new ReflectionMethod(Minisite::class, '__construct');
        $params = $ctor->getParameters();

        // Expected constructor parameters in exact order with strict types and nullability
        $expected = [
            ['id', 'string', false],
            ['slug', 'string', true],
            ['slugs', SlugPair::class, false],
            ['title', 'string', false],
            ['name', 'string', false],
            ['city', 'string', false],
            ['region', 'string', true],
            ['countryCode', 'string', false],
            ['postalCode', 'string', true],
            ['geo', GeoPoint::class, true],
            ['siteTemplate', 'string', false],
            ['palette', 'string', false],
            ['industry', 'string', false],
            ['defaultLocale', 'string', false],
            ['schemaVersion', 'int', false],
            ['siteVersion', 'int', false],
            ['siteJson', 'array', false],
            ['searchTerms', 'string', true],
            ['status', 'string', false],
            ['publishStatus', 'string', false],
            ['createdAt', DateTimeImmutable::class, true],
            ['updatedAt', DateTimeImmutable::class, true],
            ['publishedAt', DateTimeImmutable::class, true],
            ['createdBy', 'int', true],
            ['updatedBy', 'int', true],
            ['currentVersionId', 'int', true],
            ['isBookmarked', 'bool', false], // has default
            ['canEdit', 'bool', false],      // has default
        ];

        $this->assertSame(
            count($expected),
            count($params),
            'Constructor parameter count changed: added/removed fields?'
        );

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
        $later = new DateTimeImmutable('2025-01-02T00:00:00Z');
        $pub = new DateTimeImmutable('2025-01-03T00:00:00Z');

        $m = $this->makeMinisite([
            'id' => 'id-123',
            'slugs' => $slugs,
            'title' => 'Title',
            'name' => 'Name',
            'city' => 'City',
            'region' => 'Region',
            'countryCode' => 'US',
            'postalCode' => '12345',
            'geo' => $geo,
            'siteTemplate' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'defaultLocale' => 'en-US',
            'schemaVersion' => 3,
            'siteVersion' => 42,
            'siteJson' => ['a' => 1],
            'searchTerms' => 'normalized terms',
            'status' => 'draft',
            'createdAt' => $now,
            'updatedAt' => $later,
            'publishedAt' => $pub,
            'createdBy' => 10,
            'updatedBy' => 11,
            'currentVersionId' => 99,
            'isBookmarked' => true,
            'canEdit' => true,
        ]);

        $this->assertSame('id-123', $m->id);
        $this->assertSame($slugs, $m->slugs);
        $this->assertSame('Title', $m->title);
        $this->assertSame('Name', $m->name);
        $this->assertSame('City', $m->city);
        $this->assertSame('Region', $m->region);
        $this->assertSame('US', $m->countryCode);
        $this->assertSame('12345', $m->postalCode);
        $this->assertSame($geo, $m->geo);
        $this->assertSame('v2025', $m->siteTemplate);
        $this->assertSame('blue', $m->palette);
        $this->assertSame('services', $m->industry);
        $this->assertSame('en-US', $m->defaultLocale);
        $this->assertSame(3, $m->schemaVersion);
        $this->assertSame(42, $m->siteVersion);
        $this->assertSame(['a' => 1], $m->siteJson);
        $this->assertSame('normalized terms', $m->searchTerms);
        $this->assertSame('draft', $m->status);
        $this->assertSame($now, $m->createdAt);
        $this->assertSame($later, $m->updatedAt);
        $this->assertSame($pub, $m->publishedAt);
        $this->assertSame(10, $m->createdBy);
        $this->assertSame(11, $m->updatedBy);
        $this->assertSame(99, $m->currentVersionId);
        $this->assertTrue($m->isBookmarked);
        $this->assertTrue($m->canEdit);
    }

    public function testOptionalFieldsCanBeNull(): void
    {
        $m = $this->makeMinisite([
            'region' => null,
            'postalCode' => null,
            'geo' => null,
            'searchTerms' => null,
            'createdAt' => null,
            'updatedAt' => null,
            'publishedAt' => null,
            'createdBy' => null,
            'updatedBy' => null,
            'currentVersionId' => null,
        ]);

        $this->assertNull($m->region);
        $this->assertNull($m->postalCode);
        $this->assertNull($m->geo);
        $this->assertNull($m->searchTerms);
        $this->assertNull($m->createdAt);
        $this->assertNull($m->updatedAt);
        $this->assertNull($m->publishedAt);
        $this->assertNull($m->createdBy);
        $this->assertNull($m->updatedBy);
        $this->assertNull($m->currentVersionId);
    }

    public function testDefaultsForBooleans(): void
    {
        // Do not pass isBookmarked/canEdit -> should default to false
        $m = $this->makeMinisite([]);

        $this->assertFalse($m->isBookmarked);
        $this->assertFalse($m->canEdit);
    }

    public function testAcceptsGeoPointOrNull(): void
    {
        $geo = new GeoPoint(1.23, 4.56);
        $withGeo = $this->makeMinisite(['geo' => $geo]);
        $this->assertSame($geo, $withGeo->geo);

        $withoutGeo = $this->makeMinisite(['geo' => null]);
        $this->assertNull($withoutGeo->geo);
    }

    #[DataProvider('dpTypeErrorsOnInvalidTypes')]
    public function testTypeErrorsOnInvalidTypes(callable $factory): void
    {
        $this->expectException(\TypeError::class);
        $factory();
    }

    public static function dpTypeErrorsOnInvalidTypes(): array
    {
        $base = function (array $overrides): void {
            new Minisite(
                id: $overrides['id'] ?? 'id',
                slug: $overrides['slug'] ?? 'test-slug',
                slugs: $overrides['slugs'] ?? new SlugPair('biz', 'loc'),
                title: 'Title',
                name: 'Name',
                city: 'City',
                region: 'Region',
                countryCode: 'US',
                postalCode: '00000',
                geo: $overrides['geo'] ?? new GeoPoint(0.0, 0.0),
                siteTemplate: 'v2025',
                palette: 'blue',
                industry: 'services',
                defaultLocale: 'en-US',
                schemaVersion: $overrides['schemaVersion'] ?? 1,
                siteVersion: 1,
                siteJson: $overrides['siteJson'] ?? [],
                searchTerms: 'terms',
                status: 'draft',
                publishStatus: 'draft',
                createdAt: new DateTimeImmutable('2025-01-01T00:00:00Z'),
                updatedAt: new DateTimeImmutable('2025-01-01T01:00:00Z'),
                publishedAt: null,
                createdBy: 1,
                updatedBy: 1,
                currentVersionId: null,
                isBookmarked: false,
                canEdit: false
            );
        };

        return [
            'id must be string' => [function () use ($base): void {
                /** @phpstan-ignore-next-line */
                $base(['id' => 123]);
            }],
            'slugs must be SlugPair' => [function () use ($base): void {
                /** @phpstan-ignore-next-line */
                $base(['slugs' => ['a', 'b']]);
            }],
            'schemaVersion must be int' => [function () use ($base): void {
                /** @phpstan-ignore-next-line */
                $base(['schemaVersion' => '3']);
            }],
            'siteJson must be array' => [function () use ($base): void {
                /** @phpstan-ignore-next-line */
                $base(['siteJson' => (object)['a' => 1]]);
            }],
            'geo must be GeoPoint|null' => [function () use ($base): void {
                /** @phpstan-ignore-next-line */
                $base(['geo' => 'not-a-geo']);
            }],
        ];
    }

    #[DataProvider('dpCommonStatuses')]
    public function testStoresCommonStatuses(string $status): void
    {
        $m = $this->makeMinisite(['status' => $status]);
        $this->assertSame($status, $m->status);
    }

    public static function dpCommonStatuses(): array
    {
        return [
            ['draft'],
            ['published'],
            ['archived'],
        ];
    }

    // ----------------- helpers -----------------

    /**
     * Instantiate Minisite with sensible defaults using named arguments,
     * so any rename/remove/type change is caught at compile/runtime in tests.
     *
     * @param array<string,mixed> $overrides
     */
    private function makeMinisite(array $overrides): Minisite
    {
        $defaults = [
            'id' => 'id',
            'slug' => 'test-slug',
            'slugs' => new SlugPair('biz', 'loc'),
            'title' => 'Title',
            'name' => 'Name',
            'city' => 'City',
            'region' => 'Region',
            'countryCode' => 'US',
            'postalCode' => '00000',
            'geo' => new GeoPoint(0.0, 0.0),
            'siteTemplate' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'defaultLocale' => 'en-US',
            'schemaVersion' => 1,
            'siteVersion' => 1,
            'siteJson' => [],
            'searchTerms' => 'terms',
            'status' => 'draft',
            'publishStatus' => 'draft',
            'createdAt' => new DateTimeImmutable('2025-01-01T00:00:00Z'),
            'updatedAt' => new DateTimeImmutable('2025-01-01T01:00:00Z'),
            'publishedAt' => null,
            'createdBy' => 1,
            'updatedBy' => 1,
            'currentVersionId' => null,
            // defaults for trailing booleans are intentionally omitted unless set
        ];

        $args = array_merge($defaults, $overrides);

        // Named arguments ensure strict matching of parameter names
        return new Minisite(
            id: $args['id'],
            slug: $args['slug'],
            slugs: $args['slugs'],
            title: $args['title'],
            name: $args['name'],
            city: $args['city'],
            region: $args['region'],
            countryCode: $args['countryCode'],
            postalCode: $args['postalCode'],
            geo: $args['geo'],
            siteTemplate: $args['siteTemplate'],
            palette: $args['palette'],
            industry: $args['industry'],
            defaultLocale: $args['defaultLocale'],
            schemaVersion: $args['schemaVersion'],
            siteVersion: $args['siteVersion'],
            siteJson: $args['siteJson'],
            searchTerms: $args['searchTerms'],
            status: $args['status'],
            publishStatus: $args['publishStatus'],
            createdAt: $args['createdAt'],
            updatedAt: $args['updatedAt'],
            publishedAt: $args['publishedAt'],
            createdBy: $args['createdBy'],
            updatedBy: $args['updatedBy'],
            currentVersionId: $args['currentVersionId'],
            // Only set trailing flags when provided, to test defaults easily
            isBookmarked: $overrides['isBookmarked'] ?? false,
            canEdit: $overrides['canEdit'] ?? false
        );
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
            $name = $type->getName(); // builtins like 'string', or FQCN/declared class name
            return [$name, $allowsNull];
        }

        // Union/intersection not expected here; fallback provides fail-fast behavior
        return ['complex', $allowsNull];
    }
}

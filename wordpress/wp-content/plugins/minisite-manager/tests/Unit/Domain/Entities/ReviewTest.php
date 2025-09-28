<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Minisite\Domain\Entities\Review;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

#[CoversClass(Review::class)]
final class ReviewTest extends TestCase
{
    public function testConstructorSignature_StrictContract(): void
    {
        $ctor = new ReflectionMethod(Review::class, '__construct');
        $params = $ctor->getParameters();

        $expected = [
            ['id', 'int', true],
            ['minisiteId', 'int', false],
            ['authorName', 'string', false],
            ['authorUrl', 'string', true],
            ['rating', 'float', false],
            ['body', 'string', false],
            ['locale', 'string', true],
            ['visitedMonth', 'string', true],
            ['source', 'string', false],
            ['sourceId', 'string', true],
            ['status', 'string', false],
            ['createdAt', DateTimeImmutable::class, true],
            ['updatedAt', DateTimeImmutable::class, true],
            ['createdBy', 'int', true],
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
        $now = new DateTimeImmutable('2025-01-01T00:00:00Z');
        $later = new DateTimeImmutable('2025-01-02T00:00:00Z');

        $r = new Review(
            id: 1,
            minisiteId: 42,
            authorName: 'Alice',
            authorUrl: 'https://example.com',
            rating: 4.5,
            body: 'Great service!',
            locale: 'en-US',
            visitedMonth: '2025-07',
            source: 'google',
            sourceId: 'g-123',
            status: 'approved',
            createdAt: $now,
            updatedAt: $later,
            createdBy: 10,
        );

        $this->assertSame(1, $r->id);
        $this->assertSame(42, $r->minisiteId);
        $this->assertSame('Alice', $r->authorName);
        $this->assertSame('https://example.com', $r->authorUrl);
        $this->assertSame(4.5, $r->rating);
        $this->assertSame('Great service!', $r->body);
        $this->assertSame('en-US', $r->locale);
        $this->assertSame('2025-07', $r->visitedMonth);
        $this->assertSame('google', $r->source);
        $this->assertSame('g-123', $r->sourceId);
        $this->assertSame('approved', $r->status);
        $this->assertSame($now, $r->createdAt);
        $this->assertSame($later, $r->updatedAt);
        $this->assertSame(10, $r->createdBy);
    }

    public function testOptionalFieldsCanBeNull(): void
    {
        $r = new Review(
            id: null,
            minisiteId: 42,
            authorName: 'Bob',
            authorUrl: null,
            rating: 5.0,
            body: 'Awesome',
            locale: null,
            visitedMonth: null,
            source: 'manual',
            sourceId: null,
            status: 'pending',
            createdAt: null,
            updatedAt: null,
            createdBy: null,
        );

        $this->assertNull($r->id);
        $this->assertNull($r->authorUrl);
        $this->assertNull($r->locale);
        $this->assertNull($r->visitedMonth);
        $this->assertNull($r->sourceId);
        $this->assertNull($r->createdAt);
        $this->assertNull($r->updatedAt);
        $this->assertNull($r->createdBy);
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
            'minisiteId must be int' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Review(id: null, minisiteId: '42', authorName: 'A', authorUrl: null, rating: 1.0, body: 'x', locale: null, visitedMonth: null, source: 'manual', sourceId: null, status: 'pending', createdAt: null, updatedAt: null, createdBy: null);
            }],
            'authorName must be string' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Review(id: null, minisiteId: 1, authorName: 123, authorUrl: null, rating: 1.0, body: 'x', locale: null, visitedMonth: null, source: 'manual', sourceId: null, status: 'pending', createdAt: null, updatedAt: null, createdBy: null);
            }],
            'rating must be float' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Review(id: null, minisiteId: 1, authorName: 'A', authorUrl: null, rating: '5', body: 'x', locale: null, visitedMonth: null, source: 'manual', sourceId: null, status: 'pending', createdAt: null, updatedAt: null, createdBy: null);
            }],
            'body must be string' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Review(id: null, minisiteId: 1, authorName: 'A', authorUrl: null, rating: 5.0, body: 111, locale: null, visitedMonth: null, source: 'manual', sourceId: null, status: 'pending', createdAt: null, updatedAt: null, createdBy: null);
            }],
            'status must be string' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Review(id: null, minisiteId: 1, authorName: 'A', authorUrl: null, rating: 5.0, body: 'x', locale: null, visitedMonth: null, source: 'manual', sourceId: null, status: 0, createdAt: null, updatedAt: null, createdBy: null);
            }],
            'createdAt must be DateTimeImmutable|null' => [function (): void {
                /** @phpstan-ignore-next-line */
                new Review(id: null, minisiteId: 1, authorName: 'A', authorUrl: null, rating: 5.0, body: 'x', locale: null, visitedMonth: null, source: 'manual', sourceId: null, status: 'pending', createdAt: 'now', updatedAt: null, createdBy: null);
            }],
        ];
    }

    #[DataProvider('dpCommonStatuses')]
    public function testStoresCommonStatuses(string $status): void
    {
        $r = new Review(id: null, minisiteId: 1, authorName: 'A', authorUrl: null, rating: 5.0, body: 'x', locale: null, visitedMonth: null, source: 'manual', sourceId: null, status: $status, createdAt: null, updatedAt: null, createdBy: null);
        $this->assertSame($status, $r->status);
    }

    public static function dpCommonStatuses(): array
    {
        return [
            ['pending'],
            ['approved'],
            ['rejected'],
        ];
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

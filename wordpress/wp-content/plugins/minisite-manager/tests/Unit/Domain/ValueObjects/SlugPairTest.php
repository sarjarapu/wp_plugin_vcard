<?php
declare(strict_types=1);

namespace Unit\Domain\ValueObjects;

use Minisite\Domain\ValueObjects\SlugPair;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SlugPairTest extends TestCase
{
    public function testConstructAndAccessors(): void
    {
        $p = new SlugPair('my-biz', 'nyc');
        $this->assertSame('my-biz', $p->business);
        $this->assertSame('nyc', $p->location);
    }

    public function testAsArrayAndFull(): void
    {
        $p = new SlugPair('dallas-dental', 'tx-downtown');
        $this->assertSame(['business_slug' => 'dallas-dental', 'location_slug' => 'tx-downtown'], $p->asArray());
        $this->assertSame('dallas-dental/tx-downtown', $p->full());
    }

    public function testEqualitySemantics(): void
    {
        $a = new SlugPair('a', 'b');
        $b = new SlugPair('a', 'b');
        $c = new SlugPair('a', 'c');

        // If you have an equals() method, use it; otherwise compare tuples.
        $this->assertSame($a->asArray(), $b->asArray());
        $this->assertNotSame($a->asArray(), $c->asArray());
    }

    public function testTrimsInputsAndAllowsEmptyLocation(): void
    {
        $p = new SlugPair('  biz  ', "  loc\t");
        $this->assertSame('biz', $p->business);
        $this->assertSame('loc', $p->location);
        $this->assertSame('biz/loc', $p->full());

        $p2 = new SlugPair("  only-biz  ", null);
        $this->assertSame('only-biz', $p2->business);
        $this->assertSame('', $p2->location);
        $this->assertSame('only-biz', $p2->full());
        $this->assertSame(['business_slug' => 'only-biz', 'location_slug' => ''], $p2->asArray());

        $p3 = new SlugPair('biz', '   ');
        $this->assertSame('', $p3->location);
        $this->assertSame('biz', $p3->full());
        $this->assertSame(['business_slug' => 'biz', 'location_slug' => ''], $p3->asArray());
    }

    #[DataProvider('dpInvalidBusiness')]
    public function testThrowsOnInvalidBusiness(string $business): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SlugPair($business, 'loc');
    }

    public static function dpInvalidBusiness(): array
    {
        return [
            [''],
            [' '],
            ["\t"],
            ["\n\t  \n"],
        ];
    }

    public function testAllowsEmptyOrWhitespaceLocation(): void
    {
        $p = new SlugPair('biz', '');
        $this->assertSame('', $p->location);
        $this->assertSame('biz', $p->full());

        $p2 = new SlugPair('biz', " \t \n ");
        $this->assertSame('', $p2->location);
        $this->assertSame('biz', $p2->full());
    }

    #[DataProvider('dpTypeErrors')]
    public function testTypeErrorsOnInvalidTypes(mixed $business, mixed $location): void
    {
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line */
        new SlugPair($business, $location);
    }

    public static function dpTypeErrors(): array
    {
        return [
            [123, 'loc'],
            ['biz', 123],
            ['biz', true],
            [[], 'x'],
            ['x', (object)[]],
        ];
    }
}

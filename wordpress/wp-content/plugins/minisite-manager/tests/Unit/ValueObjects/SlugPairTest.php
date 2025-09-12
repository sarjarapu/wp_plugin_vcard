<?php
declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use Minisite\Domain\ValueObjects\SlugPair;
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
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use Minisite\Domain\Services\MinisiteIdGenerator;
use PHPUnit\Framework\TestCase;

final class MinisiteIdGeneratorTest extends TestCase
{
    public function testGenerate_Returns24CharLowercaseHexAndIsUnique(): void
    {
        $count = 500;
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $id = MinisiteIdGenerator::generate();
            $this->assertMatchesRegularExpression('/^[a-f0-9]{24}$/', $id, 'ID must be 24 lowercase hex chars');
            $ids[] = $id;
        }
        $this->assertSame($count, count(array_unique($ids)), 'IDs should be unique across many generations');
    }

    public function testGenerateTempSlug_UsesDraftPrefixAndFirst8Chars(): void
    {
        $id = MinisiteIdGenerator::generate();
        $slug = MinisiteIdGenerator::generateTempSlug($id);

        $this->assertStringStartsWith('draft-', $slug);
        $this->assertSame('draft-' . substr($id, 0, 8), $slug);
        $this->assertMatchesRegularExpression('/^draft-[a-f0-9]{8}$/', $slug);
    }

    public function testGenerateTempSlug_WhenIdShorterThan8_UsesAvailableChars(): void
    {
        $shortId = 'abc123';
        $slug = MinisiteIdGenerator::generateTempSlug($shortId);
        $this->assertSame('draft-abc123', $slug);
    }
}

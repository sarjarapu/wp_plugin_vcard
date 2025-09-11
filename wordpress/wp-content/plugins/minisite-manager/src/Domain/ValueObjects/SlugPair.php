<?php
namespace Minisite\Domain\ValueObjects;

final class SlugPair
{
    public function __construct(
        public readonly string $business,
        public readonly string $location
    ) {
        if ($business === '' || $location === '') {
            throw new \InvalidArgumentException('Slugs cannot be empty.');
        }
    }

    public function asArray(): array { return ['business_slug' => $this->business, 'location_slug' => $this->location]; }
    public function full(): string { return "{$this->business}/{$this->location}"; }
}
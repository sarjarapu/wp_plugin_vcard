<?php

namespace Minisite\Features\MinisiteManagement\Domain\ValueObjects;

final class SlugPair
{
    public function __construct(
        public string $business,
        public ?string $location
    ) {
        // Trim inputs before validation
        $biz = trim($this->business);
        // Convert null to empty string; trim whitespace to allow empty-string location
        $loc = $this->location === null ? '' : trim($this->location);

        if ($biz === '') {
            throw new \InvalidArgumentException('Business slug must be a non-empty string.');
        }
        // Location may be empty string; no exception for empty after trim

        // Assign trimmed values after validation
        $this->business = $biz;
        $this->location = $loc;
    }

    public function asArray(): array
    {
        return array(
            'business_slug' => $this->business,
            'location_slug' => $this->location,
        );
    }
    public function full(): string
    {
        return $this->location !== '' ? "{$this->business}/{$this->location}" : $this->business;
    }
}


<?php
namespace Minisite\Domain\ValueObjects;

final class GeoPoint
{
    public function __construct(
        public readonly ?float $lat,
        public readonly ?float $lng
    ) {}
    public function isSet(): bool { return $this->lat !== null && $this->lng !== null; }
}
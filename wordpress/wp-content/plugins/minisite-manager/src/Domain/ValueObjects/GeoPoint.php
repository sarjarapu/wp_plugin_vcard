<?php

namespace Minisite\Domain\ValueObjects;

final class GeoPoint
{
    public function __construct(
        public readonly ?float $lat,
        public readonly ?float $lng
    ) {
        if ($this->lat !== null) {
            if (! is_finite($this->lat) || $this->lat < -90.0 || $this->lat > 90.0) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid latitude %s: must be a finite number between -90 and 90.', (string) $this->lat)
                );
            }
        }
        if ($this->lng !== null) {
            if (! is_finite($this->lng) || $this->lng < -180.0 || $this->lng > 180.0) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid longitude %s: must be a finite number between -180 and 180.', (string) $this->lng)
                );
            }
        }
    }

    public function isSet(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}

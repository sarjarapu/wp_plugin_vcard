<?php

namespace Minisite\Features\MinisiteManagement\Domain\ValueObjects;

final class GeoPoint
{
    private readonly ?float $lat;
    private readonly ?float $lng;

    public function __construct(?float $lat, ?float $lng)
    {
        if ($lat !== null) {
            if (! is_finite($lat) || $lat < -90.0 || $lat > 90.0) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Invalid latitude %s: must be a finite number between -90 and 90.',
                        // Note: Domain layer is framework-agnostic - no WordPress escaping functions here
                        // This is exception message text, not HTML output, so basic string casting is sufficient
                        (string) $lat
                    )
                );
            }
        }
        if ($lng !== null) {
            if (! is_finite($lng) || $lng < -180.0 || $lng > 180.0) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Invalid longitude %s: must be a finite number between -180 and 180.',
                        // Note: Domain layer is framework-agnostic - no WordPress escaping functions here
                        // This is exception message text, not HTML output, so basic string casting is sufficient
                        (string) $lng
                    )
                );
            }
        }

        $this->lat = $lat;
        $this->lng = $lng;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function getLng(): ?float
    {
        return $this->lng;
    }

    public function isSet(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}


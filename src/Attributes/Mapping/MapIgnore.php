<?php

declare(strict_types=1);

namespace Jengo\Base\Attributes\Mapping;

use Attribute;

/**
 * Exclude a property from mapping.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MapIgnore
{
    public const DIRECTION_BOTH = 'both';
    public const DIRECTION_TO_TARGET = 'to_target';
    public const DIRECTION_TO_SOURCE = 'to_source';

    public function __construct(
        public string $direction = self::DIRECTION_BOTH,
    ) {
    }

    public function ignoresToTarget(): bool
    {
        return $this->direction === self::DIRECTION_BOTH || $this->direction === self::DIRECTION_TO_TARGET;
    }

    public function ignoresToSource(): bool
    {
        return $this->direction === self::DIRECTION_BOTH || $this->direction === self::DIRECTION_TO_SOURCE;
    }
}

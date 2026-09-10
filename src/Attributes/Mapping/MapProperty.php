<?php

declare(strict_types=1);

namespace Jengo\Base\Attributes\Mapping;

use Attribute;

/**
 * Declare property mapping at the class level.
 * Useful for CI4 entities that rely on dynamic $attributes without typed PHP properties.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class MapProperty
{
    public function __construct(
        public string $target,
        public string $source,
        public ?string $transformer = null,
    ) {
    }
}

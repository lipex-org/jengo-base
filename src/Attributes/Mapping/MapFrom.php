<?php

declare(strict_types=1);

namespace Jengo\Base\Attributes\Mapping;

use Attribute;

/**
 * Specify the source property or column name to populate this target property from.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class MapFrom
{
    public function __construct(
        public string $sourceKey,
        public ?string $transformer = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Attributes\Mapping;

use Attribute;

/**
 * Specify the destination key or column name when exporting or syncing back to a source.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class MapTo
{
    public function __construct(
        public string $targetKey,
        public ?string $transformer = null,
    ) {
    }
}

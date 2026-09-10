<?php

declare(strict_types=1);

namespace Jengo\Base\Attributes\Mapping;

use Attribute;

/**
 * Specify the default source class that this entity maps from and syncs back to.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class MapSource
{
    /**
     * @param class-string $sourceClass
     */
    public function __construct(
        public string $sourceClass,
    ) {
    }
}

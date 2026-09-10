<?php

declare(strict_types=1);

namespace Jengo\Base\Attributes\Mapping;

use Attribute;

/**
 * Specify the default target class that this source entity maps into.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class MapTarget
{
    /**
     * @param class-string $targetClass
     */
    public function __construct(
        public string $targetClass,
    ) {
    }
}

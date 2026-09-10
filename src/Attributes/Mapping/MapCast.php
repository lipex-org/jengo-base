<?php

declare(strict_types=1);

namespace Jengo\Base\Attributes\Mapping;

use Attribute;

/**
 * Declare a property should be automatically converted to another entity or DTO class.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MapCast
{
    public function __construct(
        public string $targetClass,
        public bool $isCollection = false,
    ) {
    }
}

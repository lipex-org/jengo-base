<?php

declare(strict_types=1);

namespace Jengo\Base\Attributes\Mapping;

use Attribute;

/**
 * Specify a custom transformer class (implementing ValueTransformerInterface) for a property.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MapWith
{
    /**
     * @param class-string $transformerClass
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $transformerClass,
        public array $params = [],
    ) {
    }
}

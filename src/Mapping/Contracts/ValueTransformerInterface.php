<?php

declare(strict_types=1);

namespace Jengo\Base\Mapping\Contracts;

interface ValueTransformerInterface
{
    /**
     * Transform a value from the source format to the target format.
     */
    public function transform(mixed $value, string $sourceKey, object|array $source): mixed;

    /**
     * Reverse-transform a value from the target format back to the source format.
     */
    public function reverse(mixed $value, string $targetKey, object|array $target): mixed;
}

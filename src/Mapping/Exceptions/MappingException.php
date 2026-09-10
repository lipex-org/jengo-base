<?php

declare(strict_types=1);

namespace Jengo\Base\Mapping\Exceptions;

use RuntimeException;

class MappingException extends RuntimeException
{
    public static function forNonExistentTarget(string $targetClass): self
    {
        return new self("Target mapping class '{$targetClass}' does not exist.");
    }

    public static function forUnsupportedSource(mixed $source): self
    {
        $type = is_object($source) ? $source::class : gettype($source);
        return new self("Cannot map from unsupported source type: '{$type}'. Expected array, object, or Entity.");
    }

    public static function forMissingOriginContext(): self
    {
        return new self("Cannot sync or reconstruct origin: entity does not contain a recorded MappingContext or source reference.");
    }

    public static function forInvalidTransformer(string $transformerClass): self
    {
        return new self("Transformer class '{$transformerClass}' must implement Jengo\\Base\\Mapping\\Contracts\\ValueTransformerInterface or be callable.");
    }
}

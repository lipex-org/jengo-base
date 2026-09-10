<?php

declare(strict_types=1);

namespace Jengo\Base\Mapping\Metadata;

use Jengo\Base\Mapping\Contracts\ValueTransformerInterface;

class ClassMappingMetadata
{
    /**
     * @param class-string $className
     * @param array<string, string> $fieldMap [targetKey => sourceKey]
     * @param array<string, string> $reverseFieldMap [sourceKey => targetKey]
     * @param array<string, array{targetClass: string, isCollection: bool}> $casts [targetKey => info]
     * @param array<string, ValueTransformerInterface|callable> $transformers [targetKey => transformer]
     * @param array<string, bool> $ignoredToTarget [key => true]
     * @param array<string, bool> $ignoredToSource [key => true]
     * @param array<string, bool> $propertyNames [propName => true]
     */
    public function __construct(
        public string $className,
        public array $fieldMap = [],
        public array $reverseFieldMap = [],
        public array $casts = [],
        public array $transformers = [],
        public array $ignoredToTarget = [],
        public array $ignoredToSource = [],
        public ?string $defaultSourceClass = null,
        public ?string $defaultTargetClass = null,
        public bool $isCI4Entity = false,
        public array $propertyNames = [],
    ) {
    }

    /**
     * Resolve the source key for a target property.
     */
    public function getSourceKey(string $targetKey): string
    {
        return $this->fieldMap[$targetKey] ?? $targetKey;
    }

    /**
     * Resolve the target key for a source key.
     */
    public function getTargetKey(string $sourceKey): string
    {
        return $this->reverseFieldMap[$sourceKey] ?? $sourceKey;
    }

    /**
     * Determine if a property is ignored when mapping to target.
     */
    public function isIgnoredToTarget(string $key): bool
    {
        return isset($this->ignoredToTarget[$key]);
    }

    /**
     * Determine if a property is ignored when syncing back to source.
     */
    public function isIgnoredToSource(string $key): bool
    {
        return isset($this->ignoredToSource[$key]);
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Mapping;

class MappingContext
{
    /**
     * @param class-string|null $sourceClass
     * @param array<string, string> $fieldMap [targetKey => sourceKey]
     * @param array<string, string> $reverseFieldMap [sourceKey => targetKey]
     * @param array<string, mixed> $originalValues
     */
    public function __construct(
        public ?string $sourceClass = null,
        public bool $sourceIsArray = false,
        public mixed $sourceInstance = null,
        public array $fieldMap = [],
        public array $reverseFieldMap = [],
        public array $originalValues = [],
    ) {
    }
}

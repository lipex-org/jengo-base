<?php

declare(strict_types=1);

namespace Jengo\Base\Mapping\Contracts;

use Jengo\Base\Mapping\MappingContext;

interface MappableInterface
{
    /**
     * Map a source (entity, array, or object) to a new instance of this class.
     *
     * @param array<string, mixed> $options
     */
    public static function from(mixed $source, array $options = []): static;

    /**
     * Map a collection of sources to an array of instances of this class.
     *
     * @param array<string, mixed> $options
     * @return array<static>
     */
    public static function collect(iterable $sources, array $options = []): array;

    /**
     * Synchronize changes on this instance back to the original source.
     *
     * @param object|array|null $source Source to sync back to; if null, syncs to tracked origin.
     * @param bool $onlyChanged Only sync attributes that have been modified.
     */
    public function syncTo(object|array|null &$source = null, bool $onlyChanged = false): object|array;

    /**
     * Reconstruct the original source entity or array with reversed mappings.
     */
    public function toOriginal(?string $sourceClass = null): object|array;

    /**
     * Retrieve the active mapping context, if any.
     */
    public function getMappingContext(): ?MappingContext;

    /**
     * Assign the mapping context.
     */
    public function setMappingContext(MappingContext $context): static;

    /**
     * Retrieve the recorded origin source instance, if available.
     */
    public function getSource(): mixed;
}

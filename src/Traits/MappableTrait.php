<?php

declare(strict_types=1);

namespace Jengo\Base\Traits;

use Jengo\Base\Mapping\Mapper;
use Jengo\Base\Mapping\MappingContext;

trait MappableTrait
{
    /**
     * Active mapping context recording origin and field bindings.
     */
    protected ?MappingContext $mappingContext = null;

    /**
     * Map a source (entity, array, or object) to a new instance of this class.
     *
     * @param array<string, mixed> $options
     */
    public static function from(mixed $source, array $options = []): static
    {
        /** @var static */
        return Mapper::map($source, static::class, $options);
    }

    /**
     * Map a collection of sources to an array of instances of this class.
     *
     * @param array<string, mixed> $options
     * @return array<static>
     */
    public static function collect(iterable $sources, array $options = []): array
    {
        /** @var array<static> */
        return Mapper::collect($sources, static::class, $options);
    }

    /**
     * Synchronize modified attributes on this instance back to the original source.
     *
     * @param object|array|null $source Source to sync back to; if null, syncs to tracked origin.
     * @param bool $onlyChanged Only sync attributes that have been modified.
     */
    public function syncTo(object|array|null &$source = null, bool $onlyChanged = false): object|array
    {
        return Mapper::sync($this, $source, ['only_changed' => $onlyChanged]);
    }

    /**
     * Reconstruct the original source entity or array with reversed mappings.
     */
    public function toOriginal(?string $sourceClass = null): object|array
    {
        return Mapper::toOriginal($this, $sourceClass);
    }

    /**
     * Retrieve the active mapping context, if any.
     */
    public function getMappingContext(): ?MappingContext
    {
        return $this->mappingContext;
    }

    /**
     * Assign the mapping context.
     */
    public function setMappingContext(MappingContext $context): static
    {
        $this->mappingContext = $context;
        return $this;
    }

    /**
     * Retrieve the recorded origin source instance, if available.
     */
    public function getSource(): mixed
    {
        return $this->mappingContext?->sourceInstance;
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Mapping;

class PendingMapping
{
    private array $options = [];

    public function __construct(
        private mixed $source
    ) {
    }

    /**
     * Pass additional extra attributes to merge during hydration.
     *
     * @param array<string, mixed> $attributes
     */
    public function with(array $attributes): self
    {
        $this->options['with'] = array_merge($this->options['with'] ?? [], $attributes);
        return $this;
    }

    /**
     * Restrict mapped attributes to only the specified keys.
     *
     * @param array<int, string> $keys
     */
    public function only(array $keys): self
    {
        $this->options['only'] = $keys;
        return $this;
    }

    /**
     * Exclude specified keys from the mapped output.
     *
     * @param array<int, string> $keys
     */
    public function except(array $keys): self
    {
        $this->options['except'] = $keys;
        return $this;
    }

    /**
     * Set whether the mapped entity should be marked pristine (clean original attributes).
     */
    public function pristine(bool $pristine = true): self
    {
        $this->options['pristine'] = $pristine;
        return $this;
    }

    /**
     * Map the source to a new instance of the specified target class.
     *
     * @template T of object
     * @param class-string<T> $targetClass
     * @return T
     */
    public function to(string $targetClass): object
    {
        return Mapper::map($this->source, $targetClass, $this->options);
    }

    /**
     * Map the source into an existing target instance.
     *
     * @template T of object
     * @param T $targetInstance
     * @return T
     */
    public function into(object $targetInstance): object
    {
        return Mapper::map($this->source, $targetInstance, $this->options);
    }

    /**
     * Map a collection source to an array of target class instances.
     *
     * @template T of object
     * @param class-string<T> $targetClass
     * @return array<T>
     */
    public function collectTo(string $targetClass): array
    {
        return Mapper::collect($this->source, $targetClass, $this->options);
    }
}

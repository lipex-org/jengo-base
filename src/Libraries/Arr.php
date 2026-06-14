<?php

declare(strict_types=1);

namespace Jengo\Base\Libraries;

use CodeIgniter\Entity\Entity;
use JsonSerializable;

/**
 * Class Arr
 *
 * A fluent wrapper for PHP array operations.
 *
 * @package Jengo\Base\Libraries
 */
class Arr implements JsonSerializable
{
    /**
     * The underlying array data.
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Static entry point to create a new instance.
     *
     * @param array|Entity $array
     * @return static
     */
    public static function set(array|Entity $array): static
    {
        $static = new static();

        if ($array instanceof Entity) {
            $array = $array->toArray();
        }

        $static->data = $array;

        return $static;
    }

    /**
     * Return the final array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Convert the array to its JSON representation.
     *
     * @param int $flags
     * @return string
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->data, $flags);
    }

    /**
     * Get the number of elements in the array.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Alias for count().
     *
     * @return int
     */
    public function length(): int
    {
        return $this->count();
    }

    /**
     * Get the array keys.
     *
     * @return static
     */
    public function keys(): static
    {
        $this->data = array_keys($this->data);

        return $this;
    }

    /**
     * Checks whether the array is associative or not.
     *
     * @return bool
     */
    public function isAssoc(): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return ! array_is_list($this->data);
    }

    /**
     * Get the array values.
     *
     * @return static
     */
    public function values(): static
    {
        $this->data = array_values($this->data);

        return $this;
    }

    /**
     * Get only specific keys from the array.
     *
     * @param array $keys
     * @return static
     */
    public function only(array $keys): static
    {
        $this->data = array_intersect_key($this->data, array_flip($keys));

        return $this;
    }

    /**
     * Exclude specific keys from the array.
     *
     * @param array $keys
     * @return static
     */
    public function except(array $keys): static
    {
        $this->data = array_diff_key($this->data, array_flip($keys));

        return $this;
    }

    /**
     * Map through the array and return a new instance.
     *
     * @param callable $callback
     * @return static
     */
    public function map(callable $callback): static
    {
        $result = [];

        foreach ($this->data as $key => $value) {
            $result[$key] = $callback($value, $key);
        }

        $this->data = $result;

        return $this;
    }

    /**
     * Filter the array using a callback.
     * Default removes falsy values.
     *
     * @param callable|null $callback
     * @param int $mode
     * @return static
     */
    public function filter(?callable $callback = null, int $mode = 0): static
    {
        $this->data = array_filter($this->data, $callback ?? fn ($v) => ! empty($v), $mode);

        return $this;
    }

    /**
     * Reduce the array to a single value.
     *
     * @param callable $callback
     * @param mixed|null $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->data, $callback, $initial);
    }

    /**
     * Iterate over each item (for side effects, doesn't change the array).
     *
     * @param callable $callback
     * @return static
     */
    public function each(callable $callback): static
    {
        foreach ($this->data as $key => $value) {
            $callback($value, $key);
        }

        return $this;
    }

    /**
     * Add more items to the array.
     *
     * @param array $arr
     * @return static
     */
    public function with(array $arr): static
    {
        $this->data = [...$this->data, ...$arr];

        return $this;
    }

    /**
     * Tap allows inspecting/modifying the array within a chain.
     *
     * @param callable $callback
     * @return static
     */
    public function tap(callable $callback): static
    {
        $callback($this->data);

        return $this;
    }

    /**
     * Merge another array into the current one.
     *
     * @param array $array
     * @return static
     */
    public function merge(array $array): static
    {
        $this->data = array_merge($this->data, $array);

        return $this;
    }

    /**
     * Flatten a multi-dimensional array.
     *
     * @param int|float $depth
     * @return static
     */
    public function flatten($depth = INF): static
    {
        $this->data = $this->flattenArray($this->data, (int) $depth);

        return $this;
    }

    /**
     * Internal helper for flattening.
     *
     * @param array $array
     * @param int $depth
     * @param int $level
     * @return array
     */
    protected function flattenArray(array $array, int $depth, int $level = 0): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item) && $level < $depth) {
                $result = array_merge($result, $this->flattenArray($item, $depth, $level + 1));
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Group items by key or callback.
     *
     * @param string|callable $groupBy
     * @return static
     */
    public function groupBy(string|callable $groupBy): static
    {
        $result = [];

        foreach ($this->data as $item) {
            $key = is_callable($groupBy)
                ? $groupBy($item)
                : ($item[$groupBy] ?? null);

            $result[$key][] = $item;
        }

        $this->data = $result;

        return $this;
    }

    /**
     * Unset a key from the array.
     *
     * @param string|int $index
     * @return static
     */
    public function unset(string|int $index): static
    {
        unset($this->data[$index]);

        return $this;
    }

    /**
     * Check if the array is empty.
     *
     * @return bool
     */
    public function empty(): bool
    {
        return empty($this->data);
    }

    /**
     * Alias for empty().
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->empty();
    }

    /**
     * Check if the array is not empty.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the last key of the array.
     *
     * @return string|int|null
     */
    public function lastKey(): string|int|null
    {
        if ($this->isEmpty()) {
            return null;
        }

        return array_key_last($this->data);
    }

    /**
     * Get the first key of the array.
     *
     * @return string|int|null
     */
    public function firstKey(): string|int|null
    {
        if ($this->isEmpty()) {
            return null;
        }

        return array_key_first($this->data);
    }

    /**
     * Unset the last item in the array.
     *
     * @return static
     */
    public function unsetLast(): static
    {
        $key = $this->lastKey();

        if ($key === null) {
            return $this;
        }

        return $this->unset($key);
    }

    /**
     * Unset the first item in the array.
     *
     * @return static
     */
    public function unsetFirst(): static
    {
        $key = $this->firstKey();

        if ($key === null) {
            return $this;
        }

        return $this->unset($key);
    }

    /**
     * Get unique values (optionally by callback or key).
     *
     * @param string|callable|null $by
     * @return static
     */
    public function unique(string|callable|null $by = null): static
    {
        $seen   = [];
        $result = [];

        foreach ($this->data as $item) {
            $value = $by
                ? (is_callable($by) ? $by($item) : ($item[$by] ?? null))
                : $item;

            if (! in_array($value, $seen, true)) {
                $seen[]   = $value;
                $result[] = $item;
            }
        }

        $this->data = $result;

        return $this;
    }

    /**
     * Pluck a single field from array of arrays.
     *
     * @param string $key
     * @return static
     */
    public function pluck(string $key): static
    {
        $this->data = array_map(fn ($item) => $item[$key] ?? null, $this->data);

        return $this;
    }

    /**
     * Sort array by callback or value.
     *
     * @param callable|null $callback
     * @return static
     */
    public function sort(?callable $callback = null): static
    {
        if ($callback !== null) {
            uasort($this->data, $callback);
        } else {
            asort($this->data);
        }

        return $this;
    }

    /**
     * Reverse the array.
     *
     * @param bool $preserveKeys
     * @return static
     */
    public function reverse(bool $preserveKeys = true): static
    {
        $this->data = array_reverse($this->data, $preserveKeys);

        return $this;
    }

    /**
     * Reindex the array numerically.
     *
     * @return static
     */
    public function reindex(): static
    {
        $this->data = array_values($this->data);

        return $this;
    }

    /**
     * Check if a key exists in the array.
     *
     * @param string|int $key
     * @return bool
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get a key's value with optional default.
     *
     * @param string|int $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set a value for a specific key.
     *
     * @param string|int $key
     * @param mixed $value
     * @return static
     */
    public function setKey($key, $value): static
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Get the first item in the array.
     *
     * @return mixed
     */
    public function first()
    {
        return reset($this->data);
    }

    /**
     * Get the last item in the array.
     *
     * @return mixed
     */
    public function last()
    {
        return end($this->data);
    }

    /**
     * Chunk the array into sizes.
     *
     * @param int $size
     * @param bool $preserveKeys
     * @return static
     */
    public function chunk(int $size, bool $preserveKeys = false): static
    {
        $this->data = array_chunk($this->data, $size, $preserveKeys);

        return $this;
    }

    /**
     * Collapse an array of arrays into a single array.
     *
     * @return static
     */
    public function collapse(): static
    {
        $results = [];

        foreach ($this->data as $values) {
            if (! is_array($values)) {
                continue;
            }

            $results = array_merge($results, $values);
        }

        $this->data = $results;

        return $this;
    }

    /**
     * Diff the array with another array.
     *
     * @param array $array
     * @return static
     */
    public function diff(array $array): static
    {
        $this->data = array_diff($this->data, $array);

        return $this;
    }

    /**
     * Intersect the array with another array.
     *
     * @param array $array
     * @return static
     */
    public function intersect(array $array): static
    {
        $this->data = array_intersect($this->data, $array);

        return $this;
    }

    /**
     * Get the sum of the array values.
     *
     * @return int|float
     */
    public function sum()
    {
        return array_sum($this->data);
    }

    /**
     * Get the average of the array values.
     *
     * @return int|float
     */
    public function avg()
    {
        $count = $this->count();

        if ($count === 0) {
            return 0;
        }

        return $this->sum() / $count;
    }

    /**
     * Get the minimum value.
     *
     * @return mixed
     */
    public function min()
    {
        return $this->isEmpty() ? null : min($this->data);
    }

    /**
     * Get the maximum value.
     *
     * @return mixed
     */
    public function max()
    {
        return $this->isEmpty() ? null : max($this->data);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Libraries;

use CodeIgniter\Entity\Entity;

class Arr
{
    protected array $data = [];

    /**
     * Static entry point.
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
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Convert to JSON.
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->data, $flags);
    }

    /**
     * Get the number of elements.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Get array keys.
     */
    public function keys(): static
    {
        $this->data = array_keys($this->data);
        return $this;
    }

    /**
     * Get array values.
     */
    public function values(): static
    {
        $this->data = array_values($this->data);
        return $this;
    }

    /**
     * Get only specific keys.
     */
    public function only(array $keys): static
    {
        $this->data = array_intersect_key($this->data, array_flip($keys));
        return $this;
    }

    /**
     * Exclude specific keys.
     */
    public function except(array $keys): static
    {
        $this->data = array_diff_key($this->data, array_flip($keys));
        return $this;
    }

    /**
     * Map through the array.
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
     * Filter array (default removes falsy values).
     */
    public function filter(?callable $callback = null, int $mode = 0): static
    {
        $this->data = array_filter($this->data, $callback ?? fn($v) => !empty($v), $mode);
        return $this;
    }

    /**
     * Reduce array.
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->data, $callback, $initial);
    }

    /**
     * Each (for side effects, doesn’t change array).
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
     */
    public function with(array $arr): static
    {
        $this->data = [...$this->data, ...$arr];

        return $this;
    }

    /**
     * Tap — allows inspecting/modifying array within chain.
     */
    public function tap(callable $callback): static
    {
        $callback($this->data);
        return $this;
    }

    /**
     * Merge another array.
     */
    public function merge(array $array): static
    {
        $this->data = array_merge($this->data, $array);
        return $this;
    }

    /**
     * Flatten a multi-dimensional array.
     */
    public function flatten(int $depth = INF): static
    {
        $this->data = $this->flattenArray($this->data, $depth);
        return $this;
    }

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
     * Get unique values (optionally by callback or key).
     */
    public function unique(string|callable|null $by = null): static
    {
        $seen = [];
        $result = [];

        foreach ($this->data as $item) {
            $value = $by
                ? (is_callable($by) ? $by($item) : ($item[$by] ?? null))
                : $item;

            if (!in_array($value, $seen, true)) {
                $seen[] = $value;
                $result[] = $item;
            }
        }

        $this->data = $result;
        return $this;
    }

    /**
     * Pluck a single field from array of arrays.
     */
    public function pluck(string $key): static
    {
        $this->data = array_map(fn($item) => $item[$key] ?? null, $this->data);
        return $this;
    }

    /**
     * Sort array by callback or value.
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
     * Reverse array.
     */
    public function reverse(bool $preserveKeys = true): static
    {
        $this->data = array_reverse($this->data, $preserveKeys);
        return $this;
    }

    /**
     * Reindex array numerically.
     */
    public function reindex(): static
    {
        $this->data = array_values($this->data);
        return $this;
    }

    /**
     * Check if key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get a key’s value with optional default.
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set a value for a key.
     */
    public function setKey(string $key, $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }
}

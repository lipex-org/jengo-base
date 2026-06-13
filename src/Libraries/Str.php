<?php

declare(strict_types=1);

namespace Jengo\Base\Libraries;

use Jengo\Base\Libraries\RandomGenerator;

/**
 * Agnostic String Manager Library.
 * Handles common string operations in a chainable manner.
 */
class Str
{
    protected string $value;

    public function __construct(string $value = '')
    {
        $this->value = $value;
    }

    /**
     * Static entry point.
     */
    public static function set(string $value): static
    {
        return new static($value);
    }

    /**
     * Generate a random, secure string of a given length.
     */
    public static function random(int $length = 16): string
    {
        return (new RandomGenerator())->alphanumeric($length);
    }

    /**
     * Returns the string value.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Returns the string value when used as a string.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Convert to lowercase.
     */
    public function lower(): static
    {
        $this->value = mb_strtolower($this->value, 'UTF-8');
        return $this;
    }

    /**
     * Convert to uppercase.
     */
    public function upper(): static
    {
        $this->value = mb_strtoupper($this->value, 'UTF-8');
        return $this;
    }

    /**
     * Capitalize the first character.
     */
    public function ucfirst(): static
    {
        $this->value = mb_strtoupper(mb_substr($this->value, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($this->value, 1, null, 'UTF-8');
        return $this;
    }

    /**
     * Convert to kebab-case.
     */
    public function kebab(): static
    {
        $this->value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $this->value);
        $this->value = preg_replace('/([a-z])([A-Z])/', '$1-$2', $this->value);
        $this->value = mb_strtolower($this->value, 'UTF-8');
        $this->value = trim($this->value, '-');
        return $this;
    }

    /**
     * Convert to snake_case.
     */
    public function snake(): static
    {
        $this->value = preg_replace('/[^\p{L}\p{N}]+/u', '_', $this->value);
        $this->value = preg_replace('/([a-z])([A-Z])/', '$1_$2', $this->value);
        $this->value = mb_strtolower($this->value, 'UTF-8');
        $this->value = trim($this->value, '_');
        return $this;
    }

    /**
     * Convert to camelCase.
     */
    public function camel(): static
    {
        $this->studly();
        $this->value = lcfirst($this->value);
        return $this;
    }

    /**
     * Convert to Headline.
     */
    public function headline(): static
    {
        $parts = explode(' ', $this->snake()->replace('_', ' ')->toString());

        if (count($parts) > 1) {
            $this->value = implode(' ', array_map('ucfirst', $parts));
        } else {
            $this->value = ucfirst($parts[0]);
        }

        return $this;
    }

    /**
     * Determine if a given string matches a given pattern.
     */
    public function is(string|array $patterns): bool
    {
        $patterns = (array) $patterns;

        foreach ($patterns as $pattern) {
            $pattern = (string) $pattern;

            if ($pattern === $this->value) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');
            $pattern = str_replace('\*', '.*', $pattern);

            if (preg_match('#^' . $pattern . '\z#u', $this->value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask a portion of a string with a repeated character.
     */
    public function mask(string $character, int $index, ?int $length = null): static
    {
        if ($character === '') {
            return $this;
        }

        $segment = mb_substr($this->value, $index, $length, 'UTF-8');

        if ($segment === '') {
            return $this;
        }

        $strlen = mb_strlen($this->value, 'UTF-8');
        $startIndex = $index;

        if ($index < 0) {
            $startIndex = max(0, $strlen + $index);
        }

        $start = mb_substr($this->value, 0, $startIndex, 'UTF-8');
        $segmentLen = mb_strlen($segment, 'UTF-8');
        $end = mb_substr($this->value, $startIndex + $segmentLen, null, 'UTF-8');

        $this->value = $start . str_repeat(mb_substr($character, 0, 1, 'UTF-8'), $segmentLen) . $end;

        return $this;
    }

    /**
     * Wrap the string with the given strings.
     */
    public function wrap(string $before, ?string $after = null): static
    {
        $this->value = $before . $this->value . ($after ?? $before);
        return $this;
    }

    /**
     * Unwrap the string with the given strings.
     */
    public function unwrap(string $before, ?string $after = null): static
    {
        $after ??= $before;

        if ($this->startsWith($before)) {
            $this->value = mb_substr($this->value, mb_strlen($before, 'UTF-8'), null, 'UTF-8');
        }

        if ($this->endsWith($after)) {
            $this->value = mb_substr($this->value, 0, -mb_strlen($after, 'UTF-8'), 'UTF-8');
        }

        return $this;
    }

    /**
     * Get the portion of a string before the first occurrence of a given value.
     */
    public function before(string $search): static
    {
        if ($search === '') {
            return $this;
        }

        $result = mb_strstr($this->value, $search, true, 'UTF-8');

        if ($result !== false) {
            $this->value = $result;
        }

        return $this;
    }

    /**
     * Get the portion of a string after the first occurrence of a given value.
     */
    public function after(string $search): static
    {
        if ($search === '') {
            return $this;
        }

        $result = mb_strstr($this->value, $search, false, 'UTF-8');

        if ($result !== false) {
            $this->value = mb_substr($result, mb_strlen($search, 'UTF-8'), null, 'UTF-8');
        }

        return $this;
    }

    /**
     * Get the portion of a string before the last occurrence of a given value.
     */
    public function beforeLast(string $search): static
    {
        if ($search === '') {
            return $this;
        }

        $pos = mb_strrpos($this->value, $search, 0, 'UTF-8');

        if ($pos !== false) {
            $this->value = mb_substr($this->value, 0, $pos, 'UTF-8');
        }

        return $this;
    }

    /**
     * Get the portion of a string after the last occurrence of a given value.
     */
    public function afterLast(string $search): static
    {
        if ($search === '') {
            return $this;
        }

        $pos = mb_strrpos($this->value, $search, 0, 'UTF-8');

        if ($pos !== false) {
            $this->value = mb_substr($this->value, $pos + mb_strlen($search, 'UTF-8'), null, 'UTF-8');
        }

        return $this;
    }

    /**
     * Convert to StudlyCase.
     */
    public function studly(): static
    {
        $this->value = str_replace(['-', '_'], ' ', $this->value);
        $this->value = ucwords($this->value);
        $this->value = str_replace(' ', '', $this->value);
        return $this;
    }

    /**
     * Convert to Title Case.
     */
    public function title(): static
    {
        $this->value = mb_convert_case($this->value, MB_CASE_TITLE, 'UTF-8');
        return $this;
    }

    /**
     * Convert to a URL friendly slug.
     */
    public function slug(string $separator = '-'): static
    {
        $this->value = mb_strtolower($this->value, 'UTF-8');
        $this->value = preg_replace('/[^\p{L}\p{N}]+/u', $separator, $this->value);
        $this->value = trim($this->value, $separator);
        return $this;
    }

    /**
     * Append strings to the current string.
     */
    public function append(string ...$values): static
    {
        $this->value .= implode('', $values);
        return $this;
    }

    /**
     * Prepend strings to the current string.
     */
    public function prepend(string ...$values): static
    {
        $this->value = implode('', $values) . $this->value;
        return $this;
    }

    /**
     * Truncate the string to a certain length.
     */
    public function limit(int $length = 100, string $end = '...'): static
    {
        if (mb_strlen($this->value, 'UTF-8') <= $length) {
            return $this;
        }

        $this->value = mb_substr($this->value, 0, $length, 'UTF-8') . $end;
        return $this;
    }

    /**
     * Truncate the string to a certain number of words.
     */
    public function words(int $words = 100, string $end = '...'): static
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $this->value, $matches);

        if (!isset($matches[0]) || mb_strlen($this->value, 'UTF-8') === mb_strlen($matches[0], 'UTF-8')) {
            return $this;
        }

        $this->value = rtrim($matches[0]) . $end;
        return $this;
    }

    /**
     * Remove all extra whitespace from a string.
     */
    public function squish(): static
    {
        $this->value = preg_replace('~(?:\s| | | | | | | | | | | | | | | | |　)+~u', ' ', trim($this->value));
        return $this;
    }

    /**
     * Cap a string with a single instance of a given value.
     */
    public function finish(string $cap): static
    {
        $quoted = preg_quote($cap, '/');

        $this->value = preg_replace('/(?:' . $quoted . ')+$/u', '', $this->value) . $cap;
        return $this;
    }

    /**
     * Begin a string with a single instance of a given value.
     */
    public function start(string $prefix): static
    {
        $quoted = preg_quote($prefix, '/');

        $this->value = $prefix . preg_replace('/^(?:' . $quoted . ')+/u', '', $this->value);
        return $this;
    }

    /**
     * Repeat the string.
     */
    public function repeat(int $times): static
    {
        $this->value = str_repeat($this->value, $times);
        return $this;
    }

    /**
     * Replace the first occurrence of a given value in the string.
     */
    public function replaceFirst(string $search, string $replace): static
    {
        if ($search === '') {
            return $this;
        }

        $position = mb_strpos($this->value, $search);

        if ($position !== false) {
            $this->value = mb_substr($this->value, 0, $position, 'UTF-8') . $replace . mb_substr($this->value, $position + mb_strlen($search, 'UTF-8'), null, 'UTF-8');
        }

        return $this;
    }

    /**
     * Replace the last occurrence of a given value in the string.
     */
    public function replaceLast(string $search, string $replace): static
    {
        if ($search === '') {
            return $this;
        }

        $position = mb_strrpos($this->value, $search);

        if ($position !== false) {
            $this->value = mb_substr($this->value, 0, $position, 'UTF-8') . $replace . mb_substr($this->value, $position + mb_strlen($search, 'UTF-8'), null, 'UTF-8');
        }

        return $this;
    }

    /**
     * Replace occurrences of a value.
     */
    public function replace(string|array $search, string|array $replace): static
    {
        $this->value = str_replace($search, $replace, $this->value);
        return $this;
    }

    /**
     * Check if string contains a value.
     */
    public function contains(string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && mb_strpos($this->value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string starts with a value.
     */
    public function startsWith(string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle !== '' && mb_strpos($this->value, (string) $needle) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string ends with a value.
     */
    public function endsWith(string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (
                $needle !== '' &&
                mb_substr($this->value, -mb_strlen($needle, 'UTF-8'), null, 'UTF-8') === (string) $needle
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trim the string.
     */
    public function trim(string $characters = " \n\r\t\v\x00"): static
    {
        $this->value = trim($this->value, $characters);
        return $this;
    }

    /**
     * Get the length of the string.
     */
    public function length(): int
    {
        return mb_strlen($this->value, 'UTF-8');
    }

    /**
     * Split the string into an array.
     */
    public function explode(string $delimiter): array
    {
        return explode($delimiter, $this->value);
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Entities;

use CodeIgniter\Entity\Entity;
use Jengo\Base\Entities\Casts\CastPgBoolean;
use Jengo\Base\Mapping\Contracts\MappableInterface;
use Jengo\Base\Traits\MacroableTrait;
use Jengo\Base\Traits\MappableTrait;

class BaseEntity extends Entity implements MappableInterface
{
    use MappableTrait;
    use MacroableTrait;

    protected $castHandlers = [
        'pg_bool' => CastPgBoolean::class,
    ];

    /**
     * Fields that should be visible in JSON serialization.
     * If not empty, only these fields will be serialized.
     */
    protected array $visible = [];

    /**
     * Fields that should be hidden from JSON serialization.
     */
    protected array $hidden = [];

    /**
     * Fields that should be obfuscated using Sqids.
     */
    protected array $obfuscatedFields = [];

    /**
     * Obfuscate an integer ID using Sqids.
     */
    protected function obfuscateValue(int $value): string
    {
        helper('\Jengo\Base\Helpers\jengo');

        return sqids_hash($value) ?? '';
    }

    /**
     * Decode an obfuscated string back to its integer ID.
     */
    protected function deobfuscateValue(string $value): ?int
    {
        helper('\Jengo\Base\Helpers\jengo');

        return sqids_unhash($value);
    }

    /**
     * Intercept setting properties to automatically decode obfuscated values.
     */
    public function __set(string $key, $value = null)
    {
        parent::__set($key, $value);
    }

    /**
     * Customize JSON serialization to support visible, hidden, and obfuscated fields,
     * while preserving and recursively executing jsonSerialize on nested entities and collections.
     */
    public function jsonSerialize(): array
    {
        $data = $this->toArray(false, true, false);

        // Filter visible fields
        if (!empty($this->visible)) {
            $data = array_intersect_key($data, array_flip($this->visible));
        } elseif (!empty($this->hidden)) {
            // Remove hidden fields
            foreach ($this->hidden as $key) {
                unset($data[$key]);
            }
        }

        // Apply obfuscation
        if (!empty($this->obfuscatedFields)) {
            foreach ($this->obfuscatedFields as $field) {
                if (array_key_exists($field, $data) && is_numeric($data[$field])) {
                    $data[$field] = $this->obfuscateValue((int) $data[$field]);
                }
            }
        }

        // Recursively serialize nested JsonSerializable/BaseEntity objects and collections
        foreach ($data as $key => $val) {
            if ($val instanceof \JsonSerializable) {
                $data[$key] = $val->jsonSerialize();
            } elseif (is_array($val)) {
                $data[$key] = $this->serializeNestedArray($val);
            }
        }

        return $data;
    }

    /**
     * Helper to recursively serialize nested arrays containing JsonSerializable items.
     */
    private function serializeNestedArray(array $items): array
    {
        $result = [];
        foreach ($items as $k => $item) {
            if ($item instanceof \JsonSerializable) {
                $result[$k] = $item->jsonSerialize();
            } elseif (is_array($item)) {
                $result[$k] = $this->serializeNestedArray($item);
            } else {
                $result[$k] = $item;
            }
        }

        return $result;
    }
}

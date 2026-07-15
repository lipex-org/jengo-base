<?php

declare(strict_types=1);

namespace Jengo\Base\Entities;

use CodeIgniter\Entity\Entity;
use Jengo\Base\Config\Sqids as SqidsConfig;
use Sqids\Sqids;

class BaseEntity extends Entity
{
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
        helper('jengo');

        return sqids_hash($value) ?? '';
    }

    /**
     * Decode an obfuscated string back to its integer ID.
     */
    protected function deobfuscateValue(string $value): ?int
    {
        helper('jengo');

        return sqids_unhash($value);
    }

    /**
     * Intercept setting properties to automatically decode obfuscated values.
     */
    public function __set(string $key, $value = null)
    {
        if (is_string($value) && in_array($key, $this->obfuscatedFields, true)) {
            $decoded = $this->deobfuscateValue($value);
            if ($decoded !== null) {
                $value = $decoded;
            }
        }

        parent::__set($key, $value);
    }

    /**
     * Customize JSON serialization to support visible, hidden, and obfuscated fields.
     */
    public function jsonSerialize(): array
    {
        $data = $this->toArray(false, true, true);

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

        return $data;
    }
}

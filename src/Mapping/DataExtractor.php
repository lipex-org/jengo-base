<?php

declare(strict_types=1);

namespace Jengo\Base\Mapping;

use CodeIgniter\Entity\Entity;
use Jengo\Base\Mapping\Exceptions\MappingException;
use JsonSerializable;
use ReflectionClass;
use ReflectionMethod;
use stdClass;

class DataExtractor
{
    /**
     * Extract attributes array from various source data types.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function extract(mixed $source, array $options = []): array
    {
        if (is_array($source)) {
            return $source;
        }

        if ($source instanceof Entity) {
            $data = !empty($options['use_cast']) ? $source->toArray() : $source->toRawArray();

            $ref = new ReflectionClass($source);
            foreach ($ref->getProperties() as $prop) {
                if ($prop->isStatic()) {
                    continue;
                }
                $name = $prop->getName();
                if (str_starts_with($name, '_') || in_array($name, [
                    'attributes', 'original', 'casts', 'datamap', 'dates',
                    'primaryKey', 'castHandlers', 'visible', 'hidden',
                    'obfuscatedFields', 'mappingContext'
                ], true)) {
                    continue;
                }
                if ($prop->isInitialized($source)) {
                    $val = $prop->getValue($source);
                    if ($val !== null || !array_key_exists($name, $data)) {
                        $data[$name] = $val;
                    }
                }
            }

            return $data;
        }

        if ($source instanceof stdClass) {
            return (array) $source;
        }

        if (is_object($source)) {
            if (method_exists($source, 'toRawArray')) {
                return $source->toRawArray();
            }

            if (method_exists($source, 'toArray')) {
                return $source->toArray();
            }

            if ($source instanceof JsonSerializable) {
                $serialized = $source->jsonSerialize();
                if (is_array($serialized)) {
                    return $serialized;
                }
            }

            return self::extractFromGenericObject($source);
        }

        throw MappingException::forUnsupportedSource($source);
    }

    /**
     * Extract properties from a generic object using getters and public properties.
     *
     * @return array<string, mixed>
     */
    private static function extractFromGenericObject(object $source): array
    {
        $data = get_object_vars($source);

        $ref = new ReflectionClass($source);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $name = $method->getName();
            if (str_starts_with($name, 'get') && strlen($name) > 3) {
                $key = lcfirst(substr($name, 3));
                $snakeKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key) ?? $key);
                $val = $method->invoke($source);
                $data[$key] = $val;
                $data[$snakeKey] = $val;
            } elseif (str_starts_with($name, 'is') && strlen($name) > 2) {
                $key = lcfirst(substr($name, 2));
                $snakeKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key) ?? $key);
                $val = $method->invoke($source);
                $data[$key] = $val;
                $data[$snakeKey] = $val;
            }
        }

        return $data;
    }
}

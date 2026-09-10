<?php

declare(strict_types=1);

namespace Jengo\Base\Mapping;

use CodeIgniter\Entity\Entity;
use Jengo\Base\Mapping\Contracts\MappableInterface;
use Jengo\Base\Mapping\Contracts\ValueTransformerInterface;
use Jengo\Base\Mapping\Metadata\ClassMappingMetadata;
use Jengo\Base\Mapping\Metadata\MetadataReader;
use ReflectionClass;

class DataHydrator
{
    /**
     * Hydrate a target class or existing instance from extracted source data.
     *
     * @param array<string, mixed> $data
     * @param class-string|object $target
     * @param array<string, mixed> $options
     */
    public static function hydrate(
        array $data,
        string|object $target,
        mixed $source,
        array $options = []
    ): object {
        $targetClass = is_object($target) ? $target::class : $target;
        $metadata = MetadataReader::for($targetClass);

        $hydrated = [];

        // 1. Initial 1:1 mapping from source data
        foreach ($data as $sourceKey => $val) {
            $targetKey = $metadata->reverseFieldMap[$sourceKey] ?? $sourceKey;

            if ($metadata->isIgnoredToTarget($targetKey)) {
                continue;
            }

            $hydrated[$targetKey] = $val;
        }

        // 2. Explicit target-to-source overrides from fieldMap
        foreach ($metadata->fieldMap as $targetKey => $sourceKey) {
            if ($metadata->isIgnoredToTarget($targetKey)) {
                continue;
            }

            if (array_key_exists($sourceKey, $data)) {
                $hydrated[$targetKey] = $data[$sourceKey];
            }
        }

        // 3. Process custom transformers and MapCast
        foreach ($hydrated as $targetKey => $val) {
            // Apply MapWith / transformer
            if (isset($metadata->transformers[$targetKey])) {
                $transformer = $metadata->transformers[$targetKey];
                $sourceKey = $metadata->getSourceKey($targetKey);

                if ($transformer instanceof ValueTransformerInterface) {
                    $hydrated[$targetKey] = $transformer->transform($val, $sourceKey, $source);
                } elseif (is_callable($transformer)) {
                    $hydrated[$targetKey] = $transformer($val, $sourceKey, $source);
                }
            }

            // Apply MapCast
            if (isset($metadata->casts[$targetKey]) && $val !== null) {
                $castInfo = $metadata->casts[$targetKey];
                $castTarget = $castInfo['targetClass'];

                if ($castInfo['isCollection']) {
                    $hydrated[$targetKey] = is_iterable($val) ? Mapper::collect($val, $castTarget) : [];
                } else {
                    $hydrated[$targetKey] = Mapper::map($val, $castTarget);
                }
            }
        }

        // 4. Handle options: only, except, with
        if (!empty($options['only']) && is_array($options['only'])) {
            $hydrated = array_intersect_key($hydrated, array_flip($options['only']));
        }

        if (!empty($options['except']) && is_array($options['except'])) {
            foreach ($options['except'] as $exceptKey) {
                unset($hydrated[$exceptKey]);
            }
        }

        if (!empty($options['with']) && is_array($options['with'])) {
            $hydrated = array_merge($hydrated, $options['with']);
        }

        // 5. Instantiate or populate target
        $instance = is_object($target) ? $target : new $targetClass();

        if ($instance instanceof Entity) {
            $instance->fill($hydrated);
            self::populateObjectProperties($instance, $hydrated);

            // By default, mark pristine unless explicitly requested otherwise
            $pristine = $options['pristine'] ?? true;
            if ($pristine) {
                $instance->syncOriginal();
            }
        } else {
            self::populateObjectProperties($instance, $hydrated);
        }

        // 6. Record mapping context
        $context = new MappingContext(
            sourceClass: is_object($source) ? $source::class : null,
            sourceIsArray: is_array($source),
            sourceInstance: $source,
            fieldMap: $metadata->fieldMap,
            reverseFieldMap: $metadata->reverseFieldMap,
            originalValues: $hydrated,
        );

        if ($instance instanceof MappableInterface || method_exists($instance, 'setMappingContext')) {
            $instance->setMappingContext($context);
        }

        return $instance;
    }

    /**
     * Populate standard object properties and declared entity properties.
     *
     * @param array<string, mixed> $data
     */
    private static function populateObjectProperties(object $instance, array $data): void
    {
        $ref = new ReflectionClass($instance);

        foreach ($data as $key => $val) {
            // Check setter method set<Key>()
            $setter = 'set' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key)));
            if ($ref->hasMethod($setter) && $ref->getMethod($setter)->isPublic()) {
                $instance->{$setter}($val);
                continue;
            }

            // Check property
            if ($ref->hasProperty($key)) {
                $prop = $ref->getProperty($key);
                if (!$prop->isStatic()) {
                    $prop->setValue($instance, $val);
                }
            } elseif (!($instance instanceof Entity)) {
                // Dynamic property fallback for plain stdClass/objects
                $instance->{$key} = $val;
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Mapping;

use CodeIgniter\Entity\Entity;
use Jengo\Base\Mapping\Contracts\MappableInterface;
use Jengo\Base\Mapping\Contracts\ValueTransformerInterface;
use Jengo\Base\Mapping\Exceptions\MappingException;
use Jengo\Base\Mapping\Metadata\MetadataReader;
use ReflectionClass;

class Mapper
{
    /**
     * Registered custom transformation callbacks indexed by "SourceClass->TargetClass".
     *
     * @var array<string, callable>
     */
    private static array $registry = [];

    /**
     * Registered named transformers.
     *
     * @var array<string, ValueTransformerInterface|callable>
     */
    private static array $namedTransformers = [];

    /**
     * Map a source data structure (Entity, array, or object) into a target class or object.
     *
     * @template T of object
     * @param mixed $source Source entity, array, or object
     * @param class-string<T>|T $target Target class name or existing instance
     * @param array<string, mixed> $options
     * @return T
     */
    public static function map(mixed $source, string|object $target, array $options = []): object
    {
        $targetClass = is_object($target) ? $target::class : $target;
        $sourceClass = is_object($source) ? $source::class : null;

        $extractedData = DataExtractor::extract($source, $options);

        // Check for registered custom profile
        $registryKey = $sourceClass ? "{$sourceClass}->{$targetClass}" : null;
        if ($registryKey !== null && isset(self::$registry[$registryKey])) {
            $instance = is_object($target) ? $target : new $targetClass();
            $customCallback = self::$registry[$registryKey];
            $customCallback($source, $instance, $extractedData);
            return $instance;
        }

        return DataHydrator::hydrate($extractedData, $target, $source, $options);
    }

    /**
     * Start a fluent mapping chain from the given source.
     */
    public static function from(mixed $source): PendingMapping
    {
        return new PendingMapping($source);
    }

    /**
     * Map an iterable collection of items to an array of target class instances.
     *
     * @template T of object
     * @param iterable<mixed> $sources
     * @param class-string<T> $targetClass
     * @param array<string, mixed> $options
     * @return array<T>
     */
    public static function collect(iterable $sources, string $targetClass, array $options = []): array
    {
        $result = [];
        foreach ($sources as $key => $source) {
            $result[$key] = self::map($source, $targetClass, $options);
        }
        return $result;
    }

    /**
     * Synchronize values from a mapped target back into a source entity or array.
     *
     * @param object $target The modified mapped entity
     * @param object|array|null $source The source to sync into; if null, syncs to recorded origin context
     * @param array<string, mixed> $options
     * @return object|array The updated source
     */
    public static function sync(object $target, object|array|null &$source = null, array $options = []): object|array
    {
        $context = ($target instanceof MappableInterface || method_exists($target, 'getMappingContext'))
            ? $target->getMappingContext()
            : null;

        if ($source === null) {
            if ($context !== null && $context->sourceInstance !== null) {
                $source = $context->sourceInstance;
            } else {
                throw MappingException::forMissingOriginContext();
            }
        }

        $targetClass = $target::class;
        $metadata = MetadataReader::for($targetClass);
        $onlyChanged = $options['only_changed'] ?? false;

        // Extract target data
        if ($target instanceof Entity) {
            $data = $onlyChanged ? $target->toArray(true) : $target->toRawArray();

            $ref = new ReflectionClass($target);
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

                if ($prop->isInitialized($target)) {
                    $val = $prop->getValue($target);
                    if ($onlyChanged) {
                        $origVal = $context?->originalValues[$name] ?? null;
                        if ($val !== $origVal) {
                            $data[$name] = $val;
                        }
                    } else {
                        if ($val !== null || !array_key_exists($name, $data)) {
                            $data[$name] = $val;
                        }
                    }
                }
            }
        } else {
            $data = DataExtractor::extract($target, $options);
            if ($onlyChanged && $context !== null) {
                $changed = [];
                foreach ($data as $k => $v) {
                    if ($v !== ($context->originalValues[$k] ?? null)) {
                        $changed[$k] = $v;
                    }
                }
                $data = $changed;
            }
        }

        foreach ($data as $targetKey => $val) {
            if ($metadata->isIgnoredToSource($targetKey)) {
                continue;
            }

            // Reverse transformer if defined
            if (isset($metadata->transformers[$targetKey])) {
                $transformer = $metadata->transformers[$targetKey];
                if ($transformer instanceof ValueTransformerInterface) {
                    $val = $transformer->reverse($val, $targetKey, $target);
                }
            }

            // Determine source key
            $sourceKey = $metadata->reverseFieldMap[$targetKey] ?? $metadata->getSourceKey($targetKey);

            // Populate source
            if (is_array($source)) {
                $source[$sourceKey] = $val;
            } elseif ($source instanceof Entity) {
                $source->__set($sourceKey, $val);
            } elseif (is_object($source)) {
                self::setObjectProperty($source, $sourceKey, $val);
            }
        }

        return $source;
    }

    /**
     * Reconstruct or export the target back into an instance of the original source class or array.
     *
     * @param object $target
     * @param class-string|null $sourceClass
     * @param array<string, mixed> $options
     * @return object|array
     */
    public static function toOriginal(object $target, ?string $sourceClass = null, array $options = []): object|array
    {
        $context = ($target instanceof MappableInterface || method_exists($target, 'getMappingContext'))
            ? $target->getMappingContext()
            : null;

        $metadata = MetadataReader::for($target::class);

        if ($sourceClass === null) {
            if ($metadata->defaultSourceClass !== null) {
                $sourceClass = $metadata->defaultSourceClass;
            } elseif ($context !== null) {
                if ($context->sourceIsArray) {
                    $arr = [];
                    return self::sync($target, $arr, $options);
                }
                $sourceClass = $context->sourceClass;
            }
        }

        if ($sourceClass === null) {
            throw MappingException::forMissingOriginContext();
        }

        $sourceInstance = new $sourceClass();
        return self::sync($target, $sourceInstance, $options);
    }

    /**
     * Register a custom mapping recipe between two classes.
     *
     * @param class-string $sourceClass
     * @param class-string $targetClass
     * @param callable $callback function(object|array $source, object $target, array $data): void
     */
    public static function register(string $sourceClass, string $targetClass, callable $callback): void
    {
        self::$registry["{$sourceClass}->{$targetClass}"] = $callback;
    }

    /**
     * Register a named value transformer.
     */
    public static function registerTransformer(string $name, ValueTransformerInterface|callable $transformer): void
    {
        self::$namedTransformers[$name] = $transformer;
    }

    /**
     * Get a named transformer.
     */
    public static function getTransformer(string $name): ValueTransformerInterface|callable|null
    {
        return self::$namedTransformers[$name] ?? null;
    }

    /**
     * Clear registered custom recipes and named transformers.
     */
    public static function clearRegistry(): void
    {
        self::$registry = [];
        self::$namedTransformers = [];
    }

    /**
     * Assign a property to a standard object using setter or reflection.
     */
    private static function setObjectProperty(object $source, string $key, mixed $val): void
    {
        $ref = new ReflectionClass($source);
        $setter = 'set' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key)));

        if ($ref->hasMethod($setter) && $ref->getMethod($setter)->isPublic()) {
            $source->{$setter}($val);
            return;
        }

        if ($ref->hasProperty($key)) {
            $prop = $ref->getProperty($key);
            if ($prop->isPublic()) {
                $source->{$key} = $val;
            } else {
                $prop->setValue($source, $val);
            }
            return;
        }

        $source->{$key} = $val;
    }
}

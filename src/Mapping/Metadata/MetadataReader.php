<?php

declare(strict_types=1);

namespace Jengo\Base\Mapping\Metadata;

use CodeIgniter\Entity\Entity;
use Jengo\Base\Attributes\Mapping\MapCast;
use Jengo\Base\Attributes\Mapping\MapFrom;
use Jengo\Base\Attributes\Mapping\MapIgnore;
use Jengo\Base\Attributes\Mapping\MapProperty;
use Jengo\Base\Attributes\Mapping\MapSource;
use Jengo\Base\Attributes\Mapping\MapTarget;
use Jengo\Base\Attributes\Mapping\MapTo;
use Jengo\Base\Attributes\Mapping\MapWith;
use Jengo\Base\Mapping\Contracts\ValueTransformerInterface;
use Jengo\Base\Mapping\Exceptions\MappingException;
use ReflectionClass;

class MetadataReader
{
    /**
     * Statically cached metadata indexed by class name.
     *
     * @var array<string, ClassMappingMetadata>
     */
    private static array $cache = [];

    /**
     * Read and cache mapping metadata for a class.
     *
     * @param class-string $className
     */
    public static function for(string $className): ClassMappingMetadata
    {
        if (isset(self::$cache[$className])) {
            return self::$cache[$className];
        }

        if (!class_exists($className)) {
            throw MappingException::forNonExistentTarget($className);
        }

        $ref = new ReflectionClass($className);
        $isCI4Entity = $ref->isSubclassOf(Entity::class);

        $fieldMap = [];
        $reverseFieldMap = [];
        $casts = [];
        $transformers = [];
        $ignoredToTarget = [];
        $ignoredToSource = [];
        $defaultSourceClass = null;
        $defaultTargetClass = null;
        $propertyNames = [];

        // 1. Inspect class attributes
        foreach ($ref->getAttributes(MapSource::class) as $attr) {
            $instance = $attr->newInstance();
            $defaultSourceClass = $instance->sourceClass;
        }

        foreach ($ref->getAttributes(MapTarget::class) as $attr) {
            $instance = $attr->newInstance();
            $defaultTargetClass = $instance->targetClass;
        }

        foreach ($ref->getAttributes(MapProperty::class) as $attr) {
            $instance = $attr->newInstance();
            $fieldMap[$instance->target] = $instance->source;
            $reverseFieldMap[$instance->source] = $instance->target;

            if ($instance->transformer !== null) {
                $transformers[$instance->target] = self::resolveTransformer($instance->transformer);
            }
        }

        // 2. Inspect default properties (e.g. protected array $fieldMap = [...])
        $defaultProperties = $ref->getDefaultProperties();
        if (!empty($defaultProperties['fieldMap']) && is_array($defaultProperties['fieldMap'])) {
            foreach ($defaultProperties['fieldMap'] as $targetKey => $sourceKey) {
                if (is_string($targetKey) && is_string($sourceKey)) {
                    $fieldMap[$targetKey] = $sourceKey;
                    $reverseFieldMap[$sourceKey] = $targetKey;
                }
            }
        }

        // 3. Inspect class properties
        foreach ($ref->getProperties() as $prop) {
            $propName = $prop->getName();
            $propertyNames[$propName] = true;

            // Check MapIgnore
            foreach ($prop->getAttributes(MapIgnore::class) as $attr) {
                $instance = $attr->newInstance();
                if ($instance->ignoresToTarget()) {
                    $ignoredToTarget[$propName] = true;
                }
                if ($instance->ignoresToSource()) {
                    $ignoredToSource[$propName] = true;
                }
            }

            // Check MapFrom
            foreach ($prop->getAttributes(MapFrom::class) as $attr) {
                $instance = $attr->newInstance();
                $fieldMap[$propName] = $instance->sourceKey;
                $reverseFieldMap[$instance->sourceKey] = $propName;

                if ($instance->transformer !== null) {
                    $transformers[$propName] = self::resolveTransformer($instance->transformer);
                }
            }

            // Check MapTo
            foreach ($prop->getAttributes(MapTo::class) as $attr) {
                $instance = $attr->newInstance();
                $reverseFieldMap[$propName] = $instance->targetKey;

                if ($instance->transformer !== null) {
                    $transformers[$propName] = self::resolveTransformer($instance->transformer);
                }
            }

            // Check MapCast
            foreach ($prop->getAttributes(MapCast::class) as $attr) {
                $instance = $attr->newInstance();
                $casts[$propName] = [
                    'targetClass' => $instance->targetClass,
                    'isCollection' => $instance->isCollection,
                ];
            }

            // Check MapWith
            foreach ($prop->getAttributes(MapWith::class) as $attr) {
                $instance = $attr->newInstance();
                $transformers[$propName] = self::resolveTransformer($instance->transformerClass, $instance->params);
            }
        }

        $metadata = new ClassMappingMetadata(
            className: $className,
            fieldMap: $fieldMap,
            reverseFieldMap: $reverseFieldMap,
            casts: $casts,
            transformers: $transformers,
            ignoredToTarget: $ignoredToTarget,
            ignoredToSource: $ignoredToSource,
            defaultSourceClass: $defaultSourceClass,
            defaultTargetClass: $defaultTargetClass,
            isCI4Entity: $isCI4Entity,
            propertyNames: $propertyNames,
        );

        return self::$cache[$className] = $metadata;
    }

    /**
     * Resolve a transformer instance.
     */
    private static function resolveTransformer(string $transformerClass, array $params = []): ValueTransformerInterface|callable
    {
        if (class_exists($transformerClass)) {
            $instance = empty($params) ? new $transformerClass() : new $transformerClass(...$params);
            if ($instance instanceof ValueTransformerInterface || is_callable($instance)) {
                return $instance;
            }
        }

        if (is_callable($transformerClass)) {
            return $transformerClass;
        }

        throw MappingException::forInvalidTransformer($transformerClass);
    }

    /**
     * Clear the internal reflection metadata cache.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}

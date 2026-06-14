<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Repositories;

use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use RuntimeException;

/**
 * Handles discovery and loading of Command Variants.
 */
class VariantRepository
{
    /**
     * Scans directories for variants and returns them.
     * 
     * @param string $path The relative path to scan for variants (e.g., 'Commands/Variants/Make')
     * @return CommandVariantInterface[]
     */
    public static function all(string $path): array
    {
        $locator = service('locator');
        
        $files = $locator->listFiles($path);
        
        $variants = [];

        foreach ($files as $file) {
            $className = $locator->getClassname($file);

            if ($className && class_exists($className) && is_subclass_of($className, CommandVariantInterface::class)) {
                $reflection = new \ReflectionClass($className);
                if (!$reflection->isAbstract()) {
                    $variants[] = new $className();
                }
            }
        }

        return $variants;
    }

    /**
     * Finds a specific variant by name within a path.
     */
    public static function find(string $path, string $name): ?CommandVariantInterface
    {
        foreach (self::all($path) as $variant) {
            if ($variant::name() === $name) {
                return $variant;
            }
        }

        return null;
    }
}

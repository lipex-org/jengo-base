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
     * Supports exact name matching as well as hierarchical auto-resolution (e.g., 'mpesa:register-c2b' or 'register-c2b').
     */
    public static function find(string $path, string $name): ?CommandVariantInterface
    {
        $variants = self::all($path);

        // 1. Direct name match
        foreach ($variants as $variant) {
            if ($variant::name() === $name) {
                return $variant;
            }
        }

        // 2. Colon-separated prefix match (e.g. searching for 'mpesa' when variant is named 'mpesa' or subfolder is 'Mpesa')
        $normalizedName = str_replace([':', '/'], '\\', strtolower($name));
        foreach ($variants as $variant) {
            $variantName = str_replace([':', '/'], '\\', strtolower($variant::name()));
            if ($variantName === $normalizedName) {
                return $variant;
            }
        }

        return null;
    }
}

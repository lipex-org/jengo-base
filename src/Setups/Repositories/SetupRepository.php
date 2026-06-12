<?php

declare(strict_types=1);

namespace Jengo\Base\Setups\Repositories;

use Jengo\Base\Setups\Contracts\SetupInterface;

class SetupRepository
{
    /** @return SetupInterface[] */
    public static function all(): array
    {
        $locator = service('locator');
        $files = $locator->listFiles('Setups/');
        
        $setups = [];
        $classes = [];

        foreach ($files as $file) {
            $className = $locator->getClassname($file);

            if ($className && class_exists($className) && is_subclass_of($className, SetupInterface::class)) {
                $reflection = new \ReflectionClass($className);
                if (!$reflection->isAbstract()) {
                    $classes[] = $className;
                }
            }
        }

        foreach ($classes as $class) {
            $setups[] = new $class();
        }

        return $setups;
    }

    public static function find(string $name): ?SetupInterface
    {
        foreach (self::all() as $setup) {
            if ($setup::name() === $name) {
                return $setup;
            }
        }

        return null;
    }
}

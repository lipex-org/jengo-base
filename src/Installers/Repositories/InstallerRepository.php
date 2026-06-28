<?php

declare(strict_types=1);

namespace Jengo\Base\Installers\Repositories;

use Jengo\Base\Config\Jengo;
use Jengo\Base\Installers\Contracts\InstallerInterface;

class InstallerRepository
{
    /** @return InstallerInterface[] */
    public static function all(): array
    {
        $locator = service('locator');
        $files = $locator->listFiles('Installers/');

        $installers = [];
        $classes = [];

        foreach ($files as $file) {
            $className = $locator->getClassname($file);

            if ($className && class_exists($className) && is_subclass_of($className, InstallerInterface::class)) {
                $reflection = new \ReflectionClass($className);
                if (!$reflection->isAbstract()) {
                    $classes[] = $className;
                }
            }
        }

        // Merge with config for backward compatibility and explicit control
        $config = config(Jengo::class);
        if (isset($config->installers)) {
            $classes = array_unique(array_merge($classes, $config->installers));
        }

        foreach ($classes as $class) {
            $installers[] = new $class();
        }

        return $installers;
    }

    public static function find(string $name): ?InstallerInterface
    {
        foreach (self::all() as $installer) {
            if ($installer::name() === $name) {
                return $installer;
            }
        }

        return null;
    }
}

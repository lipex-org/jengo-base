<?php

declare(strict_types=1);

namespace Jengo\Base\Container;

use Config\Services;
use Jengo\Base\Attributes\Bind;
use Jengo\Base\Support\JengoDirectory;
use ReflectionClass;
use Throwable;

class BindingScanner
{
    public const CACHE_FILE = 'cache/bindings.php';

    /**
     * Scan classes across the application and modules for #[Bind] attributes.
     *
     * @return array<string, array{concrete: string, shared: bool}>
     */
    public static function scan(): array
    {
        $locator = Services::locator();
        $files = $locator->listFiles('');

        $bindings = [];

        foreach ($files as $file) {
            // Only inspect PHP files
            if (! str_ends_with($file, '.php')) {
                continue;
            }

            $className = $locator->getClassname($file);

            if (! $className || ! class_exists($className, false)) {
                continue;
            }

            try {
                $reflector = new ReflectionClass($className);

                if ($reflector->isAbstract() || $reflector->isInterface() || $reflector->isTrait()) {
                    continue;
                }

                $attributes = $reflector->getAttributes(Bind::class);

                foreach ($attributes as $attribute) {
                    /** @var Bind $instance */
                    $instance = $attribute->newInstance();

                    $bindings[$instance->abstract] = [
                        'concrete' => $className,
                        'shared'   => $instance->singleton,
                    ];
                }
            } catch (Throwable) {
                // Ignore unreflective classes
            }
        }

        return $bindings;
    }

    /**
     * Scan and compile #[Bind] attribute bindings into .jengo/cache/bindings.php.
     *
     * @return array<string, array{concrete: string, shared: bool}>
     */
    public static function compile(): array
    {
        $bindings = self::scan();

        JengoDirectory::writePhpArray(self::CACHE_FILE, [
            'compiled_at' => time(),
            'bindings'    => $bindings,
        ]);

        return $bindings;
    }

    /**
     * Register cached or scanned bindings directly into the Container.
     */
    public static function loadIntoContainer(): void
    {
        $cached = JengoDirectory::readPhpArray(self::CACHE_FILE);

        $bindings = $cached['bindings'] ?? [];

        // In development, if cache is missing, scan and compile on the fly
        if (empty($bindings) && (! defined('ENVIRONMENT') || ENVIRONMENT === 'development')) {
            $bindings = self::compile();
        }

        foreach ($bindings as $abstract => $config) {
            app()->bind($abstract, $config['concrete'], $config['shared'] ?? true);
        }
    }
}

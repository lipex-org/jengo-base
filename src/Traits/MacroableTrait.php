<?php

declare(strict_types=1);

namespace Jengo\Base\Traits;

use BadMethodCallException;
use Closure;
use ReflectionClass;
use ReflectionMethod;

trait MacroableTrait
{
    /**
     * Registered dynamic macro closures.
     *
     * @var array<string, Closure>
     */
    protected static array $macros = [];

    /**
     * Register a custom macro.
     */
    public static function macro(string $name, Closure $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Register a class or object mixin containing macro methods.
     */
    public static function mixin(object|string $mixin, bool $replace = true): void
    {
        $reflection = new ReflectionClass($mixin);
        $methods = $reflection->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            if ($replace || ! static::hasMacro($method->name)) {
                $closure = $method->getClosure(is_object($mixin) ? $mixin : null);
                static::macro($method->name, $closure());
            }
        }
    }


    /**
     * Check if a macro is registered.
     */
    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[$name]);
    }

    /**
     * Flush all registered macros.
     */
    public static function flushMacros(): void
    {
        static::$macros = [];
    }

    /**
     * Dynamically handle calls to the class.
     */
    public function __call(string $method, array $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method
            ));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof Closure) {
            $macro = $macro->bindTo($this, static::class);
        }

        return $macro(...$parameters);
    }

    /**
     * Dynamically handle static calls to the class.
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Static method %s::%s does not exist.',
                static::class,
                $method
            ));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof Closure) {
            $macro = $macro->bindTo(null, static::class);
        }

        return $macro(...$parameters);
    }
}

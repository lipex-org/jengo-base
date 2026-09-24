<?php

declare(strict_types=1);

namespace Jengo\Base\Container;

use Closure;
use Config\Services;
use Jengo\Base\Container\Exceptions\ContainerException;
use Jengo\Base\Container\Exceptions\NotFoundException;
use Jengo\Base\Validation\ValidatedData;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

class Container implements ContainerInterface
{
    /**
     * The current globally available container instance.
     */
    protected static ?Container $instance = null;

    /**
     * The container's registered bindings.
     *
     * @var array<string, array{concrete: Closure|string, shared: bool}>
     */
    protected array $bindings = [];

    /**
     * The container's shared instances (singletons).
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * The stack of concretions currently being built to detect circular dependencies.
     *
     * @var array<string, bool>
     */
    protected array $buildStack = [];

    /**
     * Get or set the globally available container instance.
     */
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Set the shared container instance.
     */
    public static function setInstance(?Container $container = null): ?Container
    {
        return static::$instance = $container;
    }

    /**
     * Register a binding with the container.
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): self
    {
        unset($this->instances[$abstract]);

        $concrete ??= $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared'   => $shared,
        ];

        return $this;
    }

    /**
     * Register a shared binding (singleton) in the container.
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): self
    {
        return $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as shared in the container.
     */
    public function instance(string $abstract, mixed $instance): self
    {
        $this->instances[$abstract] = $instance;

        return $this;
    }

    /**
     * Determine if the given abstract type has been bound.
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Determine if the given abstract type has been resolved.
     */
    public function resolved(string $abstract): bool
    {
        return isset($this->instances[$abstract]);
    }

    /**
     * Flush the container of all bindings and resolved instances.
     */
    public function flush(): void
    {
        $this->bindings   = [];
        $this->instances  = [];
        $this->buildStack = [];
    }

    /**
     * Finds an entry of the container by its identifier and returns it (PSR-11).
     *
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function get(string $id): mixed
    {
        try {
            return $this->make($id);
        } catch (NotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ContainerException("Error while resolving [{$id}]: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Returns true if the container can return an entry for the given identifier (PSR-11).
     */
    public function has(string $id): bool
    {
        return $this->bound($id) || class_exists($id) || interface_exists($id);
    }

    /**
     * Resolve the given type from the container.
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        // 1. If an instance already exists as a singleton, return it
        if (isset($this->instances[$abstract]) && empty($parameters)) {
            return $this->instances[$abstract];
        }

        // 2. Resolve concrete target
        $concrete = $this->getConcrete($abstract);

        // 3. Prevent circular dependency loops
        if (isset($this->buildStack[$abstract])) {
            throw new ContainerException("Circular dependency detected while resolving [{$abstract}].");
        }

        $this->buildStack[$abstract] = true;

        try {
            if ($concrete instanceof Closure) {
                $object = $concrete($this, $parameters);
            } elseif ($concrete === $abstract || is_string($concrete)) {
                $object = $this->build($concrete, $parameters);
            } else {
                $object = $concrete;
            }
        } finally {
            unset($this->buildStack[$abstract]);
        }

        // 4. Cache if registered as shared
        if ($this->isShared($abstract) && empty($parameters)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param callable|array|string $callable
     * @param array<int|string, mixed> $parameters
     *
     * @throws ContainerException
     */
    public function call(callable|array|string $callable, array $parameters = [], ?string $defaultMethod = null): mixed
    {
        if (is_string($callable) && str_contains($callable, '@')) {
            $segments = explode('@', $callable, 2);
            $callable = [$this->make($segments[0]), $segments[1]];
        } elseif (is_string($callable) && str_contains($callable, '::')) {
            $segments = explode('::', $callable, 2);
            $callable = [$this->make($segments[0]), $segments[1]];
        } elseif (is_string($callable) && class_exists($callable)) {
            $instance = $this->make($callable);
            $method   = $defaultMethod ?? '__invoke';
            $callable = [$instance, $method];
        } elseif (is_array($callable) && isset($callable[0]) && is_string($callable[0])) {
            $callable[0] = $this->make($callable[0]);
        }

        if (! is_callable($callable)) {
            throw new ContainerException("Target callback is not callable.");
        }

        $reflection = $this->getCallableReflection($callable);
        $dependencies = $this->resolveMethodDependencies($reflection, $parameters);

        return $callable(...$dependencies);
    }

    /**
     * Build an instance of the given class.
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function build(string $concrete, array $parameters = []): mixed
    {
        // Check if concrete is a known CodeIgniter service
        $ciService = $this->resolveFromCodeIgniterServices($concrete);
        if ($ciService !== null) {
            return $ciService;
        }

        if (! class_exists($concrete)) {
            throw new NotFoundException("Target class [{$concrete}] does not exist.");
        }

        $reflector = new ReflectionClass($concrete);

        if (! $reflector->isInstantiable()) {
            throw new ContainerException("Target [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveConstructorDependencies($constructor, $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Get the concrete type for a given abstract.
     */
    protected function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Determine if a given type is shared.
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->instances[$abstract]) ||
            (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'] === true);
    }

    /**
     * Resolve constructor dependencies via reflection.
     *
     * @throws ContainerException
     */
    protected function resolveConstructorDependencies(ReflectionMethod $constructor, array $parameters = []): array
    {
        $dependencies = [];
        $unnamedParamIndex = 0;

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            // 1. Explicit named parameter passed
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // 2. Class / Interface typehint
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $className = $type->getName();
                try {
                    $dependencies[] = $this->make($className);
                    continue;
                } catch (Throwable $e) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                        continue;
                    }
                    if ($parameter->allowsNull()) {
                        $dependencies[] = null;
                        continue;
                    }
                    throw $e;
                }
            }

            // 3. Positional parameter fallback
            if (array_key_exists($unnamedParamIndex, $parameters)) {
                $dependencies[] = $parameters[$unnamedParamIndex++];
                continue;
            }

            // 4. Default value
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // 5. Nullable primitive
            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            $declaringClass = $constructor->getDeclaringClass()->getName();
            throw new ContainerException("Unresolvable dependency resolving [Parameter #{$parameter->getPosition()} (\${$name})] in class [{$declaringClass}].");
        }

        return $dependencies;
    }

    /**
     * Resolve method dependencies combining typed dependencies and positional route parameters.
     *
     * @throws ContainerException
     */
    protected function resolveMethodDependencies(ReflectionFunctionAbstract $reflection, array $parameters = []): array
    {
        $dependencies = [];
        $positionalParams = array_values(array_filter($parameters, static fn ($k) => is_int($k), ARRAY_FILTER_USE_KEY));
        $positionalIndex = 0;

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            // 1. Explicit named parameter match
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // 2. Typehinted class / interface / Form request
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $className = $type->getName();

                // Special handling: ValidatedData resolution
                if ($className === ValidatedData::class) {
                    $dependencies[] = $this->resolveValidatedData();
                    continue;
                }

                try {
                    $dependencies[] = $this->make($className);
                    continue;
                } catch (Throwable $e) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                        continue;
                    }
                    if ($parameter->allowsNull()) {
                        $dependencies[] = null;
                        continue;
                    }
                    throw $e;
                }
            }

            // 3. Positional route parameter match (sequential order for primitives)
            if (array_key_exists($positionalIndex, $positionalParams)) {
                $val = $positionalParams[$positionalIndex++];
                $dependencies[] = $this->castPrimitiveValue($val, $type);
                continue;
            }

            // 4. Default value
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // 5. Nullable parameter
            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            $functionName = $reflection->getName();
            throw new ContainerException("Unresolvable parameter [\${$name}] in [{$functionName}].");
        }

        return $dependencies;
    }

    /**
     * Helper to resolve ValidatedData from the current request context if available.
     */
    protected function resolveValidatedData(): ValidatedData
    {
        if (function_exists('request')) {
            $request = request();
            return new ValidatedData(
                get: (array) ($request->getGet() ?? []),
                post: (array) ($request->getPost() ?? []),
                json: (array) ($request->getJSON(true) ?? []),
                router: []
            );
        }

        return new ValidatedData();
    }

    /**
     * Cast primitive string value from URI segments if type is specified.
     */
    protected function castPrimitiveValue(mixed $val, ?ReflectionNamedType $type): mixed
    {
        if ($type === null || ! is_string($val)) {
            return $val;
        }

        return match ($type->getName()) {
            'int'    => (int) $val,
            'float'  => (float) $val,
            'bool'   => filter_var($val, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $val,
            default  => $val,
        };
    }

    /**
     * Get a reflection instance for a callable.
     */
    protected function getCallableReflection(callable|array|string $callable): ReflectionFunctionAbstract
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        if (is_object($callable) && ! $callable instanceof Closure) {
            return new ReflectionMethod($callable, '__invoke');
        }

        return new ReflectionFunction($callable);
    }

    /**
     * Bridge resolution to CodeIgniter 4 Services locator if available.
     */
    protected function resolveFromCodeIgniterServices(string $class): mixed
    {
        if (! class_exists(Services::class)) {
            return null;
        }

        // Match common CI4 service class names
        $shortName = lcfirst(basename(str_replace('\\', '/', $class)));

        if (method_exists(Services::class, $shortName)) {
            try {
                $service = Services::{$shortName}();
                if ($service instanceof $class) {
                    return $service;
                }
            } catch (Throwable) {
                // Ignore and continue normal container build
            }
        }

        return null;
    }
}

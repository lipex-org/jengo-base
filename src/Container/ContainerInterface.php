<?php

declare(strict_types=1);

namespace Jengo\Base\Container;

use Closure;
use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Register a binding with the container.
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): self;

    /**
     * Register a shared binding (singleton) in the container.
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): self;

    /**
     * Register an existing instance as shared in the container.
     */
    public function instance(string $abstract, mixed $instance): self;

    /**
     * Resolve the given type from the container.
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Call the given Closure / class@method and inject its dependencies.
     */
    public function call(callable|array|string $callable, array $parameters = [], ?string $defaultMethod = null): mixed;

    /**
     * Determine if the given abstract type has been bound.
     */
    public function bound(string $abstract): bool;

    /**
     * Determine if the given abstract type has been resolved.
     */
    public function resolved(string $abstract): bool;

    /**
     * Flush the container of all bindings and resolved instances.
     */
    public function flush(): void;
}

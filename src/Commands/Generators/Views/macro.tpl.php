<@php

declare(strict_types=1);

namespace {namespace};

/**
 * Macro mixin for {target_class}.
 */
class {class}
{
    /**
     * Example macro method.
     */
    public function exampleMacro(): \Closure
    {
        return function () {
            // Define macro logic here.
            // When bound to an instance, $this refers to the {target_class} instance.
            return true;
        };
    }
}

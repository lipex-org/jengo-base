<?php

declare(strict_types=1);

namespace Jengo\Base\Support;

class HigherOrderTapProxy
{
    public function __construct(
        public mixed $target
    ) {
    }

    public function __call(string $method, array $parameters): mixed
    {
        $this->target->{$method}(...$parameters);

        return $this->target;
    }
}

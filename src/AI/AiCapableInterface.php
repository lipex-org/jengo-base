<?php

declare(strict_types=1);

namespace Jengo\Base\AI;

interface AiCapableInterface
{
    /**
     * Return capability metadata for this package or class.
     *
     * @return array
     */
    public function getCapabilities(): array;
}

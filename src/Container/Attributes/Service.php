<?php

declare(strict_types=1);

namespace Jengo\Base\Container\Attributes;

use Attribute;

/**
 * Attribute used to hint that a parameter should be resolved
 * from CodeIgniter 4's service locator (e.g. service($name)).
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Service
{
    /**
     * @param string|null $name The service name (e.g. 'curlrequest', 'logger', 'queue', 'pesa').
     *                          If null, the container infers the service name from the parameter name or type.
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly bool $getShared = true
    ) {}
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Attributes;

use Attribute;

/**
 * Attribute to declare container bindings directly on implementation classes.
 *
 * Usage:
 *   #[Bind(UserRepositoryInterface::class, singleton: true)]
 *   class PostgresUserRepository implements UserRepositoryInterface { ... }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Bind
{
    /**
     * @param string $abstract The interface or abstract class to bind to.
     * @param bool $singleton Whether the binding should be a shared singleton.
     */
    public function __construct(
        public readonly string $abstract,
        public readonly bool $singleton = true
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Contracts;

/**
 * Interface for Command Variants.
 */
interface CommandVariantInterface
{
    /**
     * Returns the variant name.
     */
    public static function name(): string;

    /**
     * Returns the variant description.
     */
    public static function description(): string;

    /**
     * Returns the variant arguments.
     * @return array<string, string>
     */
    public function arguments(): array;

    /**
     * Returns the variant options.
     * @return array<string, string>
     */
    public function options(): array;

    /**
     * Executes the variant logic.
     * @param array $params
     */
    public function run(array $params): void;
}

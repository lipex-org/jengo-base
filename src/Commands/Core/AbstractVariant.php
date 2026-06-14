<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Core;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;

/**
 * Base class for all Command Variants.
 */
abstract class AbstractVariant implements CommandVariantInterface
{
    /**
     * Helper to write to CLI.
     */
    protected function write(string $text, ?string $foreground = null, ?string $background = null): void
    {
        CLI::write($text, $foreground, $background);
    }

    /**
     * Returns default empty arguments.
     */
    public function arguments(): array
    {
        return [];
    }

    /**
     * Returns default empty options.
     */
    public function options(): array
    {
        return [];
    }
}

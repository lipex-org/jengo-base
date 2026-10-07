<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Contracts;

/**
 * Interface for Command Variants that contain nested child variants.
 */
interface NestedCommandVariantInterface extends CommandVariantInterface
{
    /**
     * Returns the relative path or subdirectory where child variants are stored.
     * e.g., 'Commands/Variants/Pesa/Mpesa' or 'Mpesa' relative to the parent.
     */
    public function variantPath(): string;
}

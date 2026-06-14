<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

/**
 * Master command for Vite operations.
 */
class ViteCommand extends AbstractMasterCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:vite';
    protected $description = 'Consolidated Vite operations.';
    protected $usage = 'jengo:vite <variant> [arguments] [options]';

    protected string $variantPath = 'Commands/Variants/Vite';
}

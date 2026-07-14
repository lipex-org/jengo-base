<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

/**
 * Master command for Jengo AI utilities.
 */
class AiCommand extends AbstractMasterCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:ai';
    protected $description = 'AI utility commands for Jengo ecosystem.';
    protected $usage = 'jengo:ai <variant> [arguments] [options]';

    protected string $variantPath = 'Commands/Variants/Ai';
}

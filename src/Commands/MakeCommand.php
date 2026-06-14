<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

/**
 * Master command for generating resources.
 */
class MakeCommand extends AbstractMasterCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:make';
    protected $description = 'Consolidated resource generators.';
    protected $usage = 'jengo:make <variant> [arguments] [options]';

    protected string $variantPath = 'Commands/Variants/Make';
}

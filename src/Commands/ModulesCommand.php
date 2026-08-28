<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

class ModulesCommand extends AbstractMasterCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:modules';
    protected $description = 'Manage Jengo modules discovery and caching.';
    protected $usage = 'jengo:modules <variant> [arguments] [options]';

    protected string $variantPath = 'Commands/Variants/Modules';
}

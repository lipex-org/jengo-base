<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

class ClearCommand extends AbstractMasterCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:clear';
    protected $description = 'Consolidated cache and asset cleanup commands.';
    protected $usage = 'jengo:clear <variant> [arguments] [options]';

    protected string $variantPath = 'Commands/Variants/Clear';
}

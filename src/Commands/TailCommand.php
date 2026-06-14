<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

/**
 * Master command for tailing resources.
 */
class TailCommand extends AbstractMasterCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:tail';
    protected $description = 'Consolidated resource tailing.';
    protected $usage = 'jengo:tail <variant> [arguments] [options]';

    protected string $variantPath = 'Commands/Variants/Tail';
}

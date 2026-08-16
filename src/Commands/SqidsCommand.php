<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

/**
 * Master command for Jengo Sqids utilities.
 */
class SqidsCommand extends AbstractMasterCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:sqids';
    protected $description = 'Sqids hashing and unhashing utility commands.';
    protected $usage = 'jengo:sqids <variant> [arguments] [options]';

    protected string $variantPath = 'Commands/Variants/Sqids';
}

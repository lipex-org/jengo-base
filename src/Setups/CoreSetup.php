<?php

declare(strict_types=1);

namespace Jengo\Base\Setups;

class CoreSetup extends AbstractSetup
{
    public static function name(): string
    {
        return 'core';
    }

    public static function title(): string
    {
        return 'THE FOUNDATION';
    }

    public static function description(): string
    {
        return 'Configure Jengo core helpers and autoloading';
    }

    public function setup(): void
    {
        $this->renderHeader(self::title(), self::description());

        $this->addHelperToAutoload(['Jengo\Base\Helpers\jengo']);
    }
}

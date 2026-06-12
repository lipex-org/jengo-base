<?php

declare(strict_types=1);

namespace Jengo\Base\Setups\Contracts;

interface SetupInterface
{
    /**
     * Unique name for the setup (used in CLI arguments).
     */
    public static function name(): string;

    /**
     * Display title for the setup (shown in headers).
     */
    public static function title(): string;

    /**
     * Brief description of what this setup bridges.
     */
    public static function description(): string;

    /**
     * The main execution logic for the setup.
     */
    public function setup(): void;
}

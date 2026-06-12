<?php 

declare(strict_types=1);

namespace Jengo\Base\Installers\Contracts;

interface InstallerInterface
{
    public static function name(): string;

    public static function description(): string;
    
    public static function reasonForSkipping(): string;

    public static function dependencies(): array;

    public function install(): void;

    public function shouldRun(): bool;
}

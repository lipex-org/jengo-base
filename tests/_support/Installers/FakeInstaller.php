<?php

declare(strict_types=1);

namespace Tests\Support\Installers;

use Jengo\Base\Installers\Contracts\AbstractInstaller;

final class FakeInstaller extends AbstractInstaller
{
    private static string $name;
    public function __construct(
        string $name = 'fake'
    ) {
        parent::__construct();
        static::$name = $name;
    }

    public static function name(): string
    {
        return static::$name;
    }
    public static function description(): string
    {
        return 'Fake';
    }
    public static function reasonForSkipping(): string
    {
        return 'Fake skip';
    }
    public function shouldRun(): bool
    {
        return true;
    }
    public function install(): void
    {
        $this->addRun();

        $this->publish(
            __DIR__ . '/../stubs/vite',
            'vite-test'
        );

        $this->env()
            ->set('FOO', 'baz')
            ->set('BAR', 'baz')
            ->save();
    }
}


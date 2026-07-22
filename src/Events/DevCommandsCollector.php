<?php

declare(strict_types=1);

namespace Jengo\Base\Events;

final class DevCommandsCollector
{
    private array $commands = [];

    /**
     * Register a dev command.
     *
     * @param string $command The command line string (e.g. 'php spark queue:work')
     * @param string $label   The label for prefixing logs (e.g. 'Queue')
     * @param string|null $color Optional ANSI color code (e.g. '32', '35', etc.)
     */
    public function register(string $command, string $label, ?string $color = null): self
    {
        $this->commands[] = [
            'command' => $command,
            'label'   => $label,
            'color'   => $color,
        ];

        return $this;

    }

    /**
     * Get all registered dev commands.
     *
     * @return array<int, array{command: string, label: string, color?: string}>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

/**
 * Fluent builder class for dev processes configuration.
 */
class DevProcessBuilder
{
    private string $command;
    private string $label;
    private string $color = '';
    private bool $autoRestart = false;
    private array $watch = [];
    private bool $sequential = false;
    private array $dependsOn = [];

    private bool $registered = false;

    public function __construct(string $command, string $label)
    {
        $this->command = $command;
        $this->label = $label;
    }

    /**
     * Automatically register on destruction if not explicitly registered yet.
     */
    public function __destruct()
    {
        if (!$this->registered) {
            $this->register();
        }
    }

    /**
     * Set a custom ANSI color code (e.g. '32', '35').
     */
    public function color(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    /**
     * Color helper: Green ('32')
     */
    public function green(): self
    {
        return $this->color('32');
    }

    /**
     * Color helper: Yellow ('33')
     */
    public function yellow(): self
    {
        return $this->color('33');
    }

    /**
     * Color helper: Blue ('34')
     */
    public function blue(): self
    {
        return $this->color('34');
    }

    /**
     * Color helper: Magenta ('35')
     */
    public function magenta(): self
    {
        return $this->color('35');
    }

    /**
     * Color helper: Cyan ('36')
     */
    public function cyan(): self
    {
        return $this->color('36');
    }

    /**
     * Color helper: Red ('31')
     */
    public function red(): self
    {
        return $this->color('31');
    }

    /**
     * Enable auto restart.
     */
    public function autoRestart(bool $enable = true): self
    {
        $this->autoRestart = $enable;
        return $this;
    }

    /**
     * Set watch paths.
     */
    public function watch(string ...$paths): self
    {
        $this->watch = array_merge($this->watch, $paths);
        return $this;
    }

    /**
     * Mark task as sequential startup task.
     */
    public function sequential(bool $enable = true): self
    {
        $this->sequential = $enable;
        return $this;
    }

    /**
     * Define process dependencies by label.
     */
    public function dependsOn(string ...$labels): self
    {
        $this->dependsOn = array_merge($this->dependsOn, $labels);
        return $this;
    }

    /**
     * Registers the process config into DevCommand and returns it.
     */
    public function register(): void
    {
        $this->registered = true;

        DevCommand::addProcess([
            'command' => $this->command,
            'label' => $this->label,
            'color' => $this->color === '' ? null : $this->color,
            'auto_restart' => $this->autoRestart,
            'watch' => $this->watch,
            'sequential' => $this->sequential,
            'depends_on' => $this->dependsOn,
        ]);
    }
}

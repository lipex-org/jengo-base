<?php

declare(strict_types=1);

namespace Jengo\Base\Installers\Libraries;

use RuntimeException;

class EnvHandler
{
    protected string $path;
    protected array $lines = [];

    public function __construct(string $path)
    {
        $this->path = $path;

        if (!file_exists($path)) {
            // Attempt to create the file if it doesn't exist
            if (file_put_contents($path, '') === false) {
                throw new RuntimeException("Failed to create .env file at: {$path}");
            }
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $this->lines = $lines !== false ? $lines : [];
    }

    /**
     * Update or add a key-value pair with an optional comment.
     */
    public function set(string $key, string $value, ?string $comment = null): self
    {
        $found = false;
        $newLine = "{$key}={$value}" . ($comment ? " # {$comment}" : "");

        foreach ($this->lines as $index => $line) {
            // Match the key at the start of the line
            if (preg_match("/^{$key}=/", $line)) {
                $this->lines[$index] = $newLine;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->lines[] = $newLine;
        }

        return $this;
    }

    /**
     * Add a standalone comment line.
     */
    public function addComment(string $comment): self
    {
        $this->lines[] = "# {$comment}";
        return $this;
    }

    /**
     * Adds title lines
     * @param string $title
     * @return EnvHandler
     */
    public function addTitle(string $title): self
    {
        $this->addSpacer();
        $this->addComment('--------------------------------------------------------------------');
        $this->addComment($title);
        $this->addComment('--------------------------------------------------------------------');
        $this->addSpacer();

        return $this;
    }

    /**
     * Add an empty line for readability.
     */
    public function addSpacer(): self
    {
        $this->lines[] = "";
        return $this;
    }

    /**
     * Save the changes back to the file.
     */
    public function save(): bool
    {
        $content = implode(PHP_EOL, $this->lines) . PHP_EOL;
        return file_put_contents($this->path, $content) !== false;
    }
}
<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Core;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Repositories\VariantRepository;

/**
 * Base class for all Master Commands that route to Variants.
 */
abstract class AbstractMasterCommand extends BaseCommand
{
    /**
     * The relative path where this command's variants are stored.
     * e.g., 'Commands/Variants/Make'
     */
    protected string $variantPath;

    /**
     * Orchestrates the routing to the appropriate variant.
     */
    public function run(array $params)
    {
        $variantName = array_shift($params);

        if (!$variantName || $variantName === 'list') {
            $this->showHelp();
            return;
        }

        $variant = VariantRepository::find($this->variantPath, $variantName);

        if (!$variant) {
            CLI::error("Variant [{$variantName}] not found for command [{$this->name}].");
            CLI::newLine();
            $this->showHelp();
            return;
        }

        $variant->run($params);
    }

    public function showAvailableVariants(): void
    {
        $variants = VariantRepository::all($this->variantPath);

        CLI::write("Available Variants:", 'yellow');

        if (empty($variants)) {
            CLI::write("  (No variants found in path: {$this->variantPath})", 'dark_gray');
            return;
        }

        $maxlen = 0;
        foreach ($variants as $variant) {
            $maxlen = max($maxlen, strlen($variant::name()));
        }

        foreach ($variants as $variant) {
            CLI::write("  " . CLI::color(str_pad($variant::name(), $maxlen + 2), 'green') . $variant::description());

            $args = $variant->arguments();
            if (!empty($args)) {
                $maxArgLen = 0;
                foreach ($args as $name => $desc) {
                    $maxArgLen = max($maxArgLen, strlen($name));
                }

                foreach ($args as $name => $desc) {
                    CLI::write("    " . str_pad($name, $maxArgLen + 2) . CLI::color($desc, 'dark_gray'));
                }
            }
        }
    }

    /**
     * Displays a dynamic help screen based on discovered variants.
     */
    public function showHelp(): void
    {
        CLI::write("Usage:", 'yellow');
        CLI::write("  {$this->name} <variant> [arguments] [options]");
        CLI::newLine();

        $params = CLI::getSegments();
        $variant = $params[1] ?? null;
        $variantInstances = VariantRepository::all($this->variantPath);

        if (!$variant) {
            $this->showAvailableVariants();
            return;
        }

        $variantAvailable = false;
        $variantInstance = null;
        foreach ($variantInstances as $v) {
            if ($v::name() === $variant) {
                $variantAvailable = true;
                $variantInstance = $v;
                break;
            }
        }

        if (!$variantAvailable) {
            CLI::error("Variant [{$variant}] not found for command [{$this->name}].");
            CLI::newLine();
            $this->showAvailableVariants();
            return;
        }

        CLI::write("Specific Usage:", 'yellow');
        CLI::write("  {$this->name} {$variant} [arguments] [options]");
        CLI::newLine();

        CLI::write("Description:", 'yellow');
        CLI::write("  " . $variantInstance::description());
        CLI::newLine();

        $args = $variantInstance->arguments();
        if (!empty($args)) {
            CLI::write("Arguments:", 'yellow');
            $maxArgLen = 0;
            foreach ($args as $name => $desc) {
                $maxArgLen = max($maxArgLen, strlen($name));
            }
            foreach ($args as $name => $desc) {
                CLI::write("  " . CLI::color(str_pad($name, $maxArgLen + 2), 'green') . $desc);
            }
            CLI::newLine();
        }

        $opts = $variantInstance->options();
        if (!empty($opts)) {
            CLI::write("Options:", 'yellow');
            $maxOptLen = 0;
            foreach ($opts as $name => $desc) {
                $maxOptLen = max($maxOptLen, strlen($name));
            }
            foreach ($opts as $name => $desc) {
                CLI::write("  " . CLI::color(str_pad($name, $maxOptLen + 2), 'green') . $desc);
            }
            CLI::newLine();
        }
    }
}

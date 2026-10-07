<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Core;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Contracts\NestedCommandVariantInterface;
use Jengo\Base\Commands\Repositories\VariantRepository;

/**
 * Base class for Command Variants that contain nested child variants.
 */
abstract class AbstractNestedVariant extends AbstractVariant implements NestedCommandVariantInterface
{
    /**
     * The relative path where this variant's child variants are stored.
     * e.g., 'Commands/Variants/Pesa/Mpesa'
     */
    protected string $variantPath;

    public function variantPath(): string
    {
        return $this->variantPath;
    }

    /**
     * Executes the child routing logic.
     */
    public function run(array $params): void
    {
        $childName = array_shift($params);

        if (!$childName || $childName === 'list') {
            $this->showHelp();
            return;
        }

        $child = VariantRepository::find($this->variantPath(), $childName);

        if (!$child) {
            CLI::error("Sub-variant [{$childName}] not found under [" . static::name() . "].");
            CLI::newLine();
            $this->showHelp();
            return;
        }

        $child->run($params);
    }

    /**
     * Displays available child variants.
     */
    public function showAvailableVariants(): void
    {
        $variants = VariantRepository::all($this->variantPath());

        CLI::write("Available Sub-Variants:", 'yellow');

        if (empty($variants)) {
            CLI::write("  (No sub-variants found in path: {$this->variantPath()})", 'dark_gray');
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
     * Displays help screen for this nested variant.
     */
    public function showHelp(): void
    {
        CLI::write("Usage:", 'yellow');
        CLI::write("  " . static::name() . " <sub-variant> [arguments] [options]");
        CLI::newLine();

        $this->showAvailableVariants();
    }
}

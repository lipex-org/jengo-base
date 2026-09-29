<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Make;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Core\AbstractGeneratorVariant;

class MacroVariant extends AbstractGeneratorVariant
{
    protected $component = 'Macros';
    protected $directory = 'Macros';
    protected ?string $targetOption = null;

    public static function name(): string
    {
        return 'macro';
    }

    public static function description(): string
    {
        return 'Generates a new Macro mixin class.';
    }

    public function arguments(): array
    {
        return [
            'class_name' => 'Name of the macro class to create (e.g. UserMacros)',
        ];
    }

    public function options(): array
    {
        return [
            '--namespace' => 'Namespace to create the class in',
            '--target'    => 'Target entity or class being extended (e.g. App\Entities\User)',
            '--force'     => 'Overwrite existing file',
        ];
    }

    public function run(array $params): void
    {
        $this->templatePath = config('Generators')->views['jengo:make']['macro'];
        $this->template = 'macro';

        $this->targetOption = $this->extractTarget($params);
        $this->params = $params;

        parent::run($params);
    }

    /**
     * Prepare options and do the necessary replacements.
     */
    protected function prepare(string $class): string
    {
        $target = $this->targetOption ?? $this->extractTarget($this->params ?? []);

        $template = $this->prepareTrait($class);

        return str_replace('{target_class}', $target, $template);
    }

    private function extractTarget(array $params): string
    {
        $target = $this->getOption('target');
        if ($target && is_string($target) && trim($target, '"\'') !== '') {
            return trim($target, '"\'');
        }

        foreach ($params as $key => $param) {
            if (is_string($key)) {
                if (($key === 'target' || $key === 'target=' || $key === '-target=' || $key === '--target=') && is_string($param) && $param !== '') {
                    return trim($param, '"\'');
                }
                if (str_starts_with($key, 'target=') && strlen($key) > 7) {
                    return trim(substr($key, 7), '"\'');
                }
                if (str_starts_with($key, '-target=') && strlen($key) > 8) {
                    return trim(substr($key, 8), '"\'');
                }
                if (str_starts_with($key, '--target=') && strlen($key) > 9) {
                    return trim(substr($key, 9), '"\'');
                }
            }
            if (is_string($param)) {
                if (str_starts_with($param, '--target=') && strlen($param) > 9) {
                    return trim(substr($param, 9), '"\'');
                }
                if (str_starts_with($param, '-target=') && strlen($param) > 8) {
                    return trim(substr($param, 8), '"\'');
                }
            }
        }

        return 'BaseEntity';
    }
}

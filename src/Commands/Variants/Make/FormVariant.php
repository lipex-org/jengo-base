<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Make;

use Jengo\Base\Commands\Core\AbstractGeneratorVariant;

class FormVariant extends AbstractGeneratorVariant
{
    protected $component = 'Forms';
    protected $directory = 'Forms';

    public static function name(): string
    {
        return 'form';
    }

    public static function description(): string
    {
        return 'Generates a new FormHandler class.';
    }

    public function arguments(): array
    {
        return [
            'class_name' => 'Name of the class to create',
        ];
    }

    public function options(): array
    {
        return [
            '--namespace' => 'Namespace to create the class in',
            '--force'     => 'Overwrite existing file',
        ];
    }

    public function run(array $params): void
    {
        $this->templatePath = config('Generators')->views['jengo:make']['form'];
        $this->template = 'form';

        parent::run($params);
    }

    /**
     * Prepare options and do the necessary replacements.
     */
    protected function prepare(string $class): string
    {
        return $this->prepareTrait($class);
    }
}

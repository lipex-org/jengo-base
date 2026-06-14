<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Make;

use Jengo\Base\Commands\Core\AbstractGeneratorVariant;

class ActionVariant extends AbstractGeneratorVariant
{
    protected $component = 'Actions';
    protected $directory = 'Actions';

    public static function name(): string
    {
        return 'action';
    }

    public static function description(): string
    {
        return 'Generates a new action class.';
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
            '--force' => 'Overwrite existing file',
        ];
    }

    public function run(array $params): void
    {
        $this->templatePath = config('Generators')->views['jengo:make']['action'];
        $this->template = "action";

        parent::run($params);
    }

    /**
     * Prepare options and do the necessary replacements.
     */
    protected function prepare(string $class): string
    {
        $template = $this->prepareTrait($class);

        $namespace = trim(
            implode(
                '\\',
                array_slice(explode('\\', $class), 0, -1),
            ),
            '\\',
        );

        $className = str_replace($namespace . '\\', '', $class);
        $resourceName = $this->getResourceName($className);

        $repo = "{$resourceName}Repository";
        $actionName = "{$resourceName}";
        $repo_namespace = str_replace($this->directory, 'Repositories', $namespace);

        $search[] = "{repo}";
        $search[] = "{repo_namespace}";
        $search[] = "{action_name}";
        $replace[] = $repo;
        $replace[] = $repo_namespace;
        $replace[] = $actionName;

        return str_replace($search, $replace, $template);
    }

    private function getResourceName(string $class): string
    {
        if (str_contains($class, "Action")) {
            return str_replace("Action", "", $class);
        }

        return $class;
    }
}

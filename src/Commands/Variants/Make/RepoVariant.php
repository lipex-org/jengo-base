<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Make;

use Jengo\Base\Commands\Core\AbstractGeneratorVariant;

class RepoVariant extends AbstractGeneratorVariant
{
    protected $component = 'Repositories';
    protected $directory = 'Repositories';

    public static function name(): string
    {
        return 'repo';
    }

    public static function description(): string
    {
        return 'Generates a new repository class for a model.';
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
        $this->templatePath = config('Generators')->views['jengo:make']['repo'];
        $this->template = "repo";

        parent::run($params);
    }

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

        $model = "{$resourceName}Model";
        $repoName = "{$resourceName}";
        $model_namespace = str_replace($this->directory, 'Models', $namespace);

        $search[] = "{model}";
        $search[] = "{model_namespace}";
        $search[] = "{repo_name}";
        $replace[] = $model;
        $replace[] = $model_namespace;
        $replace[] = $repoName;

        return str_replace($search, $replace, $template);
    }

    private function getResourceName(string $class): string
    {
        if (str_contains($class, "Repository")) {
            return str_replace("Repository", "", $class);
        }

        return $class;
    }
}

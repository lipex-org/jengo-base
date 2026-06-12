<?php

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\GeneratorTrait;

class MakeRepositoryCommand extends BaseCommand
{
    use GeneratorTrait {
        prepare as prepareTrait;
    }
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Jengo';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'jengo:make-repo';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new repository class for a model';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'jengo:make-repo [arguments] [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [
        'class_name' => 'Name of the class to create',
    ];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--namespace' => 'Namespace to create the class in',
        '--force' => 'Overwrite existing file',
    ];
    /**
     * Creates a new token class file.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $this->component = "Repositories";
        $this->directory = "Repositories";

        $this->generateClass($params);
    }

    /**
     * Prepare options and do the necessary replacements.
     *
     * @param string $class
     * @return string
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

        $model = "{$resourceName}Model";
        $repoName = "{$resourceName}";
        $model_namespace = str_replace($this->directory, 'Models', $namespace);

        $search[] = "{model}";
        $search[] = "{model_namespace}";
        $search[] = "{repo_name}";
        $replace[] = $model;
        $replace[] = $model_namespace;
        $replace[] = $repoName;

        // 3. Perform the replacement in the template
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

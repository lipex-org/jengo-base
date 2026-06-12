<?php

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\GeneratorTrait;

class MakeActionCommand extends BaseCommand
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
    protected $name = 'jengo:make-action';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new action class';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'jengo:make-action [arguments] [options]';

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
        $this->component = "Actions";
        $this->directory = "Actions";

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

        $repo = "{$resourceName}Repository";
        $actionName = "{$resourceName}";
        $repo_namespace = str_replace($this->directory, 'Repositories', $namespace);

        $search[] = "{repo}";
        $search[] = "{repo_namespace}";
        $search[] = "{action_name}";
        $replace[] = $repo;
        $replace[] = $repo_namespace;
        $replace[] = $actionName;

        // 3. Perform the replacement in the template
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

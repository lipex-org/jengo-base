<?php

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\GeneratorTrait;
use Config\Generators;

class MakeLayoutCommand extends BaseCommand
{
    use GeneratorTrait {
        prepare as prepareTrait;
        buildPath as buildPathTrait;
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
    protected $name = 'jengo:make-layout';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new layout file';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'jengo:make-layout [arguments] [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [
        'layout Name' => 'Name of layout to create e.g app',
    ];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--namespace' => 'Namespace to create the class in',
        '--force' => 'Overwrite existing file',
        '--base' => 'Creates layout as base template'
    ];
    /**
     * Creates a new token class file.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $this->component = "ViewsLayouts";
        $this->directory = "Views/layouts";

        $isBase = array_key_exists('base', $params);

        $templateName = $isBase ? 'base' : 'main';
        $template = $isBase ? 'base.tpl.php' : 'layout.tpl.php';

        $this->templatePath = config(Generators::class)->views[$this->name][$templateName];
        $this->template = $template;

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

        $layout = $this->getOption('layout') ?? 'base';

        $search[] = "@echo";
        $search[] = "{layout}";
        $replace[] = "?=";
        $replace[] = $layout;

        // 3. Perform the replacement in the template
        return str_replace($search, $replace, $template);
    }

    protected function buildPath(string $class): string
    {
        $path = $this->buildPathTrait($class);

        // ensure the file name is lowercase
        if ($path === '') {
            return $path;
        }

        $segments = explode(DIRECTORY_SEPARATOR, $path);

        for ($i=0; $i < count($segments); $i++) {
            $y = $i;
            $seg = $segments[$y];
            $segNext = $segments[++$y] ?? null;

            if ($seg === 'Views' && $segNext === 'layouts') {
                while ($y < count($segments)) {
                    $seg = $segments[$y];
                    $segments[$y] = strtolower($seg);
                    $y++;
                }

                break;
            }

            continue;
        }

        $lastPos = count($segments) - 1;

        $filename = lcfirst($segments[$lastPos]);

        if (!str_contains($filename, '.layout.php')) {
            $filename = str_replace('.php', '.layout.php', $filename);
        }

        $segments[$lastPos] = $filename;

        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}

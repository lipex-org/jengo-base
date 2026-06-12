<?php

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\GeneratorTrait;

class MakePageCommand extends BaseCommand
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
    protected $name = 'jengo:make-page';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new page with a layout extended';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'jengo:make-page [arguments] [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [
        'page_path' => 'Path of the page to create e.g users/view',
    ];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--namespace' => 'Namespace to create the class in',
        '--force' => 'Overwrite existing file',
        '--layout' => 'Layout to extend',
        '--verbose' => 'Turns on verbose comments in the generated file',
    ];
    /**
     * Creates a new token class file.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $this->component = "ViewsPages";
        $this->directory = "Views/pages";

        //var_dump($params, 'got here');

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

        $page = str_replace($namespace . '\\', '', $class);
        $layout = $this->getOption('layout') ?? 'app';
        $verbose = !!$this->getOption('verbose');
        $comments = "
        <!-- This is the {page} page generated using jengo base -->
        <!-- Available sections are: --> 
        <!-- header(placed in head element) -->
        <!-- footer(placed at the end of the of the body section) --> \n \n
        ";

        $search[] = "{page}";
        $search[] = "@echo";
        $search[] = "{layout}";
        $search[] = "{comments}";
        $replace[] = $page;
        $replace[] = "?=";
        $replace[] = "layouts/$layout.layout.php";
        $replace[] = $verbose ? $comments : "";

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

            if ($seg === 'Views' && $segNext === 'pages') {
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

        if(!str_contains($filename, '.page.php')) {
            $filename = str_replace('.php', '.page.php', $filename);
        }

        $segments[$lastPos] = $filename;

        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}

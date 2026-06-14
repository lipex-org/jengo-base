<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Make;

use Jengo\Base\Commands\Core\AbstractGeneratorVariant;

class PageVariant extends AbstractGeneratorVariant
{
    protected $component = 'ViewsPages';
    protected $directory = 'Views/pages';

    public static function name(): string
    {
        return 'page';
    }

    public static function description(): string
    {
        return 'Generates a new page with a layout extended.';
    }

    public function arguments(): array
    {
        return [
            'page_path' => 'Path of the page to create e.g users/view',
        ];
    }

    public function options(): array
    {
        return [
            '--namespace' => 'Namespace to create the class in',
            '--force' => 'Overwrite existing file',
            '--layout' => 'Layout to extend',
            '--verbose' => 'Turns on verbose comments in the generated file',
        ];
    }

    public function run(array $params): void
    {
        $this->templatePath = config('Generators')->views['jengo:make']['page'];
        $this->template = "page";

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

        $page = str_replace($namespace . '\\', '', $class);
        $layout = $this->getOption('layout') ?? 'app';
        $verbose = $this->getOption('verbose') !== null;

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

        return str_replace($search, $replace, $template);
    }

    protected function buildPath(string $class): string
    {
        $path = $this->buildPathTrait($class);

        if ($path === '') {
            return $path;
        }

        $segments = explode(DIRECTORY_SEPARATOR, $path);

        for ($i = 0; $i < count($segments); $i++) {
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
        }

        $lastPos = count($segments) - 1;
        $filename = lcfirst($segments[$lastPos]);

        if (!str_contains($filename, '.page.php')) {
            $filename = str_replace('.php', '.page.php', $filename);
        }

        $segments[$lastPos] = $filename;

        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}

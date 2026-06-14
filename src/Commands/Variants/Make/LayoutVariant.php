<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Make;

use Jengo\Base\Commands\Core\AbstractGeneratorVariant;

class LayoutVariant extends AbstractGeneratorVariant
{
    protected $component = 'ViewsLayouts';
    protected $directory = 'Views/layouts';

    public static function name(): string
    {
        return 'layout';
    }

    public static function description(): string
    {
        return 'Generates a new layout file.';
    }

    public function arguments(): array
    {
        return [
            'name' => 'Name of layout to create e.g app',
        ];
    }

    public function options(): array
    {
        return [
            '--namespace' => 'Namespace to create the class in',
            '--force' => 'Overwrite existing file',
            '--base' => 'Creates layout as base template'
        ];
    }

    public function run(array $params): void
    {
        $this->component = "ViewsLayouts";
        $this->directory = "Views/layouts";

        $isBase = $this->getOption('base') !== null;

        $templateName = $isBase ? 'base' : 'main';
        $template = $isBase ? 'base.tpl.php' : 'layout.tpl.php';

        $this->templatePath = config('Generators')->views['jengo:make']['layout'][$templateName];
        $this->template = $template;

        $this->generateClass($params);
    }

    protected function prepare(string $class): string
    {
        $template = $this->prepareTrait($class);

        $layout = $this->getOption('layout') ?? 'base';

        $search[] = "@echo";
        $search[] = "{layout}";
        $replace[] = "?=";
        $replace[] = $layout;

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

            if ($seg === 'Views' && $segNext === 'layouts') {
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

        if (!str_contains($filename, '.layout.php')) {
            $filename = str_replace('.php', '.layout.php', $filename);
        }

        $segments[$lastPos] = $filename;

        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}

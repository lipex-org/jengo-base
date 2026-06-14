<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Make;

use Config\Generators;
use Jengo\Base\Commands\Core\AbstractGeneratorVariant;

class EventVariant extends AbstractGeneratorVariant
{
    protected $component = 'Events';
    protected $directory = 'Events';

    private string $type = 'event';
    private array $eventParams = [];

    public static function name(): string
    {
        return 'event';
    }

    public static function description(): string
    {
        return 'Creates a master-grade Event and an optional Listener.';
    }

    public function arguments(): array
    {
        return [
            'name' => 'The name of the event (e.g., OrderPlaced)',
        ];
    }

    public function options(): array
    {
        return [
            '--no-listener' => 'Do not generate a listener for this event',
        ];
    }

    public function run(array $params): void
    {
        $this->component = "Events";
        $this->directory = "Events";

        $templateName = 'event';
        $template = 'event.tpl.php';
        $this->type = $templateName;

        $this->templatePath = config(Generators::class)->views['jengo:make']['event'][$templateName];
        $this->template = $template;

        $this->generateClass($params);

        $noListener = $this->getOption('no-listener') !== null;

        if ($noListener) {
            return;
        }

        $eventName = $this->eventParams['class'];
        array_unshift($params, "{$eventName}Listener");

        // Manual implementation of pipe operator equivalent or simple array manipulation
        $params = array_values(array_unique(array_diff($params, [$eventName])));

        $this->component = "EventsListeners";
        $this->directory = "Events\Listeners";
        $templateName = 'listener';
        $template = 'listener.tpl.php';
        $this->type = $templateName;

        $this->templatePath = config(Generators::class)->views['jengo:make']['event'][$templateName];
        $this->template = $template;

        $this->generateClass($params);
    }

    protected function prepare(string $class): string
    {
        $template = $this->prepareTrait($class);

        if ($this->type === 'event') {
            $namespace = trim(
                implode(
                    '\\',
                    array_slice(explode('\\', $class), 0, -1),
                ),
                '\\',
            );

            $path = $this->buildPath($class);
            $name = str_replace(['.php', DIRECTORY_SEPARATOR], ['', '.'], basename($path));

            $this->eventParams = [
                'namespace' => $namespace,
                'class' => str_replace($namespace . '\\', '', $class)
            ];

            $search[] = "{event_name}";
            $replace[] = $name;

            return str_replace($search, $replace, $template);
        }

        $search[] = "{event_import}";
        $search[] = "{event_class_name}";
        $replace[] = $this->eventParams['namespace'] . '\\' . $this->eventParams['class'];
        $replace[] = $this->eventParams['class'];

        return str_replace($search, $replace, $template);
    }
}

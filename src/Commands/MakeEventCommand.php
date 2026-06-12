<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\GeneratorTrait;
use Config\Generators;

class MakeEventCommand extends BaseCommand
{
    use GeneratorTrait {
        prepare as prepareTrait;
    }

    protected $group = 'Jengo';
    protected $name = 'jengo:make-event';
    protected $description = 'Creates a master-grade Event and an optional Listener.';
    protected $usage = 'jengo:make-event <name> [options]';

    protected $arguments = [
        'name' => 'The name of the event (e.g., OrderPlaced)',
    ];

    protected $options = [
        '--no-listener' => 'Do not generate a listener for this event',
    ];

    private string $type = 'event';

    private array $eventParams = [];

    public function run(array $params)
    {
        $this->component = "Events";
        $this->directory = "Events";

        $templateName = 'event';
        $template = 'event.tpl.php';
        $this->type = $templateName;

        $this->templatePath = config(Generators::class)->views[$this->name][$templateName];
        $this->template = $template;

        $this->generateClass($params);

        $noListener = array_key_exists('no-listener', $params);

        if ($noListener) {
            return;
        }

        $eventName = $this->eventParams['class'];
        array_unshift($params,"{$eventName}Listener");
        $params =array_diff($params, [$eventName]) |> array_unique(...) |> array_values(...);

        $this->component = "EventsListeners";
        $this->directory = "Events\Listeners";
        $templateName = 'listener';
        $template = 'listener.tpl.php';
        $this->type = $templateName;

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

            // 3. Perform the replacement in the template
            return str_replace($search, $replace, $template);
        }

        $search[] = "{event_import}";
        $search[] = "{event_class_name}";
        $replace[] = $this->eventParams['namespace'] . '\\' . $this->eventParams['class'];
        $replace[] = $this->eventParams['class'];

        // 3. Perform the replacement in the template
        return str_replace($search, $replace, $template);
    }
}

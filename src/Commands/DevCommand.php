<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Events\Events;
use Jengo\Base\Events\DevCommandsCollector;

class DevCommand extends BaseCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:dev';
    protected $description = 'Run development servers and tasks concurrently.';
    protected $usage = 'jengo:dev';

    public function run(array $params)
    {
        $commandsToRun = [];

        if (defined('ENVIRONMENT') && ENVIRONMENT !== 'testing') {
            $commandsToRun[] = [
                'command' => 'cd ' . ROOTPATH . ' && php spark serve',
                'label' => 'Dev',
                'color' => '31',
            ];
        }

        // 1. Vite check
        $viteEnabled = env('VITE_ENABLED') ?? env('vite.enabled') ?? false;
        $viteEnabled = filter_var($viteEnabled, FILTER_VALIDATE_BOOLEAN);

        if ($viteEnabled) {
            $commandsToRun[] = [
                'command' => 'npm run dev',
                'label' => 'Vite',
                'color' => '36', // Cyan
            ];
        }

        $colors = ['32', '35', '33', '34', '31', '36']; // Green, Magenta, Yellow, Blue, Red, Cyan
        $colorIdx = 0;

        // 2. Custom project dev commands from config
        $devConfig = config('Dev');
        if ($devConfig && isset($devConfig->commands) && is_array($devConfig->commands)) {
            foreach ($devConfig->commands as $cmd) {
                if (!empty($cmd['command'])) {
                    $commandsToRun[] = [
                        'command' => $cmd['command'],
                        'label' => $cmd['label'] ?? 'Task',
                        'color' => $cmd['color'] ?? $colors[$colorIdx % count($colors)],
                    ];
                    $colorIdx++;
                }
            }
        }

        // 3. Custom dev commands from event listeners
        $collector = new DevCommandsCollector();
        Events::trigger('jengo.dev.register', $collector);

        foreach ($collector->getCommands() as $cmd) {
            $commandsToRun[] = [
                'command' => $cmd['command'],
                'label' => $cmd['label'],
                'color' => $cmd['color'] ?? $colors[$colorIdx % count($colors)],
            ];
            $colorIdx++;
        }

        if (empty($commandsToRun)) {
            CLI::write("No dev commands registered or enabled.", 'yellow');
            return;
        }

        CLI::write("Starting dev processes concurrently...", 'green');

        $processes = [];
        $pipes = [];
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        try {
            foreach ($commandsToRun as $index => $cmdSpec) {
                $command = $cmdSpec['command'];
                $label = $cmdSpec['label'];
                $color = $cmdSpec['color'];

                $process = proc_open($command, $descriptors, $processPipes, ROOTPATH);

                if (is_resource($process)) {
                    stream_set_blocking($processPipes[1], false);
                    stream_set_blocking($processPipes[2], false);

                    $processes[$index] = [
                        'process' => $process,
                        'label' => $label,
                        'color' => $color,
                        'command' => $command,
                    ];

                    $pipes[$index * 2] = $processPipes[1]; // stdout
                    $pipes[$index * 2 + 1] = $processPipes[2]; // stderr
                } else {
                    CLI::write("Failed to start process: [{$label}] {$command}", 'red');
                }
            }

            // Keep track of active pipes
            while (!empty($pipes)) {
                $read = $pipes;
                $write = null;
                $except = null;

                // Stream select with a small timeout (e.g. 200ms)
                if (stream_select($read, $write, $except, 0, 200000) > 0) {
                    foreach ($read as $pipe) {
                        $pipeKey = array_search($pipe, $pipes, true);
                        if ($pipeKey === false) {
                            continue;
                        }

                        $index = (int) floor($pipeKey / 2);

                        $data = fread($pipe, 4096);
                        if ($data === false || $data === '') {
                            // EOF detected, close the pipe
                            fclose($pipe);
                            unset($pipes[$pipeKey]);
                            continue;
                        }

                        $spec = $processes[$index];
                        $label = $spec['label'];
                        $color = $spec['color'];

                        // Log output prefixed with [Label]
                        $lines = explode("\n", rtrim($data));
                        foreach ($lines as $line) {
                            if ($line === '') {
                                continue;
                            }
                            // Use ANSI escape code for colors
                            $ansiPrefix = "\033[1;{$color}m[{$label}]\033[0m ";
                            echo $ansiPrefix . $line . PHP_EOL;
                        }
                    }
                }

                // Check if any process has exited
                foreach ($processes as $index => $spec) {
                    $status = proc_get_status($spec['process']);
                    if (!$status['running']) {
                        // Cleanup remaining pipes
                        $stdoutKey = $index * 2;
                        $stderrKey = $index * 2 + 1;

                        if (isset($pipes[$stdoutKey])) {
                            // Read any remaining data
                            $data = stream_get_contents($pipes[$stdoutKey]);
                            if ($data) {
                                $lines = explode("\n", rtrim($data));
                                foreach ($lines as $line) {
                                    if ($line !== '') {
                                        echo "\033[1;{$spec['color']}m[{$spec['label']}]\033[0m " . $line . PHP_EOL;
                                    }
                                }
                            }
                            fclose($pipes[$stdoutKey]);
                            unset($pipes[$stdoutKey]);
                        }

                        if (isset($pipes[$stderrKey])) {
                            $data = stream_get_contents($pipes[$stderrKey]);
                            if ($data) {
                                $lines = explode("\n", rtrim($data));
                                foreach ($lines as $line) {
                                    if ($line !== '') {
                                        echo "\033[1;{$spec['color']}m[{$spec['label']}]\033[0m " . $line . PHP_EOL;
                                    }
                                }
                            }
                            fclose($pipes[$stderrKey]);
                            unset($pipes[$stderrKey]);
                        }

                        proc_close($spec['process']);
                        unset($processes[$index]);
                        CLI::write("Process [{$spec['label']}] exited with code {$status['exitcode']}", 'yellow');
                    }
                }
            }
        } finally {
            // Terminate all remaining processes on exit/Ctrl+C
            foreach ($processes as $spec) {
                if (is_resource($spec['process'])) {
                    proc_terminate($spec['process']);
                    proc_close($spec['process']);
                }
            }
        }
    }
}

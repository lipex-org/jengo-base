<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DevCommand extends BaseCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:dev';
    protected $description = 'Run development servers and tasks concurrently with interactive controls, file watching, and process dependencies.';
    protected $usage = 'jengo:dev [options]';

    protected $options = [
        '--format' => 'Output format: default, json, compact, tui. Defaults to tui.',
    ];

    /**
     * Holds registered command specs.
     * @var array<int, array{command: string, label: string, color: string, auto_restart: bool, watch: string[], sequential: bool, depends_on: string[]}>
     */
    private static array $customCommands = [];

    private static array $onlyDefaults = [];
    private static array $exceptDefaults = [];
    private static array $colors = ['32', '35', '33', '34', '31', '36']; // Green, Magenta, Yellow, Blue, Red, Cyan
    private static int $colorIdx = 0;

    private ?string $originalStty = null;

    /**
     * Register a general shell command or initiate a fluent builder.
     */
    public static function register(
        string $command,
        string $label,
        ?string $color = null,
        bool $autoRestart = false,
        array $watch = [],
        bool $sequential = false,
        array $dependsOn = []
    ): DevProcessBuilder {
        $builder = new DevProcessBuilder($command, $label);
        if ($color !== null) {
            $builder->color($color);
        }
        if ($autoRestart) {
            $builder->autoRestart();
        }
        if (!empty($watch)) {
            $builder->watch(...$watch);
        }
        if ($sequential) {
            $builder->sequential();
        }
        if (!empty($dependsOn)) {
            $builder->dependsOn(...$dependsOn);
        }

        return $builder;
    }

    /**
     * Add a configured process specification directly to runtime custom commands (called by builder).
     */
    public static function addProcess(array $spec): void
    {
        $chosenColor = $spec['color'] ?? self::$colors[self::$colorIdx % count(self::$colors)];
        self::$colorIdx++;

        self::$customCommands[] = [
            'command' => $spec['command'],
            'label' => $spec['label'],
            'color' => $chosenColor,
            'auto_restart' => $spec['auto_restart'],
            'watch' => $spec['watch'],
            'sequential' => $spec['sequential'],
            'depends_on' => $spec['depends_on'],
        ];
    }

    /**
     * Register a Spark CLI command or initiate a fluent builder.
     */
    public static function spark(
        string $command,
        string $label,
        ?string $color = null,
        bool $autoRestart = false,
        array $watch = [],
        bool $sequential = false,
        array $dependsOn = []
    ): DevProcessBuilder {
        $phpBinary = escapeshellarg(PHP_BINARY);
        $sparkPath = escapeshellarg(ROOTPATH . 'spark');
        $fullCommand = "{$phpBinary} {$sparkPath} {$command}";

        return self::register($fullCommand, $label, $color, $autoRestart, $watch, $sequential, $dependsOn);
    }

    public static function only(string ...$names): void
    {
        foreach ($names as $name) {
            self::$onlyDefaults[$name] = true;
        }
    }

    public static function except(string ...$names): void
    {
        foreach ($names as $name) {
            self::$exceptDefaults[$name] = true;
        }
    }

    public static function reset(): void
    {
        self::$customCommands = [];
        self::$onlyDefaults = [];
        self::$exceptDefaults = [];
        self::$colorIdx = 0;
    }

    public function run(array $params)
    {
        $format = CLI::getOption('format') ?? 'tui';

        // Clear terminal screen on startup (unless running tests)
        if (ENVIRONMENT !== 'testing') {
            echo "\033[2J\033[H";
        }

        $commandsToRun = [];
        $defaults = [
            'vite' => [
                'command' => 'npm run dev',
                'label' => 'Vite',
                'color' => '36',
                'auto_restart' => false,
                'watch' => [],
                'sequential' => false,
                'depends_on' => [],
            ],
            'server' => [
                'command' => PHP_BINARY . ' ' . ROOTPATH . 'spark serve',
                'label' => 'Server',
                'color' => '32',
                'auto_restart' => true,
                'watch' => [],
                'sequential' => false,
                'depends_on' => [],
            ],
            'logs' => [
                'command' => PHP_BINARY . ' ' . ROOTPATH . 'spark jengo:tail log',
                'label' => 'Logs',
                'color' => '33',
                'auto_restart' => true,
                'watch' => [],
                'sequential' => false,
                'depends_on' => [],
            ]
        ];

        foreach ($defaults as $name => $spec) {
            if (!empty(self::$onlyDefaults) && !isset(self::$onlyDefaults[$name])) {
                continue;
            }
            if (isset(self::$exceptDefaults[$name])) {
                continue;
            }

            if ($name === 'vite') {
                $viteEnabled = env('VITE_ENABLED') ?? env('vite.enabled') ?? false;
                $viteEnabled = filter_var($viteEnabled, FILTER_VALIDATE_BOOLEAN);
                if (!$viteEnabled) {
                    continue;
                }
            }

            if ($name === 'server' || $name === 'logs') {
                if (ENVIRONMENT === 'testing') {
                    continue;
                }
            }

            $commandsToRun[] = $spec;
        }

        foreach (self::$customCommands as $cmdSpec) {
            $commandsToRun[] = $cmdSpec;
        }

        if (empty($commandsToRun)) {
            CLI::write("No dev commands registered or enabled.", 'yellow');
            return;
        }

        // 1. Run Sequential Tasks First
        $sequentialTasks = array_filter($commandsToRun, fn($c) => !empty($c['sequential']));
        $concurrentTasks = array_values(array_filter($commandsToRun, fn($c) => empty($c['sequential'])));

        if (!empty($sequentialTasks)) {
            CLI::write("Executing sequential startup tasks...", 'yellow');
            foreach ($sequentialTasks as $seqSpec) {
                CLI::write("Running [{$seqSpec['label']}]: {$seqSpec['command']} ...", 'cyan');
                // Run synchronously
                passthru($seqSpec['command'], $exitCode);
                if ($exitCode !== 0) {
                    CLI::error("Sequential task [{$seqSpec['label']}] failed with exit code {$exitCode}. Aborting dev startup.");
                    return;
                }
                CLI::write("Completed [{$seqSpec['label']}] successfully.", 'green');
            }
        }

        if (empty($concurrentTasks)) {
            CLI::write("All dev setup tasks completed.", 'green');
            return;
        }

        if ($format === 'tui' && $this->isTtySupported()) {
            $this->runTui($concurrentTasks);
        } else {
            $this->runStandard($concurrentTasks, $format);
        }
    }

    private function isTtySupported(): bool
    {
        return function_exists('posix_isatty') && posix_isatty(STDIN) && posix_isatty(STDOUT);
    }

    /**
     * Helper to compute folder hashes for basic file watching.
     */
    private function getWatchState(array $paths): string
    {
        $hashStr = '';
        foreach ($paths as $path) {
            $fullPath = ROOTPATH . $path;
            if (file_exists($fullPath)) {
                if (is_dir($fullPath)) {
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($fullPath, \FilesystemIterator::SKIP_DOTS)
                    );
                    foreach ($iterator as $file) {
                        $hashStr .= $file->getPathname() . ':' . $file->getMTime() . ';';
                    }
                } else {
                    $hashStr .= $fullPath . ':' . filemtime($fullPath) . ';';
                }
            }
        }
        return md5($hashStr);
    }

    /**
     * Terminate the entire process tree on POSIX systems (preventing orphan child processes).
     */
    private function killProcess(array $spec): void
    {
        if (!is_resource($spec['process'])) {
            return;
        }

        $status = proc_get_status($spec['process']);
        if ($status && !empty($status['pid'])) {
            $pid = (int) $status['pid'];
            if (function_exists('posix_kill')) {
                // Send TERM to process group (using negative PID kills parent and grandchildren)
                posix_kill(-$pid, SIGTERM);
            }
        }

        proc_terminate($spec['process']);
        proc_close($spec['process']);
    }

    /**
     * Standard Non-Interactive Mode
     */
    private function runStandard(array $commandsToRun, string $format)
    {
        if ($format !== 'json') {
            CLI::write("Starting dev processes concurrently [Format: {$format}]...", 'green');
        }

        $processes = [];
        $pipes = [];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        try {
            foreach ($commandsToRun as $index => $cmdSpec) {
                // If this task depends on other active tasks, wait until they start/exit, or let it start.
                // In standard concurrent mode we start everything but handle dependencies via status checks.
                $processes[$index] = [
                    'process' => null,
                    'label' => $cmdSpec['label'],
                    'color' => $cmdSpec['color'],
                    'command' => $cmdSpec['command'],
                    'auto_restart' => $cmdSpec['auto_restart'],
                    'restart_count' => 0,
                    'watch' => $cmdSpec['watch'],
                    'watch_hash' => !empty($cmdSpec['watch']) ? $this->getWatchState($cmdSpec['watch']) : '',
                    'depends_on' => $cmdSpec['depends_on'],
                    'status' => !empty($cmdSpec['depends_on']) ? 'pending' : 'running',
                ];

                if ($processes[$index]['status'] === 'running') {
                    $process = proc_open($cmdSpec['command'], $descriptors, $processPipes, ROOTPATH);
                    if (is_resource($process)) {
                        stream_set_blocking($processPipes[1], false);
                        stream_set_blocking($processPipes[2], false);
                        $processes[$index]['process'] = $process;
                        $pipes[$index * 2] = $processPipes[1];
                        $pipes[$index * 2 + 1] = $processPipes[2];
                    } else {
                        $this->logMessage($format, 'system', 'red', "Failed to start process: [{$cmdSpec['label']}]");
                        unset($processes[$index]);
                    }
                }
            }

            // Loop until everything is closed
            while (!empty($processes)) {
                // 1. Resolve pending dependencies
                foreach ($processes as $index => &$spec) {
                    if ($spec['status'] === 'pending') {
                        $dependenciesMet = true;
                        foreach ($spec['depends_on'] as $depName) {
                            foreach ($processes as $otherSpec) {
                                if ($otherSpec['label'] === $depName && $otherSpec['status'] === 'pending') {
                                    $dependenciesMet = false;
                                }
                            }
                        }
                        if ($dependenciesMet) {
                            $process = proc_open($spec['command'], $descriptors, $processPipes, ROOTPATH);
                            if (is_resource($process)) {
                                stream_set_blocking($processPipes[1], false);
                                stream_set_blocking($processPipes[2], false);
                                $spec['process'] = $process;
                                $spec['status'] = 'running';
                                $pipes[$index * 2] = $processPipes[1];
                                $pipes[$index * 2 + 1] = $processPipes[2];
                                $this->logMessage($format, 'system', '32', "Dependency met. Starting process [{$spec['label']}]");
                            } else {
                                $this->logMessage($format, 'system', 'red', "Failed to start dependent process: [{$spec['label']}]");
                                unset($processes[$index]);
                            }
                        }
                    }
                }
                unset($spec); // Clean up reference

                // 2. Read output streams
                $read = [];
                foreach ($pipes as $k => $p) {
                    if (is_resource($p)) {
                        $read[] = $p;
                    } else {
                        unset($pipes[$k]);
                    }
                }

                $write = null;
                $except = null;

                if (!empty($read)) {
                    if (stream_select($read, $write, $except, 0, 100000) > 0) {
                        foreach ($read as $pipe) {
                            $pipeKey = array_search($pipe, $pipes, true);
                            if ($pipeKey === false) {
                                continue;
                            }

                            $index = (int) floor($pipeKey / 2);
                            if (!isset($processes[$index]) || !$processes[$index]['process']) {
                                fclose($pipe);
                                unset($pipes[$pipeKey]);
                                continue;
                            }

                            $data = fread($pipe, 4096);
                            if ($data === false || $data === '') {
                                fclose($pipe);
                                unset($pipes[$pipeKey]);
                                continue;
                            }

                            $spec = $processes[$index];
                            $lines = explode("\n", rtrim($data));
                            foreach ($lines as $line) {
                                if ($line === '') {
                                    continue;
                                }
                                $this->logMessage($format, $spec['label'], $spec['color'], $line);
                            }
                        }
                    }
                } else {
                    usleep(100000);
                }

                // 3. File Watcher checks & Process state checks
                foreach ($processes as $index => $spec) {
                    if ($spec['status'] !== 'running') {
                        continue;
                    }

                    // Check filesystem watching
                    if (!empty($spec['watch'])) {
                        $currentHash = $this->getWatchState($spec['watch']);
                        if ($currentHash !== $spec['watch_hash']) {
                            $this->logMessage($format, 'system', '33', "Changes detected in watched directories for [{$spec['label']}]. Restarting...");
                            $this->killProcess($spec);

                            $processes[$index]['watch_hash'] = $currentHash;
                            $processes[$index]['restart_count'] = 0; // Reset restart count for watch events

                            $process = proc_open($spec['command'], $descriptors, $processPipes, ROOTPATH);
                            if (is_resource($process)) {
                                stream_set_blocking($processPipes[1], false);
                                stream_set_blocking($processPipes[2], false);
                                $processes[$index]['process'] = $process;
                                $pipes[$index * 2] = $processPipes[1];
                                $pipes[$index * 2 + 1] = $processPipes[2];
                            } else {
                                $this->logMessage($format, 'system', '31', "Failed to start watched process: [{$spec['label']}]");
                                unset($processes[$index]);
                            }
                            continue;
                        }
                    }

                    $status = proc_get_status($spec['process']);
                    if (!$status['running']) {
                        $stdoutKey = $index * 2;
                        $stderrKey = $index * 2 + 1;

                        foreach ([$stdoutKey => 1, $stderrKey => 2] as $pipeKey => $pipeIndex) {
                            if (isset($pipes[$pipeKey])) {
                                $data = stream_get_contents($pipes[$pipeKey]);
                                if ($data) {
                                    $lines = explode("\n", rtrim($data));
                                    foreach ($lines as $line) {
                                        if ($line !== '') {
                                            $this->logMessage($format, $spec['label'], $spec['color'], $line);
                                        }
                                    }
                                }
                                fclose($pipes[$pipeKey]);
                                unset($pipes[$pipeKey]);
                            }
                        }

                        $this->killProcess($spec);

                        if ($spec['auto_restart'] && $spec['restart_count'] === 0) {
                            $processes[$index]['restart_count']++;
                            $this->logMessage($format, 'system', '33', "Process [{$spec['label']}] exited with code {$status['exitcode']}. Restarting (Count: {$processes[$index]['restart_count']})...");

                            $process = proc_open($spec['command'], $descriptors, $processPipes, ROOTPATH);
                            if (is_resource($process)) {
                                stream_set_blocking($processPipes[1], false);
                                stream_set_blocking($processPipes[2], false);

                                $processes[$index]['process'] = $process;
                                $pipes[$stdoutKey] = $processPipes[1];
                                $pipes[$stderrKey] = $processPipes[2];
                            } else {
                                $this->logMessage($format, 'system', '31', "Failed to restart process: [{$spec['label']}]");
                                unset($processes[$index]);
                            }
                        } else {
                            $this->logMessage($format, 'system', '33', "Process [{$spec['label']}] exited with code {$status['exitcode']}");
                            unset($processes[$index]);
                        }
                    }
                }
            }
        } finally {
            foreach ($processes as $spec) {
                if ($spec['process']) {
                    $this->killProcess($spec);
                }
            }
        }
    }

    private function logMessage(string $format, string $label, string $color, string $message): void
    {
        if ($format === 'json') {
            echo json_encode([
                'timestamp' => date('c'),
                'process' => $label,
                'message' => preg_replace('/\e\[[0-9;]*m/', '', $message),
            ]) . PHP_EOL;
        } elseif ($format === 'compact') {
            if ($label === 'system') {
                echo "\033[1;{$color}m[{$label}]\033[0m {$message}" . PHP_EOL;
            }
        } else {
            echo "\033[1;{$color}m[{$label}]\033[0m {$message}" . PHP_EOL;
        }
    }

    /**
     * Interactive Terminal UI (TUI) Dashboard
     */
    private function runTui(array $commandsToRun)
    {
        $this->originalStty = shell_exec('stty -g');
        shell_exec('stty -icanon -echo');
        stream_set_blocking(STDIN, false);

        echo "\033[?25l"; // Hide cursor

        $processes = [];
        $pipes = [];
        $logBuffers = [];
        $activeTab = -1;
        $maxBufferLines = 80;

        $logBuffers[-1] = [];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        try {
            foreach ($commandsToRun as $index => $cmdSpec) {
                $processes[$index] = [
                    'process' => null,
                    'label' => $cmdSpec['label'],
                    'color' => $cmdSpec['color'],
                    'command' => $cmdSpec['command'],
                    'auto_restart' => $cmdSpec['auto_restart'],
                    'restart_count' => 0,
                    'watch' => $cmdSpec['watch'],
                    'watch_hash' => !empty($cmdSpec['watch']) ? $this->getWatchState($cmdSpec['watch']) : '',
                    'depends_on' => $cmdSpec['depends_on'],
                    'status' => !empty($cmdSpec['depends_on']) ? 'pending' : 'running',
                ];

                $logBuffers[$index] = [];

                if ($processes[$index]['status'] === 'running') {
                    $process = proc_open($cmdSpec['command'], $descriptors, $processPipes, ROOTPATH);
                    if (is_resource($process)) {
                        stream_set_blocking($processPipes[1], false);
                        stream_set_blocking($processPipes[2], false);
                        $processes[$index]['process'] = $process;
                        $pipes[$index * 2] = $processPipes[1];
                        $pipes[$index * 2 + 1] = $processPipes[2];
                    } else {
                        $msg = "Failed to start process: [{$cmdSpec['label']}]";
                        $logBuffers[-1][] = "\033[1;31m[system]\033[0m {$msg}";
                    }
                }
            }

            $lastDraw = 0;
            echo "\033[2J\033[H";

            while (!empty($processes)) {
                // Keystrokes
                $char = fread(STDIN, 1);
                if ($char !== false && $char !== '') {
                    if ($char === 'q') {
                        break;
                    } elseif ($char === 'a' || $char === '0') {
                        $activeTab = -1;
                        $lastDraw = 0;
                    } elseif (is_numeric($char)) {
                        $idx = (int) $char - 1;
                        if (isset($processes[$idx])) {
                            $activeTab = $idx;
                            $lastDraw = 0;
                        }
                    } elseif ($char === 'c') {
                        $logBuffers[$activeTab] = [];
                        $lastDraw = 0;
                    } elseif ($char === 'r') {
                        if ($activeTab >= 0 && isset($processes[$activeTab])) {
                            $spec = $processes[$activeTab];
                            if ($spec['process'] && is_resource($spec['process'])) {
                                $this->killProcess($spec);
                            }
                        }
                    }
                }

                // Resolve dependencies
                foreach ($processes as $index => &$spec) {
                    if ($spec['status'] === 'pending') {
                        $dependenciesMet = true;
                        foreach ($spec['depends_on'] as $depName) {
                            foreach ($processes as $otherSpec) {
                                if ($otherSpec['label'] === $depName && $otherSpec['status'] === 'pending') {
                                    $dependenciesMet = false;
                                }
                            }
                        }
                        if ($dependenciesMet) {
                            $process = proc_open($spec['command'], $descriptors, $processPipes, ROOTPATH);
                            if (is_resource($process)) {
                                stream_set_blocking($processPipes[1], false);
                                stream_set_blocking($processPipes[2], false);
                                $spec['process'] = $process;
                                $spec['status'] = 'running';
                                $pipes[$index * 2] = $processPipes[1];
                                $pipes[$index * 2 + 1] = $processPipes[2];
                                $msg = "\033[1;32m[system]\033[0m Dependency met. Starting process [{$spec['label']}]";
                                $logBuffers[-1][] = $msg;
                                $logBuffers[$index][] = $msg;
                            } else {
                                $msg = "\033[1;31m[system]\033[0m Failed to start dependent process: [{$spec['label']}]";
                                $logBuffers[-1][] = $msg;
                                unset($processes[$index]);
                            }
                            $lastDraw = 0;
                        }
                    }
                }
                unset($spec);

                // Output Streams
                $read = [];
                foreach ($pipes as $k => $p) {
                    if (is_resource($p)) {
                        $read[] = $p;
                    } else {
                        unset($pipes[$k]);
                    }
                }

                $write = null;
                $except = null;

                if (!empty($read)) {
                    if (stream_select($read, $write, $except, 0, 50000) > 0) {
                        foreach ($read as $pipe) {
                            $pipeKey = array_search($pipe, $pipes, true);
                            if ($pipeKey === false) {
                                continue;
                            }

                            $index = (int) floor($pipeKey / 2);
                            if (!isset($processes[$index]) || !$processes[$index]['process']) {
                                fclose($pipe);
                                unset($pipes[$pipeKey]);
                                continue;
                            }

                            $data = fread($pipe, 4096);
                            if ($data === false || $data === '') {
                                fclose($pipe);
                                unset($pipes[$pipeKey]);
                                continue;
                            }

                            $spec = $processes[$index];
                            $lines = explode("\n", rtrim($data));
                            foreach ($lines as $line) {
                                if ($line === '') {
                                    continue;
                                }

                                $formattedLine = "\033[1;{$spec['color']}m[{$spec['label']}]\033[0m " . $line;
                                $logBuffers[$index][] = $formattedLine;
                                $logBuffers[-1][] = $formattedLine;

                                if (count($logBuffers[$index]) > $maxBufferLines) {
                                    array_shift($logBuffers[$index]);
                                }
                                if (count($logBuffers[-1]) > $maxBufferLines) {
                                    array_shift($logBuffers[-1]);
                                }
                            }
                        }
                    }
                } else {
                    usleep(50000);
                }

                // File Watcher & Exits
                foreach ($processes as $index => $spec) {
                    if ($spec['status'] !== 'running') {
                        continue;
                    }

                    if (!empty($spec['watch'])) {
                        $currentHash = $this->getWatchState($spec['watch']);
                        if ($currentHash !== $spec['watch_hash']) {
                            $msg = "\033[1;33m[system]\033[0m Files changed in watched directories for [{$spec['label']}]. Restarting...";
                            $logBuffers[-1][] = $msg;
                            $logBuffers[$index][] = $msg;

                            $this->killProcess($spec);
                            $processes[$index]['watch_hash'] = $currentHash;
                            $processes[$index]['restart_count'] = 0; // Reset restart count for watching restart events

                            $process = proc_open($spec['command'], $descriptors, $processPipes, ROOTPATH);
                            if (is_resource($process)) {
                                stream_set_blocking($processPipes[1], false);
                                stream_set_blocking($processPipes[2], false);

                                $processes[$index]['process'] = $process;
                                $pipes[$index * 2] = $processPipes[1];
                                $pipes[$index * 2 + 1] = $processPipes[2];
                            } else {
                                $msg = "\033[1;31m[system]\033[0m Failed to restart watched process: [{$spec['label']}]";
                                $logBuffers[$index][] = $msg;
                                $logBuffers[-1][] = $msg;
                                unset($processes[$index]);
                            }
                            $lastDraw = 0;
                            continue;
                        }
                    }

                    $status = proc_get_status($spec['process']);
                    if (!$status['running']) {
                        $processes[$index]['status'] = 'stopped';
                        $stdoutKey = $index * 2;
                        $stderrKey = $index * 2 + 1;

                        foreach ([$stdoutKey => 1, $stderrKey => 2] as $pipeKey => $pipeIndex) {
                            if (isset($pipes[$pipeKey])) {
                                $data = stream_get_contents($pipes[$pipeKey]);
                                if ($data) {
                                    $lines = explode("\n", rtrim($data));
                                    foreach ($lines as $line) {
                                        if ($line !== '') {
                                            $formattedLine = "\033[1;{$spec['color']}m[{$spec['label']}]\033[0m " . $line;
                                            $logBuffers[$index][] = $formattedLine;
                                            $logBuffers[-1][] = $formattedLine;
                                        }
                                    }
                                }
                                fclose($pipes[$pipeKey]);
                                unset($pipes[$pipeKey]);
                            }
                        }

                        $this->killProcess($spec);

                        if ($spec['auto_restart'] && $spec['restart_count'] === 0) {
                            $processes[$index]['restart_count']++;
                            $processes[$index]['status'] = 'restarting';
                            $msg = "\033[1;33m[system]\033[0m Process [{$spec['label']}] exited. Restarting (Count: {$processes[$index]['restart_count']})...";
                            $logBuffers[$index][] = $msg;
                            $logBuffers[-1][] = $msg;

                            $process = proc_open($spec['command'], $descriptors, $processPipes, ROOTPATH);
                            if (is_resource($process)) {
                                stream_set_blocking($processPipes[1], false);
                                stream_set_blocking($processPipes[2], false);

                                $processes[$index]['process'] = $process;
                                $processes[$index]['status'] = 'running';
                                $pipes[$stdoutKey] = $processPipes[1];
                                $pipes[$stderrKey] = $processPipes[2];
                            } else {
                                $msg = "\033[1;31m[system]\033[0m Failed to restart: [{$spec['label']}]";
                                $logBuffers[$index][] = $msg;
                                $logBuffers[-1][] = $msg;
                                unset($processes[$index]);
                            }
                        } else {
                            $msg = "\033[1;33m[system]\033[0m Process [{$spec['label']}] exited with code {$status['exitcode']}";
                            $logBuffers[$index][] = $msg;
                            $logBuffers[-1][] = $msg;
                            unset($processes[$index]);
                        }
                        $lastDraw = 0;
                    }
                }

                // Refresh TUI
                $now = microtime(true);
                if ($now - $lastDraw > 0.15) {
                    $this->drawDashboard($processes, $logBuffers, $activeTab);
                    $lastDraw = $now;
                }
            }
        } finally {
            if ($this->originalStty) {
                shell_exec('stty ' . $this->originalStty);
            }
            echo "\033[?25h"; // Restore cursor

            foreach ($processes as $spec) {
                if ($spec['process']) {
                    $this->killProcess($spec);
                }
            }
            echo "\033[2J\033[H";
        }
    }

    private function drawDashboard(array $processes, array $logBuffers, int $activeTab): void
    {
        $width = (int) shell_exec('tput cols') ?: 80;
        $height = (int) shell_exec('tput lines') ?: 24;

        echo "\033[H";

        echo "\033[1;36m" . str_pad(" JENGO DEVELOPMENT CONSOLE ", $width, "=", STR_PAD_BOTH) . "\033[0m" . PHP_EOL;

        $tabs = [];
        $allSelected = ($activeTab === -1) ? "\033[1;30;42m [0: All] \033[0m" : "\033[1;37m [0: All] \033[0m";
        $tabs[] = $allSelected;

        foreach ($processes as $idx => $spec) {
            $key = $idx + 1;
            $selected = ($activeTab === $idx) ? "\033[1;30;42m [{$key}: {$spec['label']}] \033[0m" : "\033[1;37m [{$key}: {$spec['label']}] \033[0m";
            $tabs[] = $selected;
        }
        echo implode(" ", $tabs) . PHP_EOL;
        echo str_repeat("-", $width) . PHP_EOL;

        if ($activeTab >= 0 && isset($processes[$activeTab])) {
            $spec = $processes[$activeTab];
            $pid = 0;
            if ($spec['process']) {
                $statusInfo = proc_get_status($spec['process']);
                $pid = $statusInfo ? (int) $statusInfo['pid'] : 0;
            }
            $mem = $this->getProcessMemory($pid);
            $restartStr = $spec['restart_count'] > 0 ? " (Restarts: {$spec['restart_count']})" : "";
            echo "Active Process: \033[1;{$spec['color']}m{$spec['label']}\033[0m | Status: {$spec['status']}{$restartStr} | Mem: {$mem}" . PHP_EOL;
        } else {
            $summary = [];
            foreach ($processes as $spec) {
                $statusColor = $spec['status'] === 'running' ? '32' : '31';
                $summary[] = "\033[1;{$spec['color']}m{$spec['label']}\033[0m: \033[{$statusColor}m{$spec['status']}\033[0m";
            }
            echo "Processes Summary: " . implode(" | ", $summary) . PHP_EOL;
        }
        echo str_repeat("-", $width) . PHP_EOL;

        $headerHeight = 6;
        $footerHeight = 3;
        $logWindowHeight = $height - $headerHeight - $footerHeight;
        if ($logWindowHeight < 2) {
            $logWindowHeight = 2;
        }

        $activeLogs = $logBuffers[$activeTab] ?? [];
        $linesToDraw = array_slice($activeLogs, -$logWindowHeight);

        while (count($linesToDraw) < $logWindowHeight) {
            $linesToDraw[] = '';
        }

        foreach ($linesToDraw as $line) {
            $plainLine = preg_replace('/\e\[[0-9;]*m/', '', $line);
            if (strlen($plainLine) > $width) {
                echo substr($line, 0, $width + (strlen($line) - strlen($plainLine))) . PHP_EOL;
            } else {
                echo str_pad($line, $width) . PHP_EOL;
            }
        }

        echo str_repeat("=", $width) . PHP_EOL;
        echo "\033[1;30;47m Controls: [0/a] Show All | [1-9] Switch Tab | [c] Clear Log | [r] Restart Active | [q] Quit \033[0m" . PHP_EOL;
    }

    private function getProcessMemory(int $pid): string
    {
        if ($pid <= 0) {
            return 'N/A';
        }
        $output = shell_exec("ps -p {$pid} -o rss=");
        if ($output) {
            $kb = (int) trim($output);
            if ($kb > 1024) {
                return round($kb / 1024, 1) . ' MB';
            }
            return $kb . ' KB';
        }
        return 'N/A';
    }
}

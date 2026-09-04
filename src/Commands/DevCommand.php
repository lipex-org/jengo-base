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
        '--format' => 'Output format: stream, json, compact, tui. Defaults to stream.',
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
    private bool $inAlternateScreen = false;

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

    /**
     * Restore terminal cursor, screen buffer, and stty settings.
     */
    private function restoreTerminal(): void
    {
        // 1. Unconditionally restore cursor visibility
        echo "\033[?25h";

        // 2. Exit alternate screen buffer if in TUI mode
        if ($this->inAlternateScreen) {
            echo "\033[?1049l";
            $this->inAlternateScreen = false;
        }

        // 3. Restore stty settings
        if ($this->originalStty) {
            @shell_exec('stty ' . escapeshellarg(trim($this->originalStty)) . ' 2>/dev/null');
            $this->originalStty = null;
        } else {
            @shell_exec('stty sane 2>/dev/null || stty echo icanon 2>/dev/null');
        }
    }

    public function run(array $params)
    {
        // Default format is 'stream'
        $format = CLI::getOption('format') ?? 'stream';
        if ($format === 'default') {
            $format = 'stream';
        }

        $activeProcesses = [];

        // Register shutdown hook to guarantee cursor and terminal restoration on exit
        register_shutdown_function(function () use (&$activeProcesses) {
            $this->restoreTerminal();
            if (is_array($activeProcesses)) {
                foreach ($activeProcesses as $spec) {
                    if (!empty($spec['process'])) {
                        $this->killProcess($spec);
                    }
                }
            }
        });

        // Register async signal handlers if pcntl is available
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            $exitHandler = function () use (&$activeProcesses) {
                $this->restoreTerminal();
                if (is_array($activeProcesses)) {
                    foreach ($activeProcesses as $spec) {
                        if (!empty($spec['process'])) {
                            $this->killProcess($spec);
                        }
                    }
                }
                exit(0);
            };

            if (defined('SIGINT')) {
                @pcntl_signal(SIGINT, $exitHandler);
            }
            if (defined('SIGTERM')) {
                @pcntl_signal(SIGTERM, $exitHandler);
            }
            if (defined('SIGHUP')) {
                @pcntl_signal(SIGHUP, $exitHandler);
            }
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
        if (!isset($spec['process']) || !is_resource($spec['process'])) {
            return;
        }

        $status = proc_get_status($spec['process']);
        if ($status && !empty($status['pid'])) {
            $pid = (int) $status['pid'];
            if (function_exists('posix_kill')) {
                // Send TERM to process group (using negative PID kills parent and grandchildren)
                @posix_kill(-$pid, SIGTERM);
            }
        }

        @proc_terminate($spec['process']);
        @proc_close($spec['process']);
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
                unset($spec);

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

                    if (!empty($spec['watch'])) {
                        $currentHash = $this->getWatchState($spec['watch']);
                        if ($currentHash !== $spec['watch_hash']) {
                            $this->logMessage($format, 'system', '33', "Changes detected in watched directories for [{$spec['label']}]. Restarting...");
                            $this->killProcess($spec);

                            $processes[$index]['watch_hash'] = $currentHash;
                            $processes[$index]['restart_count'] = 0;

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
            $this->restoreTerminal();
            foreach ($processes as $spec) {
                if (!empty($spec['process'])) {
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
     * Reads a single key or ANSI escape sequence from non-blocking STDIN.
     */
    private function readKey(): ?string
    {
        $c = fread(STDIN, 1);
        if ($c === false || $c === '') {
            return null;
        }

        if ($c === "\033") {
            // Escape sequence (e.g. arrow keys, page up/down)
            $seq = fread(STDIN, 4);
            if ($seq === '[A') return 'UP';
            if ($seq === '[B') return 'DOWN';
            if ($seq === '[C') return 'RIGHT';
            if ($seq === '[D') return 'LEFT';
            if ($seq === '[5~') return 'PAGE_UP';
            if ($seq === '[6~') return 'PAGE_DOWN';
            if ($seq === '[H' || $seq === '[1~') return 'HOME';
            if ($seq === '[F' || $seq === '[4~') return 'END';
            return 'ESC';
        }

        return $c;
    }

    /**
     * Wrap ANSI text lines cleanly across terminal width without cutting off characters.
     *
     * @return string[]
     */
    private function wrapAnsiText(string $text, int $width): array
    {
        if ($width <= 15) {
            return [$text];
        }

        $plain = preg_replace('/\e\[[0-9;]*m/', '', $text);
        if (mb_strwidth($plain) <= $width) {
            return [$text];
        }

        $wrapped = [];
        $words = explode(' ', $text);
        $currentLine = '';
        $currentPlainLen = 0;

        foreach ($words as $word) {
            $wordPlain = preg_replace('/\e\[[0-9;]*m/', '', $word);
            $wordLen = mb_strwidth($wordPlain);

            if ($currentPlainLen === 0) {
                if ($wordLen > $width) {
                    $chars = mb_str_split($word);
                    $chunk = '';
                    $chunkLen = 0;
                    foreach ($chars as $char) {
                        $cLen = mb_strwidth($char);
                        if ($chunkLen + $cLen > $width) {
                            $wrapped[] = $chunk;
                            $chunk = '  ↳ ' . $char;
                            $chunkLen = 4 + $cLen;
                        } else {
                            $chunk .= $char;
                            $chunkLen += $cLen;
                        }
                    }
                    if ($chunk !== '') {
                        $currentLine = $chunk;
                        $currentPlainLen = $chunkLen;
                    }
                } else {
                    $currentLine = $word;
                    $currentPlainLen = $wordLen;
                }
            } else {
                if ($currentPlainLen + 1 + $wordLen <= $width) {
                    $currentLine .= ' ' . $word;
                    $currentPlainLen += 1 + $wordLen;
                } else {
                    $wrapped[] = $currentLine;
                    $currentLine = '  ↳ ' . $word;
                    $currentPlainLen = 4 + $wordLen;
                }
            }
        }

        if ($currentLine !== '') {
            $wrapped[] = $currentLine;
        }

        return $wrapped;
    }

    /**
     * Interactive Terminal UI (TUI) Dashboard with smooth double-buffering, text wrapping, and scrolling.
     */
    private function runTui(array $commandsToRun)
    {
        $this->originalStty = shell_exec('stty -g 2>/dev/null');
        @shell_exec('stty -icanon -echo 2>/dev/null');
        stream_set_blocking(STDIN, false);

        // Enter Alternate Screen Buffer and hide cursor
        echo "\033[?1049h\033[?25l\033[2J\033[H";
        $this->inAlternateScreen = true;

        $processes = [];
        $pipes = [];
        $logBuffers = [];
        $scrollOffsets = [];
        $activeTab = -1;
        $maxBufferLines = 2000;

        $logBuffers[-1] = [];
        $scrollOffsets[-1] = 0;

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
                    'cached_mem' => 'N/A',
                    'last_mem_check' => 0,
                ];

                $logBuffers[$index] = [];
                $scrollOffsets[$index] = 0;

                if ($processes[$index]['status'] === 'running') {
                    $process = proc_open($cmdSpec['command'], $descriptors, $processPipes, ROOTPATH);
                    if (is_resource($process)) {
                        stream_set_blocking($processPipes[1], false);
                        stream_set_blocking($processPipes[2], false);
                        $processes[$index]['process'] = $process;
                        $pipes[$index * 2] = $processPipes[1];
                        $pipes[$index * 2 + 1] = $processPipes[2];
                    } else {
                        $msg = "\033[1;31m[system]\033[0m Failed to start process: [{$cmdSpec['label']}]";
                        $logBuffers[-1][] = $msg;
                    }
                }
            }

            $lastDraw = 0;
            $needsRedraw = true;

            // Dimensions cache to avoid spawning shell_exec on every frame
            $termWidth = 80;
            $termHeight = 24;
            $lastDimCheck = 0;

            while (!empty($processes)) {
                $now = microtime(true);

                // Update terminal dimensions every 1 second
                if ($now - $lastDimCheck > 1.0) {
                    $cols = (int) @shell_exec('tput cols 2>/dev/null');
                    $lines = (int) @shell_exec('tput lines 2>/dev/null');
                    if ($cols > 10) $termWidth = $cols;
                    if ($lines > 5) $termHeight = $lines;
                    $lastDimCheck = $now;
                }

                // Keystroke processing
                $key = $this->readKey();
                if ($key !== null) {
                    $needsRedraw = true;

                    if ($key === 'q') {
                        break;
                    } elseif ($key === 'a' || $key === '0') {
                        $activeTab = -1;
                    } elseif (is_numeric($key)) {
                        $idx = (int) $key - 1;
                        if (isset($processes[$idx])) {
                            $activeTab = $idx;
                        }
                    } elseif ($key === 'c') {
                        $logBuffers[$activeTab] = [];
                        $scrollOffsets[$activeTab] = 0;
                    } elseif ($key === 'r') {
                        if ($activeTab >= 0 && isset($processes[$activeTab])) {
                            $spec = $processes[$activeTab];
                            if ($spec['process'] && is_resource($spec['process'])) {
                                $this->killProcess($spec);
                            }
                        }
                    } elseif ($key === 'UP' || $key === 'k') {
                        // Scroll up 1 line
                        $scrollOffsets[$activeTab] = ($scrollOffsets[$activeTab] ?? 0) + 1;
                    } elseif ($key === 'DOWN' || $key === 'j') {
                        // Scroll down 1 line
                        $scrollOffsets[$activeTab] = max(0, ($scrollOffsets[$activeTab] ?? 0) - 1);
                    } elseif ($key === 'PAGE_UP' || $key === 'u') {
                        // Scroll up half page
                        $scrollOffsets[$activeTab] = ($scrollOffsets[$activeTab] ?? 0) + max(1, (int) floor($termHeight / 2));
                    } elseif ($key === 'PAGE_DOWN' || $key === 'd') {
                        // Scroll down half page
                        $scrollOffsets[$activeTab] = max(0, ($scrollOffsets[$activeTab] ?? 0) - max(1, (int) floor($termHeight / 2)));
                    } elseif ($key === 'HOME' || $key === 'g') {
                        // Scroll to top
                        $scrollOffsets[$activeTab] = count($logBuffers[$activeTab] ?? []);
                    } elseif ($key === 'END' || $key === 'G') {
                        // Jump to bottom (live stream)
                        $scrollOffsets[$activeTab] = 0;
                    } elseif ($key === 'h') {
                        $cores = is_file('/proc/cpuinfo') ? (int) @shell_exec('grep -c ^processor /proc/cpuinfo 2>/dev/null') : 1;
                        $load = function_exists('sys_getloadavg') ? implode(', ', sys_getloadavg()) : 'N/A';
                        $freeMem = @shell_exec('free -h 2>/dev/null | grep Mem | awk \'{print $4}\'') ?: 'N/A';
                        $jengoVer = 'v1.0.0 (Base)';

                        $diagMsg = "\n\033[1;35m--- JENGO ARCHITECTURE ENGINE DIAGNOSTICS ---\033[0m\n" .
                                   "  \033[36mOS Kernel:\033[0m     " . php_uname('s') . " (" . php_uname('r') . ")\n" .
                                   "  \033[36mCPU Cores:\033[0m     {$cores} Core(s) (Load Avg: {$load})\n" .
                                   "  \033[36mFree memory:\033[0m   " . trim((string) $freeMem) . "\n" .
                                   "  \033[36mPHP Version:\033[0m   " . PHP_VERSION . " (" . PHP_SAPI . ")\n" .
                                   "  \033[36mJengo Engine:\033[0m  {$jengoVer}\n" .
                                   "\033[1;35m---------------------------------------------\033[0m\n";

                        foreach (explode("\n", rtrim($diagMsg)) as $line) {
                            $wrappedLines = $this->wrapAnsiText($line, $termWidth);
                            foreach ($wrappedLines as $wLine) {
                                $logBuffers[$activeTab][] = $wLine;
                                if ($activeTab !== -1) {
                                    $logBuffers[-1][] = $wLine;
                                }
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
                            $needsRedraw = true;
                        }
                    }
                }
                unset($spec);

                // Read output streams
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
                    if (stream_select($read, $write, $except, 0, 30000) > 0) {
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

                            $data = fread($pipe, 8192);
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
                                $wrappedLines = $this->wrapAnsiText($formattedLine, $termWidth);

                                foreach ($wrappedLines as $wLine) {
                                    $logBuffers[$index][] = $wLine;
                                    $logBuffers[-1][] = $wLine;

                                    if (count($logBuffers[$index]) > $maxBufferLines) {
                                        array_shift($logBuffers[$index]);
                                    }
                                    if (count($logBuffers[-1]) > $maxBufferLines) {
                                        array_shift($logBuffers[-1]);
                                    }
                                }
                            }
                            $needsRedraw = true;
                        }
                    }
                } else {
                    usleep(30000);
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
                            $processes[$index]['restart_count'] = 0;

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
                            $needsRedraw = true;
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
                                            $wrappedLines = $this->wrapAnsiText($formattedLine, $termWidth);
                                            foreach ($wrappedLines as $wLine) {
                                                $logBuffers[$index][] = $wLine;
                                                $logBuffers[-1][] = $wLine;
                                            }
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
                        $needsRedraw = true;
                    }
                }

                // Smooth Double-Buffered Dashboard Render (throttled to ~10 FPS or when dirty)
                if ($needsRedraw || ($now - $lastDraw > 0.1)) {
                    $this->drawDashboard($processes, $logBuffers, $scrollOffsets, $activeTab, $termWidth, $termHeight);
                    $lastDraw = $now;
                    $needsRedraw = false;
                }
            }
        } finally {
            $this->restoreTerminal();

            foreach ($processes as $spec) {
                if (!empty($spec['process'])) {
                    $this->killProcess($spec);
                }
            }
        }
    }

    /**
     * Renders the full TUI frame in a single atomic string to eliminate screen flickering.
     */
    private function drawDashboard(
        array &$processes,
        array $logBuffers,
        array &$scrollOffsets,
        int $activeTab,
        int $width,
        int $height
    ): void {
        $frame = [];

        // 1. Title bar
        $titleText = " JENGO DEVELOPMENT CONSOLE ";
        $frame[] = "\033[1;36m" . str_pad($titleText, max(1, $width), "=", STR_PAD_BOTH) . "\033[0m\033[K";

        // 2. Tabs bar
        $tabs = [];
        $allSelected = ($activeTab === -1) ? "\033[1;30;42m [0: All] \033[0m" : "\033[1;37m [0: All] \033[0m";
        $tabs[] = $allSelected;

        foreach ($processes as $idx => $spec) {
            $key = $idx + 1;
            $selected = ($activeTab === $idx) ? "\033[1;30;42m [{$key}: {$spec['label']}] \033[0m" : "\033[1;37m [{$key}: {$spec['label']}] \033[0m";
            $tabs[] = $selected;
        }
        $frame[] = implode(" ", $tabs) . "\033[K";
        $frame[] = str_repeat("-", max(1, $width)) . "\033[K";

        // 3. Process Status / Memory summary
        if ($activeTab >= 0 && isset($processes[$activeTab])) {
            $spec = &$processes[$activeTab];
            $now = microtime(true);
            if ($now - ($spec['last_mem_check'] ?? 0) > 2.0) {
                $pid = 0;
                if ($spec['process']) {
                    $statusInfo = proc_get_status($spec['process']);
                    $pid = $statusInfo ? (int) $statusInfo['pid'] : 0;
                }
                $spec['cached_mem'] = $this->getProcessMemory($pid);
                $spec['last_mem_check'] = $now;
            }
            $mem = $spec['cached_mem'] ?? 'N/A';
            $restartStr = $spec['restart_count'] > 0 ? " (Restarts: {$spec['restart_count']})" : "";
            $frame[] = "Active: \033[1;{$spec['color']}m{$spec['label']}\033[0m | Status: {$spec['status']}{$restartStr} | Mem: {$mem}\033[K";
            unset($spec);
        } else {
            $summary = [];
            foreach ($processes as $spec) {
                $statusColor = $spec['status'] === 'running' ? '32' : '31';
                $summary[] = "\033[1;{$spec['color']}m{$spec['label']}\033[0m: \033[{$statusColor}m{$spec['status']}\033[0m";
            }
            $frame[] = "Processes: " . implode(" | ", $summary) . "\033[K";
        }
        $frame[] = str_repeat("-", max(1, $width)) . "\033[K";

        // 4. Calculate exact visible log height to strictly fit within terminal height
        $headerLinesCount = 5;
        $footerLinesCount = 2;
        $logWindowHeight = max(2, $height - $headerLinesCount - $footerLinesCount);

        $activeLogs = $logBuffers[$activeTab] ?? [];
        $totalLogs = count($activeLogs);

        // Calculate bounded scroll offset
        $offset = $scrollOffsets[$activeTab] ?? 0;
        $maxOffset = max(0, $totalLogs - $logWindowHeight);
        $offset = max(0, min($offset, $maxOffset));
        $scrollOffsets[$activeTab] = $offset;

        if ($offset === 0) {
            $linesToDraw = array_slice($activeLogs, -$logWindowHeight);
        } else {
            $linesToDraw = array_slice($activeLogs, -($logWindowHeight + $offset), $logWindowHeight);
        }

        // Pad empty lines if logs are fewer than logWindowHeight
        while (count($linesToDraw) < $logWindowHeight) {
            $linesToDraw[] = '';
        }

        // Draw log lines with clear-to-eol (\033[K)
        foreach ($linesToDraw as $line) {
            $frame[] = $line . "\033[K";
        }

        // 5. Footer & Controls Bar
        $frame[] = str_repeat("=", max(1, $width)) . "\033[K";

        $scrollIndicator = "";
        if ($offset > 0) {
            $scrollIndicator = "\033[1;33m[SCROLL: -{$offset} lines | Press End/G for live]\033[0m ";
        }

        $controlsText = "{$scrollIndicator}\033[1;30;47m [0/a] All | [1-9] Tab | [↑/↓, u/d] Scroll | [c] Clear | [r] Restart | [h] Diag | [q] Quit \033[0m\033[K";
        $frame[] = $controlsText;

        // Atomic output with cursor at home position (0,0) without trailing newline to avoid auto-scroll
        echo "\033[H" . implode("\n", $frame);
    }

    private function getProcessMemory(int $pid): string
    {
        if ($pid <= 0) {
            return 'N/A';
        }
        $output = @shell_exec("ps -p {$pid} -o rss= 2>/dev/null");
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

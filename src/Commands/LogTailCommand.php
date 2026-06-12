<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;

class LogTailCommand extends BaseCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:tail-log';
    protected $description = 'Stream application logs in real-time.';
    protected $usage = 'jengo:tail-log [options]';

    protected $options = [
        '--level' => 'Filter by log level (e.g., error,warning,info)',
        '--date' => 'Target a specific date (YYYY-MM-DD)',
        '--yesterday' => 'Target yesterday\'s log file',
        '--search' => 'Search for a specific keyword in log messages',
        '--lines' => 'Number of lines to show initially (default: 20)',
    ];

    private array $levelColors = [
        'CRITICAL' => 'red',
        'ERROR' => 'light_red',
        'WARNING' => 'yellow',
        'INFO' => 'cyan',
        'DEBUG' => 'dark_gray',
        'NOTICE' => 'blue',
        'ALERT' => 'light_red',
        'EMERGENCY' => 'red',
    ];

    /**
     * For testing purposes to break the infinite loop.
     */
    protected bool $once = false;

    public function run(array $params)
    {
        $date = $this->resolveDate();
        $this->tail($date);
    }

    protected function getOption(string $name)
    {
        return CLI::getOption($name);
    }

    protected function resolveDate(): string
    {
        if ($this->getOption('yesterday')) {
            return Time::yesterday()->format('Y-m-d');
        }

        if ($date = $this->getOption('date')) {
            return (string) $date;
        }

        return Time::now()->format('Y-m-d');
    }

    protected function tail(string $date): void
    {
        $levels = $this->getOption('level') ? explode(',', strtolower((string) $this->getOption('level'))) : [];
        $search = (string) ($this->getOption('search') ?? '');
        $lines = (int) ($this->getOption('lines') ?? 20);

        $autoSwitch = !CLI::getOption('date') && !CLI::getOption('yesterday');
        $currentDate = $date;
        $filePath = $this->getLogPath($currentDate);

        if (!$this->validateLogFile($filePath, $currentDate)) {
            return;
        }

        CLI::write("Tailing: " . CLI::color($filePath, 'cyan'));
        CLI::write("Press Ctrl+C to stop.", 'yellow');
        CLI::newLine();

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            CLI::error("Could not open log file.");
            return;
        }

        // 1. Show initial lines
        $this->printInitialLines($filePath, $lines, $levels, $search);

        // 2. Seek to end for live tailing
        fseek($handle, 0, SEEK_END);
        $lastPos = ftell($handle);

        while (true) {
            if ($this->once && $lastPos > 0) {
                break;
            }

            // Check for date change if we are in auto-switch mode (tailing "today")
            if ($autoSwitch) {
                $today = Time::now()->format('Y-m-d');
                if ($today !== $currentDate) {
                    $newPath = $this->getLogPath($today);
                    if (file_exists($newPath)) {
                        fclose($handle);
                        CLI::newLine();
                        CLI::write("--- Day changed to {$today}, switching log file ---", 'cyan');
                        $currentDate = $today;
                        $filePath = $newPath;
                        $handle = fopen($filePath, 'rb');
                        $lastPos = 0;
                        fseek($handle, 0, SEEK_SET);
                        CLI::write("Tailing: " . CLI::color($filePath, 'cyan'));
                        CLI::newLine();
                    }
                }
            }

            clearstatcache(true, $filePath);
            $currentSize = file_exists($filePath) ? filesize($filePath) : 0;

            if ($currentSize > $lastPos) {
                fseek($handle, $lastPos);
                while (($line = fgets($handle)) !== false) {
                    $this->processLine($line, $levels, $search);
                }
                $lastPos = ftell($handle);
            } elseif ($currentSize < $lastPos) {
                fseek($handle, 0, SEEK_SET);
                $lastPos = 0;
                CLI::write("--- Log file truncated or rotated ---", 'yellow');
            }

            if ($this->once) {
                break;
            }

            usleep(500000);
        }

        if (is_resource($handle)) {
            fclose($handle);
        }
    }

    private function getLogPath(string $date): string
    {
        return WRITEPATH . 'logs' . DIRECTORY_SEPARATOR . "log-{$date}.log";
    }

    private function validateLogFile(string $filePath, string $date): bool
    {
        if (!is_dir(WRITEPATH . 'logs')) {
            CLI::error("Logs directory not found at: " . WRITEPATH . 'logs');
            return false;
        }

        if (!file_exists($filePath)) {
            CLI::error("Log file not found: log-{$date}.log");
            CLI::write("Make sure logging is enabled in your Config/Logger.php", 'yellow');
            return false;
        }

        return true;
    }

    protected function printInitialLines(string $filePath, int $count, array $filterLevels, string $searchKeyword): void
    {
        if ($count <= 0) {
            return;
        }

        // Read the file and get the last N lines.
        // We read it as a whole to simplify things, daily logs are usually manageable.
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        $lastLines = array_slice($lines, -$count);

        foreach ($lastLines as $line) {
            // We append a newline because processLine expects it for raw output
            $this->processLine($line . PHP_EOL, $filterLevels, $searchKeyword);
        }

        if (!empty($lastLines)) {
            CLI::write(str_repeat('-', 20), 'dark_gray');
        }
    }

    protected function processLine(string $line, array $filterLevels, string $searchKeyword): void
    {
        $parsed = $this->parseLine($line);

        if (!$parsed) {
            // Unparseable line (e.g., stack trace or continuation)
            // If no filters are active, we always show it.
            // If filters ARE active, it's technically ambiguous, but for now we show it 
            // to avoid missing critical info like stack traces.
            CLI::print($line);
            return;
        }

        // Apply Level Filter
        if (!empty($filterLevels) && !in_array(strtolower($parsed['level']), $filterLevels, true)) {
            return;
        }

        // Apply Search Filter
        if (!empty($searchKeyword) && !str_contains(strtolower($line), strtolower($searchKeyword))) {
            return;
        }

        $this->formatOutput($parsed);
    }

    protected function parseLine(string $line): ?array
    {
        // CI4 log format: LEVEL - YYYY-MM-DD HH:MM:SS --> Message
        // Regex: /^([A-Z]+)\s+-\s+(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\s+-->\s+(.*)$/
        if (preg_match('/^([A-Z]+)\s+-\s+(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\s+-->\s+(.*)$/s', trim($line), $matches)) {
            return [
                'level' => $matches[1],
                'date' => $matches[2],
                'message' => $matches[3],
            ];
        }

        return null;
    }

    protected function formatOutput(array $parsed): void
    {
        $color = $this->levelColors[$parsed['level']] ?? 'white';

        CLI::print(CLI::color(sprintf('%-9s', $parsed['level']), $color));
        CLI::print(CLI::color($parsed['date'] . ' ', 'dark_gray'));
        CLI::print('--> ');
        CLI::print($parsed['message'] . PHP_EOL);
    }
}

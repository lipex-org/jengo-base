<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Tail;

use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;
use Jengo\Base\Commands\Core\AbstractVariant;

class LogVariant extends AbstractVariant
{
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

    protected bool $once = false;

    /**
     * Stored parameters/options for option retrieval.
     */
    protected array $params = [];

    public static function name(): string
    {
        return 'log';
    }

    public static function description(): string
    {
        return 'Stream application logs in real-time.';
    }

    public function options(): array
    {
        return [
            '--level' => 'Filter by log level (e.g., error,warning,info)',
            '--date' => 'Target a specific date (YYYY-MM-DD)',
            '--yesterday' => 'Target yesterday\'s log file',
            '--search' => 'Search for a specific keyword in log messages',
            '--lines' => 'Number of lines to show initially (default: 20)',
            '--time' => 'Time to tail the log file for (e.g., 30s, 5m, 1h)',
        ];
    }

    public function run(array $params): void
    {
        $this->params = $params;
        $date = $this->resolveDate();
        $this->tail($date);
    }

    /**
     * Helper to get options from CLI or passed parameters (useful for tests).
     */
    protected function getOption(string $name): string|bool|null
    {
        $optionKey = $name;
        foreach ($this->params as $index => $param) {
            if ($index === $optionKey) {
                return $param;
            }

            if ($param === $optionKey) {
                return true;
            }
        }

        $val = CLI::getOption($name);

        return $val;
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

        $timeOpt = $this->getOption('time');

        $duration = 0;
        if ($timeOpt !== null) {
            if (preg_match('/^(\d+)([smh]?)$/i', $timeOpt, $matches)) {
                $value = (int) $matches[1];
                $unit = strtolower($matches[2]);
                $duration = match ($unit) {
                    'm' => $value * 60,
                    'h' => $value * 3600,
                    default => $value,
                };
            }
        }

        $autoSwitch = !$this->getOption('date') && !$this->getOption('yesterday');
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

        $this->printInitialLines($filePath, $lines, $levels, $search);

        fseek($handle, 0, SEEK_END);
        $lastPos = ftell($handle);

        $startTime = time();

        while (true) {
            if ($this->once && $lastPos > 0) {
                break;
            }

            if ($duration > 0 && (time() - $startTime) >= $duration) {
                CLI::write("Tailing completed after {$timeOpt}.", 'green');
                break;
            }

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

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        $lastLines = array_slice($lines, -$count);

        foreach ($lastLines as $line) {
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
            CLI::print($line);
            return;
        }

        if (!empty($filterLevels) && !in_array(strtolower($parsed['level']), $filterLevels, true)) {
            return;
        }

        if (!empty($searchKeyword) && !str_contains(strtolower($line), strtolower($searchKeyword))) {
            return;
        }

        $this->formatOutput($parsed);
    }

    protected function parseLine(string $line): ?array
    {
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

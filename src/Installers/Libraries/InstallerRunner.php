<?php 

declare(strict_types=1);

namespace Jengo\Base\Installers\Libraries;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\InstallerInterface;
use Jengo\Base\Installers\Repositories\InstallerRepository;

class InstallerRunner
{
    protected InstallerTracker $tracker;
    protected bool $skipTracking = false;
    protected bool $force = false;

    protected array $skipped = [];
    protected array $ran = [];

    public function __construct(
        ?InstallerTracker $tracker = null,
        bool $skipTracking = false,
        bool $force = false
    ) {
        $this->tracker = $tracker ?? new InstallerTracker();
        $this->skipTracking = $skipTracking;
        $this->force = $force;
    }

    public function run(InstallerInterface $installer): void
    {
        $name = $installer::name();
        
        // 1. Resolve and run dependencies first
        $dependencies = $installer::dependencies();
        foreach ($dependencies as $depName) {
            $depInstaller = InstallerRepository::find($depName);
            if ($depInstaller) {
                $this->run($depInstaller);
            }
        }

        $displayName = ucfirst(str_replace(['_', '-'], ' ', $name));

        if (!$this->force && !$this->skipTracking && $this->tracker->isInstalled($name)) {
            // Check if it was already reported in this session to avoid double printing
            if (!in_array($name, $this->skipped) && !in_array($name, $this->ran)) {
                $this->skipped[] = $name;
                CLI::write("  " . CLI::color('○', 'yellow') . " Skipping " . CLI::color($displayName, 'cyan') . " (Already installed)");
            }
            return;
        }

        if (!$this->force && !$installer->shouldRun()) {
            if (!in_array($name, $this->skipped) && !in_array($name, $this->ran)) {
                $this->skipped[] = $name;
                CLI::write("  " . CLI::color('○', 'yellow') . " Skipping " . CLI::color($displayName, 'cyan'));
                CLI::write("    " . CLI::color('↳ ' . $installer::reasonForSkipping(), 'dark_gray'));

                if (! $this->skipTracking) {
                    $this->tracker->markInstalled($name);
                }
            }
            return;
        }

        CLI::write("  " . CLI::color('●', 'light_cyan') . " Processing " . CLI::color($displayName, 'cyan') . "...");
        CLI::write("    " . str_repeat('┈', 40), 'dark_gray');

        $installer->install();

        if (! $this->skipTracking) {
            $this->tracker->markInstalled($name);
        }

        CLI::write("    " . str_repeat('┈', 40), 'dark_gray');
        CLI::write("  " . CLI::color('✔', 'green') . " " . CLI::color($displayName, 'green') . " successfully configured.");
        CLI::newLine();

        $this->ran[] = $name;
    }

    public function report(): array
    {
        return [
            'ran' => array_unique($this->ran),
            'skipped' => array_unique($this->skipped),
        ];
    }
}

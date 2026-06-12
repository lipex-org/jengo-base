<?php

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SetupCommand extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Jengo';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'jengo:setup';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Jengo System Integration Hub - Connect external ecosystems.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'jengo:setup [name]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [
        'name' => 'Optional: The name of a specific setup to run (e.g., auth, inertia)'
    ];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--framework' => 'The framework to use (vue, react, svelte)',
        '--kit' => 'The starter kit to use',
        '--client-dir' => 'The directory for client files',
        '--pm' => 'The package manager to use (pnpm, npm, yarn)',
        '--tailwind' => 'Include Tailwind CSS (y/n)',
        '--yes' => 'Answer yes to all prompts',
    ];

    public function run(array $params)
    {
        $name = $params[0] ?? CLI::getSegment(2);

        if ($name) {
            $setup = \Jengo\Base\Setups\Repositories\SetupRepository::find($name);
            if (!$setup) {
                CLI::error("Setup Integration [$name] not found.");
                return;
            }
            $setup->setup();
            return;
        }

        // Trigger the Setup Hub Wizard
        $this->renderWizard();
    }

    private function renderWizard(): void
    {
        $setups = \Jengo\Base\Setups\Repositories\SetupRepository::all();
        $options = [];
        $setupMap = [];

        CLI::newLine();
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::write("    JENGO SYSTEM INTEGRATION HUB", 'light_cyan');
        CLI::write("    Connect your application to external ecosystems", 'dark_gray');
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::newLine();

        foreach ($setups as $index => $setup) {
            $nameCol = str_pad($setup::name(), 12);
            $options[$index] = sprintf(
                "%s  %s  %s",
                CLI::color($nameCol, 'cyan'),
                CLI::color('·', 'dark_gray'),
                $setup::description()
            );
            $setupMap[$index] = $setup;
        }

        if (empty($options)) {
            CLI::write('    No system integrations found.', 'yellow');
            return;
        }

        $selections = CLI::promptByMultipleKeys(CLI::color('  Select integrations to establish (ID)', 'light_cyan'), $options);

        if (empty($selections)) {
            CLI::newLine();
            CLI::write('  No selections made. Exiting.', 'light_gray');
            return;
        }

        CLI::newLine();
        CLI::write("  " . str_repeat('┈', 60), 'dark_gray');
        CLI::newLine();

        $reverseMap = array_flip($options);

        foreach ($selections as $selection) {
            $index = $reverseMap[$selection];
            $setup = $setupMap[$index];
            $setup->setup();
            CLI::newLine();
        }

        CLI::write("  " . CLI::color('All selected integrations have been processed.', 'light_cyan'));
        CLI::newLine();
    }
}

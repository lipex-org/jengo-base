<?php

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class BlueprintInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'blueprint';
    }

    public static function description(): string
    {
        return 'Sets up the core UI architecture (Layouts, Partials, Home Page)';
    }

    public static function reasonForSkipping(): string
    {
        return 'Blueprint has already been established.';
    }

    public function shouldRun(): bool
    {
        return !is_dir(APPPATH . 'Views/layouts');
    }

    public function install(): void
    {
        $this->addRun();

        // 1. setup the blueprint
        //  a. create partials
        $this->createPartials();

        //  b. create base layout
        $this->createBaseLayout();

        //  c. create app layout
        $this->createAppLayout();

        // d. create the home page
        $this->createHomePage();

        // e. replace home controller
        if ($this->wantsToUpdateHomeController()) {
            $this->editHomeController();
        }

        CLI::write('Blueprint installed successfully.', 'green');
    }

    private function createPartials(): void
    {
        $dir = APPPATH . "Views/layouts/partials/";
        $files = [
            'header.layout.partial',
            'footer.layout.partial'
        ];

        $content = [
            'header.layout.partial' => '<!-- Header file - Use to add any tags in the head tag -->',
            'footer.layout.partial' => '<!-- Footer file - Use to add any links to be placed at the end of the body tag  -->'
        ];

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        foreach ($files as $file) {
            $filename = "$dir{$file}.php";

            if (!file_exists($filename)) {
                file_put_contents($filename, $content[$file]);
            }
        }
    }

    private function createBaseLayout(): void
    {
        $io = new MockInputOutput();

        CLI::setInputOutput($io);

        command('make:layout base --base');

        CLI::resetInputOutput();
    }

    private function createAppLayout(): void
    {
        $io = new MockInputOutput();

        CLI::setInputOutput($io);

        command('make:layout app');

        CLI::resetInputOutput();
    }

    private function createHomePage(): void
    {
        $io = new MockInputOutput();

        CLI::setInputOutput($io);

        command('make:page home');

        CLI::resetInputOutput();
    }

    private function editHomeController(): void
    {
        $path = APPPATH . "Controllers/Home.php";
        $welcomePagePath = APPPATH . "Views/welcome_message.php";

        $content = <<<'PHP'
<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return page('home');
    }
}
PHP;


        if (file_exists($path)) {
            unlink($path);
        }

        if (file_exists($welcomePagePath)) {
            unlink($welcomePagePath);
        }

        file_put_contents($path, $content);
        CLI::write("Home Controller updated.", 'green');
    }

    private function wantsToUpdateHomeController(): bool
    {
        if (CLI::getOption('yes')) {
            return true;
        }

        return CLI::prompt('Do you want to update the Home Controller?', ['y', 'n'], 'in_list[y,n]') === 'y';
    }
}

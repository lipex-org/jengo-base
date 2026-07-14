<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\Fabricator;
use CodeIgniter\Test\Mock\MockInputOutput;
use Config\Database;
use Tests\Support\CommandTestCase;
use Tests\Support\Models\UserModel;
use function PHPUnit\Framework\assertTrue;

class BaseTest extends CommandTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();

        helper('filesystem');
        $this->loadDependencies();
        $this->migrateDatabase();
        $this->generateData();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->regressDatabase();
        $this->migrateDatabase();
    }

    public function testMakeEventCommand(): void
    {
        command('jengo:make event example');

        $dir = APPPATH . "Events";
        $path = "$dir/Example.php";

        assertTrue(file_exists($path));

        delete_files($dir, true);
    }

    public function testMakeLayoutCommand(): void
    {
        command('jengo:make layout example --layout app');

        $dir = APPPATH . "Views/layouts";
        $path = "$dir/example.layout.php";

        assertTrue(file_exists($path));

        delete_files($dir, true);
    }

    public function testMakeBaseLayoutCommand(): void
    {
        command('jengo:make layout base --base name');

        $dir = APPPATH . "Views/layouts";
        $path = "$dir/base.layout.php";

        assertTrue(file_exists($path));

        delete_files($dir, true);
    }

    public function testMakePageCommand(): void
    {
        command('jengo:make page user');

        $dir = APPPATH . "Views/pages";
        $path = "$dir/user.page.php";

        assertTrue(file_exists($path));

        delete_files($dir, true);

    }

    public function testMakeFormCommand(): void
    {
        command('jengo:make form UserForm');

        $dir = APPPATH . "Forms";
        $path = "$dir/UserForm.php";

        assertTrue(file_exists($path));

        delete_files($dir, true);
    }

    public function ntestSetupCommand(): void
    {
        $this->io->setInputs(['y', 'y']);

        command('jengo:setup');

        $basePath = APPPATH . 'Views';
        $layoutsPath = "$basePath/layouts";
        $pagesPath = "$basePath/pages";
        $partialsPath = "$layoutsPath/partials";

        $dirs = [
            'partials' => [
                'path' => $partialsPath,
                'files' => [
                    'footer.layout.partial',
                    'header.layout.partial',
                ]
            ],

            'layouts' => [
                'path' => $layoutsPath,
                'files' => [
                    'app.layout',
                    'base.layout'
                ]
            ],

            'pages' => [
                'path' => $pagesPath,
                'files' => [
                    'home.page',
                ]
            ],
        ];

        $this->assertFileExists("$partialsPath/" . $dirs['partials']['files'][0] . ".php");
        $this->assertFileExists("$partialsPath/" . $dirs['partials']['files'][1] . ".php");
        $this->assertFileExists("$layoutsPath/" . $dirs['layouts']['files'][0] . ".php");
        $this->assertFileExists("$layoutsPath/" . $dirs['layouts']['files'][1] . ".php");
        $this->assertFileExists("$pagesPath/" . $dirs['pages']['files'][0] . ".php");

        foreach ($dirs as $dir) {
            $path = $dir['path'];
            foreach ($dir['files'] as $file) {
                $cpath = "$path/$file.php";
                if (file_exists($cpath)) {
                    unlink($cpath);
                }
            }

            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    public function testModelFacade(): void
    {
        helper('Jengo\Base\Helpers\jengo');

        $users = model_of(UserModel::class)::findAll();

        $this->assertIsArray($users);
    }

    private function generateData(): void
    {
        $db = Database::connect('tests');
        $users = (new Fabricator(UserModel::class))->make(10);

        $db->table('users')->insertBatch($users);
    }
}
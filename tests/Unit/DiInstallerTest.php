<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Base\Installers\DiInstaller;
use Tests\Support\CommandTestCase;

final class DiInstallerTest extends CommandTestCase
{
    private string $tempControllersDir;
    private string $tempBaseController;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempControllersDir = APPPATH . 'Controllers/';
        if (! is_dir($this->tempControllersDir)) {
            mkdir($this->tempControllersDir, 0777, true);
        }
        $this->tempBaseController = $this->tempControllersDir . 'BaseController.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempBaseController)) {
            unlink($this->tempBaseController);
        }
        parent::tearDown();
    }

    public function testMetadata(): void
    {
        $this->assertSame('di', DiInstaller::name());
        $this->assertNotEmpty(DiInstaller::description());
        $this->assertNotEmpty(DiInstaller::reasonForSkipping());
        $this->assertTrue((new DiInstaller())->shouldRun());
    }

    public function testInstallInjectsHasContainerIntoBaseController(): void
    {
        $dummyBaseController = <<<'PHP'
<?php

namespace App\Controllers;

use CodeIgniter\Controller;

abstract class BaseController extends Controller
{
    protected $helpers = [];
}
PHP;
        file_put_contents($this->tempBaseController, $dummyBaseController);

        $installer = new DiInstaller();
        $installer->install();

        $updated = file_get_contents($this->tempBaseController);

        $this->assertStringContainsString('HasContainer', $updated);
    }
}

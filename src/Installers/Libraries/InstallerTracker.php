<?php 

declare(strict_types=1);

namespace Jengo\Base\Installers\Libraries;

use Jengo\Base\Support\JengoDirectory;

class InstallerTracker
{
    protected string $file;
    protected bool $isCustomPath = false;

    public function __construct(?string $file = null)
    {
        if ($file !== null && (str_starts_with($file, '/') || str_starts_with($file, '\\') || str_contains($file, DIRECTORY_SEPARATOR))) {
            $this->file = $file;
            $this->isCustomPath = true;
        } else {
            $this->file = $file ?? 'installers.php';
        }
    }

    public function all(): array
    {
        if ($this->isCustomPath) {
            if (! file_exists($this->file)) {
                return [];
            }
            return require $this->file;
        }

        return JengoDirectory::readPhpArray($this->file, []);
    }

    public function isInstalled(string $name): bool
    {
        return ($this->all()[$name]['installed'] ?? false) === true;
    }

    public function markInstalled(string $name): void
    {
        $data = $this->all();

        $data[$name] = [
            'installed'    => true,
            'installed_at' => gmdate('c'),
        ];

        if ($this->isCustomPath) {
            $dir = dirname($this->file);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $export = var_export($data, true);
            file_put_contents($this->file, "<?php\n\nreturn {$export};\n");
            return;
        }

        JengoDirectory::writePhpArray($this->file, $data);
    }
}

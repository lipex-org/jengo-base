<?php

declare(strict_types=1);

namespace Jengo\Base\Config;

use CodeIgniter\Config\BaseConfig;
use Jengo\Base\Installers\BlueprintInstaller;
use Jengo\Base\Installers\MaizzleInstaller;
use Jengo\Base\Installers\ViteInstaller;

class Jengo extends BaseConfig
{
    /**
     * Global response modifier class for validation failures.
     * Precedence: per-request (#[Validate(..., modifier: ...)]) > per-form (FormHandler::$modifier) > this global config.
     *
     * @var class-string<\Jengo\Base\Contracts\ResponseModifierInterface>|null
     */
    public ?string $validationResponseModifier = \Jengo\Base\Validation\Modifiers\UniversalModifier::class;

    /**
     * List of installer class names.
     *
     * @var class-string<\Jengo\Base\Installers\Contracts\InstallerInterface>[]
     */
    public array $installers = [
        ViteInstaller::class,
        BlueprintInstaller::class,
        MaizzleInstaller::class,
    ];

    /**
     * List of target IDEs/Agents to generate rules for.
     * Supported: 'antigravity', 'cursor', 'cline', 'copilot'
     *
     * @var string[]
     */
    public array $aiTargets = [];

    /**
     * Inertia configuration options.
     *
     * @var array{
     *     version?: string|int|callable|null,
     *     rootView?: string,
     *     sharedProps?: array<string, mixed>|callable|null,
     *     redirectConversion?: bool,
     * }
     */
    public array $inertia = [
        'version'            => null,
        'rootView'           => 'app',
        'sharedProps'        => null,
        'redirectConversion' => true,
    ];

    /**
     * Vite configuration options.
     *
     * @var array{
     *     entrypoints?: string[],
     *     searchPaths?: string[],
     * }
     */
    public array $vite = [
        'entrypoints' => [],
        'searchPaths' => [
            APPPATH,
            ROOTPATH . 'resources',
        ],
    ];

    /**
     * Sqids configuration options.
     *
     * @var array{
     *     alphabet?: string,
     *     minLength?: int,
     * }
     */
    public array $sqids = [
        'alphabet'  => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        'minLength' => 10,
    ];
}


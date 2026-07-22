<?php

declare(strict_types=1);

namespace Jengo\Base\Config;

use CodeIgniter\Config\BaseConfig;

class Dev extends BaseConfig
{
    /**
     * Custom dev commands to run concurrently.
     * Each entry should be an array with keys:
     * - 'command': string (e.g. 'php spark queue:work')
     * - 'label': string (e.g. 'Queue')
     * - 'color': string (Optional ANSI color code like '32', '35', '33', etc.)
     *
     * @var array<int, array{command: string, label: string, color?: string}>
     */
    public array $commands = [
        [
            'command' => 'php spark serve',
            'label'   => 'Server',
            'color'   => '32', // Green
        ]
    ];
}

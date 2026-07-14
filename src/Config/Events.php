<?php

declare(strict_types=1);

namespace Jengo\Base\Config;

use CodeIgniter\Events\Events;
use Jengo\Base\Libraries\ModuleDiscovery;
use Jengo\Base\Vite\Repositories\ViteRepository;

Events::on('pre_system', static function () {
    ModuleDiscovery::discoverAndRegister();
});

Events::on('pre_command', static function () {
    ModuleDiscovery::discoverAndRegister();
});

Events::on('post_controller_constructor', static function () {
    (new ViteRepository())->scan();
});

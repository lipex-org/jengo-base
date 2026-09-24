<?php

declare(strict_types=1);

namespace Jengo\Base\Config;

use CodeIgniter\Events\Events;
use Jengo\Base\Events\AppLifecycle;
use Jengo\Base\Vite\Repositories\ViteRepository;

// Unified initialization on both Web and CLI entrypoints
Events::on('pre_system', [AppLifecycle::class, 'boot']);
Events::on('pre_command', [AppLifecycle::class, 'boot']);

Events::on('post_controller_constructor', static function () {
    (new ViteRepository())->scan();
});

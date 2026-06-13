<?php

namespace Jengo\Base;

use Jengo\Base\Vite\Repositories\ViteRepository;
use Jengo\Base\Vite\ViteService;

function vite_tags()
{
    helper('Jengo\Base\Helpers\jengo');

    $vite_server_url = env('VITE_DEV_SERVER', 'http://localhost:5173');
    $entrypoints = (new ViteRepository())->getFullConfig()->entrypoints;

    $service = new ViteService();

    return $service->generateTags(
        $entrypoints,
        isDevelopment(),
        $vite_server_url,
        FCPATH . 'dist/.vite/manifest.json',
        'dist/'
    );
}

function vite_version()
{
    return (new ViteService())->getVersion(FCPATH . 'dist/.vite/manifest.json');
}
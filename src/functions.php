<?php

namespace Jengo\Base;

use Jengo\Base\Vite\Repositories\ViteRepository;
use mindplay\vite\Manifest;

function vite_tags()
{
    helper('Jengo\Base\Helpers\jengo');

    $vite_server_url = trim(env('VITE_DEV_SERVER'), "/") . "/";

    $vite = new Manifest(
        isDevelopment(),
        FCPATH . "dist/.vite/manifest.json",
        isDevelopment() ? $vite_server_url : base_url("dist/")
    );

    $entrypoints = (new ViteRepository())->getFullConfig()->entrypoints;

    $tags = $vite->createTags(...$entrypoints);

    $output = $tags->preload . PHP_EOL
        . $tags->css . PHP_EOL
        . $tags->js . PHP_EOL;

    // React Refresh Preamble for Development
    if (isDevelopment()) {
        $needsReactRefresh = false;
        foreach ($entrypoints as $entry) {
            if (str_ends_with($entry, '.tsx') || str_ends_with($entry, '.jsx')) {
                $needsReactRefresh = true;
                break;
            }
        }

        if ($needsReactRefresh) {
            $preamble = <<<HTML
    <script type="module">
        import RefreshRuntime from '{$vite_server_url}@react-refresh'
        RefreshRuntime.injectIntoGlobalHook(window)
        window.$RefreshReg$ = () => {}
        window.$RefreshSig$ = () => (type) => type
        window.__vite_plugin_react_preamble_installed__ = true
    </script>
HTML;
            $output = $preamble . PHP_EOL . $output;
        }
    }

    return $output;
}
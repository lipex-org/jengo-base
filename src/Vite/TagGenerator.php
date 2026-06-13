<?php

declare(strict_types=1);

namespace Jengo\Base\Vite;

/**
 * Generates HTML tags for scripts, styles, and preloads.
 */
class TagGenerator
{
    /**
     * Generates a <script type="module"> tag.
     */
    public function makeScriptTag(string $url, array $attributes = []): string
    {
        $attrString = $this->buildAttributes($attributes);
        return sprintf('<script type="module" src="%s"%s></script>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), $attrString);
    }

    /**
     * Generates a <link rel="stylesheet"> tag.
     */
    public function makeStyleTag(string $url, array $attributes = []): string
    {
        $attrString = $this->buildAttributes($attributes);
        return sprintf('<link rel="stylesheet" href="%s"%s>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), $attrString);
    }

    /**
     * Generates a <link rel="modulepreload"> tag.
     */
    public function makePreloadTag(string $url): string
    {
        return sprintf('<link rel="modulepreload" href="%s">', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Generates the React Refresh preamble script.
     */
    public function makeReactRefreshPreamble(string $devServerUrl): string
    {
        $url = rtrim($devServerUrl, '/') . '/@react-refresh';

        return sprintf(
            '<script type="module">
                import {injectIntoGlobalHook} from "%s"
                injectIntoGlobalHook(window)
                window.$RefreshReg$ = () => {}
                window.$RefreshSig$ = () => (type) => type
            </script>',
            $url
        );
    }

    /**
     * Builds HTML attribute string from an array.
     */
    protected function buildAttributes(array $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }

        $html = [];
        foreach ($attributes as $key => $value) {
            if (is_int($key)) {
                $html[] = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            } elseif ($value === true) {
                $html[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            } elseif ($value !== false && $value !== null) {
                $html[] = sprintf('%s="%s"', htmlspecialchars($key, ENT_QUOTES, 'UTF-8'), htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
            }
        }

        return count($html) > 0 ? ' ' . implode(' ', $html) : '';
    }
}

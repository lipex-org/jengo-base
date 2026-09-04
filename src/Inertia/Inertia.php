<?php

declare(strict_types=1);

namespace Jengo\Base\Inertia;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Inertia\Protocol\Inertia as ProtocolInertia;
use Inertia\Protocol\Props\Always;
use Inertia\Protocol\Props\Defer;
use Inertia\Protocol\Props\Lazy;
use Inertia\Protocol\Props\Mergeable;
use Inertia\Protocol\Props\Once;
use Inertia\Protocol\Props\Scroll;
use Jengo\Base\Config\Jengo;

class Inertia
{
    /**
     * Create an Inertia response builder.
     *
     * @param string $component JavaScript page component name
     * @param array<string, mixed> $props Page props
     */
    public static function render(string $component, array $props = []): InertiaResponse
    {
        $config = config('Jengo') ?? new Jengo();
        $inertiaConfig = $config->inertia ?? [];

        // 1. Sync global asset version if set in config
        $version = $inertiaConfig['version'] ?? null;
        if ($version !== null) {
            ProtocolInertia::version($version);
        }

        // 2. Sync global shared props if set in config
        $sharedProps = $inertiaConfig['sharedProps'] ?? null;
        if ($sharedProps !== null) {
            $shared = is_callable($sharedProps)
                ? $sharedProps()
                : (array) $sharedProps;
            ProtocolInertia::share($shared);
        }

        $protocolResponse = ProtocolInertia::render($component, $props);

        return new InertiaResponse($protocolResponse);
    }

    /**
     * Generate an external location visit redirect (409 Conflict with X-Inertia-Location).
     */
    public static function location(string $url): ResponseInterface
    {
        $decision = ProtocolInertia::location($url);
        $response = Services::response();
        foreach ($decision->headers as $name => $val) {
            $response->setHeader($name, $val);
        }

        return $response->setStatusCode(409)->setBody($url);
    }

    /**
     * Generate a URL fragment redirect response (409 Conflict with X-Inertia-Redirect).
     */
    public static function redirectWithFragment(string $url): ResponseInterface
    {
        $decision = ProtocolInertia::redirectWithFragment($url);
        $response = Services::response();
        foreach ($decision->headers as $name => $val) {
            $response->setHeader($name, $val);
        }

        return $response->setStatusCode(409)->setBody($url);
    }

    /**
     * Generate a Precognition validation success response (204 No Content).
     */
    public static function precognitionSuccess(): ResponseInterface
    {
        $decision = ProtocolInertia::precognitionSuccess();
        $response = Services::response();
        foreach ($decision->headers as $name => $val) {
            $response->setHeader($name, $val);
        }

        return $response->setStatusCode(204)->setBody('');
    }

    public static function always(mixed $value): Always
    {
        return ProtocolInertia::always($value);
    }

    public static function lazy(mixed $callback): Lazy
    {
        return ProtocolInertia::lazy($callback);
    }

    public static function defer(mixed $callback, string $group = 'default', bool $rescue = false): Defer
    {
        return ProtocolInertia::defer($callback, $group, $rescue);
    }

    public static function once(mixed $callback, ?int $expiresAt = null): Once
    {
        return ProtocolInertia::once($callback, $expiresAt);
    }

    public static function merge(mixed $value, ?string $matchOn = null): Mergeable
    {
        return ProtocolInertia::merge($value, $matchOn);
    }

    public static function prepend(mixed $value, ?string $matchOn = null): Mergeable
    {
        return ProtocolInertia::prepend($value, $matchOn);
    }

    public static function deepMerge(mixed $value, ?string $matchOn = null): Mergeable
    {
        return ProtocolInertia::deepMerge($value, $matchOn);
    }

    public static function scroll(
        mixed $data,
        string $pageName = 'page',
        ?int $previousPage = null,
        ?int $nextPage = null,
        int $currentPage = 1,
        bool $reset = false
    ): Scroll {
        return ProtocolInertia::scroll($data, $pageName, $previousPage, $nextPage, $currentPage, $reset);
    }

    public static function share(array|string $key, mixed $value = null): void
    {
        ProtocolInertia::share($key, $value);
    }

    public static function getShared(?string $key = null, mixed $default = null): mixed
    {
        return ProtocolInertia::getShared($key, $default);
    }

    public static function flushShared(): void
    {
        ProtocolInertia::flushShared();
    }

    public static function version(mixed $version): void
    {
        ProtocolInertia::version($version);
    }

    public static function getVersion(): string
    {
        return ProtocolInertia::getVersion();
    }
}

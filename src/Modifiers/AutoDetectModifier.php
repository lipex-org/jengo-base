<?php

declare(strict_types=1);

namespace Jengo\Base\Modifiers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Base\Contracts\ResponseModifierInterface;

class AutoDetectModifier implements ResponseModifierInterface
{
    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        // 1. Check Inertia header
        if ($request->hasHeader('X-Inertia') || $request->getHeaderLine('X-Inertia') !== '') {
            if (class_exists(\Jengo\Inertia\Inertia::class)) {
                \Jengo\Inertia\Inertia::flash('errors', $errors);
            }
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        // 2. Check JSON / AJAX
        if ($request->isAJAX() ||
            str_contains((string) $request->getHeaderLine('Accept'), 'application/json') ||
            str_contains((string) $request->getHeaderLine('Content-Type'), 'application/json')) {
            return (new JsonModifier())->modifyValidationFailed($errors, $request, $options);
        }

        // 3. Fallback to standard web view redirect
        return (new StandardViewModifier())->modifyValidationFailed($errors, $request, $options);
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Validation\Modifiers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Base\Contracts\ResponseModifierInterface;

class InertiaModifier implements ResponseModifierInterface
{
    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        if (!class_exists(\Jengo\Inertia\Inertia::class)) {
            throw new \RuntimeException(
                'The jengo/inertia package is required to use InertiaModifier. Run: composer require jengo/inertia'
            );
        }

        \Jengo\Inertia\Inertia::flash('errors', $errors);
        session()->setFlashdata('errors', $errors);

        return redirect()->back()->withInput()->with('errors', $errors);
    }
}

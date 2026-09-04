<?php

declare(strict_types=1);

namespace Jengo\Base\Modifiers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Base\Contracts\ResponseModifierInterface;

class StandardViewModifier implements ResponseModifierInterface
{
    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        return redirect()->back()->withInput()->with('errors', $errors);
    }
}

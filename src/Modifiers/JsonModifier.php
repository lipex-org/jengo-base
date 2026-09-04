<?php

declare(strict_types=1);

namespace Jengo\Base\Modifiers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Base\Contracts\ResponseModifierInterface;

class JsonModifier implements ResponseModifierInterface
{
    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        return Services::response()
            ->setStatusCode(422)
            ->setJSON([
                'status'  => 'error',
                'message' => 'The given data was invalid.',
                'errors'  => $errors,
            ]);
    }
}

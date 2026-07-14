<?php

declare(strict_types=1);

namespace Jengo\Base\Validation;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

abstract class FormHandler
{
    /**
     * Validation rules.
     */
    protected array $rules = [];

    /**
     * Validation messages.
     */
    protected array $messages = [];

    /**
     * The last validated form handler instance.
     */
    private static ?FormHandler $lastInstance = null;

    /**
     * Validated data.
     */
    protected ?array $validatedData = null;

    /**
     * Validation errors.
     */
    protected ?array $errors = null;

    /**
     * Set the last validated form handler instance.
     */
    public static function setLastInstance(FormHandler $handler): void
    {
        self::$lastInstance = $handler;
    }

    /**
     * Get the last validated form handler instance.
     */
    public static function getLastInstance(): ?FormHandler
    {
        return self::$lastInstance;
    }

    /**
     * Get validation rules.
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Get validation messages.
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Gather raw data from the request.
     */
    public function getData(RequestInterface $request): array
    {
        return array_merge(
            $request->getGet() ?? [],
            $request->getPost() ?? [],
            is_array($request->getJSON(true)) ? $request->getJSON(true) : []
        );
    }

    /**
     * Run validation.
     */
    public function validate(?RequestInterface $request = null): bool
    {
        $request ??= Services::request();
        $validation = Services::validation();
        
        // Reset the validation service state/errors before running
        $validation->reset();
        
        $validation->setRules($this->getRules(), $this->getMessages());

        $data = $this->getData($request);

        if (! $validation->withRequest($request)->run($data)) {
            $this->errors = $validation->getErrors();
            $this->validatedData = null;

            return false;
        }

        // Only return keys defined in the rules to prevent extra parameter injection
        $this->validatedData = array_intersect_key($data, $this->getRules());
        $this->errors = null;

        return true;
    }

    /**
     * Return validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors ?? [];
    }

    /**
     * Return validated data.
     */
    public function validated(): array
    {
        return $this->validatedData ?? [];
    }

    /**
     * Handle failed validation by returning redirect response or JSON response.
     */
    public function redirectOrJson(array $errors, RequestInterface $request): ResponseInterface
    {
        $isJson = $request->isAJAX() ||
                  str_contains((string) $request->getHeaderLine('Accept'), 'application/json') ||
                  str_contains((string) $request->getHeaderLine('Content-Type'), 'application/json');

        if ($isJson) {
            return Services::response()
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'The given data was invalid.',
                    'errors'  => $errors,
                ])
                ->setStatusCode(422);
        }

        return redirect()->back()->withInput()->with('errors', $errors);
    }
}

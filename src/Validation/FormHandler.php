<?php

declare(strict_types=1);

namespace Jengo\Base\Validation;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Validation\Validation;
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
     * Fields that should be deobfuscated using Sqids.
     */
    protected array $obfuscatedFields = [];

    /**
     * Map of input keys to route parameter indices (e.g. ['id' => 0]).
     */
    protected array $routeParams = [];

    /**
     * The last validated form handler instance.
     */
    private static ?FormHandler $lastInstance = null;

    /**
     * Validated data.
     */
    protected ?ValidatedData $validatedData = null;

    /**
     * Validation errors.
     */
    protected ?array $errors = null;

    /**
     * Incoming request
     * @var IncomingRequest
     */
    protected RequestInterface $request;

    /**
     * Validator
     * @var Validation
     */
    protected Validation $validator;

    public function __construct(?RequestInterface $request = null)
    {
        $this->request = $request ?? Services::request();
        $this->validator = Services::validation();
    }

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
     * Get route parameter mapping.
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Get obfuscated fields.
     */
    public function getObfuscatedFields(): array
    {
        return $this->obfuscatedFields;
    }

    /**
     * Run validation.
     */
    public function validate(): bool
    {
        // Reset the validation service state/errors before running
        $this->validator->reset();

        $this->validator->setRules($this->getRules(), $this->getMessages());

        // Extract groups
        $get = $this->request->getGet() ?? [];
        $post = $this->request->getPost() ?? [];
        $json = is_array($this->request->getJSON(true)) ? $this->request->getJSON(true) : [];
        $routerData = [];

        if (!empty($this->routeParams)) {
            $router = Services::router();
            $params = $router->params();
            foreach ($this->routeParams as $key => $index) {
                if (isset($params[$index])) {
                    $routerData[$key] = $params[$index];
                }
            }
        }

        // Run validation on flat merged data
        $flatData = array_merge($get, $post, $json, $routerData);

        if (!$this->validator->withRequest($this->request)->run($flatData)) {
            $this->errors = $this->validator->getErrors();
            $this->validatedData = null;

            return false;
        }

        // Deobfuscate fields in each group if configured (after validation)
        if (!empty($this->obfuscatedFields)) {
            helper('jengo');
            foreach ($this->obfuscatedFields as $field) {
                if (isset($get[$field]) && is_string($get[$field]) && $get[$field] !== '') {
                    $unhashed = sqids_unhash($get[$field]);
                    if ($unhashed !== null) {
                        $get[$field] = $unhashed;
                    }
                }
                if (isset($post[$field]) && is_string($post[$field]) && $post[$field] !== '') {
                    $unhashed = sqids_unhash($post[$field]);
                    if ($unhashed !== null) {
                        $post[$field] = $unhashed;
                    }
                }
                if (isset($json[$field]) && is_string($json[$field]) && $json[$field] !== '') {
                    $unhashed = sqids_unhash($json[$field]);
                    if ($unhashed !== null) {
                        $json[$field] = $unhashed;
                    }
                }
                if (isset($routerData[$field]) && is_string($routerData[$field]) && $routerData[$field] !== '') {
                    $unhashed = sqids_unhash($routerData[$field]);
                    if ($unhashed !== null) {
                        $routerData[$field] = $unhashed;
                    }
                }
            }
        }

        // Filter and build ValidatedData DTO containing only rule-defined keys
        $rulesKeys = array_flip(array_keys($this->getRules()));
        $this->validatedData = new ValidatedData(
            array_intersect_key($get, $rulesKeys),
            array_intersect_key($post, $rulesKeys),
            array_intersect_key($json, $rulesKeys),
            array_intersect_key($routerData, $rulesKeys)
        );
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
    public function validated(): ValidatedData
    {
        return $this->validatedData ?? new ValidatedData();
    }

    /**
     * Handle failed validation by returning redirect response or JSON response.
     * @param array $errors
     * @param IncomingRequest $request
     */
    public function redirectOrJson(array $errors, RequestInterface $request): ResponseInterface
    {
        // Trigger event to allow other packages (e.g. Inertia) to override the failed response
        $holder = new FormFailedResponseHolder($errors, $request);
        Events::trigger('jengo.form.failed', $holder);

        if ($holder->getResponse() !== null) {
            return $holder->getResponse();
        }

        $isJson = $request->isAJAX() ||
            str_contains((string) $request->getHeaderLine('Accept'), 'application/json') ||
            str_contains((string) $request->getHeaderLine('Content-Type'), 'application/json');

        if ($isJson) {
            return Services::response()
                ->setJSON([
                    'status' => 'error',
                    'message' => 'The given data was invalid.',
                    'errors' => $errors,
                ])
                ->setStatusCode(422);
        }

        return redirect()->back()->withInput()->with('errors', $errors);
    }
}

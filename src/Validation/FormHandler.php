<?php

declare(strict_types=1);

namespace Jengo\Base\Validation;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Validation\Validation;
use Config\Services;
use Jengo\Base\Contracts\ResponseModifierInterface;

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
     * Explicit response modifier class to use for this form handler.
     * @var class-string<ResponseModifierInterface>|null
     */
    protected ?string $modifier = null;

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
        if (empty($post) && ! empty($_POST)) {
            $post = $_POST;
        }

        $json = [];
        $rawBody = (string) $this->request->getBody();
        if ($rawBody !== '') {
            $trimmed = trim($rawBody);
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                try {
                    $decoded = json_decode($trimmed, true);
                    if (is_array($decoded)) {
                        $json = $decoded;
                    }
                } catch (\Throwable) {
                    // Ignore malformed JSON body
                }
            }
        }

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

        if (!$this->validator->run($flatData)) {
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
     * Set a custom response modifier for this form handler instance.
     *
     * @param string|ResponseModifierInterface $modifier
     */
    public function setModifier(string|ResponseModifierInterface $modifier): self
    {
        $this->modifier = is_string($modifier) ? $modifier : get_class($modifier);

        return $this;
    }

    /**
     * Get the configured response modifier class name for this form handler.
     *
     * @return class-string<ResponseModifierInterface>|null
     */
    public function getModifier(): ?string
    {
        return $this->modifier;
    }

    /**
     * Handle failed validation by delegating to the active or explicit response modifier.
     *
     * @param array<string, string> $errors
     * @param RequestInterface $request
     */
    public function redirectOrJson(array $errors, RequestInterface $request): ResponseInterface
    {
        // 1. Trigger read-only event for telemetry/listeners
        $holder = new FormFailedResponseHolder($errors, $request);
        Events::trigger('jengo.form.failed', $holder);

        // If a legacy subscriber explicitly provided a response, respect it
        if ($holder->getResponse() !== null) {
            return $holder->getResponse();
        }

        // 2. Delegate to ResponseHandler using active or instance-specific modifier
        return response_handler()->validationFailed($errors, $request, $this->modifier, [
            'handler' => static::class,
        ]);
    }
}

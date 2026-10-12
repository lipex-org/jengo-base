<?php

declare(strict_types=1);

namespace Jengo\Base\Support;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Base\Contracts\ResponseModifierInterface;

class ResponseHandler
{
    protected ?ResponseModifierInterface $modifier = null;

    public function __construct(?ResponseModifierInterface $modifier = null)
    {
        if ($modifier !== null) {
            $this->modifier = $modifier;
        }
    }

    /**
     * Get the active or specified response modifier.
     */
    public function getModifier(?string $overrideClass = null): ResponseModifierInterface
    {
        if ($overrideClass !== null && class_exists($overrideClass)) {
            return new $overrideClass();
        }

        if ($this->modifier !== null) {
            return $this->modifier;
        }

        $configModifier = config('Jengo')->validationResponseModifier
            ?? config('Jengo')->responseModifier
            ?? config('App')->validationResponseModifier
            ?? config('App')->responseModifier
            ?? \Jengo\Base\Validation\Modifiers\UniversalModifier::class;

        if (class_exists($configModifier)) {
            return $this->modifier = new $configModifier();
        }

        return $this->modifier = new \Jengo\Base\Validation\Modifiers\UniversalModifier();
    }

    /**
     * Set a custom response modifier for the current runtime.
     */
    public function setModifier(ResponseModifierInterface|string $modifier): self
    {
        if (is_string($modifier)) {
            $this->modifier = new $modifier();
        } else {
            $this->modifier = $modifier;
        }

        return $this;
    }

    /**
     * Render a standardized validation failure response using the resolved modifier.
     */
    public function validationFailed(
        array $errors,
        ?RequestInterface $request = null,
        ?string $modifierClass = null,
        array $options = []
    ): ResponseInterface {
        $req = $request ?? Services::request();
        $modifier = $this->getModifier($modifierClass);

        return $modifier->modifyValidationFailed($errors, $req, $options);
    }
}

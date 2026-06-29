<?php

declare(strict_types=1);

namespace Jengo\Base\View\Decorators;

use CodeIgniter\View\ViewDecoratorInterface;

class EmailTemplateDecorator implements ViewDecoratorInterface
{
    /**
     * Decorates the rendered HTML by replacing % key % and % key.nested % placeholders.
     */
    public static function decorate(string $html): string
    {
        $renderer = service('renderer');

        // Check if the current view being rendered is an email template
        $isEmail = false;
        try {
            $ref = new \ReflectionClass($renderer);
            if ($ref->hasProperty('renderVars')) {
                $prop = $ref->getProperty('renderVars');
                $prop->setAccessible(true);
                $renderVars = $prop->getValue($renderer);
                $view = $renderVars['view'] ?? '';
                if (str_contains($view, 'emails/')) {
                    $isEmail = true;
                }
            }
        } catch (\Throwable $e) {
            // Failsafe: fall back to checking if the HTML contains Maizzle placeholders
        }

        if (!$isEmail && !preg_match('/%\s*[a-zA-Z0-9_\-\.]+\s*%/', $html)) {
            return $html;
        }

        $viewData = $renderer->getData();
        helper('array');

        return preg_replace_callback('/%\s*([a-zA-Z0-9_\-\.]+)\s*%/', function ($matches) use ($viewData) {
            $key = trim($matches[1]);
            
            // Search using CodeIgniter 4's array dot helper
            $value = dot_array_search($key, $viewData);

            if ($value !== null) {
                return is_scalar($value) ? (string) $value : json_encode($value);
            }

            return '';
        }, $html);
    }
}

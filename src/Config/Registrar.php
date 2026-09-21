<?php

declare(strict_types=1);

namespace Jengo\Base\Config;

class Registrar
{
    public static function Generators(): array
    {
        return [
            'views' => [
                'jengo:make' => [
                    'repo' => 'Jengo\Base\Commands\Generators\Views\repo.tpl.php',
                    'action' => 'Jengo\Base\Commands\Generators\Views\action.tpl.php',
                    'page' => 'Jengo\Base\Commands\Generators\Views\page.tpl.php',
                    'form' => 'Jengo\Base\Commands\Generators\Views\form.tpl.php',
                    'layout' => [
                        'main' => 'Jengo\Base\Commands\Generators\Views\Layouts\layout.tpl.php',
                        'base' => 'Jengo\Base\Commands\Generators\Views\Layouts\base.tpl.php'
                    ],
                    'event' => [
                        'event' => 'Jengo\Base\Commands\Generators\Views\Events\event.tpl.php',
                        'listener' => 'Jengo\Base\Commands\Generators\Views\Events\listener.tpl.php'
                    ]
                ],

            ]
        ];
    }
}

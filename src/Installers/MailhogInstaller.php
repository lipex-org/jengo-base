<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class MailhogInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'mailhog';
    }

    public static function description(): string
    {
        return 'Configure local email system using Mailhog';
    }

    public static function reasonForSkipping(): string
    {
        return 'Mailhog settings are already configured in .env.';
    }

    public function shouldRun(): bool
    {
        // Skip if already configured with standard SMTP port 1025
        $envFile = ROOTPATH . '.env';
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            if (str_contains($content, 'email.SMTPPort = 1025') || str_contains($content, 'email.SMTPPort = "1025"')) {
                return false;
            }
        }
        return true;
    }

    public function install(): void
    {
        $this->addRun();

        $this->env()
            ->addTitle('Mailhog Email Config')
            ->set('email.fromEmail', 'test@jengo.com')
            ->set('email.fromName', 'Jengo')
            ->set('email.protocol', 'smtp')
            ->set('email.SMTPHost', 'localhost')
            ->set('email.SMTPPort', '1025')
            ->set('email.SMTPUser', '')
            ->set('email.SMTPPass', '')
            ->set('email.SMTPCrypto', '')
            ->save();

        CLI::write('Mailhog SMTP settings successfully written to .env.', 'green');
    }
}

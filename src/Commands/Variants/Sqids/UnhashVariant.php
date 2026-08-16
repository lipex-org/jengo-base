<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Sqids;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Core\AbstractVariant;

class UnhashVariant extends AbstractVariant
{
    public static function name(): string
    {
        return 'unhash';
    }

    public static function description(): string
    {
        return 'Decode a Sqids hash back to an integer ID.';
    }

    public function arguments(): array
    {
        return [
            'hash' => 'The Sqids hash string to decode.',
        ];
    }

    public function run(array $params): void
    {
        helper('Jengo\Base\Helpers\jengo');

        $hash = array_shift($params);

        if ($hash === null || $hash === '') {
            CLI::error('Please provide a hash string to decode.');
            return;
        }

        $id = sqids_unhash($hash);

        if ($id === null) {
            CLI::error('Invalid hash or failed to decode.');
            return;
        }

        CLI::write("Hash: " . CLI::color($hash, 'yellow'));
        CLI::write("ID:   " . CLI::color((string) $id, 'green'));
    }
}

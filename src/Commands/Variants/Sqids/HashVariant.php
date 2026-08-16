<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Sqids;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Core\AbstractVariant;

class HashVariant extends AbstractVariant
{
    public static function name(): string
    {
        return 'hash';
    }

    public static function description(): string
    {
        return 'Obfuscate an integer ID using Sqids.';
    }

    public function arguments(): array
    {
        return [
            'id' => 'The integer ID to hash.',
        ];
    }

    public function run(array $params): void
    {
        helper('Jengo\Base\Helpers\jengo');

        $id = array_shift($params);

        if ($id === null) {
            CLI::error('Please provide an integer ID to hash.');
            return;
        }

        if (!is_numeric($id) || (int) $id < 0) {
            CLI::error('The ID must be a non-negative integer.');
            return;
        }

        $hash = sqids_hash((int) $id);

        if ($hash === null) {
            CLI::error('Failed to hash the provided ID.');
            return;
        }

        CLI::write("ID:   " . CLI::color($id, 'yellow'));
        CLI::write("Hash: " . CLI::color($hash, 'green'));
    }
}

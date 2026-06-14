<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Core;

use CodeIgniter\CLI\GeneratorTrait;

/**
 * Base class for Command Variants that generate files.
 */
abstract class AbstractGeneratorVariant extends AbstractVariant
{
    use GeneratorTrait {
        prepare as prepareTrait;
        buildPath as buildPathTrait;
    }

    /**
     * Executes the generation logic.
     */
    public function run(array $params): void
    {
        $this->generateClass($params);
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Base\Container\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ContainerException extends Exception implements ContainerExceptionInterface
{
}

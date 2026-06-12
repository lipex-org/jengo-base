<@php

declare(strict_types=1);

namespace {namespace};

use {repo_namespace}\{repo};

final class {class}
{
    private {repo} $repository;

    public function __construct(
    ) {
        $this->repository = new {repo}();
    }

    public function repo(): {repo}
    {
        return $this->repository;
    }
}

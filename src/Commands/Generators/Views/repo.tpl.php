<@php

declare(strict_types=1);

namespace {namespace};

use {model_namespace}\{model};
use Jengo\Base\Contracts\RepositoryInterface;

final class {class} implements RepositoryInterface
{
    private {model} $model;

    public function __construct(
    ) {
        $this->model = new {model}();
    }

    public function model(): {model}
    {
        return $this->model;
    }

    public function find(int $id): ?object
    {
        return $this->model->find($id);
    }

    public function findAll(): array
    {
        return $this->model->findAll();
    }

    public function save(array|object $entity): bool
    {
        return $this->model->save($entity);
    }

    public function insert(array|object $entity): bool|int|string
    {
        return $this->model->insert($entity);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->delete($id);
    }

    public function errors(): array
    {
        return $this->model->errors();
    }
}

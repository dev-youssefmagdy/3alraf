<?php

namespace App\Services;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Base for simple create/update/delete services. DTOs expose toArray() (column => value). */
abstract class CrudService
{
    public function __construct(protected RepositoryInterface $repo) {}

    public function create(object $dto): Model
    {
        return $this->repo->create($this->payload($dto));
    }

    public function update(Model $model, object $dto): Model
    {
        return $this->repo->update($model, $this->payload($dto, $model));
    }

    public function delete(Model $model): void
    {
        $this->guardDelete($model);
        $this->repo->delete($model);
    }

    public function find(int|string $id): Model
    {
        return $this->repo->findOrFail($id);
    }

    protected function payload(object $dto, ?Model $model = null): array
    {
        return $dto->toArray();
    }

    /** Throw a ValidationException to block deleting records still in use. */
    protected function guardDelete(Model $model): void {}

    protected function block(string $key, string $field = 'record'): never
    {
        throw ValidationException::withMessages([$field => __($key)]);
    }
}

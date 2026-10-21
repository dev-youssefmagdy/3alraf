<?php

namespace App\Services;

use App\Repositories\Contracts\CustomPageRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Custom page CRUD; manual (pinned) products are synced in order. */
class CustomPageService extends CrudService
{
    public function __construct(CustomPageRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }

    public function create(object $dto): Model
    {
        return DB::transaction(function () use ($dto) {
            $page = parent::create($dto);
            $this->sync($page, $dto->productIds);

            return $page;
        });
    }

    public function update(Model $model, object $dto): Model
    {
        return DB::transaction(function () use ($model, $dto) {
            parent::update($model, $dto);
            $this->sync($model, $dto->productIds);

            return $model;
        });
    }

    public function find(int|string $id): Model
    {
        return $this->repo->query()->with('products')->findOrFail($id);
    }

    private function sync(Model $page, array $ids): void
    {
        $page->products()->sync(collect($ids)->values()->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());
    }
}

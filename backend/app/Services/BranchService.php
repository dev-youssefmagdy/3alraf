<?php

namespace App\Services;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BranchService extends CrudService
{
    public function __construct(BranchRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }

    public function create(object $dto): Model
    {
        return DB::transaction(function () use ($dto) {
            $data = $dto->toArray();
            $data['is_default'] = $data['is_default'] || ! Branch::exists(); // first branch is always the default
            $this->clearDefaults($data['is_default'], null);

            return $this->repo->create($data);
        });
    }

    public function update(Model $model, object $dto): Model
    {
        return DB::transaction(function () use ($model, $dto) {
            $data = $dto->toArray();
            $data['is_default'] = $data['is_default'] || $model->is_default; // cannot un-default; pick another instead
            $this->clearDefaults($data['is_default'], $model->id);

            return $this->repo->update($model, $data);
        });
    }

    protected function guardDelete(Model $model): void
    {
        if ($model->is_default) {
            $this->block('ops.branch_default_delete');
        }
        if ($model->stockLevels()->where('on_hand', '!=', 0)->exists()) {
            $this->block('ops.branch_has_stock');
        }
    }

    private function clearDefaults(bool $isDefault, ?int $exceptId): void
    {
        if ($isDefault) {
            Branch::query()->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->update(['is_default' => false]);
        }
    }
}

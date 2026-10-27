<?php

namespace App\Repositories\Eloquent;

use App\Models\GrowthExperiment;
use App\Repositories\Contracts\GrowthExperimentRepositoryInterface;

class GrowthExperimentRepository extends BaseRepository implements GrowthExperimentRepositoryInterface
{
    protected string $model = GrowthExperiment::class;

    public function findWithResults(int $id): GrowthExperiment
    {
        return $this->query()->with('results')->findOrFail($id);
    }

    public function running(int $limit = 5): \Illuminate\Support\Collection
    {
        return $this->query()->with('results')->whereIn('status', ['running', 'completed', 'concluded', 'done'])->latest('id')->limit($limit)->get();
    }
}

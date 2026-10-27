<?php

namespace App\Repositories\Contracts;

interface GrowthExperimentRepositoryInterface extends RepositoryInterface
{
    public function findWithResults(int $id): \App\Models\GrowthExperiment;

    public function running(int $limit = 5): \Illuminate\Support\Collection;
}

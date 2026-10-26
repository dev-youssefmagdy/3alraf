<?php

namespace App\Repositories\Eloquent;

use App\Models\AiModel;
use App\Repositories\Contracts\AiModelRepositoryInterface;

class AiModelRepository extends BaseRepository implements AiModelRepositoryInterface
{
    protected string $model = AiModel::class;
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\AiProvider;
use App\Repositories\Contracts\AiProviderRepositoryInterface;

class AiProviderRepository extends BaseRepository implements AiProviderRepositoryInterface
{
    protected string $model = AiProvider::class;
}

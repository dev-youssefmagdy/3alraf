<?php

namespace App\Repositories\Eloquent;

use App\Models\MarketingApiToken;
use App\Repositories\Contracts\MarketingApiTokenRepositoryInterface;

class MarketingApiTokenRepository extends BaseRepository implements MarketingApiTokenRepositoryInterface
{
    protected string $model = MarketingApiToken::class;

    public function findByPrefix(string $prefix): ?MarketingApiToken
    {
        return MarketingApiToken::query()->where('token_prefix', $prefix)->first();
    }

    public function prefixExists(string $prefix): bool
    {
        return MarketingApiToken::query()->where('token_prefix', $prefix)->exists();
    }
}

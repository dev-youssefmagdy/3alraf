<?php

namespace App\Repositories\Contracts;

use App\Models\MarketingApiToken;

interface MarketingApiTokenRepositoryInterface extends RepositoryInterface
{
    public function findByPrefix(string $prefix): ?MarketingApiToken;

    public function prefixExists(string $prefix): bool;
}

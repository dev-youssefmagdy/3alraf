<?php

namespace App\Repositories\Contracts;

interface IntegrationClientRepositoryInterface extends RepositoryInterface
{
    public function findByPrefix(string $prefix): ?\App\Models\IntegrationClient;

    public function prefixExists(string $prefix): bool;
}

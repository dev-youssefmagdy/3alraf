<?php

namespace App\Repositories\Contracts;

use App\Models\QrCode;
use App\Models\TenantProduct;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface QrCodeRepositoryInterface extends RepositoryInterface
{
    /** Every code of a listing: product, its variants, with or without affiliate. */
    public function forListing(TenantProduct $tp): Collection;

    public function forAffiliate(int $affiliateId): Collection;

    public function findFor(Model $owner, ?int $affiliateId): ?QrCode;
}

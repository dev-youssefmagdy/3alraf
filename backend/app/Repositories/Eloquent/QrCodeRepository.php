<?php

namespace App\Repositories\Eloquent;

use App\Models\QrCode;
use App\Models\TenantProduct;
use App\Models\TenantProductVariant;
use App\Repositories\Contracts\QrCodeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class QrCodeRepository extends BaseRepository implements QrCodeRepositoryInterface
{
    protected string $model = QrCode::class;

    public function forListing(TenantProduct $tp): Collection
    {
        $variantIds = TenantProductVariant::query()->where('tenant_product_id', $tp->id)->pluck('id');

        return $this->query()->where(fn ($q) => $q
            ->where(fn ($w) => $w->where('owner_type', TenantProduct::class)->where('owner_id', $tp->id))
            ->orWhere(fn ($w) => $w->where('owner_type', TenantProductVariant::class)->whereIn('owner_id', $variantIds)))->get();
    }

    public function forAffiliate(int $affiliateId): Collection
    {
        return $this->query()->where('affiliate_id', $affiliateId)->get();
    }

    public function findFor(Model $owner, ?int $affiliateId): ?QrCode
    {
        return $this->query()->where('owner_type', $owner::class)->where('owner_id', $owner->getKey())
            ->when($affiliateId, fn ($q) => $q->where('affiliate_id', $affiliateId), fn ($q) => $q->whereNull('affiliate_id'))->first();
    }
}

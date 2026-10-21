<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Models\TenantProduct;
use App\Repositories\Contracts\TenantProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TenantProductRepository extends BaseRepository implements TenantProductRepositoryInterface
{
    protected string $model = TenantProduct::class;

    public function available(?string $search, int $perPage = 50): LengthAwarePaginator
    {
        // TenantProduct carries the tenant scope, so the subquery only sees the current tenant's listings.
        return Product::query()->with('images')->where('is_active', true)
            ->whereNotIn('id', TenantProduct::query()->select('product_id'))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%$search%")->orWhere('sku', 'like', "%$search%")))
            ->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function findByProductId(int $productId): ?TenantProduct
    {
        return $this->query()->where('product_id', $productId)->first();
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\TenantProduct;
use App\Repositories\Contracts\WishlistItemRepositoryInterface;

class WishlistService
{
    public function __construct(private WishlistItemRepositoryInterface $items) {}

    /** @return bool true when the product is in the wishlist after the call */
    public function toggle(Customer $c, int $productId): bool
    {
        $row = $this->items->query()->where('customer_id', $c->id)->where('product_id', $productId)->first();
        if ($row) {
            $this->items->delete($row);

            return false;
        }
        $this->items->create(['customer_id' => $c->id, 'product_id' => $productId]);

        return true;
    }

    public function has(Customer $c, int $productId): bool
    {
        return $this->items->query()->where('customer_id', $c->id)->where('product_id', $productId)->exists();
    }

    /** Active tenant products in the wishlist, newest first. */
    public function products(Customer $c)
    {
        $ids = $this->items->query()->where('customer_id', $c->id)->latest('id')->pluck('product_id');

        return TenantProduct::query()->where('is_active', true)->whereIn('product_id', $ids)->with('product.images')->get()
            ->sortBy(fn ($tp) => $ids->search($tp->product_id))->values();
    }
}

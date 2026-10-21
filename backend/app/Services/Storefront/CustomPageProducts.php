<?php

namespace App\Services\Storefront;

use App\Models\Category;
use App\Models\CustomPage;
use App\Models\TenantProduct;
use App\Services\Lists\ProductListResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Product grid of a custom page: manually added products first (page order), then products matching the stored filters (+ shopper refinements). */
class CustomPageProducts
{
    public function __construct(private ProductListResolver $lists) {}

    /** @param array{price_min?:?string,price_max?:?string,sort?:?string} $refine  shopper refinements from the query string */
    public function paginate(CustomPage $page, array $refine = [], int $perPage = 24): LengthAwarePaginator
    {
        $f = $page->filters ?? [];
        $pinned = $page->products()->pluck('products.id')->map(fn ($i) => (int) $i)->all();
        $hasFilters = ! empty(array_intersect_key($f, array_flip(['categories', 'brands', 'attribute_values', 'price_min', 'price_max', 'flash_sale'])));

        $q = TenantProduct::query()->where('is_active', true)->with(['product.images'])
            ->whereHas('product', fn ($p) => $p->where('is_active', true));

        $min = $refine['price_min'] ?? null;
        $max = $refine['price_max'] ?? null;
        $q->where(function ($w) use ($f, $pinned, $hasFilters) {
            if ($pinned) {
                $w->orWhereIn('product_id', $pinned);
            }
            if ($hasFilters || ! $pinned) {
                $w->orWhere(fn ($m) => $this->applyFilters($m, $f));
            }
        });
        // shopper refinements narrow the filtered set but never hide pinned products
        if (($min !== null && $min !== '') || ($max !== null && $max !== '')) {
            $q->where(function ($w) use ($pinned, $min, $max) {
                if ($pinned) {
                    $w->orWhereIn('product_id', $pinned);
                }
                $w->orWhere(function ($m) use ($min, $max) {
                    if ($min !== null && $min !== '') {
                        $m->whereRaw('COALESCE(sale_price, price) >= ?', [$min]);
                    }
                    if ($max !== null && $max !== '') {
                        $m->whereRaw('COALESCE(sale_price, price) <= ?', [$max]);
                    }
                });
            });
        }

        if ($pinned) {
            $q->orderByRaw('CASE WHEN product_id IN ('.implode(',', $pinned).') THEN 0 ELSE 1 END')
                ->orderByRaw('FIELD(product_id, '.implode(',', array_reverse($pinned)).') DESC');
        }
        match ($refine['sort'] ?? $f['sort'] ?? 'newest') {
            'price_asc' => $q->orderByRaw('COALESCE(sale_price, price) asc'),
            'price_desc' => $q->orderByRaw('COALESCE(sale_price, price) desc'),
            'best_selling' => ($sold = $this->lists->soldRanking()) ? $q->orderByRaw('FIELD(product_id, '.implode(',', array_reverse($sold)).') DESC') : null,
            default => null,
        };

        return $q->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    private function applyFilters($m, array $f): void
    {
        if (! empty($f['categories'])) {
            $paths = Category::whereIn('id', $f['categories'])->pluck('path');
            $ids = Category::query()->where(function ($c) use ($paths) {
                foreach ($paths as $p) {
                    $c->orWhere('path', $p)->orWhere('path', 'like', $p.'/%');
                }
            })->pluck('id');
            $m->whereHas('product.categories', fn ($c) => $c->whereIn('categories.id', $ids));
        }
        if (! empty($f['brands'])) {
            $m->whereHas('product', fn ($p) => $p->whereIn('brand_id', $f['brands']));
        }
        if (! empty($f['attribute_values'])) {
            $m->whereHas('product.variants.values', fn ($v) => $v->whereIn('attribute_values.id', $f['attribute_values']));
        }
        if (isset($f['price_min'])) {
            $m->whereRaw('COALESCE(sale_price, price) >= ?', [$f['price_min']]);
        }
        if (isset($f['price_max'])) {
            $m->whereRaw('COALESCE(sale_price, price) <= ?', [$f['price_max']]);
        }
        if (! empty($f['flash_sale'])) {
            $m->whereIn('product_id', \App\Models\FlashSaleItem::query()
                ->whereHas('flashSale', fn ($s) => $s->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now()))->select('product_id'));
        }
    }
}

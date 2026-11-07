<?php

namespace App\Services\Lists;

use App\Enums\OrderStatus;
use App\Enums\ProductListType;
use App\Enums\ReviewStatus;
use App\Models\Product;
use App\Models\ProductListItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Recommended / most sold / most reviewed rails: admin-pinned products first (ordered), then algorithmic fill, de-duplicated, capped at the limit.
 * Only active products (with a price) are ever returned.
 */
class ProductListResolver
{
    public const DEFAULT_LIMIT = 12;

    /** Ranking queries are cached for 5 minutes and capped at 200 ids. */
    public const RANK_TTL = 300;

    public const RANK_LIMIT = 200;

    private function cached(string $key, \Closure $query): array
    {
        return Cache::remember('rank.'.$key, self::RANK_TTL, $query);
    }

    public function limitFor(ProductListType $type): int
    {
        return (int) (((array) setting('list_limits', []))[$type->value] ?? self::DEFAULT_LIMIT);
    }

    /**
     * @param  int|null  $contextProductId  recommended only: bias the fill towards this product (co-purchase, same category)
     * @return Collection<int,Product> ordered, with `images` loaded
     */
    public function resolve(ProductListType $type, ?int $limit = null, ?int $contextProductId = null): Collection
    {
        $limit ??= $this->limitFor($type);
        $sellable = Product::query()->where('is_active', true)->whereNotNull('price')->pluck('id')->flip();

        $ids = array_values(array_unique(array_filter($this->pinned($type), fn ($id) => isset($sellable[$id]))));
        if (count($ids) < $limit) {
            $skip = array_merge($ids, $contextProductId ? [$contextProductId] : []);
            foreach ($this->candidates($type, $contextProductId) as $id) {
                if (isset($sellable[$id]) && ! in_array($id, $skip, true)) {
                    $ids[] = $skip[] = $id;
                    if (count($ids) >= $limit) {
                        break;
                    }
                }
            }
        }

        return $this->hydrate(array_slice($ids, 0, $limit));
    }

    /** Pinned product ids, in admin order (only sellable ones). */
    public function pinned(ProductListType $type): array
    {
        return ProductListItem::query()->where('list_type', $type->value)->orderBy('sort_order')->orderBy('id')->pluck('product_id')->all();
    }

    /** Algorithmic ids, best first (may contain duplicates / unsellable ids; the caller filters). */
    private function candidates(ProductListType $type, ?int $context): array
    {
        return match ($type) {
            ProductListType::MOST_SOLD => $this->soldRanking(),
            ProductListType::MOST_REVIEWED => $this->reviewRanking(),
            ProductListType::RECOMMENDED => array_merge(
                $context ? $this->coPurchased($context) : [],
                $context ? $this->sameCategory($context) : [],
                $this->soldRanking(),
                $this->newest(),
            ),
        };
    }

    /** Σ(qty − returned) on delivered orders. */
    public function soldRanking(): array
    {
        return $this->cached('sold', fn () => DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereNull('o.deleted_at')->where('o.status', OrderStatus::DELIVERED->value)
            ->groupBy('oi.product_id')->selectRaw('oi.product_id, SUM(oi.quantity - oi.returned_qty) as sold')
            ->havingRaw('SUM(oi.quantity - oi.returned_qty) > 0')->orderByDesc('sold')->orderBy('oi.product_id')->limit(self::RANK_LIMIT)->pluck('product_id')->all());
    }

    /** Approved review count, then average rating. */
    public function reviewRanking(): array
    {
        return $this->cached('reviews', fn () => DB::table('reviews')->where('status', ReviewStatus::APPROVED->value)
            ->groupBy('product_id')->selectRaw('product_id, COUNT(*) c, AVG(rating) a')->orderByDesc('c')->orderByDesc('a')->orderBy('product_id')->limit(self::RANK_LIMIT)->pluck('product_id')->all());
    }

    /** Products bought together with $productId (non-cancelled orders). */
    private function coPurchased(int $productId): array
    {
        return $this->cached('co.'.$productId, fn () => DB::table('order_items as a')->join('order_items as b', fn ($j) => $j->on('a.order_id', '=', 'b.order_id')->whereColumn('a.product_id', '!=', 'b.product_id'))
            ->join('orders as o', 'o.id', '=', 'a.order_id')->whereNull('o.deleted_at')->whereNotIn('o.status', [OrderStatus::CANCELLED->value])
            ->where('a.product_id', $productId)->groupBy('b.product_id')->selectRaw('b.product_id, COUNT(*) c')->orderByDesc('c')->limit(self::RANK_LIMIT)->pluck('product_id')->all());
    }

    private function sameCategory(int $productId): array
    {
        $cats = DB::table('category_product')->where('product_id', $productId)->pluck('category_id');
        if ($cats->isEmpty()) {
            return [];
        }
        $sold = array_flip($this->soldRanking());

        return DB::table('category_product')->whereIn('category_id', $cats)->where('product_id', '!=', $productId)->distinct()->pluck('product_id')
            ->sortBy(fn ($id) => $sold[$id] ?? PHP_INT_MAX)->values()->all();
    }

    private function newest(): array
    {
        return Product::query()->where('is_active', true)->whereNotNull('price')->latest('id')->limit(self::RANK_LIMIT)->pluck('id')->all();
    }

    private function hydrate(array $ids): Collection
    {
        if (! $ids) {
            return collect();
        }
        $rows = Product::query()->where('is_active', true)->whereNotNull('price')->whereIn('id', $ids)->with('images')->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $rows->get($id))->filter()->values();
    }
}

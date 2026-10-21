<?php

namespace App\Services\Storefront;

use App\Enums\ReviewStatus;
use App\Models\FlashSaleItem;
use App\Models\TenantProduct;
use App\Services\Orders\PriceResolver;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Adds display data to tenant products for the product-card component: `display` = price (flash/sale/regular), old price,
 * discount %, flash end time, rating. Uses PriceResolver's flash rules (read-only display; the cart re-prices authoritatively).
 */
class CardPresenter
{
    public function __construct(private PriceResolver $prices) {}

    /** @param Collection<int,TenantProduct> $items */
    public function decorate(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }
        $ids = $items->pluck('product_id')->all();
        $flash = FlashSaleItem::query()->with('flashSale')->whereIn('product_id', $ids)
            ->whereHas('flashSale', fn ($f) => $f->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now()))
            ->get()->filter(fn ($i) => $i->remaining() === null || $i->remaining() > 0)->sortBy('product_variant_id')->groupBy('product_id');
        $ratings = DB::table('reviews')->where('tenant_id', current_tenant()?->id)->where('status', ReviewStatus::APPROVED->value)->whereIn('product_id', $ids)
            ->groupBy('product_id')->selectRaw('product_id, COUNT(*) c, AVG(rating) a')->get()->keyBy('product_id');

        foreach ($items as $tp) {
            $regular = (string) $tp->price;
            $final = $tp->effectivePrice();
            $fi = $flash->get($tp->product_id)?->first();
            $endsAt = null;
            if ($fi) {
                $final = $this->prices->flashPrice($fi, $final);
                $endsAt = $fi->flashSale->ends_at;
            }
            $has = bccomp($final, $regular, 2) < 0;
            $tp->display = [
                'price' => Money::round($final), 'old' => $has ? Money::round($regular) : null,
                'percent' => $has && bccomp($regular, '0', 2) > 0 ? (int) round((1 - (float) $final / (float) $regular) * 100) : null,
                'flash' => (bool) $fi, 'ends_at' => $endsAt?->toIso8601String(),
                'rating' => isset($ratings[$tp->product_id]) ? round((float) $ratings[$tp->product_id]->a, 1) : null,
                'reviews' => (int) ($ratings[$tp->product_id]->c ?? 0),
            ];
        }

        return $items;
    }
}

<?php

namespace App\Services\Storefront;

use App\Enums\ReviewStatus;
use App\Models\FlashSaleItem;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\TenantProduct;
use App\Models\TenantProductVariant;
use App\Services\Orders\PriceResolver;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Adds display data to tenant products for the product-card component: `display` = lowest variant price (flash/sale/regular, per-variant overrides; `from` = variants differ), old price,
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
        \Illuminate\Database\Eloquent\Collection::make($items->all())->loadMissing('product');
        $items->each->applyContent();
        $ids = $items->pluck('product_id')->all();
        $flash = FlashSaleItem::query()->with('flashSale')->whereIn('product_id', $ids)
            ->whereHas('flashSale', fn ($f) => $f->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now()))
            ->get()->filter(fn ($i) => $i->remaining() === null || $i->remaining() > 0)->groupBy('product_id');
        $ratings = DB::table('reviews')->where('tenant_id', current_tenant()?->id)->where('status', ReviewStatus::APPROVED->value)->whereIn('product_id', $ids)
            ->groupBy('product_id')->selectRaw('product_id, COUNT(*) c, AVG(rating) a')->get()->keyBy('product_id');
        // Variable products: one batched read of active variants + the tenant's per-variant price overrides (no N+1).
        $variableIds = $items->filter(fn ($tp) => $tp->product?->isVariable())->pluck('product_id')->all();
        $variantIds = $variableIds ? ProductVariant::query()->whereIn('product_id', $variableIds)->where('is_active', true)->get(['id', 'product_id'])->groupBy('product_id') : collect();
        $overrides = $variableIds ? TenantProductVariant::query()->whereIn('tenant_product_id', $items->pluck('id'))->get()->groupBy('tenant_product_id') : collect();

        $backorder = (bool) (current_tenant()?->settings['allow_backorder'] ?? false);
        $levels = StockLevel::query()->whereIn('product_id', $ids)->whereHas('branch', fn ($b) => $b->where('is_active', true))
            ->groupBy('product_id', 'product_variant_id')->selectRaw('product_id, product_variant_id, SUM(on_hand - reserved) a')->get()
            ->mapWithKeys(fn ($l) => [$l->product_id.':'.$l->product_variant_id => (int) $l->a]);

        foreach ($items as $tp) {
            $vids = $tp->product?->isVariable() ? $variantIds->get($tp->product_id, collect())->pluck('id')->all() : [0];
            $tvs = $overrides->get($tp->id, collect())->keyBy('product_variant_id');
            $pf = $flash->get($tp->product_id, collect());
            $best = null;
            $prices = [];
            $avail = [];
            foreach ($vids ?: [0] as $vid) {
                $tv = $tvs->get($vid);
                if ($tv && ! $tv->is_active) {
                    continue;
                }
                $avail[] = max(0, (int) ($levels[$tp->product_id.':'.$vid] ?? 0));
                $alert = $tv?->min_stock_alert_qty ?? $tp->min_stock_alert_qty ?? 5;
                $src = ($tv && $tv->price !== null) ? $tv : $tp;
                $regular = (string) $src->price;
                $final = ($src->sale_price !== null && bccomp((string) $src->sale_price, $regular, 2) < 0) ? (string) $src->sale_price : $regular;
                $fi = $pf->firstWhere('product_variant_id', $vid) ?? $pf->firstWhere('product_variant_id', 0);
                if ($fi) {
                    $final = $this->prices->flashPrice($fi, $final);
                }
                $prices[] = $final;
                if ($best === null || bccomp($final, $best['final'], 2) < 0) {
                    $best = ['final' => $final, 'regular' => $regular, 'fi' => $fi];
                }
            }
            $best ??= ['final' => $tp->effectivePrice(), 'regular' => (string) $tp->price, 'fi' => null];
            $has = bccomp($best['final'], $best['regular'], 2) < 0;
            $fi = $best['fi'];
            $tp->display = [
                'price' => Money::round($best['final']), 'old' => $has ? Money::round($best['regular']) : null,
                'percent' => $has && bccomp($best['regular'], '0', 2) > 0 ? (int) round((1 - (float) $best['final'] / (float) $best['regular']) * 100) : null,
                'from' => count(array_unique(array_map(fn ($x) => Money::round($x), $prices))) > 1, // variants have different prices: show "from"
                'flash' => (bool) $fi, 'ends_at' => $fi?->flashSale->ends_at->toIso8601String(),
                'out' => ! $backorder && max($avail ?: [0]) <= 0, // every sellable variant is empty
                'low' => ! $backorder && count($avail) === 1 && $avail[0] > 0 && $avail[0] <= $alert ? $avail[0] : null, // simple product close to running out
                'rating' => isset($ratings[$tp->product_id]) ? round((float) $ratings[$tp->product_id]->a, 1) : null,
                'reviews' => (int) ($ratings[$tp->product_id]->c ?? 0),
            ];
        }

        return $items;
    }
}

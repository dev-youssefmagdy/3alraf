<?php

namespace App\Services\Storefront;

use App\Enums\ReviewStatus;
use App\Models\FlashSaleItem;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Product;
use App\Services\Orders\PriceResolver;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Adds display data to products for the product-card component: `display` = lowest variant price (flash/sale/regular, per-variant overrides; `from` = variants differ), old price,
 * discount %, flash end time, rating. Uses PriceResolver's flash rules (read-only display; the cart re-prices authoritatively).
 */
class CardPresenter
{
    public function __construct(private PriceResolver $prices) {}

    /** @param Collection<int,Product> $items */
    public function decorate(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }
        \Illuminate\Database\Eloquent\Collection::make($items->all())->loadMissing('images');
        $items->each->applyContent();
        $ids = $items->pluck('id')->all();
        $flash = FlashSaleItem::query()->with('flashSale')->whereIn('product_id', $ids)
            ->whereHas('flashSale', fn ($f) => $f->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now()))
            ->get()->filter(fn ($i) => $i->remaining() === null || $i->remaining() > 0)->groupBy('product_id');
        $ratings = DB::table('reviews')->where('status', ReviewStatus::APPROVED->value)->whereIn('product_id', $ids)
            ->groupBy('product_id')->selectRaw('product_id, COUNT(*) c, AVG(rating) a')->get()->keyBy('product_id');
        // Variable products: one batched read of the active variants with their price overrides (no N+1).
        $variableIds = $items->filter(fn ($p) => $p->isVariable())->pluck('id')->all();
        $variants = $variableIds ? ProductVariant::query()->whereIn('product_id', $variableIds)->where('is_active', true)
            ->get(['id', 'product_id', 'price', 'sale_price', 'min_stock_alert_qty'])->groupBy('product_id') : collect();

        $backorder = (bool) setting('allow_backorder', false);
        $levels = StockLevel::query()->whereIn('product_id', $ids)->whereHas('branch', fn ($b) => $b->where('is_active', true))
            ->groupBy('product_id', 'product_variant_id')->selectRaw('product_id, product_variant_id, SUM(on_hand - reserved) a')->get()
            ->mapWithKeys(fn ($l) => [$l->product_id.':'.$l->product_variant_id => (int) $l->a]);

        foreach ($items as $tp) {
            $tvs = $tp->isVariable() ? $variants->get($tp->id, collect())->keyBy('id') : collect();
            $vids = $tp->isVariable() ? $tvs->keys()->all() : [0];
            $pf = $flash->get($tp->id, collect());
            $best = null;
            $prices = [];
            $avail = [];
            foreach ($vids ?: [0] as $vid) {
                $tv = $tvs->get($vid);
                $avail[] = max(0, (int) ($levels[$tp->id.':'.$vid] ?? 0));
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
                'rating' => isset($ratings[$tp->id]) ? round((float) $ratings[$tp->id]->a, 1) : null,
                'reviews' => (int) ($ratings[$tp->id]->c ?? 0),
            ];
        }

        return $items;
    }
}

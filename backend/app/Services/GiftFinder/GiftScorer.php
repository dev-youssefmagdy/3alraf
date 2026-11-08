<?php

namespace App\Services\GiftFinder;

use App\Models\Bundle;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\Orders\BundleResolver;
use App\Services\Orders\PriceResolver;
use Illuminate\Support\Collection;

/**
 * Loads the sellable catalog ONCE per search (products + live bundles, eager-loaded, a fixed number of queries whatever the catalog size) and
 * scores products AND bundles with one fit model (weights in config/gift_finder.php, docs/CALCULATIONS.md "Gift Finder ranking").
 *
 * Product fit (max 100):
 *   category (30 when in the chosen category or one of its sub-categories; 15 for everyone when no category is chosen)
 *   + recipient (25 when tagged for the chosen recipient) + occasion (20 when tagged for the chosen occasion)
 *   + budget_use 15 × price / budget + popularity 10 × sold / max_sold.
 * Bundle fit (same model, tags aggregated from the bundle's items; max 105):
 *   category 30 × share of items in the chosen category (15 when none chosen) + recipient 25 × share of items tagged for the recipient
 *   + occasion 20 × share of items tagged for the occasion + budget_use 15 × price / budget + popularity 10 × bundles_sold / max_sold
 *   + bundle_savings 5 × min(1, savings% / savings_full_percent)   (savings% = (regular_total − price) / regular_total × 100).
 * max_sold is shared by products and bundles (units / whole bundles sold in delivered orders placed in the last popularity_days days).
 *
 * Hard filters (an item failing one is never offered): active, in stock (product: Σ branch on_hand > 0; bundle: every component available,
 * BundleResolver rules), restriction words (name of the product / bundle / any bundle item), tagging (once anything is tagged gift_suitable only
 * tagged products and bundles with at least one tagged item), recipient (product tagged for other people; bundle: ANY item tagged for other people),
 * occasion (product tagged for other occasions only; bundle: EVERY item tagged for other occasions only), chosen category (product not in it /
 * bundle with no item in it). Margin % ((price − cost) / price) is only a tie-breaker inside a fit bucket, never part of the fit.
 */
class GiftScorer
{
    public function __construct(private PriceResolver $prices, private BundleResolver $bundleResolver) {}

    /** @return string[] lower-cased words the shopper wants to avoid */
    public function restrictionWords(?string $raw): array
    {
        return collect(preg_split('/[,\n،]+/u', mb_strtolower((string) $raw)))->map(fn ($w) => trim($w))
            ->filter(fn ($w) => mb_strlen($w) >= config('gift_finder.min_restriction_word_length'))->take(config('gift_finder.max_restriction_words'))->values()->all();
    }

    /**
     * The catalog snapshot every scoring pass of one search reuses (relaxation retries do not reload it).
     *
     * @return array{products:Collection,bundles:Collection,vel:Collection,bundle_sold:Collection,bundle_stock:Collection,use_tags:bool,max_sold:int}
     */
    public function catalog(bool $withProducts = true, bool $withBundles = true): array
    {
        $products = $withProducts ? Product::with(['categories', 'images'])->where('is_active', true)->get() : collect();
        $bundles = $withBundles ? Bundle::query()->live()->orderBy('sort_order')->orderByDesc('id')
            ->with(['items.product' => fn ($q) => $q->with(['categories', 'images', 'variants.values', 'variants.images'])])->get() : collect();
        $vel = $withProducts ? $this->stockAndSales() : collect();
        $bundleSold = $bundles->isNotEmpty() ? $this->bundleSales($bundles) : collect();
        $bundleStock = $bundles->isNotEmpty() && ! $this->bundleResolver->allowsBackorder()
            ? $this->componentStock($bundles->flatMap(fn ($b) => $b->items->pluck('product_id'))->unique()->values()->all()) : collect();
        $useTags = Product::where('is_active', true)->where('gift_suitable', true)->exists();

        return ['products' => $products, 'bundles' => $bundles, 'vel' => $vel, 'bundle_sold' => $bundleSold, 'bundle_stock' => $bundleStock, 'use_tags' => $useTags,
            'max_sold' => max(1, (int) $vel->max('sold'), (int) $bundleSold->max())];
    }

    /** The chosen category plus all its sub-categories (materialized path). @return int[]|null */
    public function categoryIds(?int $categoryId): ?array
    {
        if (! $categoryId) {
            return null;
        }
        $c = Category::find($categoryId, ['id', 'path']);

        return $c ? array_values(array_unique(array_merge([$c->id], Category::where('path', 'like', $c->path.'/%')->pluck('id')->all()))) : [$categoryId];
    }

    /** @return Collection<int,array> active, in-stock, unrestricted products with price/fit/bucket/margin */
    public function candidates(array $catalog, float $budget, ?array $categoryIds, ?string $occasion, array $words, ?string $recipient = null): Collection
    {
        $w = config('gift_finder.weights');
        $vel = $catalog['vel'];

        return $catalog['products']->map(function (Product $p) use ($catalog, $vel, $categoryIds, $words, $budget, $w, $occasion, $recipient) {
            if ($p->trashed() || (int) ($vel[$p->id]['on_hand'] ?? 0) <= 0 || ($catalog['use_tags'] && ! $p->gift_suitable)) {
                return null;
            }
            $tagRecipients = (array) $p->gift_recipients;
            if ($recipient && $tagRecipients && ! in_array($recipient, $tagRecipients, true)) {
                return null; // tagged for other people (e.g. a men's shirt is never offered for "mother")
            }
            $tagOccasions = (array) $p->gift_occasions;
            if ($occasion && $tagOccasions && ! in_array($occasion, $tagOccasions, true)) {
                return null; // tagged for other occasions only (no occasion tag = any occasion)
            }
            $cats = $p->categories->pluck('id')->all();
            $inCategory = $categoryIds ? (bool) array_intersect($categoryIds, $cats) : null;
            if ($inCategory === false) {
                return null;
            }
            $hay = mb_strtolower(implode(' ', $p->getTranslations('name')));
            foreach ($words as $word) {
                if (str_contains($hay, $word)) {
                    return null;
                }
            }
            $recipientMatch = $recipient && in_array($recipient, $tagRecipients, true);
            $occasionMatch = $occasion && in_array($occasion, $tagOccasions, true);
            $price = round((float) ($p->sale_price ?: $p->price), 2);
            $sold = (int) ($vel[$p->id]['sold'] ?? 0);
            $fit = ($categoryIds ? $w['category'] : $w['category_neutral'])
                + ($recipientMatch ? $w['recipient'] : 0)
                + ($occasionMatch ? $w['occasion'] : 0)
                + ($price <= $budget ? $w['budget_use'] * ($price / $budget) : 0)
                + $w['popularity'] * ($sold / $catalog['max_sold']);
            $margin = (float) $p->cost > 0 && $price > 0 ? ($price - (float) $p->cost) / $price * 100 : null;

            return ['type' => 'product', 'key' => 'product:'.$p->id, 'id' => $p->id, 'product_id' => $p->id, 'name' => (string) $p->name, 'names' => $p->getTranslations('name'), 'slug' => $p->slug, 'price' => $price,
                'fit' => round($fit, 1), 'bucket' => (int) round($fit / config('gift_finder.bucket_size')), 'margin' => $margin,
                'category_match' => $inCategory, 'recipient_match' => (bool) $recipientMatch, 'occasion_match' => (bool) $occasionMatch,
                'sold' => $sold, 'category' => $p->categories->sortByDesc('depth')->first()?->name, 'simple' => ! $p->isVariable(), 'image' => $p->primaryImageUrl()];
        })->filter()->values();
    }

    /** @return Collection<int,array> purchasable, available bundles scored with the product fit model (see class doc) */
    public function bundleCandidates(array $catalog, float $budget, ?array $categoryIds, ?string $occasion, array $words, ?string $recipient = null): Collection
    {
        $w = config('gift_finder.weights');
        $backorder = $this->bundleResolver->allowsBackorder();

        return $catalog['bundles']->map(function (Bundle $b) use ($catalog, $w, $budget, $categoryIds, $occasion, $words, $recipient, $backorder) {
            $components = [];
            foreach ($b->items as $bi) {
                $item = $bi->product ? $this->prices->itemFor($bi->product, (int) $bi->product_variant_id) : null;
                if (! $item) {
                    return null; // a component is not purchasable: the bundle is hidden everywhere (BundleCatalog rule)
                }
                $components[] = ['item' => $item, 'per' => max(1, (int) $bi->quantity)];
            }
            if (! $components) {
                return null;
            }
            $avail = $backorder ? null : $this->available($components, $catalog['bundle_stock']);
            if ($avail !== null && $avail <= 0) {
                return null;
            }
            $products = collect($components)->map(fn ($c) => $c['item']['product']);
            if ($catalog['use_tags'] && ! $products->contains(fn ($p) => $p->gift_suitable)) {
                return null;
            }
            if ($recipient && $products->contains(fn ($p) => (array) $p->gift_recipients && ! in_array($recipient, (array) $p->gift_recipients, true))) {
                return null; // one item is meant for other people
            }
            if ($occasion && $products->every(fn ($p) => (array) $p->gift_occasions && ! in_array($occasion, (array) $p->gift_occasions, true))) {
                return null; // no item suits the occasion
            }
            $n = $products->count();
            $catShare = $categoryIds ? $products->filter(fn ($p) => (bool) array_intersect($categoryIds, $p->categories->pluck('id')->all()))->count() / $n : null;
            if ($catShare === 0.0 || $catShare === 0) {
                return null;
            }
            $hay = mb_strtolower(implode(' ', array_merge($b->getTranslations('name'), $products->flatMap(fn ($p) => $p->getTranslations('name'))->all())));
            foreach ($words as $word) {
                if (str_contains($hay, $word)) {
                    return null;
                }
            }
            $recShare = $recipient ? $products->filter(fn ($p) => in_array($recipient, (array) $p->gift_recipients, true))->count() / $n : 0;
            $occShare = $occasion ? $products->filter(fn ($p) => in_array($occasion, (array) $p->gift_occasions, true))->count() / $n : 0;
            $price = round((float) $b->price, 2);
            $regular = (float) $this->bundleResolver->regularTotal($components);
            $savingsPct = $regular > 0 && $regular > $price ? ($regular - $price) / $regular * 100 : 0.0;
            $sold = (int) ($catalog['bundle_sold'][$b->id] ?? 0);
            $cost = array_sum(array_map(fn ($c) => (float) $c['item']['cost'] * $c['per'], $components));
            $fit = ($categoryIds ? $w['category'] * $catShare : $w['category_neutral'])
                + $w['recipient'] * $recShare
                + $w['occasion'] * $occShare
                + ($price <= $budget ? $w['budget_use'] * ($price / $budget) : 0)
                + $w['popularity'] * ($sold / $catalog['max_sold'])
                + $w['bundle_savings'] * min(1, $savingsPct / max(1, (float) config('gift_finder.savings_full_percent', 30)));

            return ['type' => 'bundle', 'key' => 'bundle:'.$b->id, 'id' => $b->id, 'bundle_id' => $b->id, 'name' => (string) $b->name, 'slug' => $b->slug, 'price' => $price,
                'fit' => round($fit, 1), 'bucket' => (int) round($fit / config('gift_finder.bucket_size')), 'margin' => $cost > 0 && $price > 0 ? ($price - $cost) / $price * 100 : null,
                'category_match' => $catShare === null ? null : $catShare > 0, 'recipient_match' => $recShare > 0, 'occasion_match' => $occShare > 0,
                'sold' => $sold, 'regular_total' => $regular, 'savings' => max(0, round($regular - $price, 2)), 'savings_percent' => (int) floor($savingsPct),
                'component_names' => array_map(fn ($c) => (string) $c['item']['product']->name, $components),
                'bundle' => $b, 'components' => $components, 'available' => $avail];
        })->filter()->values();
    }

    /**
     * Per product: units on hand (all branches) and units sold in delivered orders placed in the last `popularity_days` days.
     *
     * @return Collection<int,array{on_hand:int,sold:int}> keyed by product id
     */
    public function stockAndSales(): Collection
    {
        $stock = StockLevel::selectRaw('product_id, SUM(on_hand) q')->groupBy('product_id')->pluck('q', 'product_id');
        $sold = OrderItem::whereHas('order', fn ($q) => $this->deliveredInWindow($q))
            ->selectRaw('product_id, SUM(quantity) q')->groupBy('product_id')->pluck('q', 'product_id');

        return $stock->keys()->merge($sold->keys())->unique()->mapWithKeys(fn ($id) => [(int) $id => ['on_hand' => (int) ($stock[$id] ?? 0), 'sold' => (int) ($sold[$id] ?? 0)]]);
    }

    /** Best fit first; margin only separates equally fitting items (then products before bundles, then id). Never above budget. */
    public function within(Collection $rows, float $budget, ?int $limit = null): Collection
    {
        $ok = $rows->filter(fn ($r) => $r['price'] <= $budget);
        $worthy = $ok->filter(fn ($r) => $r['price'] >= $budget * config('gift_finder.min_price_ratio'));
        $pool = $worthy->count() >= config('gift_finder.min_preferred_results') ? $worthy : $ok;

        return $pool->sortBy([['bucket', 'desc'], ['margin', 'desc'], ['type', 'desc'], ['id', 'asc']])->values()->take($limit ?? config('gift_finder.max_results'));
    }

    /** Slightly above budget: shown separately and flagged, never mixed into the main list. */
    public function nearBudget(Collection $rows, float $budget, ?int $limit = null): Collection
    {
        return $rows->filter(fn ($r) => $r['price'] > $budget && $r['price'] <= $budget * config('gift_finder.near_budget_factor'))
            ->sortBy([['price', 'asc'], ['id', 'asc']])->take($limit ?? config('gift_finder.max_near_budget'))->map(fn ($r) => $r + ['over_budget' => true])->values();
    }

    /** Whole bundles sold per bundle id: Σ component units of its order lines / Σ units per bundle (delivered orders in the popularity window). */
    private function bundleSales(Collection $bundles): Collection
    {
        $units = OrderItem::whereIn('bundle_id', $bundles->pluck('id'))->whereHas('order', fn ($q) => $this->deliveredInWindow($q))
            ->selectRaw('bundle_id, SUM(quantity) q')->groupBy('bundle_id')->pluck('q', 'bundle_id');

        return $bundles->mapWithKeys(fn (Bundle $b) => [$b->id => ($per = (int) $b->items->sum(fn ($i) => max(1, (int) $i->quantity))) > 0 ? intdiv((int) ($units[$b->id] ?? 0), $per) : 0]);
    }

    /** Available units (active branches, on_hand − reserved) per "product:variant" — BundleResolver::stock() for many components in one query. */
    private function componentStock(array $productIds): Collection
    {
        if (! $productIds) {
            return collect();
        }

        return StockLevel::whereIn('product_id', $productIds)->whereHas('branch', fn ($b) => $b->where('is_active', true))
            ->groupBy('product_id', 'product_variant_id')->selectRaw('product_id, product_variant_id, COALESCE(SUM(on_hand - reserved),0) a')->get()
            ->mapWithKeys(fn ($r) => [$r->product_id.':'.(int) $r->product_variant_id => (int) $r->a]);
    }

    /** BundleResolver::available() from the preloaded stock map: min floor(available / qty per bundle). */
    private function available(array $components, Collection $stock): int
    {
        $min = null;
        foreach ($components as $c) {
            $n = intdiv(max(0, (int) ($stock[$c['item']['product']->id.':'.($c['item']['variant']?->id ?? 0)] ?? 0)), $c['per']);
            $min = $min === null ? $n : min($min, $n);
        }

        return $min ?? 0;
    }

    private function deliveredInWindow($q)
    {
        return $q->where('status', 'delivered')->where('placed_at', '>=', now()->subDays((int) config('gift_finder.popularity_days', 30) - 1)->startOfDay());
    }
}

<?php

namespace App\Services\GiftFinder;

use App\Models\GiftFinderSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Orders\CartService;
use App\Services\Storefront\AttributionService;
use App\Services\Storefront\BundleCatalog;
use App\Services\Storefront\ProductPageData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gift Finder (standalone module, switch `gift_finder.enabled` on Settings → General; see GiftFinderSettings). Explicit rules: active + in stock + price <= budget
 * (an item above the budget is NEVER in the main list; near-budget ones are returned separately and flagged). Products AND bundles are ranked in ONE list
 * (`items`, each with `type`) by the same fit model (GiftScorer; docs/CALCULATIONS.md). Margin is only a TIE-BREAKER between equally fitting items, so a
 * better fit is never hidden or pushed down for a lower margin. Explanations are deterministic, built from real data only (GiftExplainer).
 *
 * When nothing fits, the filters are relaxed in a fixed order — category first, then occasion — and `relaxed` says which ones were dropped.
 * Response (web + API v1): token, share_url, budget, filters{type,sort}, relaxed[], items[] (unified), near_items[] (unified, over budget),
 * and the legacy arrays older app builds read: results[] (products only), bundles[] (BundleCatalog shape + fit/explanation), near_budget[] (products).
 */
class GiftFinderService
{
    public const OCCASIONS = ['birthday', 'mothers_day', 'valentine', 'wedding', 'eid_fitr', 'eid_adha', 'ramadan', 'graduation', 'thanks', 'self'];

    public const RECIPIENTS = ['mother', 'partner', 'friend', 'sister', 'colleague', 'child', 'self', 'other'];

    public const TYPES = ['all', 'products', 'bundles'];

    public const SORTS = ['best', 'price_asc', 'price_desc'];

    /** Internal ranking fields that never leave the service. */
    private const INTERNAL = ['margin', 'bucket', 'names', 'sold', 'category', 'bundle', 'components', 'component_names', 'regular_total', 'savings', 'available'];

    public function __construct(private GiftScorer $scorer, private GiftExplainer $explainer, private BundleCatalog $bundles, private CartService $cart, private ProductPageData $page) {}

    public function search(array $in): array
    {
        $budget = round((float) ($in['budget'] ?? 0), 2);
        if ($budget <= 0) {
            throw ValidationException::withMessages(['budget' => __('gift_finder.budget_required')]);
        }
        $q = $this->criteria($in + ['budget' => $budget]);
        $catalog = $this->scorer->catalog($q['type'] !== 'bundles', $q['type'] !== 'products');

        // relaxation: category, then occasion (each step only when the previous attempt left the main list empty)
        $relaxed = [];
        $ranked = $this->rank($catalog, $q, $relaxed);
        foreach (['category' => 'category_id', 'occasion' => 'occasion'] as $name => $field) {
            if ($ranked['items']->isNotEmpty() || ! $q[$field]) {
                continue;
            }
            $relaxed[] = $name;
            $ranked = $this->rank($catalog, $q, $relaxed);
        }

        $s = GiftFinderSession::create(['token' => Str::random(32), 'occasion' => $q['occasion'], 'recipient' => $q['recipient'], 'budget' => $budget, 'category_id' => $q['category_id'],
            'restrictions' => $q['words'] ? mb_substr(implode(', ', $q['words']), 0, 200) : null,
            'params' => ['type' => $q['type'], 'sort' => $q['sort'], 'relaxed' => $relaxed, 'order' => $ranked['items']->pluck('key')->all(), 'near' => $ranked['near']->pluck('key')->all()],
            'result_ids' => $ranked['results']->concat($ranked['items'])->where('type', 'product')->pluck('id')->unique()->values()->all(),
            'bundle_ids' => $ranked['bundles']->concat($ranked['items'])->where('type', 'bundle')->pluck('id')->unique()->values()->all(),
            'results_count' => $ranked['items']->count()]);

        return $this->payload($s, $q, $relaxed, $ranked);
    }

    /**
     * A stored search re-rendered for its share link (/{locale}/gift-finder/r/{token}, GET /api/v1/gift-finder/{token}): the same answers and the same
     * suggestions in the same order, with today's prices / stock (an item that sold out or no longer fits the budget drops out). Creates no new session.
     */
    public function shared(string $token): array
    {
        $s = GiftFinderSession::where('token', $token)->firstOrFail();
        $p = (array) $s->params;
        $q = $this->criteria(['budget' => (float) $s->budget, 'occasion' => $s->occasion, 'recipient' => $s->recipient, 'category_id' => $s->category_id,
            'restrictions' => $s->restrictions, 'type' => $p['type'] ?? 'all', 'sort' => $p['sort'] ?? 'best']);
        $relaxed = array_values(array_intersect((array) ($p['relaxed'] ?? []), ['category', 'occasion']));
        $catalog = $this->scorer->catalog();
        $all = $this->score($catalog, $q, $relaxed);
        $byKey = $all->keyBy('key');
        $pick = fn (array $keys) => collect($keys)->map(fn ($k) => $byKey->get($k))->filter()->values();
        // sessions stored before P3 have no params.order: their product ids (in stored order) are the list
        $order = isset($p['order']) ? (array) $p['order'] : array_map(fn ($id) => 'product:'.$id, (array) $s->result_ids);
        $items = $pick($order)->filter(fn ($r) => $r['price'] <= $q['budget'])->values();
        $near = $pick((array) ($p['near'] ?? []))->filter(fn ($r) => $r['price'] > $q['budget'])->map(fn ($r) => $r + ['over_budget' => true])->values();
        $ranked = ['items' => $items, 'near' => $near, 'results' => $items->where('type', 'product')->values(),
            'bundles' => $pick(array_map(fn ($id) => 'bundle:'.$id, (array) $s->bundle_ids))->filter(fn ($r) => $r['price'] <= $q['budget'])->take((int) config('gift_finder.max_bundles'))->values()];

        return $this->payload($s, $q, $relaxed, $ranked) + ['shared' => true];
    }

    /** Normalised answers. */
    private function criteria(array $in): array
    {
        return ['budget' => round((float) $in['budget'], 2), 'category_id' => (int) ($in['category_id'] ?? 0) ?: null,
            'occasion' => in_array($in['occasion'] ?? null, self::OCCASIONS, true) ? $in['occasion'] : null,
            'recipient' => in_array($in['recipient'] ?? null, self::RECIPIENTS, true) ? $in['recipient'] : null,
            'words' => $this->scorer->restrictionWords($in['restrictions'] ?? null),
            'type' => in_array($in['type'] ?? null, self::TYPES, true) ? $in['type'] : 'all',
            'sort' => in_array($in['sort'] ?? null, self::SORTS, true) ? $in['sort'] : 'best'];
    }

    /** All scored candidates (products and/or bundles per type filter) with the relaxed filters dropped. */
    private function score(array $catalog, array $q, array $relaxed): Collection
    {
        $cats = in_array('category', $relaxed, true) ? null : $this->scorer->categoryIds($q['category_id']);
        $occ = in_array('occasion', $relaxed, true) ? null : $q['occasion'];
        $products = $q['type'] === 'bundles' ? collect() : $this->scorer->candidates($catalog, $q['budget'], $cats, $occ, $q['words'], $q['recipient']);
        $bundles = $q['type'] === 'products' ? collect() : $this->scorer->bundleCandidates($catalog, $q['budget'], $cats, $occ, $q['words'], $q['recipient']);

        return $products->concat($bundles)->values();
    }

    /** @return array{items:Collection,near:Collection,results:Collection,bundles:Collection} */
    private function rank(array $catalog, array $q, array $relaxed): array
    {
        $all = $this->score($catalog, $q, $relaxed);
        $items = $this->scorer->within($all, $q['budget'], (int) config('gift_finder.max_items'));
        if ($q['sort'] !== 'best') { // the best matches, shown by price
            $items = $items->sortBy([['price', $q['sort'] === 'price_asc' ? 'asc' : 'desc'], ['id', 'asc']])->values();
        }

        return ['items' => $items, 'near' => $this->scorer->nearBudget($all, $q['budget']),
            'results' => $this->scorer->within($all->where('type', 'product')->values(), $q['budget']),
            'bundles' => $this->scorer->within($all->where('type', 'bundle')->values(), $q['budget'], (int) config('gift_finder.max_bundles'))];
    }

    private function payload(GiftFinderSession $s, array $q, array $relaxed, array $ranked): array
    {
        $products = $ranked['results']->concat($ranked['items'])->where('type', 'product')->unique('id')->values();
        $bundles = $ranked['bundles']->concat($ranked['items'])->concat($ranked['near'])->where('type', 'bundle')->unique('id')->values();
        $explain = $this->explainer->explain($products->all(), $q['occasion'], $q['recipient'], $q['budget']);
        $explainB = $this->explainer->explainBundles($bundles->all(), $q['occasion'], $q['recipient'], $q['budget']);
        $pickers = $products->reject(fn ($r) => $r['simple'])->mapWithKeys(fn ($r) => [$r['id'] => $this->picker($r['id'])])->all();

        $product = fn (array $r) => $this->present($r) + ['explanation' => $explain[$r['id']] ?? null, 'picker' => $r['simple'] ? null : ($pickers[$r['id']] ?? null)];
        $bundle = fn (array $r) => $this->bundles->presentResolved($r['bundle'], $r['components'], $r['available'])
            + $this->present($r) + ['explanation' => $explainB[$r['id']] ?? null];
        $unified = fn (array $r) => $r['type'] === 'bundle' ? array_merge($bundle($r), ['price' => number_format($r['price'], 2, '.', '')])
            : array_merge($product($r), ['price' => number_format($r['price'], 2, '.', '')]);

        return ['token' => $s->token, 'share_url' => route('storefront.gift-finder.shared', ['locale' => app()->getLocale(), 'token' => $s->token]),
            'budget' => $q['budget'], 'filters' => ['type' => $q['type'], 'sort' => $q['sort']], 'relaxed' => $relaxed,
            'answers' => ['budget' => $q['budget'], 'occasion' => $q['occasion'], 'recipient' => $q['recipient'], 'category_id' => $q['category_id'],
                'restrictions' => $q['words'] ? implode(', ', $q['words']) : null, 'type' => $q['type'], 'sort' => $q['sort']],
            'relaxed_notice' => $relaxed ? __('gift_finder.relaxed_'.implode('_', $relaxed)) : null,
            'items' => $ranked['items']->map($unified)->values()->all(),
            'near_items' => $ranked['near']->map(fn ($r) => $unified($r) + ['over_budget' => true])->values()->all(),
            // legacy (app builds before P3): products only / BundleCatalog bundles / products near budget
            'results' => $ranked['results']->map($product)->values()->all(),
            'bundles' => $ranked['bundles']->map($bundle)->values()->all(),
            'near_budget' => $ranked['near']->where('type', 'product')->map(fn ($r) => $this->present($r))->values()->all()];
    }

    /** Option groups + in-stock variants of a variable product, so the shopper can choose right inside the results (same data the product page uses). */
    private function picker(int $productId): ?array
    {
        $product = Product::with(['variants.values', 'variants.images', 'images'])->find($productId);
        $tp = Product::whereKey($productId)->first();
        if (! $product || ! $tp) {
            return null;
        }
        $d = $this->page->build($product, $tp);
        $d['variants'] = array_values(array_filter($d['variants'], fn ($v) => $v['in_stock']));

        return $d['variants'] ? ['options' => $d['options'], 'variants' => array_map(fn ($v) => collect($v)->only(['id', 'values', 'price', 'old', 'label', 'image'])->all(), $d['variants'])] : null;
    }

    /** Public shape of a scored row: internal ranking fields (margin, bucket, translations, units sold, loaded models) never leave the service. */
    private function present(array $row): array
    {
        return collect($row)->except(self::INTERNAL)->all();
    }

    /**
     * Prepared cart from a session's suggestions: the chosen products (a variable one needs a valid chosen variant) and bundles (CartService::addBundle,
     * the normal bundle cart path with its stock checks). Only items the session suggested are accepted. With no product_ids AND no bundle_ids the
     * suggested products are added (legacy behaviour of older app builds).
     *
     * @return array{added:int[],skipped:int[],bundles_added:int[],bundles_skipped:int[]}
     */
    public function prepareCart(string $token, array $productIds, array $variants = [], array $bundleIds = []): array
    {
        $s = GiftFinderSession::where('token', $token)->firstOrFail();
        $productIds = array_map('intval', $productIds);
        $bundleIds = array_map('intval', $bundleIds);
        $ids = array_values(array_intersect($productIds ?: ($bundleIds ? [] : (array) $s->result_ids), (array) $s->result_ids));
        $added = [];
        $skipped = [];
        $products = $ids ? Product::with('variants')->whereIn('id', $ids)->get()->keyBy('id') : collect();
        foreach ($ids as $id) {
            $p = $products->get($id);
            if (! $p) {
                $skipped[] = $id;

                continue;
            }
            $variantId = 0;
            if ($p->isVariable()) {
                $variantId = (int) ($variants[$id] ?? 0);
                if (! $p->variants->where('is_active', true)->contains('id', $variantId)) {
                    $skipped[] = $id; // a variable product needs a valid chosen variant

                    continue;
                }
            }
            try {
                $this->cart->add($id, $variantId, 1);
                $added[] = $id;
            } catch (ValidationException) {
                $skipped[] = $id;
            }
        }
        $bAdded = [];
        $bSkipped = array_values(array_diff($bundleIds, (array) $s->bundle_ids)); // not suggested by this session
        foreach (array_values(array_intersect($bundleIds, (array) $s->bundle_ids)) as $id) {
            try {
                $this->cart->addBundle($id, 1);
                $bAdded[] = $id;
            } catch (ValidationException) {
                $bSkipped[] = $id;
            }
        }
        if ($added || $bAdded) {
            $s->update(['cart_at' => $s->cart_at ?? now(), 'cart_ids' => array_values(array_unique(array_merge((array) $s->cart_ids, $added))),
                'cart_bundle_ids' => array_values(array_unique(array_merge((array) $s->cart_bundle_ids, $bAdded)))]);
            if (request()->hasSession()) {
                request()->session()->put(AttributionService::GIFT_SESSION_KEY, $s->id); // carried into orders.attribution.gift_session
            }
        }

        return ['added' => $added, 'skipped' => $skipped, 'bundles_added' => $bAdded, 'bundles_skipped' => $bSkipped];
    }

    public function stats(int $days = 30): array
    {
        $q = GiftFinderSession::where('created_at', '>=', now()->subDays($days));
        $n = (clone $q)->count();
        $done = (clone $q)->where('results_count', '>', 0)->count();
        $cart = (clone $q)->whereNotNull('cart_at')->count();

        $orders = Order::whereNotNull('attribution->gift_session')->where('placed_at', '>=', now()->subDays($days))->get(['id', 'status', 'total', 'attribution']);
        $done_orders = $orders->filter(fn ($o) => ($o->status?->value ?? $o->status) !== 'cancelled');
        $bundleOrders = $done_orders->isEmpty() ? 0 : OrderItem::whereIn('order_id', $done_orders->pluck('id'))->where('bundle_id', '>', 0)->distinct()->count('order_id');

        return ['days' => $days, 'orders' => $done_orders->count(), 'orders_revenue' => round((float) $done_orders->sum('total'), 2), 'orders_aov' => $done_orders->count() ? round((float) $done_orders->avg('total'), 2) : null,
            'order_rate' => $cart ? round($done_orders->count() / $cart * 100, 1) : null, 'sessions' => $n, 'completion_rate' => $n ? round($done / $n * 100, 1) : null, 'cart_rate' => $done ? round($cart / $done * 100, 1) : null,
            'avg_budget' => $n ? round((float) (clone $q)->avg('budget'), 2) : null, 'no_result' => $n - $done, 'by_occasion' => (clone $q)->selectRaw('occasion, COUNT(*) c')->groupBy('occasion')->pluck('c', 'occasion')->all(),
            // P3: bundles in the unified list — searches that suggested a bundle, searches where a bundle went to the cart, gift orders containing a bundle
            'bundle_sessions' => (clone $q)->whereJsonLength('bundle_ids', '>', 0)->count(), 'bundle_carts' => (clone $q)->whereJsonLength('cart_bundle_ids', '>', 0)->count(),
            'bundle_orders' => $bundleOrders];
    }
}

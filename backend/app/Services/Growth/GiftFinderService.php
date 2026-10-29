<?php

namespace App\Services\Growth;

use App\Models\GiftFinderSession;
use App\Models\Order;
use App\Models\Product;
use App\Services\Growth\GiftFinder\GiftExplainer;
use App\Services\Growth\GiftFinder\GiftScorer;
use App\Services\Orders\CartService;
use App\Services\Storefront\BundleCatalog;
use App\Services\Storefront\ProductPageData;
use App\Models\TenantProduct;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gift Finder (spec 13, 16.2). Explicit rules: active + in stock + price <= budget (a product above the budget is NEVER in the main list; near-budget
 * ones are returned separately and flagged). Ranking = fit (category, occasion, budget use, popularity). Margin is only a TIE-BREAKER between equally
 * fitting products, so a better fit is never hidden or pushed down for a lower margin. The AI explanation uses real product data only (Truth Guard checked).
 */
class GiftFinderService
{
    public const OCCASIONS = ['birthday', 'mothers_day', 'valentine', 'wedding', 'eid_fitr', 'eid_adha', 'ramadan', 'graduation', 'thanks', 'self'];

    public const RECIPIENTS = ['mother', 'partner', 'friend', 'sister', 'colleague', 'child', 'self', 'other'];

    public function __construct(private GiftScorer $scorer, private GiftExplainer $explainer, private BundleCatalog $bundles, private CartService $cart, private ProductPageData $page) {}

    public function search(array $in): array
    {
        $budget = round((float) ($in['budget'] ?? 0), 2);
        if ($budget <= 0) {
            throw ValidationException::withMessages(['budget' => __('growth_hub.gift.budget_required')]);
        }
        $catId = (int) ($in['category_id'] ?? 0) ?: null;
        $occasion = in_array($in['occasion'] ?? null, self::OCCASIONS, true) ? $in['occasion'] : null;
        $recipient = in_array($in['recipient'] ?? null, self::RECIPIENTS, true) ? $in['recipient'] : null;
        $words = $this->scorer->restrictionWords($in['restrictions'] ?? null);

        $rows = $this->scorer->candidates($budget, $catId, $occasion, $words, $recipient);
        $within = $this->scorer->within($rows, $budget);
        $near = $this->scorer->nearBudget($rows, $budget);
        $bundles = collect($this->bundles->listing())->filter(fn ($b) => $b['in_stock'] && (float) $b['price'] <= $budget)->take(config('gift_finder.max_bundles'))->values()->all();

        $explain = $this->explainer->explain($within->all(), $occasion, $recipient, $budget);
        $results = $within->map(fn ($r) => $this->present($r) + ['explanation' => $explain[$r['product_id']], 'picker' => $r['simple'] ? null : $this->picker($r['product_id'])])->all();

        $s = GiftFinderSession::create(['token' => Str::random(32), 'occasion' => $occasion, 'recipient' => $recipient, 'budget' => $budget, 'category_id' => $catId,
            'restrictions' => $words ? mb_substr(implode(', ', $words), 0, 200) : null, 'result_ids' => array_column($results, 'product_id'), 'results_count' => count($results)]);

        return ['token' => $s->token, 'budget' => $budget, 'results' => $results, 'bundles' => $bundles, 'near_budget' => $near->map(fn ($r) => $this->present($r))->all()];
    }

    /** Option groups + in-stock variants of a variable product, so the shopper can choose right inside the results (same data the product page uses). */
    private function picker(int $productId): ?array
    {
        $product = Product::with(['variants.values', 'variants.images', 'images'])->find($productId);
        $tp = TenantProduct::where('product_id', $productId)->first();
        if (! $product || ! $tp) {
            return null;
        }
        $d = $this->page->build($product, $tp);
        $d['variants'] = array_values(array_filter($d['variants'], fn ($v) => $v['in_stock']));

        return $d['variants'] ? ['options' => $d['options'], 'variants' => array_map(fn ($v) => collect($v)->only(['id', 'values', 'price', 'old', 'label', 'image'])->all(), $d['variants'])] : null;
    }

    /** Public shape of a product row: internal ranking fields (margin, bucket, translations) never leave the service. */
    private function present(array $row): array
    {
        return collect($row)->except(['margin', 'bucket', 'names'])->all();
    }

    /** Prepared cart: adds the chosen products of the session's result list (simple products only; others need a variant choice on their page). */
    public function prepareCart(string $token, array $productIds, array $variants = []): array
    {
        $s = GiftFinderSession::where('token', $token)->firstOrFail();
        $ids = array_values(array_intersect(array_map('intval', $productIds) ?: (array) $s->result_ids, (array) $s->result_ids));
        $added = [];
        $skipped = [];
        foreach ($ids as $id) {
            $p = Product::with('variants')->find($id);
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
        if ($added) {
            $s->update(['cart_at' => $s->cart_at ?? now(), 'cart_ids' => array_values(array_unique(array_merge((array) $s->cart_ids, $added)))]);
        }
        if ($added && request()->hasSession()) {
            request()->session()->put('gift_session_id', $s->id); // carried into orders.attribution.gift_session
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    public function stats(int $days = 30): array
    {
        $q = GiftFinderSession::where('created_at', '>=', now()->subDays($days));
        $n = (clone $q)->count();
        $done = (clone $q)->where('results_count', '>', 0)->count();
        $cart = (clone $q)->whereNotNull('cart_at')->count();

        $orders = Order::whereNotNull('attribution->gift_session')->where('placed_at', '>=', now()->subDays($days))->get(['id', 'status', 'total', 'attribution']);
        $done_orders = $orders->filter(fn ($o) => ($o->status?->value ?? $o->status) !== 'cancelled');

        return ['days' => $days, 'orders' => $done_orders->count(), 'orders_revenue' => round((float) $done_orders->sum('total'), 2), 'orders_aov' => $done_orders->count() ? round((float) $done_orders->avg('total'), 2) : null,
            'order_rate' => $cart ? round($done_orders->count() / $cart * 100, 1) : null, 'sessions' => $n, 'completion_rate' => $n ? round($done / $n * 100, 1) : null, 'cart_rate' => $done ? round($cart / $done * 100, 1) : null,
            'avg_budget' => $n ? round((float) (clone $q)->avg('budget'), 2) : null, 'no_result' => $n - $done, 'by_occasion' => (clone $q)->selectRaw('occasion, COUNT(*) c')->groupBy('occasion')->pluck('c', 'occasion')->all()];
    }
}

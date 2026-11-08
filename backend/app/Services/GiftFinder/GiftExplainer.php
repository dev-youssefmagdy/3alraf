<?php

namespace App\Services\GiftFinder;

use App\Enums\ReviewStatus;
use App\Models\Review;

/**
 * One short "why this gift" text per product (explain) or bundle (explainBundles), built deterministically from real data only (no AI, no invented claims):
 * occasion / recipient fit, category, price vs budget, approved review rating and popularity among the results. ar/en via lang/{locale}/store.php (gift_explain).
 */
class GiftExplainer
{
    /** Minimum approved-review average shown as a reason. */
    private const MIN_RATING = 4.0;

    /** Price at or above this share of the budget is "good use of the budget"; below it the leftover amount is shown. */
    private const CLOSE_RATIO = 0.75;

    /** @param array<int,array<string,mixed>> $rows scorer rows; @return array<int,string> product_id => text */
    public function explain(array $rows, ?string $occasion, ?string $recipient, float $budget): array
    {
        if (! $rows) {
            return [];
        }
        $ids = array_column($rows, 'product_id');
        $ratings = Review::query()->whereIn('product_id', $ids)->where('status', ReviewStatus::APPROVED->value)
            ->groupBy('product_id')->selectRaw('product_id, COUNT(*) c, AVG(rating) a')->get()->keyBy('product_id');
        $popular = collect($rows)->filter(fn ($r) => (int) ($r['sold'] ?? 0) > 0)->sortByDesc(fn ($r) => (int) $r['sold'])
            ->take(config('gift_finder.explain_popular_top', 3))->pluck('product_id')->all();

        $out = [];
        foreach ($rows as $r) {
            $id = $r['product_id'];
            $parts = [$this->lead($r, $occasion, $recipient)];
            if (! empty($r['category_match'])) {
                $parts[] = __('store.gift_explain.category_match');
            }
            $parts[] = $this->price((float) $r['price'], $budget);
            $rt = $ratings->get($id);
            if ($rt && (float) $rt->a >= self::MIN_RATING) {
                $parts[] = (int) $rt->c === 1
                    ? __('store.gift_explain.rating_one', ['avg' => $this->num((float) $rt->a, 1)])
                    : __('store.gift_explain.rating', ['avg' => $this->num((float) $rt->a, 1), 'count' => (int) $rt->c]);
            }
            if (in_array($id, $popular, true)) {
                $parts[] = __('store.gift_explain.popular');
            }
            $out[$id] = implode(' ', array_filter($parts));
        }

        return $out;
    }

    /**
     * Bundles: what is inside (up to 3 item names + "N more"), occasion / recipient fit aggregated from the items, category, price vs budget,
     * the real saving vs buying the items separately (BundleCatalog regular_total) and popularity when the bundle was actually sold recently.
     *
     * @param  array<int,array<string,mixed>>  $rows  scorer bundle rows; @return array<int,string> bundle_id => text
     */
    public function explainBundles(array $rows, ?string $occasion, ?string $recipient, float $budget): array
    {
        $out = [];
        $popular = collect($rows)->filter(fn ($r) => (int) ($r['sold'] ?? 0) > 0)->sortByDesc(fn ($r) => (int) $r['sold'])
            ->take(config('gift_finder.explain_popular_top', 3))->pluck('bundle_id')->all();
        foreach ($rows as $r) {
            $names = array_values((array) ($r['component_names'] ?? []));
            $shown = array_slice($names, 0, 3);
            $list = implode(__('store.gift_explain.list_separator'), $shown).(count($names) > 3 ? ' '.__('store.gift_explain.bundle_more', ['n' => count($names) - 3]) : '');
            $parts = [__('store.gift_explain.bundle_lead', ['name' => (string) $r['name'], 'count' => count($names), 'items' => $list])];
            $who = ! empty($r['recipient_match']) && $recipient ? __('gift_finder.recipients.'.$recipient) : null;
            $occ = ! empty($r['occasion_match']) && $occasion ? __('gift_finder.occasions.'.$occasion) : null;
            $parts[] = match (true) {
                $who && $occ => __('store.gift_explain.bundle_fit_both', ['who' => $who, 'occasion' => $occ]),
                (bool) $who => __('store.gift_explain.bundle_fit_recipient', ['who' => $who]),
                (bool) $occ => __('store.gift_explain.bundle_fit_occasion', ['occasion' => $occ]),
                default => null,
            };
            if (! empty($r['category_match'])) {
                $parts[] = __('store.gift_explain.bundle_category_match');
            }
            $parts[] = $this->price((float) $r['price'], $budget);
            if ((float) ($r['savings'] ?? 0) > 0) {
                $parts[] = __('store.gift_explain.bundle_savings', ['amount' => $this->money((float) $r['savings'])]);
            }
            if (in_array($r['bundle_id'], $popular, true)) {
                $parts[] = __('store.gift_explain.bundle_popular');
            }
            $out[$r['bundle_id']] = implode(' ', array_filter($parts));
        }

        return $out;
    }

    private function lead(array $r, ?string $occasion, ?string $recipient): string
    {
        $name = (string) $r['name'];
        $who = ! empty($r['recipient_match']) && $recipient ? __('gift_finder.recipients.'.$recipient) : null;
        $occ = ! empty($r['occasion_match']) && $occasion ? __('gift_finder.occasions.'.$occasion) : null;

        return match (true) {
            $who && $occ => __('store.gift_explain.lead_both', ['name' => $name, 'who' => $who, 'occasion' => $occ]),
            (bool) $who => __('store.gift_explain.lead_recipient', ['name' => $name, 'who' => $who]),
            (bool) $occ => __('store.gift_explain.lead_occasion', ['name' => $name, 'occasion' => $occ]),
            ! empty($r['category']) => __('store.gift_explain.lead_category', ['name' => $name, 'category' => $r['category']]),
            default => __('store.gift_explain.lead_plain', ['name' => $name]),
        };
    }

    private function price(float $price, float $budget): string
    {
        $args = ['price' => $this->money($price), 'budget' => $this->money($budget)];
        if ($budget <= 0 || $price >= $budget * self::CLOSE_RATIO) {
            return __('store.gift_explain.price_close', $args);
        }

        return __('store.gift_explain.price_room', $args + ['left' => $this->money($budget - $price)]);
    }

    private function money(float $v): string
    {
        return trim($this->num($v, 2).' '.setting('currency_symbol'));
    }

    private function num(float $v, int $dec): string
    {
        return rtrim(rtrim(number_format($v, $dec), '0'), '.');
    }
}

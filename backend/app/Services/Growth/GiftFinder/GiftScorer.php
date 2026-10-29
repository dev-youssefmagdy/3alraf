<?php

namespace App\Services\Growth\GiftFinder;

use App\Models\SeasonalEvent;
use App\Models\TenantProduct;
use App\Services\Growth\MetricsService;
use Illuminate\Support\Collection;

/** Loads the sellable catalog and scores each product against the shopper's answers (explicit rules, see config/gift_finder.php). */
class GiftScorer
{
    public function __construct(private MetricsService $metrics) {}

    /** @return string[] lower-cased words the shopper wants to avoid */
    public function restrictionWords(?string $raw): array
    {
        return collect(preg_split('/[,\n،]+/u', mb_strtolower((string) $raw)))->map(fn ($w) => trim($w))
            ->filter(fn ($w) => mb_strlen($w) >= config('gift_finder.min_restriction_word_length'))->take(config('gift_finder.max_restriction_words'))->values()->all();
    }

    /** @return Collection<int,array> active, in-stock, unrestricted products with price/fit/bucket/margin */
    public function candidates(float $budget, ?int $categoryId, ?string $occasion, array $words, ?string $recipient = null): Collection
    {
        $w = config('gift_finder.weights');
        $eventCats = $occasion ? (array) (SeasonalEvent::where('key', $occasion)->orderByDesc('year')->value('category_ids') ?? []) : [];
        // Legacy stores (nothing tagged yet) keep working untagged; once a product is tagged, only tagged products are offered.
        $useTags = TenantProduct::where('is_active', true)->where('gift_suitable', true)->exists();
        $vel = $this->metrics->velocityRows(now())->keyBy('product_id');
        $maxSold = max(1, (int) $vel->max('sold'));

        return TenantProduct::with('product.categories')->where('is_active', true)->get()->map(function ($tp) use ($vel, $categoryId, $eventCats, $words, $maxSold, $budget, $w, $useTags, $occasion, $recipient) {
            $p = $tp->product;
            if (! $p || $p->trashed() || (int) ($vel[$tp->product_id]['on_hand'] ?? 0) <= 0) {
                return null;
            }
            if ($useTags && ! $tp->gift_suitable) {
                return null;
            }
            $tagRecipients = (array) $tp->gift_recipients;
            if ($recipient && $tagRecipients && ! in_array($recipient, $tagRecipients, true)) {
                return null; // tagged for other people (e.g. a men's shirt is never offered for "mother")
            }
            $recipientMatch = $recipient && in_array($recipient, $tagRecipients, true);
            $occasionMatch = $occasion && in_array($occasion, (array) $tp->gift_occasions, true);
            $price = round((float) ($tp->sale_price ?: $tp->price), 2);
            $hay = mb_strtolower(implode(' ', $p->getTranslations('name')));
            foreach ($words as $word) {
                if (str_contains($hay, $word)) {
                    return null;
                }
            }
            $cats = $p->categories->pluck('id')->all();
            $fit = ($categoryId ? (in_array($categoryId, $cats) ? $w['category'] : 0) : $w['category_neutral'])
                + ($recipientMatch ? $w['recipient'] : 0)
                + ($occasionMatch || array_intersect($cats, $eventCats) ? $w['occasion'] : 0)
                + ($price <= $budget ? $w['budget_use'] * ($price / $budget) : 0)
                + $w['popularity'] * ((int) ($vel[$tp->product_id]['sold'] ?? 0) / $maxSold);
            $margin = (float) $tp->cost > 0 && $price > 0 ? ($price - (float) $tp->cost) / $price * 100 : null;

            return ['product_id' => $tp->product_id, 'name' => (string) $p->name, 'names' => $p->getTranslations('name'), 'slug' => $p->slug, 'price' => $price,
                'fit' => round($fit, 1), 'bucket' => (int) round($fit / config('gift_finder.bucket_size')), 'margin' => $margin,
                'category_match' => $categoryId ? in_array($categoryId, $cats) : null, 'recipient_match' => (bool) $recipientMatch, 'occasion_match' => (bool) $occasionMatch, 'simple' => ! $p->isVariable(), 'image' => $p->primaryImageUrl()];
        })->filter()->values();
    }

    /** Best fit first; margin only separates equally fitting products. Never above budget. */
    public function within(Collection $rows, float $budget): Collection
    {
        $ok = $rows->filter(fn ($r) => $r['price'] <= $budget);
        $worthy = $ok->filter(fn ($r) => $r['price'] >= $budget * config('gift_finder.min_price_ratio'));
        $pool = $worthy->count() >= config('gift_finder.min_preferred_results') ? $worthy : $ok;

        return $pool->sortBy([['bucket', 'desc'], ['margin', 'desc'], ['product_id', 'asc']])->values()->take(config('gift_finder.max_results'));
    }

    /** Slightly above budget: shown separately and flagged, never mixed into the main list. */
    public function nearBudget(Collection $rows, float $budget): Collection
    {
        return $rows->filter(fn ($r) => $r['price'] > $budget && $r['price'] <= $budget * config('gift_finder.near_budget_factor'))
            ->sortBy('price')->take(config('gift_finder.max_near_budget'))->map(fn ($r) => $r + ['over_budget' => true])->values();
    }
}

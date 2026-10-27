<?php

namespace App\Services\Growth;

use App\Models\ContentAsset;
use App\Models\GrowthExperiment;
use App\Models\Interaction;
use App\Models\ProductSupplyTerm;
use App\Models\TrendCandidate;
use App\Services\Ai\ProductMatcher;
use App\Services\Ai\TrendService;
use Illuminate\Validation\ValidationException;

/**
 * Market Trend Radar (spec 3). Weekly scan that REUSES TrendService (own sales movement + the cached market snapshot; it never calls the AI itself)
 * plus internal question volume. Opportunity score = weighted components (config growth.trend.weights). Unknown components count as a neutral 50 and are
 * listed as uncertainty, so a thin score is never presented as certain. Trend-to-Test creates a DRAFT experiment; it never creates a purchase.
 */
class TrendRadarService
{
    public function __construct(private TrendService $trends, private ActionLogger $log, private ExperimentService $experiments, private GrowthFeatures $features) {}

    public static function week(): string
    {
        return now()->format('o-\WW');
    }

    /** @return int candidates stored this week */
    public function scan(): int
    {
        $found = [];
        foreach ($this->trends->own(30) as $r) {
            if (($r['growth_pct'] ?? 0) <= 0 && ! $r['is_new']) {
                continue;
            }
            $found[] = $this->score('internal', 'p'.$r['product_id'], (string) $r['name'], $r['product_id'], [
                'demand' => $r['is_new'] ? 70.0 : $this->clamp(50 + (float) ($r['growth_pct'] ?? 0) / 2), 'margin' => $r['margin_pct'] === null ? null : $this->clamp((float) $r['margin_pct'] * 2.5),
                'risk' => 80.0], [['source' => 'orders', 'metric' => 'units_30d', 'value' => $r['qty'], 'prev' => $r['prev_qty'], 'period' => '30d'], ['source' => 'orders', 'metric' => 'growth_percent', 'value' => $r['growth_pct']]], []);
        }
        $snap = $this->trends->market()['snapshot'] ?? null;
        $items = $snap ? $this->trends->filterAgainstCatalog((array) $snap->items) : [];
        foreach ($items as $it) {
            if (! empty($it['dismissed'])) {
                continue;
            }
            $src = count((array) ($it['sources'] ?? []));
            $price = (float) ($it['price_max'] ?? 0);
            $cost = (float) ($it['unit_cost'] ?? 0);
            $found[] = $this->score('market', 'm'.substr(sha1(ProductMatcher::norm((string) $it['name'])), 0, 12), (string) $it['name'], $it['product_id'] ?? null, [
                'demand' => isset($it['confidence']) ? $this->clamp((float) $it['confidence'] * 100) : null, 'margin' => ($price > 0 && $cost > 0) ? $this->clamp(($price - $cost) / $price * 250) : null,
                'risk' => $src >= 2 ? 80.0 : ($src === 1 ? 60.0 : 35.0)], [['source' => 'market_snapshot', 'metric' => 'confidence', 'value' => $it['confidence'] ?? null, 'sources' => $src, 'generated_at' => $snap?->generated_at?->toDateString()]], (array) ($it['keywords'] ?? []));
        }
        usort($found, fn ($a, $b) => $b['score'] <=> $a['score']);
        $n = 0;
        foreach (array_slice(array_filter($found, fn ($f) => $f['score'] >= (float) config('growth.trend.min_score', 40)), 0, (int) config('growth.trend.max_candidates', 15)) as $f) {
            $c = TrendCandidate::firstOrCreate(['week' => self::week(), 'item_key' => $f['item_key']], $f['attrs']);
            $c->wasRecentlyCreated && $n++;
        }
        $this->log->log('trends.scanned', 'system', null, null, null, 'ok', ['new' => $n]);

        return $n;
    }

    private function clamp(float $v): float
    {
        return max(0.0, min(100.0, round($v, 1)));
    }

    /** @param array<string,?float> $known components already known from the source */
    private function score(string $source, string $key, string $name, ?int $productId, array $known, array $evidence, array $keywords): array
    {
        $term = $productId ? ProductSupplyTerm::where('product_id', $productId)->first() : null;
        $needle = array_values(array_filter(array_slice(array_merge([$name], $keywords), 0, 3), fn ($k) => mb_strlen((string) $k) >= 3));
        $mentions = $needle ? Interaction::where('received_at', '>=', now()->subDays(30))->where(function ($q) use ($needle) {
            foreach ($needle as $k) {
                $q->orWhere('text_masked', 'like', '%'.addcslashes($k, '%_\\').'%');
            }
        })->count() : 0;
        $content = $productId ? ContentAsset::where('product_id', $productId)->whereIn('status', ['approved', 'published'])->exists() : false;
        $comp = $known + [
            'supplier' => $term?->supplier_id ? 100.0 : ($term ? 60.0 : null),
            'audience' => $mentions > 0 ? $this->clamp($mentions * 20) : 20.0,
            'content' => $content ? 100.0 : 30.0,
        ];
        $w = (array) config('growth.trend.weights');
        $unknown = [];
        $sum = 0.0;
        foreach ($w as $k => $weight) {
            $v = $comp[$k] ?? null;
            if ($v === null) {
                $unknown[] = $k;
                $v = 50.0;
            }
            $sum += $v * $weight;
        }
        $score = round($sum / max(1, array_sum($w)), 1);
        $evidence[] = ['source' => 'interactions', 'metric' => 'mentions_30d', 'value' => $mentions, 'period' => '30d'];

        return ['score' => $score, 'item_key' => $key, 'attrs' => ['name' => mb_substr($name, 0, 200), 'source' => $source, 'product_id' => $productId, 'score' => $score, 'components' => array_map(fn ($v) => $v === null ? null : round($v, 1), $comp), 'evidence' => $evidence, 'uncertainty' => $unknown,
            'confidence' => count($unknown) === 0 ? 'high' : (count($unknown) <= 2 ? 'medium' : 'low'), 'status' => 'new']];
    }

    public function candidates(?string $week = null)
    {
        return TrendCandidate::where('week', $week ?: self::week())->orderByDesc('score')->get();
    }

    public function dismiss(int $id, ?int $adminId): TrendCandidate
    {
        $c = TrendCandidate::findOrFail($id);
        $c->update(['status' => 'dismissed']);
        $this->log->log('trends.dismissed', 'admin', $adminId, null, $c);

        return $c;
    }

    /** Trend-to-Test: a DRAFT experiment only (idempotent per candidate). Requires the experiments feature. */
    public function toTest(int $id, ?int $adminId): GrowthExperiment
    {
        $c = TrendCandidate::findOrFail($id);
        if ($c->experiment_id && ($e = GrowthExperiment::find($c->experiment_id))) {
            return $e;
        }
        if (! $this->features->active('experiments')) {
            throw ValidationException::withMessages(['experiments' => __('growth_capital.trend.need_experiments')]);
        }
        $e = $this->experiments->create(['name' => mb_substr(__('growth_capital.trend.exp_name', ['name' => $c->name]), 0, 150), 'hypothesis' => __('growth_capital.trend.exp_hyp', ['name' => $c->name]),
            'objective' => __('growth_capital.trend.exp_obj'), 'metric' => __('growth_capital.trend.exp_metric'), 'status' => 'draft'], $adminId);
        $c->update(['status' => 'testing', 'experiment_id' => $e->id]);
        $this->log->log('trends.to_test', 'admin', $adminId, null, $c, 'ok', ['experiment_id' => $e->id]);

        return $e;
    }
}

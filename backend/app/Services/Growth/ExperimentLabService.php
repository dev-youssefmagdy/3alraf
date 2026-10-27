<?php

namespace App\Services\Growth;

use App\Models\ContentAsset;
use App\Models\ExperimentAdDay;
use App\Models\GrowthApproval;
use App\Models\GrowthExperiment;
use App\Models\GrowthExperimentResult;
use App\Models\Order;
use App\Models\StockLevel;
use App\Models\TenantProduct;
use App\Models\TrackedLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marketing Experiment Lab (spec 5) on top of growth_experiments. Product pick is guarded by stock + margin, the angle needs a Truth Guard pass before
 * the experiment may run, ad results are imported per day (idempotent upsert), orders are linked through utm_campaign = exp{id}, and a recommendation
 * (stop / revise / retest / scale_candidate) exists ONLY after the minimum duration, clicks and orders (config growth.lab). ROAS is a ratio, never profit.
 * Budget increases are never applied directly: they become an approval bounded by growth.lab.max_budget_increase_percent and the tenant cap.
 */
class ExperimentLabService
{
    public function __construct(private ExperimentService $experiments, private GrowthConfig $config, private TruthGuardService $guard, private ApprovalService $approvals,
        private ActionLogger $log, private TrackedLinkService $links) {}

    public function cfg(string $k): float
    {
        return (float) ($this->labConfig()[$k] ?? 0);
    }

    /** config('growth.lab') with the tenant's overrides (GrowthConfig keys lab_*). */
    public function labConfig(): array
    {
        $c = (array) config('growth.lab');
        $over = $this->config->overrides();
        foreach (GrowthConfig::LAB_KEYS as $k) {
            $v = $over['lab_'.$k] ?? null;
            if ($v !== null) {
                $c[$k] = $v;
            }
        }

        return $c;
    }

    /** Active, costed, in-stock, margin above the floor; else a validation error naming the reason. */
    public function assertProduct(int $productId): TenantProduct
    {
        $tp = TenantProduct::with('product')->where('product_id', $productId)->where('is_active', true)->first();
        $price = (float) ($tp?->sale_price ?: $tp?->price);
        $onHand = (int) StockLevel::where('product_id', $productId)->sum('on_hand');
        $floor = (float) $this->config->get('min_margin_percent', 15);
        $reason = match (true) {
            ! $tp || ! $tp->product => 'not_found', (float) $tp->cost <= 0 => 'missing_cost', $price <= 0 => 'no_price',
            $onHand <= (int) $this->config->get('low_stock_threshold', 5) => 'low_stock',
            ($price - (float) $tp->cost) / $price * 100 < $floor => 'margin_below_floor', default => null,
        };
        if ($reason) {
            throw ValidationException::withMessages(['product_id' => __('growth_hub.lab.product_'.$reason, ['floor' => $floor])]);
        }

        return $tp;
    }

    /** Angles produced by the Content Factory (N2) for this product, as candidates for the experiment. */
    public function angles(int $productId): array
    {
        return ContentAsset::where('product_id', $productId)->whereIn('type', ['angle', 'hook', 'ad_copy'])->orderByDesc('id')->limit(15)->get(['id', 'type', 'title', 'body'])->all();
    }

    public function create(array $d, ?int $adminId): GrowthExperiment
    {
        $this->assertProduct((int) $d['product_id']);
        $e = $this->experiments->create(['status' => 'draft', 'metric' => 'cm_after_ads'] + $d, $adminId);
        $e->forceFill(['product_id' => (int) $d['product_id'], 'audience' => $d['audience'] ?? null, 'platform' => $d['platform'] ?? null, 'angle' => $d['angle'] ?? null])->save();
        $e->forceFill(['utm_campaign' => 'exp'.$e->id])->save();

        return $e;
    }

    /** Claims review: the angle + hypothesis must pass the Truth Guard untouched before the experiment can run. */
    public function approveContent(GrowthExperiment $e, ?int $adminId): GrowthExperiment
    {
        $r = $this->guard->check(trim($e->angle.' '.$e->hypothesis), [$e->product_id]);
        if (! $r['ok']) {
            throw ValidationException::withMessages(['angle' => __('growth_hub.lab.claims_blocked', ['n' => count($r['removed']) + count($r['flagged'])])]);
        }
        $e->forceFill(['content_approved_at' => now()])->save();
        $this->log->log('lab.content_approved', 'admin', $adminId, null, $e);

        return $e;
    }

    public function start(GrowthExperiment $e, ?int $adminId): GrowthExperiment
    {
        if (! $e->content_approved_at) {
            throw ValidationException::withMessages(['status' => __('growth_hub.lab.content_required')]);
        }
        $this->assertProduct((int) $e->product_id);
        $e->update(['status' => 'running', 'starts_on' => $e->starts_on ?? now()->toDateString()]);
        $this->log->log('lab.started', 'admin', $adminId, null, $e);

        return $e;
    }

    public function link(GrowthExperiment $e, ?string $channel): TrackedLink
    {
        $l = $this->links->create((int) $e->product_id, $channel ?: ($e->platform ?: 'paid'), null, $e->utm_campaign);
        $l->update(['growth_experiment_id' => $e->id]);

        return $l;
    }

    /** @param array<int,array> $rows {date, spend, impressions, clicks, platform?, campaign_id?, ad_id?, creative?} */
    public function importAds(GrowthExperiment $e, array $rows, string $source = 'n8n', ?int $actorId = null): array
    {
        $n = 0;
        DB::transaction(function () use ($e, $rows, &$n) {
            foreach ($rows as $r) {
                ExperimentAdDay::updateOrCreate(
                    ['growth_experiment_id' => $e->id, 'day' => $r['date'], 'creative_key' => mb_substr((string) ($r['creative'] ?? ''), 0, 80)],
                    ['platform' => $r['platform'] ?? $e->platform, 'platform_campaign_id' => $r['campaign_id'] ?? null, 'platform_ad_id' => $r['ad_id'] ?? null,
                        'spend' => $r['spend'], 'impressions' => (int) ($r['impressions'] ?? 0), 'clicks' => (int) ($r['clicks'] ?? 0)]
                );
                $n++;
            }
            // Mirror daily totals into the generic results log so MetricsService CAC keeps working (one row per day, replaced on re-import).
            ExperimentAdDay::where('growth_experiment_id', $e->id)->selectRaw('day, SUM(spend) s')->groupBy('day')->get()->each(fn ($d) => GrowthExperimentResult::updateOrCreate(
                ['growth_experiment_id' => $e->id, 'recorded_on' => $d->day, 'metric' => 'ad_spend', 'source' => 'ad_import'], ['spend' => $d->s, 'value' => $d->s]));
        });
        app(AdCapService::class)->check(collect($rows)->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all());
        $total = (float) ExperimentAdDay::where('growth_experiment_id', $e->id)->sum('spend');
        $over = $e->budget_cap !== null && $total > (float) $e->budget_cap;
        $this->log->log('lab.ads_imported', $source === 'admin' ? 'admin' : 'n8n', $actorId, null, $e, 'ok', ['rows' => $n, 'over_budget' => $over]);

        return ['rows' => $n, 'total_spend' => round($total, 2), 'over_budget' => $over];
    }

    /** All figures for one experiment. ROAS is revenue / spend (reference only). */
    public function metrics(GrowthExperiment $e): array
    {
        $ad = ExperimentAdDay::where('growth_experiment_id', $e->id);
        $spend = (float) (clone $ad)->sum('spend');
        $impr = (int) (clone $ad)->sum('impressions');
        $clicks = (int) (clone $ad)->sum('clicks');
        $days = (int) (clone $ad)->where(fn ($q) => $q->where('spend', '>', 0)->orWhere('impressions', '>', 0))->distinct()->count('day');
        $orders = Order::with('items', 'returns')->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(attribution, '$.utm_campaign')) = ?", [$e->utm_campaign ?: 'exp'.$e->id])->get();
        $placed = $orders->count();
        $cancelled = $orders->filter(fn ($o) => ($o->status?->value ?? $o->status) === 'cancelled')->count();
        $returned = $orders->filter(fn ($o) => ($o->status?->value ?? $o->status) === 'returned')->count();
        $done = $orders->filter(fn ($o) => ($o->status?->value ?? $o->status) === 'delivered');
        $rev = 0.0;
        $cm = 0.0;
        $missing = false;
        foreach ($done as $o) {
            $f = StatsAggregator::orderFigures($o);
            $rev += $f['revenue'];
            $cm += $f['cm'];
            $missing = $missing || $f['missing_cogs'] || $f['missing_shipping'];
        }
        $ids = $orders->filter(fn ($o) => ($o->status?->value ?? $o->status) !== 'cancelled' && $o->customer_id)->pluck('customer_id')->unique();
        $new = $ids->filter(fn ($cid) => ! Order::where('customer_id', $cid)->where('status', '!=', 'cancelled')->where('placed_at', '<', $orders->where('customer_id', $cid)->min('placed_at'))->exists())->count();
        $m = fn (?float $v, array $miss = []) => MetricsService::m($v, 'confirmed', $miss);
        $nc = $placed - $cancelled;
        $out = [
            'spend' => round($spend, 2), 'impressions' => $impr, 'clicks' => $clicks, 'days_with_data' => $days, 'orders_placed' => $placed, 'orders_completed' => $done->count(),
            'ctr' => $impr > 0 ? round($clicks / $impr * 100, 2) : null, 'cvr' => $clicks > 0 ? round($placed / $clicks * 100, 2) : null,
            'cancel_rate' => $placed > 0 ? round($cancelled / $placed * 100, 1) : null, 'return_rate' => $nc > 0 ? round($returned / $nc * 100, 1) : null,
            'revenue' => round($rev, 2), 'aov' => $done->count() > 0 ? round($rev / $done->count(), 2) : null,
            'new_customers' => $new, 'cac' => $spend > 0 && $new > 0 ? round($spend / $new, 2) : null, 'cpa' => $spend > 0 && $nc > 0 ? round($spend / $nc, 2) : null,
            'roas' => $spend > 0 ? round($rev / $spend, 2) : null, // reference ratio only, NOT profit
            'contribution_margin' => $done->isEmpty() ? $m(null, ['completed_orders']) : ($missing ? $m(null, ['costs']) : $m($cm)),
        ];
        $cmv = $out['contribution_margin']['value'];
        $out['cm_after_ads'] = $cmv === null ? $m(null, $out['contribution_margin']['missing']) : $m($cmv - $spend);
        $out['recommendation'] = $this->recommend($out, $e);

        return $out;
    }

    /** Pure rule set: null action + reasons until the minimum sample / duration is met. */
    public function recommend(array $x, GrowthExperiment $e): array
    {
        $need = ['days' => (int) $this->cfg('min_days'), 'clicks' => (int) $this->cfg('min_clicks'), 'orders' => (int) $this->cfg('min_orders')];
        $reasons = array_values(array_filter([
            $x['days_with_data'] < $need['days'] ? 'min_days' : null, $x['clicks'] < $need['clicks'] ? 'min_clicks' : null, $x['orders_placed'] < $need['orders'] ? 'min_orders' : null, $x['spend'] <= 0 ? 'no_spend' : null,
        ]));
        if ($reasons) {
            return ['action' => null, 'status' => 'collecting', 'reasons' => $reasons, 'need' => $need];
        }
        $cm = $x['cm_after_ads']['value'];
        if ($cm === null) {
            return ['action' => null, 'status' => 'insufficient_data', 'reasons' => $x['cm_after_ads']['missing'] ?: ['costs'], 'need' => $need];
        }
        $action = match (true) {
            $cm < 0 && ($x['roas'] ?? 0) < 1 => 'stop',
            $cm < 0 => 'revise',
            $cm / max($x['spend'], 0.01) >= $this->cfg('scale_cm_ratio') && $x['orders_placed'] >= 2 * $need['orders'] => 'scale_candidate',
            default => 'retest',
        };

        return ['action' => $action, 'status' => 'ready', 'reasons' => [], 'need' => $need];
    }

    /** Rolling CTR / CPC decline of a creative vs its own baseline, only with enough impressions in both windows. Advice only: nothing is paused. */
    public function fatigue(GrowthExperiment $e): array
    {
        $recentN = (int) $this->cfg('fatigue_recent_days');
        $baseN = (int) $this->cfg('fatigue_baseline_days');
        $minI = (int) $this->cfg('fatigue_min_impressions');
        $out = [];
        ExperimentAdDay::where('growth_experiment_id', $e->id)->where('creative_key', '!=', '')->orderBy('day')->get()->groupBy('creative_key')->each(function ($rows, $key) use (&$out, $recentN, $baseN, $minI) {
            if ($rows->count() < $recentN + 3) {
                return;
            }
            $recent = $rows->slice(-$recentN);
            $base = $rows->slice(0, $rows->count() - $recentN)->take($baseN);
            $ri = (int) $recent->sum('impressions');
            $bi = (int) $base->sum('impressions');
            if ($ri < $minI || $bi < $minI) {
                return;
            }
            $rc = $recent->sum('clicks') / $ri * 100;
            $bc = $base->sum('clicks') / $bi * 100;
            $drop = $bc > 0 ? ($bc - $rc) / $bc * 100 : 0;
            $cpcR = $recent->sum('clicks') > 0 ? $recent->sum('spend') / $recent->sum('clicks') : null;
            $cpcB = $base->sum('clicks') > 0 ? $base->sum('spend') / $base->sum('clicks') : null;
            if ($drop >= $this->cfg('fatigue_ctr_drop_percent')) {
                $out[] = ['creative' => $key, 'baseline_ctr' => round($bc, 2), 'recent_ctr' => round($rc, 2), 'ctr_drop_percent' => round($drop, 1),
                    'cpc_change_percent' => $cpcR && $cpcB ? round(($cpcR - $cpcB) / $cpcB * 100, 1) : null, 'advice' => 'new_angle'];
            }
        });

        return $out;
    }

    public function requestBudgetIncrease(GrowthExperiment $e, float $newCap, ?string $reason, ?int $adminId): GrowthApproval
    {
        $this->assertBudget($e, $newCap);
        $pending = GrowthApproval::where('type', 'experiment_budget')->where('status', GrowthApproval::PENDING)->where('subject_id', $e->id)->where('subject_type', $e->getMorphClass())->exists();
        if ($pending) {
            throw ValidationException::withMessages(['budget_cap' => __('growth_hub.lab.budget_pending')]);
        }

        return $this->approvals->request('experiment_budget', __('growth_hub.lab.budget_title', ['name' => $e->name, 'cap' => $newCap]), ['experiment_id' => $e->id, 'old_cap' => (float) $e->budget_cap, 'new_cap' => $newCap, 'reason' => $reason], $e, $adminId ? 'admin:'.$adminId : 'system');
    }

    private function assertBudget(GrowthExperiment $e, float $newCap): void
    {
        $old = (float) $e->budget_cap;
        $max = $old * (1 + $this->cfg('max_budget_increase_percent') / 100);
        $tenantCap = (float) $this->config->get('ad_total_budget_cap', 0);
        $err = match (true) {
            $newCap <= $old => 'budget_not_increase',
            $newCap > $max + 0.0001 => 'budget_over_step',
            $tenantCap > 0 && $newCap > $tenantCap => 'budget_over_cap',
            ($this->metrics($e)['recommendation']['action'] ?? null) !== 'scale_candidate' => 'budget_not_ready',
            default => null,
        };
        if ($err) {
            throw ValidationException::withMessages(['budget_cap' => __('growth_hub.lab.'.$err, ['pct' => $this->cfg('max_budget_increase_percent')])]);
        }
    }

    /** Approval handler: re-checks the limits at approval time, then raises the cap (a ceiling, never a spend instruction). */
    public function materializeBudget(GrowthApproval $a): array
    {
        $e = GrowthExperiment::findOrFail($a->payload['experiment_id']);
        $new = (float) $a->payload['new_cap'];
        $this->assertBudget($e->fresh(), $new);
        $e->update(['budget_cap' => $new]);

        return ['experiment_id' => $e->id, 'budget_cap' => $new];
    }
}

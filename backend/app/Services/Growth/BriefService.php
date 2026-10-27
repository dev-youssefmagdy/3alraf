<?php

namespace App\Services\Growth;

use App\Models\GrowthExperiment;
use App\Models\TenantProduct;
use App\Services\Ai\AiAccess;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiContextFactory;
use App\Services\Ai\StudioService;
use App\Services\Ai\Support\AiRequest;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Executive Owner Brief (spec 15). Numbers come ONLY from MetricsService/StatsAggregator; every recommendation carries evidence[]
 * (metric, value, period, source). The optional AI summary only rephrases the computed numbers (no new facts); delivery is n8n's job.
 */
class BriefService
{
    public function __construct(private MetricsService $metrics, private StatsAggregator $stats, private GrowthConfig $config) {}

    public function daily(?CarbonInterface $date = null, bool $narrate = false): array
    {
        $day = ($date ?? now()->subDay())->copy()->startOfDay();
        $this->stats->rebuild($day->copy()->subDays(7), $day);
        $s = $this->metrics->summary($day, $day);
        $base = $this->metrics->summary($day->copy()->subDays(7), $day->copy()->subDay());
        $period = $day->toDateString();
        $cover = $this->metrics->velocityRows($day);
        $issues = $this->inventoryIssues($cover);
        $interest = $this->interest($day);

        $recs = [];
        foreach ($issues as $i) {
            $recs[] = $this->rec('restock_'.$i['product_id'], 'inventory_low', ['product_id' => $i['product_id']], 4, 2, [$this->ev('stock_levels', 'days_of_cover', $i['days_of_cover'], '30d'), $this->ev('stock_levels', 'on_hand', $i['on_hand'], $period)]);
        }
        foreach ($this->slowMovers($cover) as $i) {
            $recs[] = $this->rec('test_offer_'.$i['product_id'], 'slow_mover_offer', ['product_id' => $i['product_id']], 3, 2, [$this->ev('stock_levels', 'stock_value', $i['stock_value'], $period), $this->ev('order_items', 'units_sold_30d', 0, '30d')]);
        }

        $out = [
            'type' => 'daily', 'date' => $period, 'generated_at' => now()->toIso8601String(),
            'sales' => ['orders_completed' => $s['orders_completed'], 'revenue' => $s['revenue'], 'aov' => $s['aov'], 'vs_prev_7d_avg_revenue' => $this->prevAvg($base, 7)],
            'contribution_margin' => ['value' => $s['contribution_margin'], 'percent' => $s['contribution_margin_percent'], 'note_key' => $s['contribution_margin']['value'] === null ? 'cost_data_missing' : 'confirmed_costs'],
            'products_gaining_interest' => $interest, 'inventory_issues' => $issues,
            'conversations_and_complaints' => ['available' => false], // customer messaging module arrives in a later sprint
            'recommendations' => $this->rank($recs, 5),
        ];
        $out['ai_summary'] = $narrate ? $this->narrate($out) : null;

        return $out;
    }

    public function weekly(?CarbonInterface $endDate = null, bool $narrate = false): array
    {
        $end = ($endDate ?? now()->subDay())->copy()->startOfDay();
        $from = $end->copy()->subDays(6);
        $this->stats->rebuild($from->copy()->subDays(7), $end);
        $period = $from->toDateString().'..'.$end->toDateString();
        $s = $this->metrics->summary($from, $end);
        $prev = $this->metrics->summary($from->copy()->subDays(7), $from->copy()->subDay());
        $cover = $this->metrics->velocityRows($end);
        $min = (float) $this->config->get('min_days_of_cover', 14);
        $reorder = $cover->filter(fn ($r) => $r['days_of_cover'] !== null && $r['days_of_cover'] < $min)->take(10)->values()->all();
        $reasons = $this->metrics->returnReasons($from, $end);
        $bundles = $this->metrics->bundles($from, $end);
        $experiments = GrowthExperiment::with('results')->whereIn('status', ['running', 'completed', 'concluded', 'done'])->latest('id')->limit(5)->get()
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'status' => $e->status, 'outcome' => $e->outcome, 'learnings' => $e->learnings])->all();

        $recs = [];
        foreach ($reorder as $r) {
            $recs[] = $this->rec('reorder_'.$r['product_id'], 'reorder', ['product_id' => $r['product_id']], 4, 2, [$this->ev('stock_levels', 'days_of_cover', $r['days_of_cover'], '30d'), $this->ev('config', 'min_days_of_cover', $min, 'guardrail')]);
        }
        foreach ($this->slowMovers($cover) as $r) {
            $recs[] = $this->rec('offer_'.$r['product_id'], 'slow_mover_offer', ['product_id' => $r['product_id']], 3, 2, [$this->ev('stock_levels', 'stock_value', $r['stock_value'], $period)]);
        }
        if (($s['cancel_rate']['value'] ?? 0) > ($prev['cancel_rate']['value'] ?? 0) && ($s['cancel_rate']['value'] ?? 0) >= 10) {
            $recs[] = $this->rec('cancel_rate', 'review_cancellations', [], 4, 3, [$this->ev('growth_daily_stats', 'cancel_rate', $s['cancel_rate']['value'], $period), $this->ev('growth_daily_stats', 'cancel_rate_prev', $prev['cancel_rate']['value'], 'prev_7d')]);
        }
        if ($reasons) {
            $recs[] = $this->rec('return_reason', 'address_return_reason', ['reason' => $reasons[0]['reason']], 3, 3, [$this->ev('order_returns', 'top_reason_count', $reasons[0]['count'], $period)]);
        }

        $out = [
            'type' => 'weekly', 'period' => ['from' => $from->toDateString(), 'to' => $end->toDateString()], 'generated_at' => now()->toIso8601String(),
            'sales' => ['orders_completed' => $s['orders_completed'], 'revenue' => $s['revenue'], 'aov' => $s['aov'], 'prev_week_revenue' => $prev['revenue']],
            'contribution_margin' => ['value' => $s['contribution_margin'], 'percent' => $s['contribution_margin_percent']],
            'channel_profitability' => $this->metrics->byChannel($from, $end),
            'bundle_performance' => $bundles,
            'advertising' => ['spend_recorded' => $s['cac']['missing'] === ['ad_spend'] ? null : true, 'cac' => $s['cac'], 'note_key' => 'roas_is_not_profit'],
            'why_customers_do_not_buy' => ['cancel_rate' => $s['cancel_rate'], 'return_rate' => $s['return_rate'], 'return_reasons' => $reasons],
            'reorder_candidates' => $reorder, 'experiment_results' => $experiments,
            'recommendations' => $this->rank($recs, 3),
        ];
        $out['ai_summary'] = $narrate ? $this->narrate($out) : null;

        return $out;
    }

    private function ev(string $source, string $metric, mixed $value, string $period): array
    {
        return ['source' => $source, 'metric' => $metric, 'value' => $value, 'period' => $period];
    }

    private function rec(string $key, string $kind, array $params, int $impact, int $effort, array $evidence): array
    {
        return ['key' => $key, 'kind' => $kind, 'params' => $params, 'impact' => $impact, 'effort' => $effort, 'score' => round($impact / $effort, 2), 'evidence' => $evidence];
    }

    /** Highest impact/effort first; a recommendation without evidence is dropped. */
    private function rank(array $recs, int $limit): array
    {
        return collect($recs)->filter(fn ($r) => ! empty($r['evidence']))->sortByDesc(fn ($r) => [$r['score'], $r['impact']])->take($limit)->values()->all();
    }

    private function prevAvg(array $base, int $days): array
    {
        $v = $base['revenue']['value'];

        return MetricsService::m($v === null ? null : $v / $days, 'confirmed');
    }

    private function inventoryIssues($rows): array
    {
        $min = (float) $this->config->get('min_days_of_cover', 14);
        $low = (int) $this->config->get('low_stock_threshold', 5);

        return $rows->filter(fn ($r) => $r['sold'] > 0 && ($r['on_hand'] <= $low || ($r['days_of_cover'] !== null && $r['days_of_cover'] < $min)))->sortBy('days_of_cover')->take(10)->values()->all();
    }

    private function slowMovers($rows): array
    {
        return $rows->filter(fn ($r) => $r['on_hand'] > 0 && $r['sold'] === 0 && ! $r['cost_missing'])->sortByDesc('stock_value')->take(3)->values()->all();
    }

    /** Products whose units on the day beat their trailing 7-day daily average. */
    private function interest(CarbonInterface $day): array
    {
        $rows = \App\Models\GrowthDailyStat::where('scope', 'product')->whereBetween('stat_date', [$day->copy()->subDays(7)->toDateString(), $day->toDateString()])
            ->selectRaw('product_id, stat_date, SUM(units) u')->groupBy('product_id', 'stat_date')->get()->groupBy('product_id');
        $out = [];
        foreach ($rows as $pid => $r) {
            $today = (int) $r->filter(fn ($x) => $x->stat_date->isSameDay($day))->sum('u');
            $avg = $r->filter(fn ($x) => ! $x->stat_date->isSameDay($day))->sum('u') / 7;
            if ($today >= 2 && $today > $avg * 1.5) {
                $out[] = ['product_id' => (int) $pid, 'units' => $today, 'avg_prev_7d' => round($avg, 2), 'evidence' => [$this->ev('growth_daily_stats', 'units', $today, $day->toDateString()), $this->ev('growth_daily_stats', 'avg_units_prev_7d', round($avg, 2), '7d')]];
            }
        }

        return array_slice($out, 0, 5);
    }

    /** AI rewrites the computed brief in plain language. Failure or disabled AI = null; the structured data is always authoritative. */
    private function narrate(array $brief): ?string
    {
        try {
            $access = app(AiAccess::class);
            if (! $access->enabled(current_tenant())) {
                return null;
            }
            $model = app(StudioService::class)->model('text', null);
            if (! $model) {
                return null;
            }
            $facts = json_encode(array_diff_key($brief, ['ai_summary' => 1]), JSON_UNESCAPED_UNICODE);
            $req = new AiRequest($model->model_id, [['role' => 'user', 'content' => "Summarise this store brief for the owner in 5 short bullet points. Use ONLY the numbers given, never invent figures, say 'insufficient data' where a value is null.\n".$facts]],
                system: 'You are a careful business analyst. Never state profit when value is null. ROAS is not profit.', maxTokens: 600);

            return trim(app(AiGateway::class)->chat($model, $req, 'chat', [], app(AiContextFactory::class)->forTenant())->text) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

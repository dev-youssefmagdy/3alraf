<?php

namespace App\Services\Growth;

use App\Models\Cart;
use App\Models\CeoChallenge;
use App\Models\GrowthExperiment;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Weekly CEO Challenge (spec 16.6): ONE business question per ISO week chosen by explicit rules over the tenant's own metrics (evidence stored),
 * with hypothesis, small experiment, success metric, duration and review date. The same rule is not repeated within the cooldown.
 */
class CeoChallengeService
{
    public const RULES = ['shipping_checkout', 'low_conversion', 'high_returns', 'high_cancel', 'slow_inventory', 'low_repeat', 'content_gap'];
    public const STATUSES = ['open', 'reviewed', 'skipped'];

    public function __construct(private MetricsService $metrics, private ActionLogger $log, private ExperimentService $experiments, private GrowthFeatures $features) {}

    /** @return array<string,array{score:float,evidence:array}> */
    public function evaluate(): array
    {
        $to = now();
        $from = now()->subDays(29);
        $s = $this->metrics->summary($from, $to);
        $v = fn (string $k) => $s[$k]['value'] ?? null;
        $o = Order::whereBetween('placed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->where('status', '!=', 'cancelled');
        $sub = (float) (clone $o)->sum('subtotal');
        $ship = (float) (clone $o)->sum('shipping');
        $share = $sub > 0 ? $ship / $sub * 100 : null;
        $carts = Cart::whereHas('items')->where('updated_at', '<', now()->subDay())->where('updated_at', '>=', now()->subDays(14))->count();
        $e = fn (string $metric, $value, string $period = '30d') => ['source' => 'metrics', 'metric' => $metric, 'value' => $value === null ? null : round((float) $value, 2), 'period' => $period];
        $out = [];
        $out['shipping_checkout'] = ['score' => ($share !== null && $share >= 8 && $carts >= 5) ? min(100.0, $carts * 5.0) : 0.0, 'evidence' => [$e('shipping_share_of_subtotal_percent', $share), $e('abandoned_carts_14d', $carts, '14d')]];
        $out['low_conversion'] = ['score' => ($v('conversion_rate') !== null && $v('conversion_rate') < 2) ? 100 - $v('conversion_rate') * 20 : 0.0, 'evidence' => [$e('conversion_rate', $v('conversion_rate'))]];
        $out['high_returns'] = ['score' => ($v('return_rate') !== null && $v('return_rate') >= 5) ? min(100.0, $v('return_rate') * 8) : 0.0, 'evidence' => [$e('return_rate', $v('return_rate'))]];
        $out['high_cancel'] = ['score' => ($v('cancel_rate') !== null && $v('cancel_rate') >= 10) ? min(100.0, $v('cancel_rate') * 5) : 0.0, 'evidence' => [$e('cancel_rate', $v('cancel_rate'))]];
        $out['slow_inventory'] = ['score' => ($v('slow_mover_value') ?? 0) > 0 ? 60.0 : 0.0, 'evidence' => [$e('slow_mover_value', $v('slow_mover_value'))]];
        $out['low_repeat'] = ['score' => ($v('repeat_rate') !== null && $v('repeat_rate') < 15) ? 50.0 : 0.0, 'evidence' => [$e('repeat_rate', $v('repeat_rate'))]];
        $out['content_gap'] = ['score' => 10.0, 'evidence' => [['source' => 'system', 'metric' => 'fallback', 'value' => null, 'period' => 'n/a']]];

        return $out;
    }

    /** Idempotent per ISO week. */
    public function generate(): CeoChallenge
    {
        $week = TrendRadarService::week();
        if ($c = CeoChallenge::where('week', $week)->first()) {
            return $c;
        }
        $recent = CeoChallenge::where('created_at', '>=', now()->subWeeks((int) config('growth.ceo.cooldown_weeks', 4)))->pluck('rule')->all();
        $cands = array_filter($this->evaluate(), fn ($r, $k) => ! in_array($k, $recent, true) && $r['score'] > 0, ARRAY_FILTER_USE_BOTH);
        if (! $cands) {
            $cands = ['content_gap' => $this->evaluate()['content_gap']]; // everything cooling down: fall back rather than skip the week
        }
        uasort($cands, fn ($a, $b) => $b['score'] <=> $a['score']);
        $rule = array_key_first($cands);
        $days = (int) config('growth.ceo.duration_days', 14);
        $c = CeoChallenge::create(['week' => $week, 'rule' => $rule, 'question' => __("growth_capital.ceo.rules.$rule.question"), 'hypothesis' => __("growth_capital.ceo.rules.$rule.hypothesis"),
            'experiment' => __("growth_capital.ceo.rules.$rule.experiment"), 'metric' => __("growth_capital.ceo.rules.$rule.metric"), 'duration_days' => $days, 'review_on' => now()->addDays($days)->toDateString(), 'evidence' => $cands[$rule]['evidence'], 'status' => 'open']);
        $this->log->log('ceo.generated', 'system', null, null, $c, 'ok', ['rule' => $rule]);

        return $c;
    }

    public function review(int $id, array $d, ?int $adminId): CeoChallenge
    {
        $c = CeoChallenge::findOrFail($id);
        if (! in_array($d['status'] ?? '', self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('growth_hub.voc.bad_status')]);
        }
        $c->update(['status' => $d['status'], 'result_note' => $d['result_note'] ?? $c->result_note]);
        $this->log->log('ceo.reviewed', 'admin', $adminId, null, $c, 'ok', ['status' => $c->status]);

        return $c;
    }

    /** Optional: turn the proposed small experiment into a DRAFT experiment (needs the experiments feature). */
    public function toExperiment(int $id, ?int $adminId): GrowthExperiment
    {
        $c = CeoChallenge::findOrFail($id);
        if ($c->experiment_id && ($e = GrowthExperiment::find($c->experiment_id))) {
            return $e;
        }
        if (! $this->features->active('experiments')) {
            throw ValidationException::withMessages(['experiments' => __('growth_capital.trend.need_experiments')]);
        }
        $e = $this->experiments->create(['name' => mb_substr($c->question, 0, 150), 'hypothesis' => $c->hypothesis, 'objective' => $c->experiment, 'metric' => $c->metric, 'starts_on' => now()->toDateString(), 'ends_on' => $c->review_on->toDateString(), 'status' => 'draft'], $adminId);
        $c->update(['experiment_id' => $e->id]);

        return $e;
    }

    public function history(int $limit = 20)
    {
        return CeoChallenge::orderByDesc('week')->limit($limit)->get();
    }
}

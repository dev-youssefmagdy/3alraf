<?php

namespace App\Services\Growth;

use App\Models\Cart;
use App\Models\ContentAsset;
use App\Models\Interaction;
use App\Models\MissedOpportunity;
use App\Models\OrderItem;
use App\Models\StorefrontVisit;
use App\Models\TrackedLink;
use Illuminate\Validation\ValidationException;

/**
 * Missed Opportunity Inbox: explicit rules, each with evidence, probable cause + confidence, ONE experiment suggestion, owner and deadline.
 * Idempotent per (rule, product, ISO week): re-scanning never duplicates, and a dismissed item is not re-raised that week.
 */
class MissedOpportunityService
{
    public const SLA_HOURS = 24;
    public const STATUSES = ['open', 'accepted', 'done', 'dismissed'];

    public function __construct(private ActionLogger $log, private TrackedLinkService $links) {}

    /** @return int new items */
    public function scan(): int
    {
        $n = 0;
        $from = now()->subDays(30);
        $orders = OrderItem::whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled')->where('placed_at', '>=', $from))->selectRaw('product_id, COUNT(DISTINCT order_id) c')->groupBy('product_id')->pluck('c', 'product_id');
        $inq = Interaction::whereNotNull('product_id')->where('received_at', '>=', $from)->get()->groupBy('product_id');

        foreach ($inq as $pid => $rows) {
            $top = $rows->whereNotNull('category')->groupBy('category')->map->count()->sortDesc();
            $cause = $top->isNotEmpty() ? __('growth_hub.missed.cause_topic', ['topic' => __('growth_hub.inbox.categories.'.$top->keys()->first())]) : __('growth_hub.missed.cause_unknown');
            $o = (int) ($orders[$pid] ?? 0);
            $intent = $rows->where('intent', 'purchase_intent')->count();
            if (max($intent, $rows->count()) >= 5 && $o * 4 <= max($intent, $rows->count())) {
                $n += $this->raise('inquiries_gt_orders', (int) $pid, [['source' => 'interactions', 'metric' => 'inquiries_30d', 'value' => $rows->count(), 'period' => '30d'], ['source' => 'orders', 'metric' => 'orders_30d', 'value' => $o, 'period' => '30d']], $cause, $rows->count() >= 15 ? 'high' : 'medium');
            }
            if ($rows->count() >= 5 && ! ContentAsset::where('product_id', $pid)->whereIn('status', ['approved', 'published'])->exists()) {
                $n += $this->raise('demand_no_content', (int) $pid, [['source' => 'interactions', 'metric' => 'inquiries_30d', 'value' => $rows->count(), 'period' => '30d'], ['source' => 'content_assets', 'metric' => 'approved_assets', 'value' => 0, 'period' => 'all']], __('growth_hub.missed.cause_no_content'), 'medium');
            }
            foreach ($top as $cat => $c) {
                if (in_array($cat, VocService::OBJECTIONS, true) && $c >= 5) {
                    $n += $this->raise('recurring_objection', (int) $pid, [['source' => 'interactions', 'metric' => 'mentions_30d', 'value' => $c, 'period' => '30d', 'topic' => $cat]], __('growth_hub.missed.cause_topic', ['topic' => __('growth_hub.inbox.categories.'.$cat)]), $c >= 10 ? 'high' : 'medium', $cat);
                }
            }
        }
        // traffic with poor conversion (visits are cookie-session counts; orders are distinct orders)
        $visits = StorefrontVisit::where('product_id', '>', 0)->where('visit_date', '>=', $from->toDateString())->selectRaw('product_id, SUM(unique_sessions) s')->groupBy('product_id')->pluck('s', 'product_id');
        foreach ($visits as $pid => $s) {
            $o = (int) ($orders[$pid] ?? 0);
            if ($s >= 100 && $o / $s < 0.01) {
                $n += $this->raise('traffic_low_conversion', (int) $pid, [['source' => 'storefront_visits', 'metric' => 'sessions_30d', 'value' => (int) $s, 'period' => '30d'], ['source' => 'orders', 'metric' => 'orders_30d', 'value' => $o, 'period' => '30d']], __('growth_hub.missed.cause_conversion'), $s >= 300 ? 'high' : 'medium');
            }
        }
        $late = app(InteractionService::class)->unanswered(self::SLA_HOURS)->count();
        if ($late > 0) {
            $n += $this->raise('unanswered_sla', null, [['source' => 'interactions', 'metric' => 'unanswered_over_sla', 'value' => $late, 'period' => 'now', 'sla_hours' => self::SLA_HOURS]], __('growth_hub.missed.cause_sla'), 'high');
        }
        $carts = Cart::whereHas('items')->where('updated_at', '<', now()->subDay())->where('updated_at', '>=', now()->subDays(14))->count(); // carts are cleared at checkout, so stale non-empty carts are abandoned
        if ($carts >= 5) {
            $n += $this->raise('abandoned_carts', null, [['source' => 'carts', 'metric' => 'abandoned_carts_14d', 'value' => $carts, 'period' => '14d']], __('growth_hub.missed.cause_carts'), 'low');
        }
        foreach (TrackedLink::where('clicks', '>=', 20)->get() as $l) {
            if ($this->links->stats($l)['orders'] === 0) {
                $n += $this->raise('clicks_low_profit', $l->product_id, [['source' => 'tracked_links', 'metric' => 'clicks', 'value' => $l->clicks, 'period' => 'all', 'link' => $l->code], ['source' => 'orders', 'metric' => 'orders_from_link', 'value' => 0, 'period' => 'all']], __('growth_hub.missed.cause_clicks'), 'medium');
            }
        }

        return $n;
    }

    private function raise(string $rule, ?int $pid, array $evidence, string $cause, string $conf, string $sub = ''): int
    {
        $key = substr($rule.':'.($pid ?? 0).':'.$sub.':'.now()->format('o-W'), 0, 80);
        if (MissedOpportunity::where('dedupe_key', $key)->exists()) {
            return 0;
        }
        $name = $pid ? (\App\Models\Product::whereKey($pid)->first()?->getTranslation('name', app()->getLocale(), true) ?? '#'.$pid) : '';
        $m = MissedOpportunity::create(['rule' => $rule, 'dedupe_key' => $key, 'product_id' => $pid, 'title' => mb_substr(__('growth_hub.missed.rules.'.$rule, ['product' => $name]), 0, 200), 'evidence' => $evidence, 'probable_cause' => mb_substr($cause, 0, 300),
            'confidence' => $conf, 'suggestion' => __('growth_hub.missed.experiments.'.$rule), 'due_on' => now()->addDays(7)->toDateString(), 'status' => 'open']);
        $this->log->log('missed.raised', 'system', null, null, $m, 'ok', ['rule' => $rule]);

        return 1;
    }

    public function update(int $id, array $d, ?int $adminId): MissedOpportunity
    {
        $m = MissedOpportunity::findOrFail($id);
        if (isset($d['status']) && ! in_array($d['status'], self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('growth_hub.voc.bad_status')]);
        }
        $m->update(array_intersect_key($d, array_flip(['status', 'owner', 'due_on', 'result_note'])));
        $this->log->log('missed.updated', 'admin', $adminId, null, $m, 'ok', ['status' => $m->status]);

        return $m;
    }

    public function weekly(): array
    {
        $items = MissedOpportunity::whereIn('status', ['open', 'accepted'])->orderByRaw("FIELD(confidence,'high','medium','low')")->orderBy('id')->get();

        return ['type' => 'missed_opportunities_weekly', 'week' => now()->format('o-\WW'), 'counts' => MissedOpportunity::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all(),
            'items' => $items->map(fn ($m) => ['id' => $m->id, 'rule' => $m->rule, 'title' => $m->title, 'product_id' => $m->product_id, 'evidence' => $m->evidence, 'probable_cause' => $m->probable_cause, 'confidence' => $m->confidence,
                'experiment_suggestion' => $m->suggestion, 'owner' => $m->owner, 'due_on' => $m->due_on?->toDateString(), 'status' => $m->status, 'result_note' => $m->result_note])->all()];
    }
}

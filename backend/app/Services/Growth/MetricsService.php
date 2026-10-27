<?php

namespace App\Services\Growth;

use App\Models\Cart;
use App\Models\GrowthDailyStat;
use App\Models\GrowthExperimentResult;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockLevel;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Growth metrics for the current tenant. Every metric is {value, confidence, missing[]}:
 * confirmed = computed only from stored ledger figures; estimated = derived/approximated (velocity, carts as visits, ...);
 * insufficient = an input is missing, value is null (never a profit figure from partial costs).
 */
class MetricsService
{
    public function __construct(private StatsAggregator $stats) {}

    public static function m(?float $value, string $confidence = 'confirmed', array $missing = []): array
    {
        return ['value' => $value === null ? null : round($value, 2), 'confidence' => $value === null ? 'insufficient' : $confidence, 'missing' => array_values($missing)];
    }

    private function orderStats(CarbonInterface $from, CarbonInterface $to, ?string $channel)
    {
        return GrowthDailyStat::where('scope', 'order')->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->when($channel, fn ($q) => $q->where('channel', $channel));
    }

    /** Core order-based metrics from growth_daily_stats. */
    public function summary(CarbonInterface $from, CarbonInterface $to, ?string $channel = null): array
    {
        $a = $this->orderStats($from, $to, $channel)->selectRaw('COALESCE(SUM(orders_placed),0) p, COALESCE(SUM(orders_completed),0) c, COALESCE(SUM(orders_cancelled),0) x, COALESCE(SUM(orders_returned),0) r, COALESCE(SUM(revenue),0) rev, COALESCE(SUM(contribution_margin),0) cm, COALESCE(SUM(missing_cogs),0) mc, COALESCE(SUM(missing_shipping),0) ms')->first();
        $placed = (int) $a->p;
        $done = (int) $a->c;
        $missing = array_values(array_filter([$a->mc > 0 ? 'cogs' : null, $a->ms > 0 ? 'shipping_cost' : null]));
        $cm = $done === 0 ? self::m(null, 'confirmed', ['completed_orders']) : ($missing ? self::m(null, 'confirmed', $missing) : self::m((float) $a->cm));
        $pct = ($cm['value'] === null || (float) $a->rev <= 0) ? self::m(null, 'confirmed', $cm['missing'] ?: ['revenue']) : self::m((float) $a->cm / (float) $a->rev * 100);
        $rate = fn (int $n) => $placed === 0 ? self::m(null, 'confirmed', ['orders']) : self::m($n / $placed * 100);

        return [
            'orders_placed' => self::m($placed), 'orders_completed' => self::m($done), 'revenue' => $done === 0 ? self::m(0.0) : self::m((float) $a->rev),
            'contribution_margin' => $cm, 'contribution_margin_percent' => $pct,
            'aov' => $done === 0 ? self::m(null, 'confirmed', ['completed_orders']) : self::m((float) $a->rev / $done),
            'cancel_rate' => $rate((int) $a->x), 'return_rate' => $rate((int) $a->r),
            'conversion_rate' => $this->conversion($from, $to, $placed, $channel),
            'cac' => $this->cac($from, $to, $channel),
            'repeat_rate' => $this->repeatRate($from, $to),
        ] + $this->inventory($to);
    }

    /** Orders per cart created (no visit tracking exists): an estimate, only when carts exist. */
    private function conversion(CarbonInterface $from, CarbonInterface $to, int $placed, ?string $channel): array
    {
        if ($channel) {
            return self::m(null, 'estimated', ['channel_visits']);
        }
        $carts = Cart::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count();

        return $carts === 0 ? self::m(null, 'estimated', ['visits']) : self::m(min($placed, $carts) / $carts * 100, 'estimated');
    }

    /** CAC = recorded ad spend (experiment results) / new customers with a first order in the period. Needs spend data. */
    private function cac(CarbonInterface $from, CarbonInterface $to, ?string $channel): array
    {
        $spend = (float) GrowthExperimentResult::whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()])->sum('spend');
        if ($spend <= 0) {
            return self::m(null, 'estimated', ['ad_spend']);
        }
        $new = Order::whereBetween('placed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->whereNotNull('customer_id')
            ->whereNotIn('status', ['cancelled'])->select('customer_id')->groupBy('customer_id')
            ->havingRaw('MIN(placed_at) >= ?', [$from->copy()->startOfDay()])->get()->count();

        return $new === 0 ? self::m(null, 'estimated', ['new_customers']) : self::m($spend / $new, 'estimated');
    }

    /** Share of customers with an order in the period who also ordered before it (identified customers only). */
    private function repeatRate(CarbonInterface $from, CarbonInterface $to): array
    {
        $s = $from->copy()->startOfDay();
        $ids = Order::whereBetween('placed_at', [$s, $to->copy()->endOfDay()])->whereNotNull('customer_id')->whereNotIn('status', ['cancelled'])->distinct()->pluck('customer_id');
        if ($ids->isEmpty()) {
            return self::m(null, 'estimated', ['customers']);
        }
        $repeat = Order::whereIn('customer_id', $ids)->where('placed_at', '<', $s)->whereNotIn('status', ['cancelled'])->distinct()->count('customer_id');

        return self::m($repeat / $ids->count() * 100, 'estimated');
    }

    /** Aggregate inventory metrics (30-day window ending at $to). */
    public function inventory(CarbonInterface $to, int $days = 30): array
    {
        $rows = $this->velocityRows($to, $days);
        $onHand = $rows->sum('on_hand');
        $units = $rows->sum('sold');
        $value = (float) $rows->sum('stock_value');
        $costMissing = $rows->where('on_hand', '>', 0)->where('cost_missing', true)->count() > 0;
        $slow = $rows->where('on_hand', '>', 0)->where('sold', 0);
        $cogs30 = (float) GrowthDailyStat::where('scope', 'product')->whereBetween('stat_date', [$to->copy()->subDays($days - 1)->toDateString(), $to->toDateString()])->sum('cogs');

        return [
            'days_of_cover' => $units <= 0 ? self::m(null, 'estimated', ['sales_velocity']) : self::m($onHand / ($units / $days), 'estimated'),
            'sell_through' => ($units + $onHand) <= 0 ? self::m(null, 'estimated', ['stock']) : self::m($units / ($units + $onHand) * 100, 'estimated'),
            'inventory_turnover' => ($value <= 0 || $costMissing) ? self::m(null, 'estimated', $costMissing ? ['avg_cost'] : ['inventory_value']) : self::m($cogs30 * (365 / $days) / $value, 'estimated'),
            'slow_mover_value' => $costMissing ? self::m(null, 'estimated', ['avg_cost']) : self::m((float) $slow->sum('stock_value'), 'estimated'),
        ];
    }

    /** Per product: on_hand, units sold in the window (completed orders), daily velocity, days of cover, stock value. */
    public function velocityRows(CarbonInterface $to, int $days = 30)
    {
        $stock = StockLevel::selectRaw('product_id, SUM(on_hand) on_hand, SUM(on_hand * avg_cost) val, SUM(CASE WHEN on_hand > 0 AND avg_cost <= 0 THEN 1 ELSE 0 END) nocost')->groupBy('product_id')->get()->keyBy('product_id');
        $sold = OrderItem::whereHas('order', fn ($q) => $q->where('status', 'delivered')->whereBetween('placed_at', [$to->copy()->subDays($days - 1)->startOfDay(), $to->copy()->endOfDay()]))
            ->selectRaw('product_id, SUM(quantity) q')->groupBy('product_id')->pluck('q', 'product_id');
        $ids = $stock->keys()->merge($sold->keys())->unique();

        return $ids->map(function ($id) use ($stock, $sold, $days) {
            $oh = (int) ($stock[$id]->on_hand ?? 0);
            $q = (int) ($sold[$id] ?? 0);
            $vel = $q / $days;

            return ['product_id' => (int) $id, 'on_hand' => $oh, 'sold' => $q, 'daily_velocity' => round($vel, 3), 'days_of_cover' => $vel > 0 ? round($oh / $vel, 1) : null,
                'stock_value' => round((float) ($stock[$id]->val ?? 0), 2), 'cost_missing' => (int) ($stock[$id]->nocost ?? 0) > 0];
        })->values();
    }

    /** Contribution margin per product for a period (stats product rows). */
    public function byProduct(CarbonInterface $from, CarbonInterface $to, int $limit = 100): array
    {
        return GrowthDailyStat::where('scope', 'product')->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('product_id, SUM(units) units, SUM(revenue) revenue, SUM(cogs) cogs, SUM(contribution_margin) cm, SUM(missing_cogs) mc, SUM(missing_shipping) ms')
            ->groupBy('product_id')->orderByDesc('revenue')->limit($limit)->get()->map(fn ($r) => [
                'product_id' => (int) $r->product_id, 'units' => (int) $r->units, 'revenue' => round((float) $r->revenue, 2),
                'contribution_margin' => ($r->mc > 0 || $r->ms > 0) ? self::m(null, 'confirmed', array_values(array_filter([$r->mc > 0 ? 'cogs' : null, $r->ms > 0 ? 'shipping_cost' : null]))) : self::m((float) $r->cm),
            ])->all();
    }

    /** Channel view: orders, revenue, margin (after known costs) per channel. */
    public function byChannel(CarbonInterface $from, CarbonInterface $to): array
    {
        $out = [];
        foreach (['direct', 'affiliate'] as $ch) {
            $s = $this->summary($from, $to, $ch);
            $out[$ch] = array_intersect_key($s, array_flip(['orders_placed', 'orders_completed', 'revenue', 'contribution_margin', 'contribution_margin_percent', 'aov', 'cancel_rate', 'return_rate']));
        }

        return $out;
    }

    /** Bundle performance from order lines (revenue, cogs, units, orders). Line-level margin: shipping/fees are not allocated to bundles. */
    public function bundles(CarbonInterface $from, CarbonInterface $to): array
    {
        return OrderItem::whereNotNull('bundle_id')->whereHas('order', fn ($q) => $q->where('status', 'delivered')->whereBetween('placed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))
            ->selectRaw('bundle_id, MAX(bundle_name) name, SUM(quantity) units, COUNT(DISTINCT order_id) orders, SUM(line_subtotal - line_discount) revenue, SUM(unit_cost * quantity) cogs, SUM(CASE WHEN unit_cost IS NULL OR unit_cost <= 0 THEN 1 ELSE 0 END) nocost')
            ->groupBy('bundle_id')->get()->map(fn ($r) => [
                'bundle_id' => (int) $r->bundle_id, 'name' => $r->name, 'units' => (int) $r->units, 'orders' => (int) $r->orders, 'revenue' => round((float) $r->revenue, 2),
                'line_margin' => $r->nocost > 0 ? self::m(null, 'confirmed', ['cogs']) : self::m((float) $r->revenue - (float) $r->cogs),
            ])->all();
    }

    /** Return reasons (counts + value), PII-free. */
    public function returnReasons(CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('order_returns')->where('tenant_id', current_tenant()->id)->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('COALESCE(NULLIF(reason, \'\'), \'unspecified\') reason, COUNT(*) returns_count, SUM(return_value) value')->groupBy('reason')->orderByDesc('returns_count')->limit(50)->get()
            ->map(fn ($r) => ['reason' => mb_substr((string) $r->reason, 0, 120), 'count' => (int) $r->returns_count, 'value' => round((float) $r->value, 2)])->all();
    }

    /** Aggregate customer segments (counts only, no identities). */
    public function customerSegments(CarbonInterface $to): array
    {
        $per = Order::whereNotNull('customer_id')->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])->selectRaw('customer_id, COUNT(*) n, MAX(placed_at) last_at')->groupBy('customer_id')->get();
        $cut = $to->copy()->subDays(90);

        return ['total_customers' => $per->count(), 'one_time' => $per->where('n', 1)->count(), 'repeat' => $per->where('n', '>=', 2)->count(),
            'loyal_3plus' => $per->where('n', '>=', 3)->count(), 'lapsed_90d' => $per->filter(fn ($r) => \Carbon\Carbon::parse($r->last_at)->lt($cut))->count()];
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Reports\ReportFilter;
use App\Enums\OrderStatus;
use App\Repositories\Contracts\MarketingRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** See MarketingRepositoryInterface. Plain query builder, money SUMs rounded to 2 decimals in SQL (shop currency, no conversion). */
class MarketingRepository implements MarketingRepositoryInterface
{
    private function salesStatuses(): array
    {
        return array_map(fn ($s) => $s->value, OrderStatus::salesStatuses());
    }

    private function orders(ReportFilter $f, ?array $statuses = null): Builder
    {
        return DB::table('orders as o')->whereNull('o.deleted_at')->whereBetween('o.created_at', [$f->start(), $f->end()])
            ->whereIn('o.status', $statuses ?? $this->salesStatuses());
    }

    public function itemSales(ReportFilter $f, ?array $productIds = null, bool $byVariant = false): array
    {
        $net = '(oi.quantity - oi.returned_qty)';
        $rev = "(oi.line_subtotal - oi.line_discount) * $net / oi.quantity";
        $q = $this->orders($f)->join('order_items as oi', 'oi.order_id', '=', 'o.id')->where('oi.quantity', '>', 0)
            ->when($productIds !== null, fn ($q) => $q->whereIn('oi.product_id', $productIds ?: [0]))
            ->selectRaw('oi.product_id as product_id, '.($byVariant ? 'oi.product_variant_id' : '0')." as variant_id,
                SUM($net) as units, SUM(oi.returned_qty) as returned_units, COUNT(DISTINCT o.id) as orders,
                ROUND(SUM($rev), 2) as revenue,
                ROUND(SUM(CASE WHEN oi.unit_cost > 0 THEN $rev ELSE 0 END), 2) as revenue_costed,
                ROUND(SUM(CASE WHEN oi.unit_cost > 0 THEN oi.unit_cost * $net ELSE 0 END), 2) as cost,
                SUM(CASE WHEN oi.unit_cost > 0 THEN 0 ELSE $net END) as uncosted_units,
                MAX(o.created_at) as last_sold_at")
            ->groupBy('oi.product_id');
        if ($byVariant) {
            $q->groupBy('oi.product_variant_id');
        }

        $out = [];
        foreach ($q->get() as $r) {
            $key = $byVariant ? $r->product_id.':'.(int) $r->variant_id : (string) $r->product_id;
            $out[$key] = ['product_id' => (int) $r->product_id, 'variant_id' => (int) $r->variant_id, 'units' => (int) $r->units, 'returned_units' => (int) $r->returned_units,
                'orders' => (int) $r->orders, 'revenue' => (string) ($r->revenue ?? '0.00'), 'revenue_costed' => (string) ($r->revenue_costed ?? '0.00'),
                'cost' => (string) ($r->cost ?? '0.00'), 'uncosted_units' => (int) $r->uncosted_units, 'last_sold_at' => $r->last_sold_at];
        }

        return $out;
    }

    private function stock(?array $productIds, bool $byVariant): Builder
    {
        return DB::table('stock_levels as sl')->join('branches as b', 'b.id', '=', 'sl.branch_id')->where('b.is_active', true)->whereNull('b.deleted_at')
            ->when($productIds !== null, fn ($q) => $q->whereIn('sl.product_id', $productIds ?: [0]))
            ->selectRaw('sl.product_id as product_id, '.($byVariant ? 'sl.product_variant_id' : '0').' as variant_id, SUM(sl.on_hand) as on_hand, SUM(sl.reserved) as reserved')
            ->groupBy('sl.product_id')->when($byVariant, fn ($q) => $q->groupBy('sl.product_variant_id'));
    }

    private function stockRow(object $r): array
    {
        return ['on_hand' => (int) $r->on_hand, 'reserved' => (int) $r->reserved, 'available' => max(0, (int) $r->on_hand - (int) $r->reserved), 'stocked' => true];
    }

    public function stockByProduct(?array $productIds = null): array
    {
        return $this->stock($productIds, false)->get()->mapWithKeys(fn ($r) => [(int) $r->product_id => $this->stockRow($r)])->all();
    }

    public function stockByVariant(array $productIds): array
    {
        return $this->stock($productIds, true)->get()->mapWithKeys(fn ($r) => [$r->product_id.':'.(int) $r->variant_id => $this->stockRow($r)])->all();
    }

    public function lastSoldAt(array $productIds): array
    {
        if (! $productIds) {
            return [];
        }

        return DB::table('orders as o')->join('order_items as oi', 'oi.order_id', '=', 'o.id')->whereNull('o.deleted_at')->whereIn('o.status', $this->salesStatuses())
            ->whereIn('oi.product_id', $productIds)->groupBy('oi.product_id')->selectRaw('oi.product_id as pid, MAX(o.created_at) as last')
            ->pluck('last', 'pid')->mapWithKeys(fn ($v, $k) => [(int) $k => (string) $v])->all();
    }

    public function ordersByChannel(ReportFilter $f): array
    {
        return $this->orders($f)->selectRaw("COALESCE(NULLIF(o.channel, ''), 'unknown') as ch, COUNT(*) as c")->groupBy('ch')->orderByDesc('c')
            ->pluck('c', 'ch')->map(fn ($v) => (int) $v)->all();
    }

    public function cancellations(ReportFilter $f): array
    {
        $rows = $this->orders($f, [OrderStatus::CANCELLED->value])->selectRaw("COALESCE(o.cancel_reason_code, '') as code, COUNT(*) as c")->groupBy('code')->pluck('c', 'code');

        return ['total' => (int) $rows->sum(), 'by_reason' => $rows->mapWithKeys(fn ($v, $k) => [$k === '' ? 'none' : $k => (int) $v])->all()];
    }

    public function couponUsage(ReportFilter $f): array
    {
        return $this->orders($f)->join('discount_code_usages as u', 'u.order_id', '=', 'o.id')
            ->selectRaw('u.discount_code_id as id, COUNT(*) as usages, ROUND(SUM(u.amount), 2) as discount, ROUND(SUM(o.total), 2) as orders_total')
            ->groupBy('u.discount_code_id')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->id => ['usages' => (int) $r->usages, 'discount' => (string) $r->discount, 'orders_total' => (string) $r->orders_total]])->all();
    }

    public function bundleSales(ReportFilter $f): array
    {
        return $this->orders($f)->join('order_items as oi', 'oi.order_id', '=', 'o.id')->where('oi.bundle_id', '>', 0)
            ->selectRaw('oi.bundle_id as bid, SUM(oi.quantity - oi.returned_qty) as c')->groupBy('oi.bundle_id')
            ->pluck('c', 'bid')->mapWithKeys(fn ($v, $k) => [(int) $k => (int) $v])->all();
    }
}

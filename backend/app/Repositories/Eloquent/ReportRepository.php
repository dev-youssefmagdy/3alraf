<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Reports\ReportFilter;
use App\Enums\OrderStatus;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Report aggregates with the plain query builder (cross-tenant in central context, so global scopes are bypassed and tenant ids are filtered explicitly).
 * Every money SUM is `ROUND(SUM(col * rate), 2)`; rate = tenants.currency_rate in central context, 1 in a tenant context. See docs/CALCULATIONS.md §Reports.
 */
class ReportRepository implements ReportRepositoryInterface
{
    private function salesStatuses(): array
    {
        return array_map(fn ($s) => $s->value, OrderStatus::salesStatuses());
    }

    private function rate(ReportFilter $f): string
    {
        return $f->central ? 't.currency_rate' : '1';
    }

    /** money aggregate expression */
    private function m(ReportFilter $f, string $expr): string
    {
        return "ROUND(SUM(($expr) * {$this->rate($f)}), 2)";
    }

    /** localized name of a json translatable column */
    private function nm(string $col): string
    {
        $l = preg_replace('/[^A-Za-z_-]/', '', app()->getLocale());

        return "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT($col, '$.\"$l\"')), ''), JSON_UNQUOTE(JSON_EXTRACT($col, '$.en')), '')";
    }

    private function base(ReportFilter $f, string $table, string $alias): Builder
    {
        $q = DB::table("$table as $alias")->join('tenants as t', 't.id', '=', "$alias.tenant_id");
        if ($f->tenantIds !== null) {
            $q->whereIn("$alias.tenant_id", $f->tenantIds);
        }

        return $q;
    }

    private function orders(ReportFilter $f, ?array $statuses = null, string $dateCol = 'created_at'): Builder
    {
        $q = $this->base($f, 'orders', 'o')->whereBetween("o.$dateCol", [$f->start(), $f->end()])->whereIn('o.status', $statuses ?? $this->salesStatuses());
        if ($f->branchId) {
            $q->where('o.branch_id', $f->branchId);
        }

        return $q;
    }

    private function rows(Builder $q): array
    {
        return $q->get()->map(fn ($r) => (array) $r)->all();
    }

    public function purchases(ReportFilter $f): array
    {
        $items = DB::table('purchase_order_items')->selectRaw('purchase_order_id, SUM(quantity) as q')->groupBy('purchase_order_id');
        $q = $this->base($f, 'purchase_orders', 'po')->where('po.status', 'received')->whereBetween('po.created_at', [$f->start(), $f->end()])
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')->leftJoinSub($items, 'pi', 'pi.purchase_order_id', '=', 'po.id');
        if ($f->branchId) {
            $q->where('po.branch_id', $f->branchId);
        }

        return $this->rows($q->selectRaw("t.name as tenant, COALESCE(s.name, '-') as supplier, COUNT(*) as orders, COALESCE(SUM(pi.q), 0) as qty, {$this->m($f, 'po.items_total')} as items_total, {$this->m($f, 'po.shipping_cost + po.other_cost')} as extra_cost, {$this->m($f, 'po.total_cost')} as total_cost")
            ->groupBy('po.supplier_id', 'po.tenant_id', 's.name', 't.name')->orderByDesc('total_cost'));
    }

    public function sales(ReportFilter $f): array
    {
        return $this->rows($this->orders($f)->selectRaw("DATE(o.created_at) as day, COUNT(*) as orders, {$this->m($f, 'o.subtotal')} as subtotal, {$this->m($f, 'o.total')} as total, {$this->m($f, 'o.cogs')} as cogs, {$this->m($f, 'o.discount')} as discount, {$this->m($f, 'o.shipping_net')} as shipping, {$this->m($f, 'o.payment_fee')} as fees, {$this->m($f, 'o.net_profit')} as net_profit")
            ->groupBy(DB::raw('DATE(o.created_at)'))->orderBy('day'));
    }

    public function discounts(ReportFilter $f): array
    {
        $q = $this->orders($f)->join('discount_code_usages as u', 'u.order_id', '=', 'o.id')->join('discount_codes as d', 'd.id', '=', 'u.discount_code_id');

        return $this->rows($q->selectRaw("t.name as tenant, d.code as code, COUNT(*) as usages, {$this->m($f, 'u.amount')} as discount, {$this->m($f, 'o.total')} as orders_total")
            ->groupBy('d.id', 'd.code', 't.name')->orderByDesc('discount'));
    }

    public function shipping(ReportFilter $f): array
    {
        $q = $this->orders($f)->leftJoin('shipping_companies as sc', 'sc.id', '=', 'o.shipping_company_id');

        return $this->rows($q->selectRaw("t.name as tenant, COALESCE(NULLIF({$this->nm('sc.name')}, ''), '-') as company, COUNT(*) as orders, {$this->m($f, 'o.shipping_net')} as collected, {$this->m($f, 'o.shipping_cost')} as cost, {$this->m($f, 'o.shipping_net - o.shipping_cost')} as margin")
            ->groupBy('o.shipping_company_id', 'o.tenant_id', 't.name', DB::raw($this->nm('sc.name')))->orderByDesc('collected'));
    }

    /** COD carrier settlement: cash collected by each carrier vs owed to the merchant (CALCULATIONS.md §Payment methods). */
    public function codSettlement(ReportFilter $f): array
    {
        $q = $this->orders($f, ['shipped', 'delivered'])->where('o.cod_amount', '>', 0)->leftJoin('shipping_companies as sc', 'sc.id', '=', 'o.shipping_company_id');
        $d = "o.status = 'delivered'";

        return $this->rows($q->selectRaw("t.name as tenant, COALESCE(NULLIF({$this->nm('sc.name')}, ''), '-') as company, COUNT(*) as orders,
            {$this->m($f, "CASE WHEN $d THEN o.cod_amount ELSE 0 END")} as collected, {$this->m($f, "CASE WHEN $d THEN 0 ELSE o.cod_amount END")} as in_transit,
            {$this->m($f, "CASE WHEN $d THEN o.shipping_cost ELSE 0 END")} as carrier_fee, {$this->m($f, "CASE WHEN $d THEN o.cod_amount - o.shipping_cost ELSE 0 END")} as owed")
            ->groupBy('o.shipping_company_id', 'o.tenant_id', 't.name', DB::raw($this->nm('sc.name')))->orderByDesc('owed'));
    }

    public function paymentFees(ReportFilter $f): array
    {
        $q = $this->orders($f)->leftJoin('payment_methods as pm', 'pm.id', '=', 'o.payment_method_id');

        return $this->rows($q->selectRaw("t.name as tenant, COALESCE(NULLIF({$this->nm('pm.name')}, ''), '-') as method, COALESCE(pm.fee_bearer, '-') as bearer, COUNT(*) as orders, {$this->m($f, 'o.total')} as total, {$this->m($f, 'o.payment_fee')} as fee, {$this->m($f, 'o.payment_fee_charged')} as charged, {$this->m($f, 'o.payment_fee - o.payment_fee_charged')} as merchant")
            ->groupBy('o.payment_method_id', 'o.tenant_id', 't.name', 'pm.fee_bearer', DB::raw($this->nm('pm.name')))->orderByDesc('fee'));
    }

    public function netProfit(ReportFilter $f): array
    {
        $o = (array) $this->orders($f)->selectRaw("COUNT(*) as orders, {$this->m($f, 'o.subtotal - o.discount')} as revenue, {$this->m($f, 'o.cogs')} as cogs, {$this->m($f, 'o.shipping_net')} as shipping_net, {$this->m($f, 'o.shipping_cost')} as shipping_cost, {$this->m($f, 'o.payment_fee')} as fee, {$this->m($f, 'o.payment_fee_charged')} as fee_charged, {$this->m($f, 'o.affiliate_commission')} as commission")->first();

        $r = $this->base($f, 'order_returns', 'r')->join('orders as o', 'o.id', '=', 'r.order_id')->whereIn('r.status', ['approved', 'refunded'])->whereBetween('r.created_at', [$f->start(), $f->end()]);
        if ($f->branchId) {
            $r->where('o.branch_id', $f->branchId);
        }
        $r = (array) $r->selectRaw("{$this->m($f, 'r.return_value')} as return_value, {$this->m($f, 'r.returned_cogs')} as returned_cogs, {$this->m($f, 'r.return_shipping_cost')} as return_shipping")->first();

        $e = $this->base($f, 'expenses', 'e')->whereBetween('e.expense_date', [$f->from, $f->to]);
        if ($f->branchId) {
            $e->where('e.branch_id', $f->branchId);
        }
        $e = (array) $e->selectRaw("{$this->m($f, 'e.amount')} as expenses")->first();

        return array_map(fn ($v) => $v ?? 0, $o + $r + $e);
    }

    public function returns(ReportFilter $f): array
    {
        $rate = $this->rate($f);
        $items = DB::table('order_return_items')->selectRaw('order_return_id, SUM(quantity) as q')->groupBy('order_return_id');
        $q = $this->base($f, 'order_returns', 'r')->join('orders as o', 'o.id', '=', 'r.order_id')->leftJoinSub($items, 'ri', 'ri.order_return_id', '=', 'r.id')
            ->whereIn('r.status', ['approved', 'refunded'])->whereBetween('r.created_at', [$f->start(), $f->end()]);
        if ($f->branchId) {
            $q->where('o.branch_id', $f->branchId);
        }

        return $this->rows($q->selectRaw("t.name as tenant, r.number as number, o.number as order_number, DATE(r.created_at) as day, r.status as status, r.restock as restock, COALESCE(ri.q, 0) as qty, ROUND(r.return_value * $rate, 2) as return_value, ROUND(r.returned_cogs * $rate, 2) as returned_cogs, ROUND(r.return_shipping_cost * $rate, 2) as return_shipping")
            ->orderByDesc('r.created_at'));
    }

    public function refunds(ReportFilter $f): array
    {
        $rate = $this->rate($f);
        $a = $this->base($f, 'order_returns', 'r')->join('orders as o', 'o.id', '=', 'r.order_id')->where('r.status', 'refunded')->whereBetween('r.refunded_at', [$f->start(), $f->end()]);
        $b = $this->base($f, 'orders', 'o')->where('o.status', 'cancelled')->where('o.refunded_amount', '>', 0)->whereBetween('o.cancelled_at', [$f->start(), $f->end()]);
        if ($f->branchId) {
            $a->where('o.branch_id', $f->branchId);
            $b->where('o.branch_id', $f->branchId);
        }
        $a = $this->rows($a->selectRaw("t.name as tenant, 'return' as type, r.number as number, o.number as order_number, DATE(r.refunded_at) as day, ROUND(r.refund_amount * $rate, 2) as amount, ROUND(r.return_shipping_cost * $rate, 2) as net_loss"));
        $b = $this->rows($b->selectRaw("t.name as tenant, 'cancellation' as type, o.number as number, o.number as order_number, DATE(o.cancelled_at) as day, ROUND(o.refunded_amount * $rate, 2) as amount, 0 as net_loss"));

        return collect(array_merge($a, $b))->sortByDesc('day')->values()->all();
    }

    public function expenses(ReportFilter $f): array
    {
        $q = $this->base($f, 'expenses', 'e')->join('expense_categories as c', 'c.id', '=', 'e.expense_category_id')->whereBetween('e.expense_date', [$f->from, $f->to]);
        if ($f->branchId) {
            $q->where('e.branch_id', $f->branchId);
        }

        return $this->rows($q->selectRaw("t.name as tenant, {$this->nm('c.name')} as category, COUNT(*) as count, {$this->m($f, 'e.amount')} as amount")
            ->groupBy('e.expense_category_id', 'e.tenant_id', 't.name', DB::raw($this->nm('c.name')))->orderByDesc('amount'));
    }

    public function stock(ReportFilter $f): array
    {
        $q = $this->base($f, 'stock_levels', 'sl')->join('products as p', 'p.id', '=', 'sl.product_id')->leftJoin('product_variants as pv', 'pv.id', '=', 'sl.product_variant_id');
        if ($f->branchId) {
            $q->where('sl.branch_id', $f->branchId);
        }

        return $this->rows($q->selectRaw("sl.tenant_id as tenant_id, t.name as tenant, {$this->nm('p.name')} as product, COALESCE(pv.sku, p.sku, '') as sku, SUM(sl.on_hand) as on_hand, SUM(sl.reserved) as reserved, {$this->m($f, 'sl.on_hand * sl.avg_cost')} as value")
            ->groupBy('sl.tenant_id', 'sl.product_id', 'sl.product_variant_id', 't.name', 'pv.sku', 'p.sku', DB::raw($this->nm('p.name')))->orderBy('product'));
    }

    /** Sold items net of returned qty; revenue is pro-rated for partially returned lines. */
    private function items(ReportFilter $f): Builder
    {
        return $this->orders($f)->join('order_items as oi', 'oi.order_id', '=', 'o.id');
    }

    private function itemAggregates(ReportFilter $f): string
    {
        return "SUM(oi.quantity - oi.returned_qty) as qty, {$this->m($f, '(oi.line_subtotal - oi.line_discount) * (oi.quantity - oi.returned_qty) / oi.quantity')} as revenue, {$this->m($f, 'oi.unit_cost * (oi.quantity - oi.returned_qty)')} as cost, {$this->m($f, '((oi.line_subtotal - oi.line_discount) / oi.quantity - oi.unit_cost) * (oi.quantity - oi.returned_qty)')} as profit";
    }

    public function products(ReportFilter $f): array
    {
        $q = $this->items($f)->join('products as p', 'p.id', '=', 'oi.product_id');

        return $this->rows($q->selectRaw("{$this->nm('p.name')} as product, COUNT(DISTINCT o.id) as orders, {$this->itemAggregates($f)}")->groupBy('oi.product_id', DB::raw($this->nm('p.name')))->orderByDesc('revenue')->limit(100));
    }

    public function categories(ReportFilter $f): array
    {
        $q = $this->items($f)->join('category_product as cp', 'cp.product_id', '=', 'oi.product_id')->join('categories as c', 'c.id', '=', 'cp.category_id');

        return $this->rows($q->selectRaw("{$this->nm('c.name')} as category, COUNT(DISTINCT o.id) as orders, {$this->itemAggregates($f)}")->groupBy('cp.category_id', DB::raw($this->nm('c.name')))->orderByDesc('revenue')->limit(100));
    }

    public function affiliates(ReportFilter $f): array
    {
        $q = $this->base($f, 'affiliate_commissions', 'ac')->join('affiliates as a', 'a.id', '=', 'ac.affiliate_id')->whereBetween('ac.created_at', [$f->start(), $f->end()]);
        $sum = fn (string $st) => $this->m($f, "CASE WHEN ac.status = '$st' THEN ac.amount ELSE 0 END");
        $rows = $this->rows($q->selectRaw("t.name as tenant, a.name as affiliate, COUNT(*) as orders, {$this->m($f, "CASE WHEN ac.status = 'voided' THEN 0 ELSE ac.base END")} as base, {$this->m($f, 'ac.amount')} as commission, {$sum('pending')} as pending, {$sum('approved')} as approved, {$sum('paid')} as paid")
            ->groupBy('ac.affiliate_id', 'ac.tenant_id', 'a.name', 't.name')->orderByDesc('commission'));
        $paid = $this->base($f, 'affiliate_payouts', 'ap')->where('ap.status', 'paid')->whereBetween('ap.paid_at', [$f->start(), $f->end()])->selectRaw("{$this->m($f, 'ap.amount')} as paid")->value('paid');

        return ['rows' => $rows, 'payouts_paid' => $paid ?? 0];
    }

    public function flashSales(ReportFilter $f): array
    {
        $q = $this->orders($f)->join('order_items as oi', 'oi.order_id', '=', 'o.id')->join('flash_sale_items as fi', 'fi.id', '=', 'oi.flash_sale_item_id')->join('flash_sales as fs', 'fs.id', '=', 'fi.flash_sale_id');

        return $this->rows($q->selectRaw("t.name as tenant, {$this->nm('fs.name')} as flash_sale, DATE(fs.starts_at) as starts_at, COUNT(DISTINCT o.id) as orders, SUM(oi.quantity - oi.returned_qty) as units, {$this->m($f, '(oi.line_subtotal - oi.line_discount) * (oi.quantity - oi.returned_qty) / oi.quantity')} as revenue")
            ->groupBy('fi.flash_sale_id', 'fs.starts_at', 'o.tenant_id', 't.name', DB::raw($this->nm('fs.name')))->orderByDesc('revenue'));
    }

    public function newCustomers(ReportFilter $f): int
    {
        return $this->base($f, 'customers', 'c')->whereBetween('c.created_at', [$f->start(), $f->end()])->count();
    }

    public function customers(ReportFilter $f): array
    {
        $st = "'".implode("','", $this->salesStatuses())."'";
        $q = $this->orders($f)->join('customers as c', 'c.id', '=', 'o.customer_id')->whereNotNull('o.customer_id');

        return $this->rows($q->selectRaw("t.name as tenant, c.name as customer, c.email as email, COUNT(*) as orders, {$this->m($f, 'o.total')} as spent, DATE(MIN(o.created_at)) as first_order, DATE(MAX(o.created_at)) as last_order,
            (SELECT COUNT(*) FROM orders p WHERE p.customer_id = c.id AND p.status IN ($st) AND p.created_at < ?) as prior_orders,
            (SELECT ROUND(COALESCE(SUM(p.total), 0) * {$this->rate($f)}, 2) FROM orders p WHERE p.customer_id = c.id AND p.status IN ($st)) as ltv", [$f->start()])
            ->groupBy('c.id', 'c.name', 'c.email', 't.name', 't.currency_rate')->orderByDesc('spent')->limit(200));
    }

    public function branches(ReportFilter $f): array
    {
        $q = $this->orders($f)->leftJoin('branches as b', 'b.id', '=', 'o.branch_id');
        $rows = $this->rows($q->selectRaw("o.branch_id as branch_id, t.name as tenant, COALESCE(NULLIF({$this->nm('b.name')}, ''), '-') as branch, COUNT(*) as orders, {$this->m($f, 'o.total')} as sales, {$this->m($f, "CASE WHEN o.status = 'delivered' THEN o.net_profit ELSE 0 END")} as profit")
            ->groupBy('o.branch_id', 'o.tenant_id', 't.name', DB::raw($this->nm('b.name'))));
        $exp = $this->base($f, 'expenses', 'e')->whereBetween('e.expense_date', [$f->from, $f->to])->selectRaw("e.branch_id as branch_id, {$this->m($f, 'e.amount')} as expenses")->groupBy('e.branch_id')->pluck('expenses', 'branch_id');
        $stock = $this->base($f, 'stock_levels', 'sl')->selectRaw("sl.branch_id as branch_id, {$this->m($f, 'sl.on_hand * sl.avg_cost')} as value")->groupBy('sl.branch_id')->pluck('value', 'branch_id');

        return array_map(fn ($r) => $r + ['expenses' => $exp[$r['branch_id']] ?? 0, 'stock_value' => $stock[$r['branch_id']] ?? 0], $rows);
    }

    public function paymentMethods(ReportFilter $f): array
    {
        $q = $this->orders($f)->leftJoin('payment_methods as pm', 'pm.id', '=', 'o.payment_method_id');

        return $this->rows($q->selectRaw("t.name as tenant, COALESCE(NULLIF({$this->nm('pm.name')}, ''), '-') as method, COUNT(*) as orders, {$this->m($f, 'o.total')} as total, {$this->m($f, 'o.paid_amount')} as paid, {$this->m($f, 'o.payment_fee')} as fee")
            ->groupBy('o.payment_method_id', 'o.tenant_id', 't.name', DB::raw($this->nm('pm.name')))->orderByDesc('total'));
    }

    public function statusCounts(ReportFilter $f): array
    {
        return $this->orders($f, array_map(fn ($s) => $s->value, OrderStatus::cases()))->selectRaw('o.status as status, COUNT(*) as c')->groupBy('o.status')->pluck('c', 'status')->map(fn ($v) => (int) $v)->all();
    }

    public function tenantBreakdown(ReportFilter $f): array
    {
        return $this->rows($this->orders($f)->selectRaw("t.id as tenant_id, t.name as tenant, t.currency_code as currency, COUNT(*) as orders, {$this->m($f, 'o.total')} as sales, {$this->m($f, "CASE WHEN o.status = 'delivered' THEN o.net_profit ELSE 0 END")} as profit")
            ->groupBy('t.id', 't.name', 't.currency_code')->orderByDesc('sales'));
    }
}

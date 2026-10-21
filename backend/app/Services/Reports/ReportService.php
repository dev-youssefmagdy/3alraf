<?php

namespace App\Services\Reports;

use App\DTOs\Reports\ReportFilter;
use App\Enums\FeeBearer;
use App\Enums\OrderStatus;
use App\Models\Tenant;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Builds report payloads {key,title,cards,columns,rows,chart,currency} from repository aggregates, strictly per docs/CALCULATIONS.md §Reports.
 * Column types: text|int|money|pct|date. Chart: {type: line|bar|doughnut, labels, datasets:[{label,data}]}.
 */
class ReportService
{
    public const KEYS = ['sales', 'net-profit', 'purchases', 'discounts', 'shipping', 'cod-settlement', 'payment-fees', 'returns', 'refunds', 'expenses', 'stock', 'products', 'categories', 'affiliates', 'flash-sales', 'customers', 'branches', 'payment-methods'];

    /** Reports that ignore the date range (snapshot of now). */
    public const SNAPSHOT = ['stock'];

    /** Reports where a branch filter is meaningful (tenant context only). */
    public const BRANCHABLE = ['sales', 'net-profit', 'purchases', 'discounts', 'shipping', 'cod-settlement', 'payment-fees', 'returns', 'refunds', 'expenses', 'stock', 'products', 'categories', 'flash-sales', 'customers', 'payment-methods'];

    public function __construct(private ReportRepositoryInterface $repo) {}

    public static function exists(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    public function run(string $key, ReportFilter $f): array
    {
        abort_unless(self::exists($key), 404);
        $r = $this->{'build'.Str::studly($key)}($f);
        $columns = $r['columns'];
        if ($f->central && $f->tenantIds === null && ! empty($r['rows']) && array_key_exists('tenant', $r['rows'][0])) {
            array_unshift($columns, $this->col('tenant', 'text'));
        }

        return [
            'key' => $key, 'title' => __("reports.titles.$key"), 'cards' => $r['cards'], 'columns' => $columns, 'rows' => $r['rows'],
            'chart' => $r['chart'] ?? null, 'currency' => $this->currency($f), 'snapshot' => in_array($key, self::SNAPSHOT, true),
        ];
    }

    public function currency(ReportFilter $f): string
    {
        if ($f->central) {
            return config('tenancy.base_currency');
        }

        return current_tenant()?->currency_symbol ?: (current_tenant()?->currency_code ?? '');
    }

    /** Dashboard payload for the tenant/central home page. */
    public function dashboard(ReportFilter $f): array
    {
        $sales = $this->repo->sales($f);
        $status = $this->repo->statusCounts($f);
        $np = $this->netProfitLines($f);
        $stock = $this->buildStock($f);
        $orders = (int) $this->sum($sales, 'orders');
        $total = $this->sum($sales, 'total');
        $pm = $this->buildPaymentMethods($f);
        $products = $this->buildProducts($f);

        $d = [
            'cards' => [
                $this->card('sales', $this->money($total)),
                $this->card('orders', number_format($orders), 'slate'),
                $this->card('aov', $this->money($orders ? Money::div($total, $orders) : '0'), 'slate'),
                $this->card('net_profit', $this->money($np['net']), 'emerald'),
                $this->card('pending_orders', number_format($status['pending'] ?? 0), 'slate'),
                $this->card('new_customers', number_format($this->repo->newCustomers($f)), 'slate'),
                $this->card('stock_value', $this->money($stock['value'])),
                $this->card('low_stock', number_format($stock['low']), 'slate'),
            ],
            'salesChart' => $this->buildSales($f)['chart'],
            'statusChart' => ['type' => 'doughnut', 'labels' => array_map(fn ($s) => OrderStatus::from($s)->label(), array_keys($status)), 'datasets' => [['label' => __('reports.card.orders'), 'data' => array_values($status)]]],
            'paymentChart' => $pm['chart'],
            'topProducts' => ['columns' => $products['columns'], 'rows' => array_slice($products['rows'], 0, 5)],
            'currency' => $this->currency($f),
        ];
        if ($f->central && $f->tenantIds === null) {
            $tb = $this->repo->tenantBreakdown($f);
            $d['tenants'] = ['columns' => [$this->col('tenant', 'text'), $this->col('currency', 'text'), $this->col('orders', 'int'), $this->col('sales', 'money'), $this->col('profit', 'money')], 'rows' => $tb];
            $d['tenantChart'] = $this->bar($tb, 'tenant', [['sales', 'sales']]);
        }

        return $d;
    }

    public function csv(array $report): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_column($report['columns'], 'title'));
        foreach ($report['rows'] as $row) {
            fputcsv($out, array_map(function ($c) use ($row) {
                $v = $row[$c['data']] ?? '';

                return is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) && $c['type'] === 'text' ? "'".$v : $v;
            }, $report['columns']));
        }
        rewind($out);

        return stream_get_contents($out);
    }

    // ---------- builders ----------

    private function buildSales(ReportFilter $f): array
    {
        $rows = $this->repo->sales($f);
        $orders = (int) $this->sum($rows, 'orders');

        return [
            'cards' => [
                $this->card('orders', number_format($orders), 'slate'), $this->card('sales', $this->money($this->sum($rows, 'total'))), $this->card('costs', $this->money($this->sum($rows, 'cogs')), 'slate'),
                $this->card('discounts', $this->money($this->sum($rows, 'discount')), 'slate'), $this->card('shipping', $this->money($this->sum($rows, 'shipping')), 'slate'),
                $this->card('fees', $this->money($this->sum($rows, 'fees')), 'slate'), $this->card('net_profit', $this->money($this->sum($rows, 'net_profit')), 'emerald'),
            ],
            'columns' => [$this->col('day', 'date'), $this->col('orders', 'int'), $this->col('subtotal', 'money'), $this->col('total', 'money'), $this->col('cogs', 'money'), $this->col('discount', 'money'), $this->col('shipping', 'money'), $this->col('fees', 'money'), $this->col('net_profit', 'money')],
            'rows' => $rows,
            'chart' => ['type' => 'line', 'labels' => array_column($rows, 'day'), 'datasets' => [['label' => __('reports.col.total'), 'data' => $this->nums($rows, 'total')], ['label' => __('reports.col.net_profit'), 'data' => $this->nums($rows, 'net_profit')]]],
        ];
    }

    /** Profit statement lines per CALCULATIONS.md: Σ order_net_profit − return reversal − return shipping − expenses. */
    private function netProfitLines(ReportFilter $f): array
    {
        $d = $this->repo->netProfit($f);
        $gross = Money::sub($d['revenue'], $d['cogs']);
        $fees = Money::sub($d['fee'], $d['fee_charged']);                  // merchant-borne fee cost (customer-borne fees net to 0)
        $order = Money::sub(Money::sub(Money::add($gross, $d['shipping_net']), Money::add($d['shipping_cost'], $fees)), $d['commission']);
        $reversal = Money::sub($d['return_value'], $d['returned_cogs']);
        $net = Money::sub(Money::sub(Money::sub($order, $reversal), $d['return_shipping']), $d['expenses']);

        return $d + compact('gross', 'fees', 'order', 'reversal', 'net');
    }

    private function buildNetProfit(ReportFilter $f): array
    {
        $l = $this->netProfitLines($f);
        $rows = collect([
            ['revenue', $l['revenue'], 'income'], ['cogs', $l['cogs'], 'cost'], ['gross_profit', $l['gross'], 'total'],
            ['shipping_collected', $l['shipping_net'], 'income'], ['shipping_paid', $l['shipping_cost'], 'cost'], ['payment_fees', $l['fees'], 'cost'], ['affiliate_commission', $l['commission'], 'cost'],
            ['order_profit', $l['order'], 'total'], ['returns_reversal', $l['reversal'], 'cost'], ['return_shipping', $l['return_shipping'], 'cost'], ['expenses', $l['expenses'], 'cost'], ['net_profit', $l['net'], 'total'],
        ])->map(fn ($r) => ['line' => __('reports.line.'.$r[0]), 'amount' => $r[1], 'kind' => $r[2]])->all();

        return [
            'cards' => [
                $this->card('net_profit', $this->money($l['net']), 'emerald'), $this->card('order_profit', $this->money($l['order']), 'slate'), $this->card('expenses', $this->money($l['expenses']), 'slate'),
                $this->card('margin', $this->pct($l['revenue'] > 0 ? Money::div(Money::mul($l['net'], 100), $l['revenue']) : '0'), 'slate'), $this->card('delivered_orders', number_format((int) $l['orders']), 'slate'),
            ],
            'columns' => [$this->col('line', 'text'), $this->col('amount', 'money')],
            'rows' => $rows,
            'chart' => ['type' => 'bar', 'labels' => [__('reports.line.revenue'), __('reports.line.cogs'), __('reports.line.expenses'), __('reports.line.net_profit')], 'datasets' => [['label' => $this->currency($f), 'data' => [(float) $l['revenue'], (float) $l['cogs'], (float) $l['expenses'], (float) $l['net']]]]],
        ];
    }

    private function buildPurchases(ReportFilter $f): array
    {
        $rows = $this->repo->purchases($f);
        $qty = $this->sum($rows, 'qty');
        $total = $this->sum($rows, 'total_cost');

        return [
            'cards' => [$this->card('purchase_orders', number_format((int) $this->sum($rows, 'orders')), 'slate'), $this->card('units', number_format((int) $qty), 'slate'), $this->card('total_cost', $this->money($total)), $this->card('avg_unit_cost', $this->money((float) $qty > 0 ? Money::div($total, $qty) : '0'), 'slate'), $this->card('top_supplier', $rows[0]['supplier'] ?? '-', 'slate')],
            'columns' => [$this->col('supplier', 'text'), $this->col('orders', 'int'), $this->col('qty', 'int'), $this->col('items_total', 'money'), $this->col('extra_cost', 'money'), $this->col('total_cost', 'money')],
            'rows' => $rows, 'chart' => $this->bar($rows, 'supplier', [['total_cost', 'total_cost']]),
        ];
    }

    private function buildDiscounts(ReportFilter $f): array
    {
        $rows = $this->repo->discounts($f);

        return [
            'cards' => [$this->card('discounts', $this->money($this->sum($rows, 'discount'))), $this->card('usages', number_format((int) $this->sum($rows, 'usages')), 'slate'), $this->card('codes_used', number_format(count($rows)), 'slate')],
            'columns' => [$this->col('code', 'text'), $this->col('usages', 'int'), $this->col('discount', 'money'), $this->col('orders_total', 'money')],
            'rows' => $rows, 'chart' => $this->bar($rows, 'code', [['discount', 'discount']]),
        ];
    }

    private function buildShipping(ReportFilter $f): array
    {
        $rows = $this->repo->shipping($f);

        return [
            'cards' => [$this->card('shipping_collected', $this->money($this->sum($rows, 'collected'))), $this->card('shipping_paid', $this->money($this->sum($rows, 'cost')), 'slate'), $this->card('shipping_margin', $this->money($this->sum($rows, 'margin')), 'emerald'), $this->card('orders', number_format((int) $this->sum($rows, 'orders')), 'slate')],
            'columns' => [$this->col('company', 'text'), $this->col('orders', 'int'), $this->col('collected', 'money'), $this->col('cost', 'money'), $this->col('margin', 'money')],
            'rows' => $rows, 'chart' => $this->bar($rows, 'company', [['collected', 'collected'], ['cost', 'cost']]),
        ];
    }

    private function buildCodSettlement(ReportFilter $f): array
    {
        $rows = $this->repo->codSettlement($f);

        return [
            'cards' => [$this->card('cod_collected', $this->money($this->sum($rows, 'collected'))), $this->card('cod_in_transit', $this->money($this->sum($rows, 'in_transit')), 'slate'),
                $this->card('carrier_fee', $this->money($this->sum($rows, 'carrier_fee')), 'slate'), $this->card('cod_owed', $this->money($this->sum($rows, 'owed')), 'emerald')],
            'columns' => [$this->col('company', 'text'), $this->col('orders', 'int'), $this->col('collected', 'money'), $this->col('in_transit', 'money'), $this->col('carrier_fee', 'money'), $this->col('owed', 'money'), $this->col('settled', 'money')],
            'rows' => $rows, 'chart' => $this->bar($rows, 'company', [['collected', 'collected'], ['owed', 'owed']]),
        ];
    }

    private function buildPaymentFees(ReportFilter $f): array
    {
        $rows = array_map(fn ($r) => ['bearer' => FeeBearer::tryFrom((string) $r['bearer'])?->label() ?? '-'] + $r, $this->repo->paymentFees($f));

        return [
            'cards' => [$this->card('fees', $this->money($this->sum($rows, 'fee'))), $this->card('fee_charged', $this->money($this->sum($rows, 'charged')), 'slate'), $this->card('fee_merchant', $this->money($this->sum($rows, 'merchant')), 'slate')],
            'columns' => [$this->col('method', 'text'), $this->col('bearer', 'text'), $this->col('orders', 'int'), $this->col('total', 'money'), $this->col('fee', 'money'), $this->col('charged', 'money'), $this->col('merchant', 'money')],
            'rows' => $rows, 'chart' => $this->pie($rows, 'method', 'fee'),
        ];
    }

    private function buildReturns(ReportFilter $f): array
    {
        $rows = array_map(fn ($r) => ['status' => \App\Enums\ReturnStatus::tryFrom($r['status'])?->label() ?? $r['status']] + $r, $this->repo->returns($f));
        $daily = [];
        foreach ($rows as $r) {
            $daily[$r['day']] = Money::add($daily[$r['day']] ?? 0, $r['return_value']);
        }
        ksort($daily);

        return [
            'cards' => [$this->card('returns', number_format(count($rows)), 'slate'), $this->card('units', number_format((int) $this->sum($rows, 'qty')), 'slate'), $this->card('return_value', $this->money($this->sum($rows, 'return_value'))), $this->card('returned_cogs', $this->money($this->sum($rows, 'returned_cogs')), 'slate')],
            'columns' => [$this->col('number', 'text'), $this->col('order_number', 'text'), $this->col('day', 'date'), $this->col('status', 'text'), $this->col('qty', 'int'), $this->col('return_value', 'money'), $this->col('returned_cogs', 'money'), $this->col('return_shipping', 'money')],
            'rows' => $rows, 'chart' => ['type' => 'bar', 'labels' => array_keys($daily), 'datasets' => [['label' => __('reports.col.return_value'), 'data' => array_map('floatval', array_values($daily))]]],
        ];
    }

    private function buildRefunds(ReportFilter $f): array
    {
        $raw = $this->repo->refunds($f);
        $byType = fn (string $t) => $this->sum(array_filter($raw, fn ($r) => $r['type'] === $t), 'amount');
        $rows = array_map(fn ($r) => ['type' => __('reports.type.'.$r['type'])] + $r, $raw);

        return [
            'cards' => [$this->card('refunded', $this->money($this->sum($raw, 'amount'))), $this->card('refund_returns', $this->money($byType('return')), 'slate'), $this->card('refund_cancellations', $this->money($byType('cancellation')), 'slate'), $this->card('refund_net_loss', $this->money($this->sum($raw, 'net_loss')), 'slate')],
            'columns' => [$this->col('type', 'text'), $this->col('number', 'text'), $this->col('order_number', 'text'), $this->col('day', 'date'), $this->col('amount', 'money'), $this->col('net_loss', 'money')],
            'rows' => $rows, 'chart' => ['type' => 'doughnut', 'labels' => [__('reports.type.return'), __('reports.type.cancellation')], 'datasets' => [['label' => $this->currency($f), 'data' => [(float) $byType('return'), (float) $byType('cancellation')]]]],
        ];
    }

    private function buildExpenses(ReportFilter $f): array
    {
        $rows = $this->repo->expenses($f);

        return [
            'cards' => [$this->card('expenses', $this->money($this->sum($rows, 'amount'))), $this->card('entries', number_format((int) $this->sum($rows, 'count')), 'slate'), $this->card('top_category', $rows[0]['category'] ?? '-', 'slate')],
            'columns' => [$this->col('category', 'text'), $this->col('count', 'int'), $this->col('amount', 'money')],
            'rows' => $rows, 'chart' => $this->pie($rows, 'category', 'amount'),
        ];
    }

    private function buildStock(ReportFilter $f): array
    {
        $thr = Tenant::withTrashed()->get()->mapWithKeys(fn ($t) => [$t->id => (int) ($t->settings['low_stock_threshold'] ?? 5)])->all();
        $rows = array_map(function ($r) use ($thr) {
            $avail = $r['on_hand'] - $r['reserved'];

            return $r + ['available' => $avail, 'avg_cost' => (float) $r['on_hand'] > 0 ? Money::div($r['value'], $r['on_hand']) : '0.00', 'status' => $avail <= ($thr[$r['tenant_id']] ?? 5) ? 'low' : 'ok'];
        }, $this->repo->stock($f));
        $low = array_values(array_filter($rows, fn ($r) => $r['status'] === 'low'));
        $view = array_map(fn ($r) => ['status' => __('reports.status.'.$r['status'])] + $r, $rows);
        usort($view, fn ($a, $b) => (float) $b['value'] <=> (float) $a['value']);

        return [
            'cards' => [$this->card('stock_value', $this->money($this->sum($rows, 'value'))), $this->card('units_on_hand', number_format((int) $this->sum($rows, 'on_hand')), 'slate'), $this->card('items', number_format(count($rows)), 'slate'), $this->card('low_stock', number_format(count($low)), 'slate')],
            'columns' => [$this->col('product', 'text'), $this->col('sku', 'text'), $this->col('on_hand', 'int'), $this->col('reserved', 'int'), $this->col('available', 'int'), $this->col('avg_cost', 'money'), $this->col('value', 'money'), $this->col('status', 'text')],
            'rows' => $view, 'chart' => $this->bar(array_slice($view, 0, 10), 'product', [['value', 'value']]),
            'low' => count($low), 'value' => $this->sum($rows, 'value'),
        ];
    }

    private function buildProducts(ReportFilter $f): array
    {
        $rows = $this->repo->products($f);

        return $this->itemReport($rows, 'product');
    }

    private function buildCategories(ReportFilter $f): array
    {
        return $this->itemReport($this->repo->categories($f), 'category');
    }

    private function itemReport(array $rows, string $dim): array
    {
        return [
            'cards' => [$this->card('units', number_format((int) $this->sum($rows, 'qty')), 'slate'), $this->card('revenue', $this->money($this->sum($rows, 'revenue'))), $this->card('profit', $this->money($this->sum($rows, 'profit')), 'emerald'), $this->card($dim === 'product' ? 'products' : 'categories', number_format(count($rows)), 'slate')],
            'columns' => [$this->col($dim, 'text'), $this->col('orders', 'int'), $this->col('qty', 'int'), $this->col('revenue', 'money'), $this->col('cost', 'money'), $this->col('profit', 'money')],
            'rows' => $rows, 'chart' => $this->bar(array_slice($rows, 0, 10), $dim, [['revenue', 'revenue'], ['profit', 'profit']]),
        ];
    }

    private function buildAffiliates(ReportFilter $f): array
    {
        $d = $this->repo->affiliates($f);
        $rows = $d['rows'];

        return [
            'cards' => [$this->card('commission', $this->money($this->sum($rows, 'commission'))), $this->card('pending', $this->money($this->sum($rows, 'pending')), 'slate'), $this->card('approved', $this->money($this->sum($rows, 'approved')), 'slate'), $this->card('paid', $this->money($this->sum($rows, 'paid')), 'emerald'), $this->card('payouts_paid', $this->money($d['payouts_paid']), 'slate')],
            'columns' => [$this->col('affiliate', 'text'), $this->col('orders', 'int'), $this->col('base', 'money'), $this->col('commission', 'money'), $this->col('pending', 'money'), $this->col('approved', 'money'), $this->col('paid', 'money')],
            'rows' => $rows, 'chart' => $this->bar(array_slice($rows, 0, 10), 'affiliate', [['commission', 'commission']]),
        ];
    }

    private function buildFlashSales(ReportFilter $f): array
    {
        $rows = $this->repo->flashSales($f);

        return [
            'cards' => [$this->card('revenue', $this->money($this->sum($rows, 'revenue'))), $this->card('units', number_format((int) $this->sum($rows, 'units')), 'slate'), $this->card('orders', number_format((int) $this->sum($rows, 'orders')), 'slate'), $this->card('flash_sales', number_format(count($rows)), 'slate')],
            'columns' => [$this->col('flash_sale', 'text'), $this->col('starts_at', 'date'), $this->col('orders', 'int'), $this->col('units', 'int'), $this->col('revenue', 'money')],
            'rows' => $rows, 'chart' => $this->bar($rows, 'flash_sale', [['revenue', 'revenue']]),
        ];
    }

    private function buildCustomers(ReportFilter $f): array
    {
        $rows = array_map(fn ($r) => $r + ['segment' => __('reports.segment.'.(($r['prior_orders'] > 0 || $r['orders'] >= 2) ? 'repeat' : 'new'))], $this->repo->customers($f));
        $repeat = count(array_filter($rows, fn ($r) => $r['prior_orders'] > 0 || $r['orders'] >= 2));
        $n = count($rows);

        return [
            'cards' => [$this->card('new_customers', number_format($this->repo->newCustomers($f))), $this->card('active_customers', number_format($n), 'slate'), $this->card('repeat_customers', number_format($repeat), 'slate'),
                $this->card('repeat_rate', $this->pct($n ? Money::div(Money::mul($repeat, 100), $n) : '0'), 'slate'), $this->card('avg_ltv', $this->money($n ? Money::div($this->sum($rows, 'ltv'), $n) : '0'), 'emerald')],
            'columns' => [$this->col('customer', 'text'), $this->col('email', 'text'), $this->col('orders', 'int'), $this->col('spent', 'money'), $this->col('ltv', 'money'), $this->col('first_order', 'date'), $this->col('last_order', 'date'), $this->col('segment', 'text')],
            'rows' => $rows, 'chart' => ['type' => 'doughnut', 'labels' => [__('reports.segment.new'), __('reports.segment.repeat')], 'datasets' => [['label' => __('reports.card.active_customers'), 'data' => [$n - $repeat, $repeat]]]],
        ];
    }

    private function buildBranches(ReportFilter $f): array
    {
        $rows = array_map(fn ($r) => $r + ['net' => Money::sub($r['profit'], $r['expenses'])], $this->repo->branches($f));

        return [
            'cards' => [$this->card('branches', number_format(count($rows)), 'slate'), $this->card('sales', $this->money($this->sum($rows, 'sales'))), $this->card('profit', $this->money($this->sum($rows, 'profit')), 'emerald'), $this->card('expenses', $this->money($this->sum($rows, 'expenses')), 'slate')],
            'columns' => [$this->col('branch', 'text'), $this->col('orders', 'int'), $this->col('sales', 'money'), $this->col('profit', 'money'), $this->col('expenses', 'money'), $this->col('net', 'money'), $this->col('stock_value', 'money')],
            'rows' => $rows, 'chart' => $this->bar($rows, 'branch', [['sales', 'sales'], ['profit', 'profit']]),
        ];
    }

    private function buildPaymentMethods(ReportFilter $f): array
    {
        $rows = $this->repo->paymentMethods($f);
        $total = $this->sum($rows, 'total');
        $rows = array_map(fn ($r) => $r + ['share' => (float) $total > 0 ? Money::div(Money::mul($r['total'], 100), $total) : '0.00'], $rows);

        return [
            'cards' => [$this->card('sales', $this->money($total)), $this->card('paid', $this->money($this->sum($rows, 'paid')), 'slate'), $this->card('fees', $this->money($this->sum($rows, 'fee')), 'slate'), $this->card('methods', number_format(count($rows)), 'slate')],
            'columns' => [$this->col('method', 'text'), $this->col('orders', 'int'), $this->col('total', 'money'), $this->col('paid', 'money'), $this->col('fee', 'money'), $this->col('share', 'pct')],
            'rows' => $rows, 'chart' => $this->pie($rows, 'method', 'total'),
        ];
    }

    // ---------- helpers ----------

    private function col(string $field, string $type): array
    {
        return ['data' => $field, 'title' => __("reports.col.$field"), 'type' => $type];
    }

    private function card(string $name, string $value, string $tone = 'brand'): array
    {
        return ['label' => __("reports.card.$name"), 'value' => $value, 'tone' => $tone];
    }

    private function money(string|int|float|null $v): string
    {
        return number_format((float) ($v ?? 0), 2);
    }

    private function pct(string|int|float $v): string
    {
        return number_format((float) $v, 1).'%';
    }

    private function sum(array $rows, string $key): string
    {
        return array_reduce($rows, fn ($c, $r) => Money::add($c, (string) ($r[$key] ?? 0)), '0');
    }

    private function nums(array $rows, string $key): array
    {
        return array_map(fn ($r) => (float) $r[$key], $rows);
    }

    private function bar(array $rows, string $labelKey, array $series): array
    {
        $rows = array_slice($rows, 0, 10);

        return ['type' => 'bar', 'labels' => array_column($rows, $labelKey), 'datasets' => array_map(fn ($s) => ['label' => __('reports.col.'.$s[1]), 'data' => $this->nums($rows, $s[0])], $series)];
    }

    private function pie(array $rows, string $labelKey, string $valueKey): array
    {
        return ['type' => 'doughnut', 'labels' => array_column($rows, $labelKey), 'datasets' => [['label' => __('reports.col.'.$valueKey), 'data' => $this->nums($rows, $valueKey)]]];
    }
}

<?php

namespace App\Services\Marketing;

use App\DTOs\Marketing\MarketingQuery;
use App\Enums\OrderStatus;
use App\Models\Banner;
use App\Models\Bundle;
use App\Models\DiscountCode;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Contracts\MarketingRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Admin\LowStockService;
use App\Services\GiftFinder\GiftFinderService;
use App\Services\GiftFinder\GiftFinderSettings;
use App\Services\Reports\ReportService;
use App\Support\Marketing\MarketingResponse as R;
use App\Support\Marketing\StoreText;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Sales / profit analytics for the marketer dashboard and the Marketing internal API (P4). Never re-implements order money math:
 * store totals come from the Reports module (ReportRepository: sales rows, Net profit figures, status counts, discounts, flash sales —
 * docs/CALCULATIONS.md §Reports), item figures use the Top products report definition (MarketingRepository::itemSales).
 * Cost is never invented: a line whose unit_cost snapshot is 0/null is "uncosted" — gross profit is computed over costed lines only and
 * reported with its coverage %, plus a `missing_data` warning. Advertising spend / ROAS / CAC are null (not merged with Meta insights).
 * Every method returns ['data' => ..., 'warnings' => [...]].
 */
class MarketingAnalyticsService
{
    public function __construct(
        private ReportRepositoryInterface $reports,
        private MarketingRepositoryInterface $repo,
        private CampaignScorer $scorer,
        private LowStockService $lowStock,
        private GiftFinderService $giftFinder,
        private GiftFinderSettings $giftSettings,
        private ReportService $reportService,
    ) {}

    // ---------------------------------------------------------------- shared bits

    /** Revenue-weighted share (0..100) of item revenue that carries a cost snapshot; null when there is no revenue. */
    public static function coverage(string $revenue, string $revenueCosted): ?float
    {
        return Money::cmp($revenue, '0') > 0 ? round((float) $revenueCosted / (float) $revenue * 100, 2) : null;
    }

    private function costWarning(?float $coverage, int $uncostedUnits, string $scope = 'store'): array
    {
        if ($coverage === null || $coverage >= 100) {
            return [];
        }

        return [R::warning('missing_data', "Purchase cost is missing for {$uncostedUnits} sold unit(s) ({$scope}): gross profit covers only {$coverage}% of item revenue and is not extrapolated.",
            ['field' => 'cost', 'coverage_percent' => $coverage])];
    }

    private function vatWarning(): array
    {
        return (bool) setting('prices_include_tax', false) && (float) setting('tax_rate', 0) > 0
            ? [R::warning('vat_in_item_revenue', 'Shop prices include VAT: product-level revenue (Top products definition) includes VAT; store-level revenue_goods excludes it.')]
            : [];
    }

    private function adWarning(): array
    {
        return [R::warning('no_ad_data', 'No ad data connected: advertising spend, ROAS, CAC and contribution profit after ads are null here (Meta spend is available separately from GET meta/insights, scope meta:read).')];
    }

    /** Item totals over all products for the range. */
    private function itemTotals(MarketingQuery $q): array
    {
        $rows = $this->repo->itemSales($q->report());
        $t = ['units' => 0, 'revenue' => '0.00', 'revenue_costed' => '0.00', 'cost' => '0.00', 'uncosted_units' => 0];
        foreach ($rows as $r) {
            $t['units'] += $r['units'];
            $t['uncosted_units'] += $r['uncosted_units'];
            foreach (['revenue', 'revenue_costed', 'cost'] as $k) {
                $t[$k] = Money::add($t[$k], $r[$k]);
            }
        }

        return $t;
    }

    private function sumRows(array $rows, string $k): string
    {
        return array_reduce($rows, fn ($c, $r) => Money::add($c, (string) ($r[$k] ?? 0)), '0.00');
    }

    // ---------------------------------------------------------------- overview / dashboard

    public function overview(MarketingQuery $q): array
    {
        $f = $q->report();
        $sales = $this->reports->sales($f);
        $np = $this->reportService->netProfitFigures($f);   // Net profit report lines (CALCULATIONS.md §Profit), never recomputed here
        $orders = array_sum(array_column($sales, 'orders'));
        $total = $this->sumRows($sales, 'total');
        $items = $this->itemTotals($q);
        $coverage = self::coverage($items['revenue'], $items['revenue_costed']);
        $gross = Money::sub($items['revenue_costed'], $items['cost']);
        $warnings = array_merge($this->costWarning($coverage, $items['uncosted_units']), $this->vatWarning(), $this->adWarning());

        // net profit (Net profit report) only when every sold line carries a cost
        $net = null;
        if ($coverage !== null && $coverage >= 100) {
            $net = Money::round((string) $np['net']);
        } elseif ($coverage !== null) {
            $warnings[] = R::warning('missing_data', 'Net profit is not reported while some sold items have no cost.', ['field' => 'net_profit']);
        }

        $status = $this->reports->statusCounts($f);
        $returns = $this->reports->returns($f);
        $data = [
            'range' => $q->range(),
            'sales' => [
                'orders' => $orders, 'gross_sales' => $total, 'aov' => $orders ? Money::div($total, (string) $orders) : null,
                'revenue_goods' => Money::round((string) $np['revenue']), 'discounts' => $this->sumRows($sales, 'discount'), 'shipping_collected' => $this->sumRows($sales, 'shipping'),
                'cogs_recorded' => Money::round((string) $np['cogs']), 'units_sold' => $items['units'],
            ],
            'profit' => [
                'gross_profit' => ($coverage ?? 0) > 0 ? $gross : null, 'gross_profit_basis' => 'costed_items_only', 'item_revenue' => $items['revenue'],
                'item_revenue_costed' => $items['revenue_costed'], 'item_cost' => $items['cost'], 'cost_coverage_percent' => $coverage,
                'gross_margin_percent' => Money::cmp($items['revenue_costed'], '0') > 0 ? round((float) $gross / (float) $items['revenue_costed'] * 100, 2) : null,
                'net_profit' => $net, 'contribution_profit' => null, 'advertising_spend' => null,
            ],
            'orders' => ['by_status' => $status, 'cancellations' => $this->repo->cancellations($f),
                'returns' => ['count' => count($returns), 'value' => $this->sumRows($returns, 'return_value'), 'units' => array_sum(array_column($returns, 'qty'))]],
            'merchandising' => $this->merchandisingCounts(),
            'gift_finder' => $this->giftFinderStats($q->days()),
        ];

        return ['data' => $data, 'warnings' => $warnings];
    }

    /** Live counts: catalog, stock, coupons, offers, banners, bundles. */
    public function merchandisingCounts(): array
    {
        $now = now();
        $stock = $this->repo->stockByProduct();
        $activeIds = Product::where('is_active', true)->whereNotNull('price')->pluck('id');

        return [
            'active_products' => $activeIds->count(),
            'in_stock_products' => $activeIds->filter(fn ($id) => ($stock[$id]['available'] ?? 0) > 0)->count(),
            'low_stock_items' => $this->lowStock->rows()->count(),
            'active_coupons' => DiscountCode::where('is_active', true)->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now))->where(fn ($w) => $w->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))->count(),
            'running_flash_sales' => FlashSale::where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now)->count(),
            'upcoming_flash_sales' => FlashSale::where('is_active', true)->where('starts_at', '>', $now)->count(),
            'live_banners' => Banner::live()->count(),
            'live_home_sliders' => Banner::live()->where('placement', 'home_hero')->count(),
            'live_bundles' => Bundle::live()->count(),
        ];
    }

    public function giftFinderStats(int $days): array
    {
        $s = $this->giftFinder->stats(max(1, min(365, $days)));
        $s['by_occasion'] = (object) $s['by_occasion'];

        return ['enabled' => $this->giftSettings->enabled(), 'window' => 'last_'.$s['days'].'_days'] + $s;
    }

    // ---------------------------------------------------------------- sales / orders

    /** Bucket key of a day in a sales series: day, the week's first day, or Y-m. */
    public static function periodKey(CarbonImmutable $d, string $by): string
    {
        return match ($by) {
            'week' => $d->startOfWeek()->toDateString(), 'month' => $d->format('Y-m'), default => $d->toDateString()
        };
    }

    public function salesSummary(MarketingQuery $q): array
    {
        $by = (string) $q->filter('group_by', 'day');
        $buckets = [];
        foreach ($this->reports->sales($q->report()) as $r) {
            $d = CarbonImmutable::parse($r['day']);
            $key = self::periodKey($d, $by);
            $b = $buckets[$key] ?? ['period' => $key, 'orders' => 0, 'gross_sales' => '0.00', 'subtotal' => '0.00', 'discounts' => '0.00', 'shipping' => '0.00', 'cogs_recorded' => '0.00', 'net_profit_recorded' => '0.00'];
            $b['orders'] += (int) $r['orders'];
            foreach (['gross_sales' => 'total', 'subtotal' => 'subtotal', 'discounts' => 'discount', 'shipping' => 'shipping', 'cogs_recorded' => 'cogs', 'net_profit_recorded' => 'net_profit'] as $to => $from) {
                $b[$to] = Money::add($b[$to], (string) ($r[$from] ?? 0));
            }
            $buckets[$key] = $b;
        }
        $rows = array_values(array_map(fn ($b) => $b + ['aov' => $b['orders'] ? Money::div($b['gross_sales'], (string) $b['orders']) : null], $buckets));
        $items = $this->itemTotals($q);
        $coverage = self::coverage($items['revenue'], $items['revenue_costed']);
        $warnings = $this->costWarning($coverage, $items['uncosted_units']);
        if ($warnings) {
            $warnings[] = R::warning('missing_data', 'cogs_recorded / net_profit_recorded include uncosted lines at cost 0 (as stored on the orders): treat them as upper bounds of profit.', ['field' => 'net_profit_recorded']);
        }

        return ['data' => ['range' => $q->range(), 'group_by' => $by, 'cost_coverage_percent' => $coverage, 'periods' => $rows], 'warnings' => $warnings];
    }

    public function ordersSummary(MarketingQuery $q): array
    {
        $f = $q->report();
        $status = $this->reports->statusCounts($f);
        $sales = array_map(fn ($s) => $s->value, OrderStatus::salesStatuses());
        $returns = $this->reports->returns($f);
        $total = array_sum($status);

        return ['data' => [
            'range' => $q->range(), 'orders_total' => $total, 'by_status' => $status,
            'sales_orders' => array_sum(array_intersect_key($status, array_flip($sales))), 'pending_orders' => (int) ($status['pending'] ?? 0),
            'cancellations' => $this->repo->cancellations($f) + ['rate_percent' => $total ? round(($status['cancelled'] ?? 0) / $total * 100, 2) : null],
            'returns' => ['documents' => count($returns), 'units' => array_sum(array_column($returns, 'qty')), 'value' => $this->sumRows($returns, 'return_value'),
                'returned_cogs' => $this->sumRows($returns, 'returned_cogs'), 'return_shipping_cost' => $this->sumRows($returns, 'return_shipping')],
            'by_channel' => (object) $this->repo->ordersByChannel($f),
        ], 'warnings' => []];
    }

    // ---------------------------------------------------------------- products

    /** Rows for best sellers / slow movers / candidates: product + item sales + stock. */
    private function productRows(MarketingQuery $q, ?array $ids = null): array
    {
        $sales = $this->repo->itemSales($q->report(), $ids);
        $products = Product::with('brand:id,name')->when($ids !== null, fn ($w) => $w->whereIn('id', $ids))->get()->keyBy('id');
        $stock = $this->repo->stockByProduct($ids);

        return [$sales, $products, $stock];
    }

    private function productCard(Product $p, array $s, array $stock): array
    {
        $coverage = self::coverage($s['revenue'] ?? '0', $s['revenue_costed'] ?? '0');
        $gross = Money::sub($s['revenue_costed'] ?? '0', $s['cost'] ?? '0');

        return [
            'product_id' => $p->id, 'sku' => $p->sku, 'slug' => $p->slug, 'name' => StoreText::names($p), 'active' => (bool) $p->is_active,
            'brand' => $p->brand ? ['id' => $p->brand->id, 'name' => StoreText::names($p->brand)] : null,
            'price' => $p->price !== null ? (string) $p->price : null, 'effective_price' => $p->effectivePrice(),
            'units_sold' => (int) ($s['units'] ?? 0), 'orders' => (int) ($s['orders'] ?? 0), 'revenue' => (string) ($s['revenue'] ?? '0.00'),
            'gross_profit' => ($coverage ?? 0) > 0 ? $gross : null, 'cost_coverage_percent' => $coverage,
            'stock' => ['available' => $stock['available'] ?? 0, 'on_hand' => $stock['on_hand'] ?? 0, 'reserved' => $stock['reserved'] ?? 0],
            'last_sold_at' => $s['last_sold_at'] ?? null,
        ];
    }

    public function bestSellers(MarketingQuery $q): array
    {
        [$sales, $products, $stock] = $this->productRows($q);
        $rows = [];
        $uncosted = 0;
        foreach ($sales as $s) {
            if ($s['units'] <= 0 || ! ($p = $products->get($s['product_id']))) {
                continue;
            }
            $uncosted += $s['uncosted_units'];
            $rows[] = $this->productCard($p, $s, $stock[$p->id] ?? []);
        }
        usort($rows, fn ($a, $b) => [$b['units_sold'], (float) $b['revenue'], $a['product_id']] <=> [$a['units_sold'], (float) $a['revenue'], $b['product_id']]);
        $warnings = array_merge($uncosted ? [R::warning('missing_data', "{$uncosted} sold unit(s) have no cost: their gross profit is not computed.", ['field' => 'cost'])] : [], $this->vatWarning());

        return ['data' => $rows, 'warnings' => $warnings];
    }

    public function slowMoving(MarketingQuery $q): array
    {
        $max = (int) config('marketing.slow_moving_max_units', 2);
        $stock = $this->repo->stockByProduct();
        $candidates = Product::with('brand:id,name')->where('is_active', true)->whereNotNull('price')->get()
            ->filter(fn ($p) => ($stock[$p->id]['available'] ?? 0) > 0);
        $sales = $this->repo->itemSales($q->report(), $candidates->pluck('id')->all());
        $last = $this->repo->lastSoldAt($candidates->pluck('id')->all());
        $rows = [];
        foreach ($candidates as $p) {
            $s = $sales[(string) $p->id] ?? [];
            if ((int) ($s['units'] ?? 0) > $max) {
                continue;
            }
            $card = $this->productCard($p, $s, $stock[$p->id]);
            $card['last_sold_at'] = $last[$p->id] ?? null;
            $card['days_since_last_sale'] = isset($last[$p->id]) ? (int) CarbonImmutable::parse($last[$p->id])->startOfDay()->diffInDays(now()->startOfDay()) : null;
            $card['stock_value_at_cost'] = $p->cost !== null && (float) $p->cost > 0 ? Money::mul((string) $p->cost, (string) $stock[$p->id]['on_hand']) : null;
            $rows[] = $card;
        }
        usort($rows, fn ($a, $b) => [$a['units_sold'], $b['stock']['available'], $a['product_id']] <=> [$b['units_sold'], $a['stock']['available'], $b['product_id']]);
        $noCost = count(array_filter($rows, fn ($r) => $r['stock_value_at_cost'] === null));

        return ['data' => $rows, 'warnings' => $noCost ? [R::warning('missing_data', "{$noCost} slow mover(s) have no listing cost: stock_value_at_cost is null.", ['field' => 'cost'])] : [],
            'meta' => ['max_units' => $max]];
    }

    public function lowStock(): array
    {
        $rows = $this->lowStock->rows()->map(fn ($r) => [
            'product_id' => $r['product']->id, 'variant_id' => $r['variant']?->id, 'sku' => $r['variant']?->sku ?? $r['product']->sku,
            'name' => StoreText::names($r['product']), 'variant' => $r['variant']?->label(), 'available' => $r['available'], 'alert_qty' => $r['alert'],
        ])->values()->all();

        return ['data' => $rows, 'warnings' => []];
    }

    public function profitability(Product $p, MarketingQuery $q): array
    {
        $f = $q->report();
        $total = $this->repo->itemSales($f, [$p->id])[(string) $p->id] ?? null;
        $byVariant = $this->repo->itemSales($f, [$p->id], true);
        $variants = $p->variants()->withTrashed()->get()->keyBy('id');
        $warnings = $this->vatWarning();

        $line = function (?array $s) {
            $s ??= ['units' => 0, 'returned_units' => 0, 'orders' => 0, 'revenue' => '0.00', 'revenue_costed' => '0.00', 'cost' => '0.00', 'uncosted_units' => 0];
            $cov = self::coverage($s['revenue'], $s['revenue_costed']);
            $gross = Money::sub($s['revenue_costed'], $s['cost']);

            return ['units_sold' => $s['units'], 'units_returned' => $s['returned_units'], 'orders' => $s['orders'], 'revenue' => $s['revenue'],
                'revenue_costed' => $s['revenue_costed'], 'cost' => $s['cost'], 'uncosted_units' => $s['uncosted_units'],
                'gross_profit' => ($cov ?? 0) > 0 ? $gross : null, 'cost_coverage_percent' => $cov,
                'gross_margin_percent' => Money::cmp($s['revenue_costed'], '0') > 0 ? round((float) $gross / (float) $s['revenue_costed'] * 100, 2) : null];
        };
        $sold = $line($total);
        $warnings = array_merge($warnings, $this->costWarning($sold['cost_coverage_percent'], $sold['uncosted_units'], 'this product'));

        // current listing unit economics (what a unit sells for now vs the recorded weighted-average cost)
        $listing = function ($price, $cost, string $label) use (&$warnings) {
            $price = $price !== null ? (string) $price : null;
            $hasCost = $cost !== null && (float) $cost > 0;
            if (! $hasCost) {
                $warnings[] = R::warning('missing_data', "No purchase cost recorded for {$label}: unit margin not computed.", ['field' => 'cost', 'entity' => $label]);
            }

            return ['effective_price' => $price, 'cost' => $hasCost ? (string) $cost : null,
                'unit_gross_profit' => $hasCost && $price !== null ? Money::sub($price, (string) $cost) : null,
                'margin_percent' => $hasCost && $price !== null && (float) $price > 0 ? round(((float) $price - (float) $cost) / (float) $price * 100, 2) : null];
        };

        $variantRows = [];
        foreach ($byVariant as $s) {
            $v = $s['variant_id'] ? $variants->get($s['variant_id']) : null;
            $variantRows[] = ['variant_id' => $s['variant_id'] ?: null, 'sku' => $v?->sku ?? $p->sku, 'label' => $v?->label()] + $line($s);
        }
        $current = [$listing($p->effectivePrice(), $p->cost, 'product')];
        foreach ($p->variants->where('is_active', true) as $v) {
            /** @var ProductVariant $v */
            $src = $v->price !== null ? $v : $p;
            $current[] = ['variant_id' => $v->id, 'sku' => $v->sku] + $listing($src->effectivePrice(), $v->cost ?? $p->cost, 'variant '.$v->sku);
        }

        return ['data' => [
            'product_id' => $p->id, 'sku' => $p->sku, 'name' => StoreText::names($p), 'range' => $q->range(),
            'definitions' => [
                'revenue' => 'Σ (line_subtotal − line_discount) × (qty − returned_qty) / qty over admin-confirmed orders (confirmed..delivered) created in the range',
                'gross_profit' => 'revenue of lines with a cost snapshot − Σ unit_cost × net qty of those lines (uncosted lines excluded, never estimated)',
                'contribution_profit' => 'not computed: needs per-product ad spend and fulfilment cost allocation (not available)',
                'net_profit' => 'not computed per product (expenses / shipping / fees are order- or period-level)',
            ],
            'sold' => $sold + ['contribution_profit' => null, 'net_profit' => null, 'advertising_spend' => null],
            'by_variant' => $variantRows,
            'current_listing' => $current,
        ], 'warnings' => array_merge($warnings, $this->adWarning())];
    }

    public function campaignCandidates(MarketingQuery $q): array
    {
        $days = $q->days();
        $min = (int) config('marketing.campaign.min_available', 1);
        $stock = $this->repo->stockByProduct();
        $products = Product::with('brand:id,name')->where('is_active', true)->whereNotNull('price')->get()
            ->filter(fn ($p) => ($stock[$p->id]['available'] ?? 0) >= $min);
        $sales = $this->repo->itemSales($q->report(), $products->pluck('id')->all());
        $maxUnits = max([0, ...array_column($sales, 'units')]);
        $to = CarbonImmutable::parse($q->to)->startOfDay();

        $rows = [];
        foreach ($products as $p) {
            $s = $sales[(string) $p->id] ?? [];
            $in = ['units' => (int) ($s['units'] ?? 0), 'price' => $p->effectivePrice(), 'cost' => $p->cost !== null ? (string) $p->cost : null,
                'available' => $stock[$p->id]['available'],
                'days_since_last_sale' => ! empty($s['last_sold_at']) && (int) ($s['units'] ?? 0) > 0 ? max(0, (int) CarbonImmutable::parse($s['last_sold_at'])->startOfDay()->diffInDays($to, false)) : null];
            $r = $this->scorer->score($in, $maxUnits, $days);
            $rows[] = $this->productCard($p, $s, $stock[$p->id]) + [
                'score' => $r['score'], 'components' => $r['components'], 'margin_percent' => $r['margin_percent'], 'stock_cover_days' => $r['stock_cover_days'],
                'days_since_last_sale' => $in['days_since_last_sale'], 'explanation' => $this->scorer->explain($in, $r, $days),
                'data_completeness' => ['price' => $in['price'] !== null, 'cost' => $in['cost'] !== null && (float) $in['cost'] > 0, 'stock' => true, 'sales_history' => $in['units'] > 0],
                'warnings' => $r['warnings'],
            ];
        }
        $rows = $this->scorer->sort($rows);
        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        unset($row);
        $noCost = count(array_filter($rows, fn ($r) => ! $r['data_completeness']['cost']));

        return ['data' => $rows, 'warnings' => $noCost ? [R::warning('missing_data', "{$noCost} candidate(s) have no purchase cost: their margin component is 0 (see each item's warnings).", ['field' => 'cost'])] : [],
            'meta' => ['formula' => 'score = 35·velocity + 30·margin + 20·stock_cover + 15·recency (each 0..1); see docs/marketing/API_DOCUMENTATION.md', 'weights' => config('marketing.campaign.weights')]];
    }

    // ---------------------------------------------------------------- marketing metrics

    public function marketingMetrics(MarketingQuery $q): array
    {
        $f = $q->report();
        $sales = $this->reports->sales($f);
        $orders = array_sum(array_column($sales, 'orders'));
        $total = $this->sumRows($sales, 'total');
        $discount = $this->sumRows($sales, 'discount');
        $coupons = $this->reports->discounts($f);
        $gift = $this->giftFinderStats($q->days());
        $flash = $this->reports->flashSales($f);
        $channels = $this->repo->ordersByChannel($f);
        $affiliateOrders = (int) DB::table('orders')->whereNull('deleted_at')->whereNotNull('affiliate_id')
            ->whereBetween('created_at', [$f->start(), $f->end()])->whereIn('status', array_map(fn ($s) => $s->value, OrderStatus::salesStatuses()))->count();

        return ['data' => [
            'range' => $q->range(),
            'store' => ['orders' => $orders, 'gross_sales' => $total, 'aov' => $orders ? Money::div($total, (string) $orders) : null, 'new_customers' => $this->reports->newCustomers($f)],
            'coupons' => ['orders_with_coupon' => array_sum(array_column($coupons, 'usages')), 'discount_total' => $this->sumRows($coupons, 'discount'),
                'discount_share_percent' => Money::cmp($total, '0') > 0 ? round((float) $discount / (float) $total * 100, 2) : null,
                'by_code' => array_map(fn ($c) => ['code' => $c['code'], 'usages' => (int) $c['usages'], 'discount' => (string) $c['discount'], 'orders_total' => (string) $c['orders_total']], $coupons)],
            'flash_sales' => array_map(fn ($r) => ['name' => StoreText::plain((string) $r['flash_sale']), 'starts_at' => $r['starts_at'], 'orders' => (int) $r['orders'], 'units' => (int) $r['units'], 'revenue' => (string) $r['revenue']], $flash),
            'channels' => (object) $channels, 'affiliate_orders' => $affiliateOrders,
            'conversion_proxies' => [
                'gift_finder_completion_rate' => $gift['completion_rate'], 'gift_finder_cart_rate' => $gift['cart_rate'], 'gift_finder_order_rate' => $gift['order_rate'],
                'site_conversion_rate' => null,
            ],
            'gift_finder' => $gift,
            'advertising' => ['spend' => null, 'roas' => null, 'cac' => null, 'source' => null],
        ], 'warnings' => array_merge($this->adWarning(), [R::warning('missing_data', 'Site sessions are not tracked server-side: site_conversion_rate is null (Gift Finder rates are the only funnel proxies).', ['field' => 'sessions'])])];
    }
}

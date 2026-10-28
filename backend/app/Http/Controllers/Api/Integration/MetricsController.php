<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Product;
use App\Models\TenantProduct;
use App\Services\Growth\BrandMemoryService;
use App\Services\Growth\BriefService;
use App\Services\Growth\MetricsService;
use App\Services\Growth\StatsAggregator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Read-only, scope `read`, tenant-scoped (current tenant = the client's), whitelisted fields, aggregate only (no customer identities / PII). */
class MetricsController extends Controller
{
    public function __construct(private MetricsService $metrics, private StatsAggregator $stats, private BriefService $brief) {}

    private function period(Request $r): array
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'channel' => 'nullable|in:direct,affiliate', 'per_page' => 'nullable|integer|min:1|max:100', 'date' => 'nullable|date']);
        $to = $r->filled('to') ? Carbon::parse($r->query('to'))->startOfDay() : now()->subDay()->startOfDay();
        $from = $r->filled('from') ? Carbon::parse($r->query('from'))->startOfDay() : $to->copy()->subDays(29);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 365) {
            $from = $to->copy()->subDays(365);
        }

        return [$from, $to];
    }

    private function out(array $data, array $meta = []): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'meta' => $meta]);
    }

    private function name(?Product $p): ?string
    {
        return $p?->name ?: $p?->name_en ?: $p?->name_ar;
    }

    public function products(Request $r): JsonResponse
    {
        $this->period($r);
        $p = TenantProduct::with('product')->orderBy('id')->paginate((int) $r->query('per_page', 25));

        return $this->out($p->getCollection()->map(fn ($t) => $this->productRow($t))->all(), ['page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total()]);
    }

    private function productRow(TenantProduct $t): array
    {
        return ['id' => $t->product_id, 'name' => $this->name($t->product), 'sku' => $t->product?->sku, 'active' => (bool) $t->is_active, 'price' => (float) $t->price, 'sale_price' => $t->sale_price !== null ? (float) $t->sale_price : null];
    }

    public function facts(Request $r, int $id): JsonResponse
    {
        $t = TenantProduct::with('product')->where('product_id', $id)->firstOrFail();
        [$from, $to] = $this->period($r);
        $row = collect($this->metrics->velocityRows($to))->firstWhere('product_id', $id) ?? ['on_hand' => 0, 'sold' => 0, 'daily_velocity' => 0, 'days_of_cover' => null, 'stock_value' => 0, 'cost_missing' => false];
        $price = (float) ($t->sale_price ?? $t->price);
        $cost = $t->cost !== null ? (float) $t->cost : null;
        $margin = ($cost === null || $cost <= 0 || $price <= 0) ? MetricsService::m(null, 'confirmed', ['cost']) : MetricsService::m(($price - $cost) / $price * 100);
        $perf = collect($this->metrics->byProduct($from, $to, 1000))->firstWhere('product_id', $id);

        return $this->out($this->productRow($t) + ['cost' => $cost, 'unit_margin_percent' => $margin, 'stock' => ['on_hand' => $row['on_hand'], 'days_of_cover' => $row['days_of_cover'], 'daily_velocity' => $row['daily_velocity']],
            'period_performance' => $perf, 'description' => mb_substr((string) $t->product?->description, 0, 1000),
            'approved_claims' => app(BrandMemoryService::class)->claims($id)->map(fn ($c) => ['type' => $c->type, 'claim' => $c->claim])->values()->all()], ['period' => [$from->toDateString(), $to->toDateString()]]);
    }

    public function velocity(Request $r): JsonResponse
    {
        [, $to] = $this->period($r);
        $rows = $this->metrics->velocityRows($to)->sortBy(fn ($x) => $x['days_of_cover'] ?? PHP_INT_MAX)->values();
        $page = max(1, (int) $r->query('page', 1));
        $per = (int) $r->query('per_page', 25);

        $slice = $rows->forPage($page, $per)->values();
        $names = Product::whereIn('id', $slice->pluck('product_id'))->get()->mapWithKeys(fn ($p) => [$p->id => $p->name]); // display only: readable owner messages
        $slice = $slice->map(fn ($x) => $x + ['product_name' => $names[$x['product_id']] ?? null]);

        return $this->out($slice->all(), ['page' => $page, 'per_page' => $per, 'total' => $rows->count(), 'confidence' => 'estimated']);
    }

    public function ordersSummary(Request $r): JsonResponse
    {
        [$from, $to] = $this->period($r);
        $this->stats->rebuild($to, $to);
        $s = $this->metrics->summary($from, $to, $r->query('channel'));

        return $this->out(array_intersect_key($s, array_flip(['orders_placed', 'orders_completed', 'revenue', 'aov', 'cancel_rate', 'return_rate'])), ['period' => [$from->toDateString(), $to->toDateString()]]);
    }

    public function attribution(Request $r): JsonResponse
    {
        [$from, $to] = $this->period($r);

        return $this->out($this->metrics->byChannel($from, $to), ['period' => [$from->toDateString(), $to->toDateString()], 'note' => 'ROAS is not profit']);
    }

    public function bundles(Request $r): JsonResponse
    {
        [$from, $to] = $this->period($r);
        $perf = collect($this->metrics->bundles($from, $to))->keyBy('bundle_id');
        $list = Bundle::orderBy('id')->limit(200)->get()->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'price' => (float) $b->price, 'active' => (bool) $b->is_active, 'performance' => $perf[$b->id] ?? null]);

        return $this->out($list->all(), ['period' => [$from->toDateString(), $to->toDateString()]]);
    }

    public function segments(Request $r): JsonResponse
    {
        [, $to] = $this->period($r);

        return $this->out($this->metrics->customerSegments($to));
    }

    public function summary(Request $r): JsonResponse
    {
        [$from, $to] = $this->period($r);
        $this->stats->rebuild($to, $to);

        return $this->out($this->metrics->summary($from, $to, $r->query('channel')), ['period' => [$from->toDateString(), $to->toDateString()], 'channel' => $r->query('channel')]);
    }

    public function metricsProducts(Request $r): JsonResponse
    {
        [$from, $to] = $this->period($r);

        return $this->out($this->metrics->byProduct($from, $to), ['period' => [$from->toDateString(), $to->toDateString()]]);
    }

    public function metricsChannels(Request $r): JsonResponse
    {
        return $this->attribution($r);
    }

    public function daily(Request $r): JsonResponse
    {
        $this->period($r);

        return $this->out($this->brief->daily($r->filled('date') ? Carbon::parse($r->query('date')) : null));
    }

    public function weekly(Request $r): JsonResponse
    {
        $this->period($r);

        return $this->out($this->brief->weekly($r->filled('date') ? Carbon::parse($r->query('date')) : null));
    }
}

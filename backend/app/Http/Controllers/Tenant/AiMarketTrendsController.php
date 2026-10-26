<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Ai\MarketTrendQuery;
use App\Exceptions\AiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AiMarketDismissRequest;
use App\Http\Requests\Tenant\AiMarketTrendRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Supplier;
use App\Services\Ai\GlobalMarketTrendService;
use App\Support\MarketCountries;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AiMarketTrendsController extends Controller implements HasMiddleware
{
    public function __construct(private GlobalMarketTrendService $market) {}

    public static function middleware(): array
    {
        return [new Middleware('can:ai_trends.view')];
    }

    public function index()
    {
        $models = $this->market->webModels();

        return view('tenant.ai.market-trends', [
            'countries' => MarketCountries::names(), 'defaultCountry' => in_array(current_tenant()->country, MarketCountries::codes(), true) ? current_tenant()->country : 'EG',
            'categories' => Category::query()->where('is_active', true)->get()->map(fn ($c) => (string) $c->name)->filter()->values()->all(),
            'webModels' => $models, 'webModel' => $this->market->pickModel(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'branches' => Branch::where('is_active', true)->get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
            'canPurchase' => auth('tenant')->user()?->can('purchases.create'),
            'canStudio' => auth('tenant')->user()?->can('ai_studio.use') && app(\App\Services\Ai\AiAccess::class)->feature('studio', current_tenant()),
        ]);
    }

    public function data(AiMarketTrendRequest $r): JsonResponse
    {
        $s = $this->market->state(MarketTrendQuery::fromRequest($r));

        return response()->json(['success' => true, 'data' => $s + ['has_model' => (bool) $this->market->pickModel()]]);
    }

    public function refresh(AiMarketTrendRequest $r): JsonResponse
    {
        try {
            $state = $this->market->requestRefresh(MarketTrendQuery::fromRequest($r));
        } catch (AiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'reason' => $e->reason], $e->reason === 'throttled' ? 429 : 422);
        }

        return response()->json(['success' => true, 'state' => $state]);
    }

    public function dismiss(AiMarketDismissRequest $r): JsonResponse
    {
        $q = new MarketTrendQuery(collect($r->input('countries'))->map(fn ($c) => strtoupper($c))->unique()->sort()->values()->all(), (int) ($r->input('period') ?: 30), trim((string) $r->input('keyword', '')), (int) ($r->input('count') ?: 10));
        abort_unless($this->market->dismiss($q, $r->input('key')), 404);

        return response()->json(['success' => true]);
    }

    public function export(AiMarketTrendRequest $r)
    {
        $items = collect($this->market->state(MarketTrendQuery::fromRequest($r))['items'])->all();

        return response($this->market->csv($items), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="market-trends-'.now()->format('Ymd').'.csv"']);
    }
}

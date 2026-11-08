<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Api\ListFilterRequest;
use App\Http\Requests\Marketing\Api\MarketingQueryRequest;
use App\Http\Requests\Marketing\Api\SalesSummaryRequest;
use App\Models\Product;
use App\Services\Marketing\MarketingAnalyticsService;
use App\Support\Marketing\MarketingResponse as R;
use Illuminate\Http\JsonResponse;

/** GET /api/internal/marketing — sales, profit and ranking reads (scope marketing:read). Aggregates only, no customer data. */
class AnalyticsController extends Controller
{
    public function __construct(private MarketingAnalyticsService $svc) {}

    private function wrap(array $r): JsonResponse
    {
        return R::ok($r['data'], $r['warnings'] ?? [], $r['meta'] ?? []);
    }

    private function list(array $r, MarketingQueryRequest $req): JsonResponse
    {
        $q = $req->toQuery();

        return R::slice($r['data'], $q->page, $q->perPage, $r['warnings'] ?? [], ['range' => $q->range()] + ($r['meta'] ?? []));
    }

    public function overview(MarketingQueryRequest $r): JsonResponse
    {
        return $this->wrap($this->svc->overview($r->toQuery()));
    }

    public function salesSummary(SalesSummaryRequest $r): JsonResponse
    {
        return $this->wrap($this->svc->salesSummary($r->toQuery()));
    }

    public function ordersSummary(MarketingQueryRequest $r): JsonResponse
    {
        return $this->wrap($this->svc->ordersSummary($r->toQuery()));
    }

    public function bestSellers(MarketingQueryRequest $r): JsonResponse
    {
        return $this->list($this->svc->bestSellers($r->toQuery()), $r);
    }

    public function slowMoving(MarketingQueryRequest $r): JsonResponse
    {
        return $this->list($this->svc->slowMoving($r->toQuery()), $r);
    }

    public function lowStock(MarketingQueryRequest $r): JsonResponse
    {
        return $this->list($this->svc->lowStock(), $r);
    }

    public function campaignCandidates(MarketingQueryRequest $r): JsonResponse
    {
        return $this->list($this->svc->campaignCandidates($r->toQuery()), $r);
    }

    public function marketingMetrics(MarketingQueryRequest $r): JsonResponse
    {
        return $this->wrap($this->svc->marketingMetrics($r->toQuery()));
    }

    public function profitability(MarketingQueryRequest $r, int $id): JsonResponse
    {
        $p = Product::withTrashed()->find($id);

        return $p ? $this->wrap($this->svc->profitability($p, $r->toQuery())) : R::error(404, 'not_found');
    }

    /** gift-finder/stats?days=30 (1..366, default = range length). */
    public function giftFinderStats(ListFilterRequest $r): JsonResponse
    {
        $q = $r->toQuery();

        return R::ok($this->svc->giftFinderStats((int) $q->filter('days', $q->days())));
    }
}

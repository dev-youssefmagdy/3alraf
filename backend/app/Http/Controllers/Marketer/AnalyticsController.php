<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketer\MarketerAnalyticsRequest;
use App\Services\Marketing\MarketingAnalyticsPageService;

/** /marketer/analytics (perm marketing.analytics): sales, profit, orders and promotion performance from real store data. */
class AnalyticsController extends Controller
{
    public function index(MarketerAnalyticsRequest $r, MarketingAnalyticsPageService $svc)
    {
        return view('marketer.analytics.index', ['f' => $r->filter(), 'groupBy' => $r->groupBy()] + $svc->build($r->toQuery()));
    }
}

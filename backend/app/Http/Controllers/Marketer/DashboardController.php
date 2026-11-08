<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketer\MarketerDashboardRequest;
use App\Services\Marketing\MarketingDashboardService;
use App\Support\Marketing\MarketerNav;

/** /marketer: KPIs from real data (perm marketing.view; profit block needs marketing.analytics). Without marketing.view → first page the admin may open. */
class DashboardController extends Controller
{
    public function index(MarketerDashboardRequest $r, MarketingDashboardService $svc)
    {
        $user = auth('admin')->user();
        if (! $user->can('marketing.view')) {
            $first = MarketerNav::firstRoute($user);
            abort_if($first === null || $first === 'marketer.dashboard', 403);

            return redirect()->route($first);
        }

        return view('marketer.dashboard', ['f' => $r->filter()] + $svc->build($r->toQuery(), $user->can('marketing.analytics')));
    }
}

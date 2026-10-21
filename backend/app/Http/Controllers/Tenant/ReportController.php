<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\HandlesReports;
use App\Http\Controllers\Controller;
use App\DTOs\Reports\ReportFilter;
use App\Services\Orders\CarrierSettlementService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class ReportController extends Controller implements HasMiddleware
{
    use HandlesReports;

    protected static string $guard = 'tenant';

    /** "Mark carrier settled": delivered COD orders of one carrier in the report window leave the outstanding balance. */
    public function settleCod(Request $request, CarrierSettlementService $settlement)
    {
        abort_unless(auth('tenant')->user()?->can('orders.payments'), 403);
        $data = $request->validate(['shipping_company_id' => ['required', 'integer', 'exists:shipping_companies,id'], 'from' => ['required', 'date'], 'to' => ['required', 'date']]);
        $f = ReportFilter::fromRequest($request, false);
        $n = $settlement->settle((int) $data['shipping_company_id'], $f->from, $f->to);

        return redirect()->route('tenant.reports.show', ['report' => 'cod-settlement'] + $f->query())->with('status', __('reports.settled_done', ['n' => $n]));
    }
}

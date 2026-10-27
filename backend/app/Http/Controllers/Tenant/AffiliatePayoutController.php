<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\AffiliatePayoutDataTable;
use App\Http\Controllers\Controller;
use App\Services\Affiliates\AffiliateService;
use App\Services\Growth\AmbassadorService;
use App\Services\Growth\GrowthFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AffiliatePayoutController extends Controller implements HasMiddleware
{
    public function __construct(private AffiliateService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:affiliate_payouts.view', only: ['index', 'datatable']),
            new Middleware('can:affiliate_payouts.edit', only: ['pay', 'reject']),
        ];
    }

    public function index()
    {
        return view('tenant.affiliate-payouts.index', ['table' => new AffiliatePayoutDataTable]);
    }

    public function datatable(): JsonResponse
    {
        return (new AffiliatePayoutDataTable)->ajax();
    }

    public function pay(Request $r, int $id): JsonResponse
    {
        $payout = $this->service->markPaid($this->service->findPayout($id), $r->input('note'));
        if (app(GrowthFeatures::class)->active('ambassadors')) {
            app(AmbassadorService::class)->settle($payout->affiliate_id); // ambassador rewards of this affiliate are paid in the same run
        }

        return $this->ok(__('growth.payout_paid'));
    }

    public function reject(Request $r, int $id): JsonResponse
    {
        $this->service->rejectPayout($this->service->findPayout($id), $r->input('note'));

        return $this->ok(__('growth.payout_rejected'));
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\AffiliateDataTable;
use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Services\Affiliates\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Affiliate accounts: list, approve / suspend / reactivate, delete. */
class AffiliateController extends Controller implements HasMiddleware
{
    public function __construct(private AffiliateService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:affiliates.view', only: ['index', 'datatable']),
            new Middleware('can:affiliates.edit', only: ['approve', 'suspend']),
            new Middleware('can:affiliates.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('tenant.affiliates.index', ['table' => new AffiliateDataTable]);
    }

    public function datatable(): JsonResponse
    {
        return (new AffiliateDataTable)->ajax();
    }

    public function approve(int $id): JsonResponse
    {
        $this->service->setStatus($this->service->find($id), AffiliateStatus::APPROVED);

        return $this->ok(__('growth.affiliate_approved'));
    }

    public function suspend(int $id): JsonResponse
    {
        $this->service->setStatus($this->service->find($id), AffiliateStatus::SUSPENDED);

        return $this->ok(__('growth.affiliate_suspended'));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($this->service->find($id));

        return $this->ok(__('admin.deleted'));
    }
}

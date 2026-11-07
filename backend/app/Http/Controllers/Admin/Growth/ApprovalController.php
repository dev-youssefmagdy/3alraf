<?php

namespace App\Http\Controllers\Admin\Growth;

use App\DataTables\GrowthApprovalDataTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrowthDecisionRequest;
use App\Services\Growth\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ApprovalController extends Controller implements HasMiddleware
{
    public function __construct(private ApprovalService $approvals) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index', 'datatable']), new Middleware('can:growth.approve', only: ['approve', 'reject'])];
    }

    public function index(GrowthApprovalDataTable $table)
    {
        return view('admin.growth.approvals', ['table' => $table]);
    }

    public function datatable(GrowthApprovalDataTable $table)
    {
        return $table->ajax();
    }

    public function approve(GrowthDecisionRequest $r, int $id): JsonResponse
    {
        $this->approvals->approve($id, auth('admin')->id(), $r->validated('reason'));

        return $this->ok(__('growth_hub.approvals.approved_ok'));
    }

    public function reject(GrowthDecisionRequest $r, int $id): JsonResponse
    {
        $this->approvals->reject($id, auth('admin')->id(), $r->validated('reason'));

        return $this->ok(__('growth_hub.approvals.rejected_ok'));
    }
}

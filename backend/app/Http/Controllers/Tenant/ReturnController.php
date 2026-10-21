<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\OrderReturnDataTable;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Orders\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReturnController extends Controller implements HasMiddleware
{
    public function __construct(private ReturnService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:returns.view', only: ['index', 'datatable', 'show']),
            new Middleware('can:returns.approve', only: ['approve', 'reject']),
            new Middleware('can:returns.refund', only: ['refund']),
        ];
    }

    public function index()
    {
        return view('tenant.returns.index', ['table' => new OrderReturnDataTable, 'statuses' => ReturnStatus::options()]);
    }

    public function datatable(): JsonResponse
    {
        return (new OrderReturnDataTable)->ajax();
    }

    public function show(int $id)
    {
        $ret = $this->service->find($id);

        return view('tenant.returns.show', ['ret' => $ret, 'refundable' => $this->service->refundable($ret),
            'branches' => Branch::where('is_active', true)->get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all()]);
    }

    public function approve(Request $r, int $id): JsonResponse
    {
        $this->service->approve($this->service->find($id), $r->boolean('restock'), $r->filled('branch_id') ? (int) $r->input('branch_id') : null);

        return $this->ok(__('orders.return_approved'), route('tenant.returns.show', $id));
    }

    public function reject(int $id): JsonResponse
    {
        $this->service->reject($this->service->find($id));

        return $this->ok(__('admin.saved'), route('tenant.returns.show', $id));
    }

    public function refund(Request $r, int $id): JsonResponse
    {
        $r->validate(['amount' => ['nullable', 'numeric', 'min:0.01']]);
        $this->service->refund($this->service->find($id), $r->input('amount'));

        return $this->ok(__('orders.refunded'), route('tenant.returns.show', $id));
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\StockRequestDataTable;
use App\Enums\StockRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockRequestController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:stock_requests.view', only: ['index', 'datatable']),
            new Middleware('can:stock_requests.edit', only: ['status']),
            new Middleware('can:stock_requests.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('tenant.stock-requests.index', ['table' => new StockRequestDataTable]);
    }

    public function datatable(): JsonResponse
    {
        return (new StockRequestDataTable)->ajax();
    }

    public function status(int $id, string $status): JsonResponse
    {
        StockRequest::findOrFail($id)->update(['status' => StockRequestStatus::from($status)]);

        return $this->ok(__('admin.saved'));
    }

    public function destroy(int $id): JsonResponse
    {
        StockRequest::findOrFail($id)->delete();

        return $this->ok(__('admin.deleted'));
    }
}

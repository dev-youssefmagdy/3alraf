<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\StockAdjustmentDataTable;
use App\DTOs\StockAdjustmentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockAdjustmentRequest;
use App\Models\Branch;
use App\Services\Stock\StockItemCatalog;
use App\Services\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockAdjustmentController extends Controller implements HasMiddleware
{
    public function __construct(private StockAdjustmentService $service, private StockItemCatalog $catalog) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:stock_adjustments.view', only: ['index', 'datatable', 'show']),
            new Middleware('can:stock_adjustments.create', only: ['create', 'store']),
        ];
    }

    public function index()
    {
        return view('admin.crud.index', ['table' => new StockAdjustmentDataTable, 'title' => __('ops.stock_adjustments'), 'route' => 'admin.stock-adjustments', 'perm' => 'stock_adjustments']);
    }

    public function datatable(): JsonResponse
    {
        return (new StockAdjustmentDataTable)->ajax();
    }

    public function create()
    {
        return view('admin.stock-adjustments.form', [
            'items' => $this->catalog->options(),
            'branches' => Branch::where('is_active', true)->get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
        ]);
    }

    public function show(int $id)
    {
        return view('admin.stock-adjustments.show', ['a' => $this->service->find($id)]);
    }

    public function store(StockAdjustmentRequest $request): JsonResponse
    {
        $a = $this->service->create(StockAdjustmentData::fromRequest($request));

        return $this->ok(__('ops.adjustment_applied'), route('admin.stock-adjustments.show', $a->id));
    }

    public function validateForm(StockAdjustmentRequest $request): JsonResponse
    {
        return $this->ok();
    }
}

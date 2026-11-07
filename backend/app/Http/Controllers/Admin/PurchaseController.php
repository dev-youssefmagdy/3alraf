<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\PurchaseOrderDataTable;
use App\DTOs\PurchaseOrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\PurchaseOrderRequest;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseService;
use App\Services\Stock\StockItemCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PurchaseController extends Controller implements HasMiddleware
{
    public function __construct(private PurchaseService $service, private StockItemCatalog $catalog) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:purchases.view', only: ['index', 'datatable', 'show']),
            new Middleware('can:purchases.create', only: ['create', 'store']),
            new Middleware('can:purchases.edit', only: ['edit', 'update', 'cancel']),
            new Middleware('can:purchases.delete', only: ['destroy']),
            new Middleware('can:purchases.receive', only: ['receive']),
        ];
    }

    public function index()
    {
        return view('tenant.crud.index', ['table' => new PurchaseOrderDataTable, 'title' => __('ops.purchases'), 'route' => 'tenant.purchases', 'perm' => 'purchases']);
    }

    public function datatable(): JsonResponse
    {
        return (new PurchaseOrderDataTable)->ajax();
    }

    public function create()
    {
        return $this->form(new PurchaseOrder(['ordered_at' => now()]));
    }

    public function edit(int $id)
    {
        $po = $this->service->find($id);
        abort_unless($po->isDraft(), 403);

        return $this->form($po);
    }

    public function show(int $id)
    {
        return view('tenant.purchases.show', ['po' => $this->service->find($id)]);
    }

    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        $po = $this->service->create(PurchaseOrderData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('tenant.purchases.show', $po->id));
    }

    public function update(PurchaseOrderRequest $request, int $id): JsonResponse
    {
        $po = $this->service->update($this->service->find($id), PurchaseOrderData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('tenant.purchases.show', $po->id));
    }

    public function validateForm(PurchaseOrderRequest $request, ?int $id = null): JsonResponse
    {
        return $this->ok();
    }

    public function receive(int $id): JsonResponse
    {
        $this->service->receive($this->service->find($id));

        return $this->ok(__('ops.purchase_received'), route('tenant.purchases.show', $id));
    }

    public function cancel(int $id): JsonResponse
    {
        $this->service->cancel($this->service->find($id));

        return $this->ok(__('admin.saved'), route('tenant.purchases.show', $id));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($this->service->find($id));

        return $this->ok(__('admin.deleted'));
    }

    private function form(PurchaseOrder $po)
    {
        $po->loadMissing('items');

        return view('tenant.purchases.form', [
            'po' => $po, 'editing' => $po->exists, 'items' => $this->catalog->options(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'branches' => Branch::where('is_active', true)->get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
        ]);
    }
}

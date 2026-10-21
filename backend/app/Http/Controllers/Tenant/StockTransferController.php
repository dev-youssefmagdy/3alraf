<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\StockTransferDataTable;
use App\DTOs\StockTransferData;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StockTransferRequest;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Services\Stock\StockItemCatalog;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockTransferController extends Controller implements HasMiddleware
{
    public function __construct(private StockTransferService $service, private StockItemCatalog $catalog) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:stock_transfers.view', only: ['index', 'datatable', 'show']),
            new Middleware('can:stock_transfers.create', only: ['create', 'store']),
            new Middleware('can:stock_transfers.edit', only: ['edit', 'update', 'cancel']),
            new Middleware('can:stock_transfers.delete', only: ['destroy']),
            new Middleware('can:stock_transfers.send', only: ['send']),
            new Middleware('can:stock_transfers.receive', only: ['receive']),
        ];
    }

    public function index()
    {
        return view('tenant.crud.index', ['table' => new StockTransferDataTable, 'title' => __('ops.stock_transfers'), 'route' => 'tenant.stock-transfers', 'perm' => 'stock_transfers']);
    }

    public function datatable(): JsonResponse
    {
        return (new StockTransferDataTable)->ajax();
    }

    public function create()
    {
        return $this->form(new StockTransfer);
    }

    public function edit(int $id)
    {
        $t = $this->service->find($id);
        abort_unless($t->status === TransferStatus::DRAFT, 403);

        return $this->form($t);
    }

    public function show(int $id)
    {
        return view('tenant.stock-transfers.show', ['t' => $this->service->find($id)]);
    }

    public function store(StockTransferRequest $request): JsonResponse
    {
        $t = $this->service->create(StockTransferData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('tenant.stock-transfers.show', $t->id));
    }

    public function update(StockTransferRequest $request, int $id): JsonResponse
    {
        $t = $this->service->update($this->service->find($id), StockTransferData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('tenant.stock-transfers.show', $t->id));
    }

    public function validateForm(StockTransferRequest $request, ?int $id = null): JsonResponse
    {
        return $this->ok();
    }

    public function send(int $id): JsonResponse
    {
        $this->service->send($this->service->find($id));

        return $this->ok(__('ops.transfer_sent'), route('tenant.stock-transfers.show', $id));
    }

    public function receive(int $id): JsonResponse
    {
        $this->service->receive($this->service->find($id));

        return $this->ok(__('ops.transfer_received'), route('tenant.stock-transfers.show', $id));
    }

    public function cancel(int $id): JsonResponse
    {
        $this->service->cancel($this->service->find($id));

        return $this->ok(__('admin.saved'), route('tenant.stock-transfers.show', $id));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($this->service->find($id));

        return $this->ok(__('admin.deleted'));
    }

    private function form(StockTransfer $t)
    {
        $t->loadMissing('items');

        return view('tenant.stock-transfers.form', [
            't' => $t, 'editing' => $t->exists, 'items' => $this->catalog->options(),
            'branches' => Branch::where('is_active', true)->get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
        ]);
    }
}

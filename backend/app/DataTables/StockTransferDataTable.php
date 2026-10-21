<?php

namespace App\DataTables;

use App\Enums\TransferStatus;
use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class StockTransferDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return StockTransfer::query()->with(['from', 'to'])->withSum('items', 'quantity')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'number_link', 'title' => __('ops.fields.number'), 'name' => 'number'],
            ['data' => 'from_label', 'title' => __('ops.fields.from_branch'), 'orderable' => false, 'searchable' => false],
            ['data' => 'to_label', 'title' => __('ops.fields.to_branch'), 'orderable' => false, 'searchable' => false],
            ['data' => 'items_sum_quantity', 'title' => __('ops.fields.quantity'), 'searchable' => false],
            ['data' => 'created_at', 'title' => __('ops.fields.date')],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('tenant')->user();
        $dt->addColumn('number_link', fn (StockTransfer $m) => '<a class="font-semibold text-brand-600" href="'.e(route('tenant.stock-transfers.show', $m->id)).'">'.e($m->number).'</a>')
            ->filterColumn('number_link', fn ($q, $k) => $q->where('number', 'like', "%$k%"))
            ->addColumn('from_label', fn (StockTransfer $m) => e($m->from?->name))
            ->addColumn('to_label', fn (StockTransfer $m) => e($m->to?->name))
            ->editColumn('created_at', fn (StockTransfer $m) => $m->created_at->toDateString())
            ->addColumn('status_label', fn (StockTransfer $m) => '<span class="badge '.($m->status === TransferStatus::RECEIVED ? 'badge-success' : 'badge-muted').'">'.e($m->status->label()).'</span>')
            ->addColumn('actions', fn (StockTransfer $m) => $m->status === TransferStatus::DRAFT
                ? $this->actionButtons(route('tenant.stock-transfers.edit', $m->id), route('tenant.stock-transfers.destroy', $m->id), (bool) $u?->can('stock_transfers.edit'), (bool) $u?->can('stock_transfers.delete'))
                : '');
    }

    protected function rawColumns(): array
    {
        return ['number_link', 'from_label', 'to_label', 'status_label', 'actions'];
    }
}

<?php

namespace App\DataTables;

use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class PurchaseOrderDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return PurchaseOrder::query()->with(['supplier', 'branch'])->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'number_link', 'title' => __('ops.fields.number'), 'name' => 'number'],
            ['data' => 'supplier_label', 'title' => __('ops.fields.supplier'), 'orderable' => false, 'searchable' => false],
            ['data' => 'branch_label', 'title' => __('ops.fields.branch'), 'orderable' => false, 'searchable' => false],
            ['data' => 'ordered_at', 'title' => __('ops.fields.date')],
            ['data' => 'total_cost', 'title' => __('ops.fields.total')],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('tenant')->user();
        $dt->addColumn('number_link', fn (PurchaseOrder $m) => '<a class="font-semibold text-brand-600" href="'.e(route('tenant.purchases.show', $m->id)).'">'.e($m->number).'</a>')
            ->filterColumn('number_link', fn ($q, $k) => $q->where('number', 'like', "%$k%"))
            ->addColumn('supplier_label', fn (PurchaseOrder $m) => e($m->supplier?->name))
            ->addColumn('branch_label', fn (PurchaseOrder $m) => e($m->branch?->name))
            ->editColumn('ordered_at', fn (PurchaseOrder $m) => $m->ordered_at->toDateString())
            ->editColumn('total_cost', fn (PurchaseOrder $m) => $this->money($m->total_cost))
            ->addColumn('status_label', fn (PurchaseOrder $m) => '<span class="badge '.($m->status->value === 'received' ? 'badge-success' : 'badge-muted').'">'.e($m->status->label()).'</span>')
            ->addColumn('actions', fn (PurchaseOrder $m) => $m->isDraft()
                ? $this->actionButtons(route('tenant.purchases.edit', $m->id), route('tenant.purchases.destroy', $m->id), (bool) $u?->can('purchases.edit'), (bool) $u?->can('purchases.delete'))
                : '');
    }

    protected function rawColumns(): array
    {
        return ['number_link', 'supplier_label', 'branch_label', 'status_label', 'actions'];
    }
}

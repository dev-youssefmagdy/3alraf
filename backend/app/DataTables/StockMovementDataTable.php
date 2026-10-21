<?php

namespace App\DataTables;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class StockMovementDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        $r = request();

        return StockMovement::query()->with(['branch', 'product', 'variant.values'])
            ->when($r->integer('branch_id'), fn ($q, $b) => $q->where('branch_id', $b))
            ->when($r->string('type')->toString(), fn ($q, $t) => $q->where('type', $t))
            ->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'created_at', 'title' => __('ops.fields.date')],
            ['data' => 'item_label', 'title' => __('ops.fields.item'), 'orderable' => false],
            ['data' => 'branch_label', 'title' => __('ops.fields.branch'), 'orderable' => false, 'searchable' => false],
            ['data' => 'type_label', 'title' => __('ops.fields.type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'quantity', 'title' => __('ops.fields.quantity')],
            ['data' => 'unit_cost', 'title' => __('ops.fields.unit_cost')],
            ['data' => 'balance_after', 'title' => __('ops.fields.balance')],
            ['data' => 'note', 'title' => __('ops.fields.note'), 'orderable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->editColumn('created_at', fn (StockMovement $m) => $m->created_at->format('Y-m-d H:i'))
            ->addColumn('item_label', fn (StockMovement $m) => e($m->product?->name.($m->product_variant_id ? ' - '.$m->variant?->label() : '')))
            ->filterColumn('item_label', fn ($q, $k) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%$k%")->orWhere('sku', 'like', "%$k%")))
            ->addColumn('branch_label', fn (StockMovement $m) => e($m->branch?->name))
            ->addColumn('type_label', fn (StockMovement $m) => e($m->type->label()))
            ->editColumn('quantity', fn (StockMovement $m) => ($m->quantity > 0 ? '+' : '').$m->quantity)
            ->editColumn('unit_cost', fn (StockMovement $m) => $this->money($m->unit_cost))
            ->editColumn('note', fn (StockMovement $m) => e($m->note));
    }

    protected function rawColumns(): array
    {
        return ['item_label', 'branch_label', 'type_label', 'note'];
    }
}

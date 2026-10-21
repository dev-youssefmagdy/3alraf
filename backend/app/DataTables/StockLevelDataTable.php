<?php

namespace App\DataTables;

use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class StockLevelDataTable extends BaseDataTable
{
    public function __construct(private ?int $branchId = null) {}

    public function query(): Builder
    {
        $branch = $this->branchId ?? (request()->integer('branch_id') ?: null);

        return StockLevel::query()->with(['branch', 'product', 'variant.values'])->when($branch, fn ($q) => $q->where('branch_id', $branch))->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'item_label', 'title' => __('ops.fields.item'), 'orderable' => false],
            ['data' => 'sku', 'title' => __('admin.fields.sku'), 'orderable' => false, 'searchable' => false],
            ['data' => 'branch_label', 'title' => __('ops.fields.branch'), 'orderable' => false, 'searchable' => false],
            ['data' => 'on_hand', 'title' => __('ops.fields.on_hand')],
            ['data' => 'reserved', 'title' => __('ops.fields.reserved')],
            ['data' => 'available', 'title' => __('ops.fields.available'), 'orderable' => false, 'searchable' => false],
            ['data' => 'avg_cost', 'title' => __('ops.fields.avg_cost')],
            ['data' => 'valuation', 'title' => __('ops.fields.valuation'), 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('item_label', fn (StockLevel $m) => e($m->product?->name.($m->product_variant_id ? ' - '.$m->variant?->label() : '')))
            ->filterColumn('item_label', fn ($q, $k) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%$k%")->orWhere('sku', 'like', "%$k%")))
            ->addColumn('sku', fn (StockLevel $m) => e($m->product_variant_id ? $m->variant?->sku : $m->product?->sku))
            ->addColumn('branch_label', fn (StockLevel $m) => e($m->branch?->name))
            ->addColumn('available', fn (StockLevel $m) => $m->available)
            ->editColumn('avg_cost', fn (StockLevel $m) => $this->money($m->avg_cost))
            ->addColumn('valuation', fn (StockLevel $m) => $this->money($m->valuation));
    }

    protected function rawColumns(): array
    {
        return ['item_label', 'sku', 'branch_label'];
    }
}

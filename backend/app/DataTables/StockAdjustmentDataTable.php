<?php

namespace App\DataTables;

use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class StockAdjustmentDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return StockAdjustment::query()->with('branch')->withSum('items', 'quantity')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'number_link', 'title' => __('ops.fields.number'), 'name' => 'number'],
            ['data' => 'branch_label', 'title' => __('ops.fields.branch'), 'orderable' => false, 'searchable' => false],
            ['data' => 'type_label', 'title' => __('ops.fields.type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'reason_label', 'title' => __('ops.fields.reason'), 'orderable' => false, 'searchable' => false],
            ['data' => 'items_sum_quantity', 'title' => __('ops.fields.quantity'), 'searchable' => false],
            ['data' => 'created_at', 'title' => __('ops.fields.date')],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('number_link', fn (StockAdjustment $m) => '<a class="font-semibold text-brand-600" href="'.e(route('tenant.stock-adjustments.show', $m->id)).'">'.e($m->number).'</a>')
            ->filterColumn('number_link', fn ($q, $k) => $q->where('number', 'like', "%$k%"))
            ->addColumn('branch_label', fn (StockAdjustment $m) => e($m->branch?->name))
            ->addColumn('type_label', fn (StockAdjustment $m) => e($m->type->label()))
            ->addColumn('reason_label', fn (StockAdjustment $m) => e($m->reason->label()))
            ->editColumn('created_at', fn (StockAdjustment $m) => $m->created_at->toDateString());
    }

    protected function rawColumns(): array
    {
        return ['number_link', 'branch_label', 'type_label', 'reason_label'];
    }
}

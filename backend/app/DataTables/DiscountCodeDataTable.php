<?php

namespace App\DataTables;

use App\Models\DiscountCode;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class DiscountCodeDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return DiscountCode::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'code', 'title' => __('orders.fields.code')],
            ['data' => 'value_label', 'title' => __('orders.fields.discount'), 'orderable' => false, 'searchable' => false],
            ['data' => 'usage', 'title' => __('orders.fields.usage'), 'orderable' => false, 'searchable' => false],
            ['data' => 'period', 'title' => __('orders.fields.period'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('value_label', fn (DiscountCode $m) => $m->free_shipping ? __('orders.fields.free_shipping') : ($m->type->value === 'percent' ? rtrim(rtrim((string) $m->value, '0'), '.').'%' : $this->money($m->value)))
            ->addColumn('usage', fn (DiscountCode $m) => $m->used_count.($m->usage_limit ? ' / '.$m->usage_limit : ''))
            ->addColumn('period', fn (DiscountCode $m) => ($m->starts_at?->toDateString() ?? '—').' → '.($m->ends_at?->toDateString() ?? '—'))
            ->addColumn('status', fn (DiscountCode $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (DiscountCode $m) => $this->tenantActions($m, 'tenant.discount-codes', 'discount_codes'));
    }

    protected function rawColumns(): array
    {
        return ['status', 'actions'];
    }
}

<?php

namespace App\DataTables;

use App\Models\FlashSale;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class FlashSaleDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return FlashSale::query()->withCount('items')->withSum('items as sold_sum', 'sold')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'period', 'title' => __('orders.fields.period'), 'orderable' => false, 'searchable' => false],
            ['data' => 'items_count', 'title' => __('orders.fields.products'), 'searchable' => false],
            ['data' => 'sold_sum', 'title' => __('orders.fields.sold'), 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('name_label', fn (FlashSale $m) => e($m->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('period', fn (FlashSale $m) => $m->starts_at->format('Y-m-d H:i').' → '.$m->ends_at->format('Y-m-d H:i'))
            ->editColumn('sold_sum', fn (FlashSale $m) => (int) $m->sold_sum)
            ->addColumn('status', fn (FlashSale $m) => $m->isRunning() ? $this->badge(true, __('orders.flash.running')) : $this->badge(false, null, $m->is_active ? ($m->starts_at->isFuture() ? __('orders.flash.scheduled') : __('orders.flash.ended')) : __('admin.inactive')))
            ->addColumn('actions', fn (FlashSale $m) => $this->tenantActions($m, 'tenant.flash-sales', 'flash_sales'));
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'status', 'actions'];
    }
}

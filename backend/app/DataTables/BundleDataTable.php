<?php

namespace App\DataTables;

use App\Models\Bundle;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class BundleDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Bundle::query()->withCount('items')->orderBy('sort_order')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'price', 'title' => __('orders.bundle.price')],
            ['data' => 'items_count', 'title' => __('orders.fields.products'), 'searchable' => false],
            ['data' => 'period', 'title' => __('orders.fields.period'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('name_label', fn (Bundle $m) => e($m->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('period', fn (Bundle $m) => ($m->starts_at || $m->ends_at) ? ($m->starts_at?->format('Y-m-d H:i') ?? '…').' → '.($m->ends_at?->format('Y-m-d H:i') ?? '…') : '—')
            ->addColumn('status', fn (Bundle $m) => $m->isLive() ? $this->badge(true, __('orders.flash.running')) : $this->badge(false, null, $m->is_active ? __('orders.flash.scheduled') : __('admin.inactive')))
            ->addColumn('actions', fn (Bundle $m) => $this->tenantActions($m, 'tenant.bundles', 'bundles'));
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'status', 'actions'];
    }
}

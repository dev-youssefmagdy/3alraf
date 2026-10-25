<?php

namespace App\DataTables;

use App\Models\PackagingOption;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class PackagingOptionDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return PackagingOption::query()->orderBy('sort_order')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'price', 'title' => __('orders.packaging.price')],
            ['data' => 'cost', 'title' => __('orders.packaging.cost')],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('name_label', fn (PackagingOption $m) => e($m->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('status', fn (PackagingOption $m) => $this->badge($m->is_active, __('admin.active'), __('admin.inactive')))
            ->addColumn('actions', fn (PackagingOption $m) => $this->tenantActions($m, 'tenant.packaging', 'packaging'));
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'status', 'actions'];
    }
}

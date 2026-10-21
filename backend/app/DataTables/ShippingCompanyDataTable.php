<?php

namespace App\DataTables;

use App\Models\ShippingCompany;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class ShippingCompanyDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return ShippingCompany::query()->withCount('rates')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'rates_count', 'title' => __('ops.fields.cities_covered'), 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('name_label', fn (ShippingCompany $m) => e($m->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('status', fn (ShippingCompany $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (ShippingCompany $m) => $this->tenantActions($m, 'tenant.shipping-companies', 'shipping'));
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'status', 'actions'];
    }
}

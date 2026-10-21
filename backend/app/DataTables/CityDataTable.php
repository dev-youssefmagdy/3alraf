<?php

namespace App\DataTables;

use App\Models\City;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class CityDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return City::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'sort_order', 'title' => __('admin.fields.sort_order')],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('name_label', fn (City $m) => e($m->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('status', fn (City $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (City $m) => $this->tenantActions($m, 'tenant.cities', 'cities'));
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'status', 'actions'];
    }
}

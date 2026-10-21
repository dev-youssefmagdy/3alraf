<?php

namespace App\DataTables;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class BranchDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Branch::query()->with('city')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'city_label', 'title' => __('ops.fields.city'), 'orderable' => false, 'searchable' => false],
            ['data' => 'phone', 'title' => __('ops.fields.phone')],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('name_label', fn (Branch $m) => e($m->name).($m->is_default ? ' <span class="badge badge-success">'.e(__('admin.default')).'</span>' : ''))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('city_label', fn (Branch $m) => e($m->city?->name ?? '—'))
            ->addColumn('status', fn (Branch $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (Branch $m) => $this->tenantActions($m, 'tenant.branches', 'branches'));
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'city_label', 'status', 'actions'];
    }
}

<?php

namespace App\DataTables;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class SupplierDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Supplier::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name', 'title' => __('admin.fields.name')],
            ['data' => 'contact_name', 'title' => __('ops.fields.contact_name')],
            ['data' => 'phone', 'title' => __('ops.fields.phone')],
            ['data' => 'email', 'title' => __('ops.fields.email')],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->editColumn('name', fn (Supplier $m) => e($m->name))
            ->addColumn('status', fn (Supplier $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (Supplier $m) => $this->tenantActions($m, 'tenant.suppliers', 'suppliers'));
    }

    protected function rawColumns(): array
    {
        return ['name', 'status', 'actions'];
    }
}

<?php

namespace App\DataTables;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class TenantDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Tenant::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name', 'title' => __('admin.fields.name')],
            ['data' => 'slug', 'title' => __('admin.fields.slug')],
            ['data' => 'currency', 'title' => __('admin.fields.currency'), 'orderable' => false, 'searchable' => false],
            ['data' => 'locales_label', 'title' => __('admin.fields.locales'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $user = auth('central')->user();
        $dt->addColumn('currency', fn (Tenant $t) => e($t->currency_code.' ('.$t->currency_symbol.')'))
            ->addColumn('locales_label', fn (Tenant $t) => e(strtoupper(implode(', ', $t->locales))))
            ->addColumn('status', fn (Tenant $t) => $this->badge($t->is_active))
            ->editColumn('slug', fn (Tenant $t) => '<a class="text-brand-600 hover:underline" target="_blank" href="'.e($t->url('admin')).'">'.e($t->slug).'.'.e(central_domain()).'</a>')
            ->addColumn('actions', fn (Tenant $t) => $this->actionButtons(
                route('central.tenants.edit', $t), route('central.tenants.destroy', $t),
                $user?->can('tenants.edit'), $user?->can('tenants.delete')))
            ->rawColumns(['slug', 'status', 'actions']);
    }

    protected function rawColumns(): array
    {
        return ['slug', 'status', 'actions'];
    }
}

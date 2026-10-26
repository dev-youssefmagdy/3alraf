<?php

namespace App\DataTables;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class AiTenantDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Tenant::query()->leftJoin('ai_tenant_settings as s', 's.tenant_id', '=', 'tenants.id')
            ->select('tenants.id', 'tenants.name', 'tenants.country', 's.ai_enabled', 's.monthly_token_quota', 's.features')->orderBy('tenants.name');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name', 'title' => __('ai.fields.tenant')],
            ['data' => 'country', 'title' => __('ai.fields.country')],
            ['data' => 'enabled', 'title' => __('ai.fields.ai_enabled'), 'orderable' => false, 'searchable' => false],
            ['data' => 'quota', 'title' => __('ai.fields.monthly_token_quota'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('central')->user();
        $dt->filterColumn('name', fn ($q, $k) => $q->where('tenants.name', 'like', "%$k%"))
            ->editColumn('name', fn ($t) => e($t->name))
            ->addColumn('enabled', fn ($t) => $this->badge((bool) $t->ai_enabled, __('ai.on'), __('ai.off')))
            ->addColumn('quota', fn ($t) => $t->monthly_token_quota === null ? __('ai.unlimited') : number_format((int) $t->monthly_token_quota))
            ->addColumn('actions', fn ($t) => $u?->can('ai_tenants.edit') ? '<a href="'.e(route('central.ai.tenants.edit', $t->id)).'" class="btn-sm btn-secondary">'.e(__('admin.edit')).'</a>' : '');
    }

    protected function rawColumns(): array
    {
        return ['name', 'enabled', 'actions'];
    }
}

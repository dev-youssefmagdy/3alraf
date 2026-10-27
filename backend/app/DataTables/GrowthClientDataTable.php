<?php

namespace App\DataTables;

use App\Models\IntegrationClient;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class GrowthClientDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return IntegrationClient::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name', 'title' => __('growth_hub.fields.name')],
            ['data' => 'scopes_label', 'title' => __('growth_hub.fields.scopes'), 'orderable' => false, 'searchable' => false],
            ['data' => 'webhook_url', 'title' => __('growth_hub.fields.webhook_url'), 'orderable' => false],
            ['data' => 'last_used_at', 'title' => __('growth_hub.clients.last_used')],
            ['data' => 'state', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $can = (bool) auth('tenant')->user()?->can('growth.manage');
        $dt->addColumn('scopes_label', fn (IntegrationClient $c) => e(implode(', ', $c->scopes ?? [])))
            ->editColumn('webhook_url', fn (IntegrationClient $c) => e($c->webhook_url ?? '—'))
            ->editColumn('last_used_at', fn (IntegrationClient $c) => $c->last_used_at?->format('Y-m-d H:i') ?? '—')
            ->addColumn('state', fn (IntegrationClient $c) => $this->badge(! $c->isRevoked(), __('growth_hub.clients.active'), __('growth_hub.clients.revoked')))
            ->addColumn('actions', function (IntegrationClient $c) use ($can) {
                if (! $can || $c->isRevoked()) {
                    return '';
                }

                return '<div class="flex gap-2"><button type="button" class="btn-sm btn-secondary" data-gpost="'.e(route('tenant.growth.clients.rotate', $c->id)).'" data-confirm="'.e(__('growth_hub.clients.rotate_confirm')).'" data-secret="1">'.e(__('growth_hub.clients.rotate')).'</button>'
                    .'<button type="button" class="btn-sm btn-danger" data-gpost="'.e(route('tenant.growth.clients.revoke', $c->id)).'" data-confirm="'.e(__('growth_hub.clients.revoke_confirm')).'">'.e(__('growth_hub.clients.revoke')).'</button></div>';
            });
    }
}

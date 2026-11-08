<?php

namespace App\DataTables;

use App\Models\IntegrationClient;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class IntegrationClientDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return IntegrationClient::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name', 'title' => __('admin.integration.name')],
            ['data' => 'scopes_label', 'title' => __('admin.integration.scopes'), 'orderable' => false, 'searchable' => false],
            ['data' => 'last_used_at', 'title' => __('admin.integration.last_used')],
            ['data' => 'state', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $can = (bool) auth('admin')->user()?->can('integration_clients.manage');
        $dt->addColumn('scopes_label', fn (IntegrationClient $c) => e(implode(', ', $c->scopes ?? [])))
            ->editColumn('last_used_at', fn (IntegrationClient $c) => $c->last_used_at?->format('Y-m-d H:i') ?? '—')
            ->addColumn('state', fn (IntegrationClient $c) => $this->badge(! $c->isRevoked(), __('admin.integration.active'), __('admin.integration.revoked')))
            ->addColumn('actions', function (IntegrationClient $c) use ($can) {
                if (! $can || $c->isRevoked()) {
                    return '';
                }

                $edit = e(json_encode(['url' => route('marketer.integration-clients.update', $c->id), 'name' => $c->name, 'scopes' => $c->scopes ?? []]));

                return '<div class="flex gap-2"><button type="button" class="btn-sm btn-secondary" data-edit-client="'.$edit.'">'.e(__('admin.edit')).'</button><button type="button" class="btn-sm btn-secondary" data-gpost="'.e(route('marketer.integration-clients.rotate', $c->id)).'" data-confirm="'.e(__('admin.integration.rotate_confirm')).'" data-secret="1">'.e(__('admin.integration.rotate')).'</button>'
                    .'<button type="button" class="btn-sm btn-danger" data-gpost="'.e(route('marketer.integration-clients.revoke', $c->id)).'" data-confirm="'.e(__('admin.integration.revoke_confirm')).'">'.e(__('admin.integration.revoke')).'</button></div>';
            });
    }
}

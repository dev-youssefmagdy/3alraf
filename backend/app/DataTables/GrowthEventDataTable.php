<?php

namespace App\DataTables;

use App\Models\GrowthEvent;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class GrowthEventDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return GrowthEvent::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'id', 'title' => '#'],
            ['data' => 'type', 'title' => __('growth_hub.events.type')],
            ['data' => 'event_id', 'title' => __('growth_hub.events.event_id'), 'orderable' => false],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'attempts', 'title' => __('growth_hub.events.attempts')],
            ['data' => 'last_error', 'title' => __('growth_hub.events.error'), 'orderable' => false],
            ['data' => 'created_at', 'title' => __('growth_hub.events.created')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $can = (bool) auth('tenant')->user()?->can('growth.manage');
        $cls = ['pending' => 'badge-warning', 'delivered' => 'badge-success', 'dead' => 'badge-danger'];
        $dt->addColumn('status_label', fn (GrowthEvent $e) => '<span class="badge '.($cls[$e->status] ?? 'badge-muted').'">'.e(__('growth_hub.events.statuses.'.$e->status)).'</span>')
            ->editColumn('last_error', fn (GrowthEvent $e) => e($e->last_error ?? '—'))
            ->editColumn('created_at', fn (GrowthEvent $e) => $e->created_at?->format('Y-m-d H:i'))
            ->addColumn('actions', fn (GrowthEvent $e) => $can && $e->status !== 'pending'
                ? '<button type="button" class="btn-sm btn-secondary" data-gpost="'.e(route('tenant.growth.events.replay', $e->id)).'">'.e(__('growth_hub.events.replay')).'</button>' : '');
    }

    protected function rawColumns(): array
    {
        return ['status_label', 'actions'];
    }
}

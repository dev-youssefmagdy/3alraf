<?php

namespace App\DataTables;

use App\Models\GrowthExperiment;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class GrowthExperimentDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return GrowthExperiment::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'id', 'title' => '#'],
            ['data' => 'name', 'title' => __('growth_hub.fields.name')],
            ['data' => 'metric', 'title' => __('growth_hub.fields.metric')],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'outcome_label', 'title' => __('growth_hub.fields.outcome'), 'orderable' => false, 'searchable' => false],
            ['data' => 'starts_on', 'title' => __('growth_hub.fields.starts_on')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->editColumn('name', fn (GrowthExperiment $e) => e($e->name))
            ->editColumn('metric', fn (GrowthExperiment $e) => e($e->metric ?? '—'))
            ->addColumn('status_label', fn (GrowthExperiment $e) => '<span class="badge badge-muted">'.e(__('growth_hub.experiments.statuses.'.$e->status)).'</span>')
            ->addColumn('outcome_label', fn (GrowthExperiment $e) => $e->outcome ? e(__('growth_hub.experiments.outcomes.'.$e->outcome)) : '—')
            ->editColumn('starts_on', fn (GrowthExperiment $e) => $e->starts_on?->format('Y-m-d') ?? '—')
            ->addColumn('actions', fn (GrowthExperiment $e) => '<a href="'.e(route('tenant.growth.experiments.show', $e->id)).'" class="btn-sm btn-secondary">'.e(__('growth_hub.experiments.open')).'</a>');
    }

    protected function rawColumns(): array
    {
        return ['status_label', 'actions'];
    }
}

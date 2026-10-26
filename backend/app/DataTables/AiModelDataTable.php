<?php

namespace App\DataTables;

use App\Models\AiModel;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class AiModelDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        $r = request();

        return AiModel::query()->with('provider:id,name')
            ->when($r->integer('provider_id'), fn ($q, $p) => $q->where('ai_provider_id', $p))
            ->when($r->string('kind')->toString(), fn ($q, $k) => $q->where('kind', $k))
            ->when($r->filled('cost_min'), fn ($q) => $q->where('input_cost', '>=', (float) $r->input('cost_min')))
            ->when($r->filled('cost_max'), fn ($q) => $q->where('input_cost', '<=', (float) $r->input('cost_max')))
            ->orderBy('kind')->orderByDesc('is_default')->orderBy('label');
    }

    public function columns(): array
    {
        return [
            ['data' => 'label', 'title' => __('ai.fields.label')],
            ['data' => 'model_id', 'title' => __('ai.fields.model_id')],
            ['data' => 'provider_name', 'title' => __('ai.fields.provider'), 'orderable' => false, 'searchable' => false],
            ['data' => 'kind_label', 'title' => __('ai.fields.kind'), 'orderable' => false, 'searchable' => false],
            ['data' => 'default', 'title' => __('ai.fields.is_default'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('central')->user();
        $dt->editColumn('label', fn (AiModel $m) => e($m->label))->editColumn('model_id', fn (AiModel $m) => e($m->model_id))
            ->addColumn('provider_name', fn (AiModel $m) => e($m->provider?->name))
            ->addColumn('kind_label', fn (AiModel $m) => e($m->kind->label()))
            ->addColumn('default', fn (AiModel $m) => $m->is_default ? '<span class="badge badge-success">'.e(__('ai.fields.is_default')).'</span>' : '')
            ->addColumn('status', fn (AiModel $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (AiModel $m) => $this->actionButtons(route('central.ai.models.edit', $m), route('central.ai.models.destroy', $m), (bool) $u?->can('ai_models.edit'), (bool) $u?->can('ai_models.delete')));
    }

    protected function rawColumns(): array
    {
        return ['label', 'model_id', 'provider_name', 'kind_label', 'default', 'status', 'actions'];
    }
}

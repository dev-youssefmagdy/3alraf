<?php

namespace App\DataTables;

use App\Enums\AiGenerationStatus;
use App\Models\AiGeneration;
use App\Services\Ai\GeneratorService;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/** Generator review queue (banner | offer | bundle drafts). */
class AiGenerationDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return AiGeneration::query()->with('aiModel')->whereIn('type', GeneratorService::typeValues())->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'id', 'title' => '#'],
            ['data' => 'type_label', 'title' => __('ai.gen.type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'summary', 'title' => __('ai.gen.summary'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'model_label', 'title' => __('ai.fields.model'), 'orderable' => false, 'searchable' => false],
            ['data' => 'created_at', 'title' => __('ai.gen.created')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $cls = ['draft' => 'badge-warning', 'approved' => 'badge-success', 'rejected' => 'badge-muted', 'failed' => 'badge-danger', 'queued' => 'badge-muted'];
        $dt->addColumn('type_label', fn (AiGeneration $g) => e(($g->output['kind'] ?? null) ? __('ai.gen.kinds.'.$g->output['kind']) : $g->type->label()))
            ->addColumn('summary', function (AiGeneration $g) {
                $d = (array) ($g->output['draft'] ?? []);
                $l = current_tenant()->default_locale;
                $s = $d['code'] ?? ($d['name'][$l] ?? $d['title'][$l] ?? ($d['name'] ?? $d['title'] ?? null));

                return e(is_array($s) ? (string) (array_values(array_filter($s))[0] ?? '') : (string) ($s ?? \Illuminate\Support\Str::limit((string) ($g->inputs['goal'] ?? ''), 60)));
            })
            ->addColumn('status_label', function (AiGeneration $g) use ($cls) {
                $expired = $g->status === AiGenerationStatus::DRAFT && $g->created_at?->lt(now()->subDays(GeneratorService::TTL_DAYS));

                return '<span class="badge '.($expired ? 'badge-muted' : ($cls[$g->status->value] ?? 'badge-muted')).'">'.e($expired ? __('ai.gen.expired_badge') : $g->status->label()).'</span>';
            })
            ->addColumn('model_label', fn (AiGeneration $g) => e($g->aiModel?->label ?? '—'))
            ->editColumn('created_at', fn (AiGeneration $g) => $g->created_at?->format('Y-m-d H:i'))
            ->addColumn('actions', fn (AiGeneration $g) => '<a href="'.e(route('tenant.ai.generators.show', $g->id)).'" class="btn-sm btn-secondary">'.e(__('ai.gen.review')).'</a>');
    }

    protected function rawColumns(): array
    {
        return ['status_label', 'actions'];
    }
}

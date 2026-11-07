<?php

namespace App\DataTables;

use App\Models\AiUsageLog;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class AiUsageDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return AiUsageLog::query()->leftJoin('ai_models', 'ai_models.id', '=', 'ai_usage_logs.ai_model_id')
            ->select('ai_usage_logs.*', 'ai_models.label as model_label')->orderByDesc('ai_usage_logs.id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'created_at', 'title' => __('ai.fields.date'), 'searchable' => false],
            ['data' => 'feature', 'title' => __('ai.fields.feature')],
            ['data' => 'model_label', 'title' => __('ai.fields.model'), 'orderable' => false],
            ['data' => 'tokens', 'title' => __('ai.tokens'), 'orderable' => false, 'searchable' => false],
            ['data' => 'images', 'title' => __('ai.images'), 'searchable' => false],
            ['data' => 'est_cost', 'title' => __('ai.cost'), 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status')],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->orderColumn('created_at', 'ai_usage_logs.created_at $1')
            ->filterColumn('model_label', fn ($q, $k) => $q->where('ai_models.label', 'like', "%$k%"))
            ->filterColumn('feature', fn ($q, $k) => $q->where('ai_usage_logs.feature', 'like', "%$k%"))
            ->filterColumn('status', fn ($q, $k) => $q->where('ai_usage_logs.status', 'like', "%$k%"))
            ->editColumn('created_at', fn ($r) => $r->created_at?->format('Y-m-d H:i'))
            ->editColumn('est_cost', fn ($r) => number_format((float) $r->est_cost, 4))
            ->addColumn('tokens', fn ($r) => number_format($r->tokens_in + $r->tokens_out));
    }

    protected function rawColumns(): array
    {
        return [];
    }
}

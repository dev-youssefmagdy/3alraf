<?php

namespace App\DataTables;

use App\Models\MarketingSuggestionExecution;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/** Execution log (apply / rollback / publish / schedule) of every suggestion. */
class MarketingSuggestionExecutionDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return MarketingSuggestionExecution::query()->with(['suggestion:id,title,type', 'executor:id,name'])->latest('id');
    }

    public function columns(): array
    {
        $c = fn ($k) => __('marketing.suggestions.cols.'.$k);

        return [
            ['data' => 'id', 'title' => $c('id'), 'searchable' => false],
            ['data' => 'suggestion_link', 'title' => $c('suggestion'), 'orderable' => false, 'searchable' => false],
            ['data' => 'kind_label', 'title' => $c('kind'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status_badge', 'title' => $c('result'), 'orderable' => false, 'searchable' => false],
            ['data' => 'version', 'title' => $c('version'), 'searchable' => false],
            ['data' => 'executor_name', 'title' => $c('actor'), 'orderable' => false, 'searchable' => false],
            ['data' => 'started_at', 'title' => $c('when'), 'searchable' => false],
            ['data' => 'error', 'title' => $c('error'), 'orderable' => false],
        ];
    }

    protected function rawColumns(): array
    {
        return ['suggestion_link', 'status_badge'];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('suggestion_link', fn (MarketingSuggestionExecution $e) => $e->suggestion ? '<a class="text-brand-700 hover:underline" href="'.e(route('marketer.suggestions.show', $e->suggestion_id)).'">#'.$e->suggestion_id.' '.e($e->suggestion->title).'</a>' : '#'.$e->suggestion_id)
            ->addColumn('kind_label', fn (MarketingSuggestionExecution $e) => __('marketing.suggestions.exec_kinds.'.$e->kind))
            ->addColumn('status_badge', fn (MarketingSuggestionExecution $e) => '<span class="badge '.['succeeded' => 'badge-success', 'failed' => 'badge-danger', 'running' => 'badge-warn'][$e->status->value].'">'.e($e->status->label()).'</span>')
            ->addColumn('executor_name', fn (MarketingSuggestionExecution $e) => $e->executor?->name ?? '—')
            ->editColumn('started_at', fn (MarketingSuggestionExecution $e) => $e->started_at?->format('Y-m-d H:i:s'))
            ->editColumn('error', fn (MarketingSuggestionExecution $e) => $e->error ? \Illuminate\Support\Str::limit($e->error, 160) : '');
    }
}

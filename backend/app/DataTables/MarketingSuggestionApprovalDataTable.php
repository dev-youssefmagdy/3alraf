<?php

namespace App\DataTables;

use App\Models\MarketingSuggestionApproval;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/** Approval / rejection decisions (bound to a version + content hash; invalidated ones flagged). */
class MarketingSuggestionApprovalDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return MarketingSuggestionApproval::query()->with(['suggestion:id,title,type', 'admin:id,name'])->latest('id');
    }

    public function columns(): array
    {
        $c = fn ($k) => __('marketing.suggestions.cols.'.$k);

        return [
            ['data' => 'id', 'title' => $c('id'), 'searchable' => false],
            ['data' => 'suggestion_link', 'title' => $c('suggestion'), 'orderable' => false, 'searchable' => false],
            ['data' => 'decision_badge', 'title' => $c('decision'), 'orderable' => false, 'searchable' => false],
            ['data' => 'version', 'title' => $c('version'), 'searchable' => false],
            ['data' => 'admin_name', 'title' => $c('actor'), 'orderable' => false, 'searchable' => false],
            ['data' => 'note', 'title' => $c('note')],
            ['data' => 'created_at', 'title' => $c('when'), 'searchable' => false],
            ['data' => 'invalidated', 'title' => $c('invalidated'), 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function rawColumns(): array
    {
        return ['suggestion_link', 'decision_badge'];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('suggestion_link', fn (MarketingSuggestionApproval $a) => $a->suggestion ? '<a class="text-brand-700 hover:underline" href="'.e(route('marketer.suggestions.show', $a->suggestion_id)).'">#'.$a->suggestion_id.' '.e($a->suggestion->title).'</a>' : '#'.$a->suggestion_id)
            ->addColumn('decision_badge', fn (MarketingSuggestionApproval $a) => '<span class="badge '.($a->decision === 'approved' ? 'badge-success' : 'badge-danger').'">'.e(__('marketing.suggestions.statuses.'.$a->decision)).'</span>')
            ->addColumn('admin_name', fn (MarketingSuggestionApproval $a) => $a->admin?->name ?? '—')
            ->editColumn('created_at', fn (MarketingSuggestionApproval $a) => $a->created_at?->format('Y-m-d H:i'))
            ->addColumn('invalidated', fn (MarketingSuggestionApproval $a) => $a->invalidated_at ? $a->invalidated_at->format('Y-m-d H:i').' ('.$a->invalidated_reason.')' : '');
    }
}

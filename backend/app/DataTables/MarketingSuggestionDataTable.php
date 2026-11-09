<?php

namespace App\DataTables;

use App\Enums\Marketing\SuggestionStatus;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Models\MarketingSuggestion;
use App\Repositories\Contracts\MarketingSuggestionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/** Suggestions list of the marketer panel (overview / per-type pages). Filters come from the ajax query string (page, status, type, ...). */
class MarketingSuggestionDataTable extends BaseDataTable
{
    public function __construct(private MarketingSuggestionRepositoryInterface $repo) {}

    public function query(): Builder
    {
        $r = request();
        $page = (string) $r->query('page_key', $r->query('page', ''));
        $types = in_array($page, SuggestionTypeKey::pages(), true) ? array_map(fn ($t) => $t->value, SuggestionTypeKey::forPage($page)) : [];
        if (($t = $r->query('type')) && in_array($t, SuggestionTypeKey::values(), true)) {
            $types = $types ? array_values(array_intersect($types, [$t])) : [$t];
        }
        $date = fn ($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r->query($k)) ? (string) $r->query($k) : null;

        return $this->repo->filtered(['types' => $types ?: ($page && $page !== 'overview' && in_array($page, SuggestionTypeKey::pages(), true) ? ['__none__'] : []),
            'status' => $r->query('status') ?: null, 'priority' => $r->query('priority') ?: null, 'target_entity_type' => $r->query('target') ?: null,
            'search' => is_string($r->query('q')) ? mb_substr($r->query('q'), 0, 100) : null, 'from' => $date('from'), 'to' => $date('to'),
            'product_id' => (int) $r->query('product_id') ?: null, 'category_id' => (int) $r->query('category_id') ?: null,
            'sort' => in_array($r->query('sort'), ['newest', 'oldest', 'priority', 'updated'], true) ? $r->query('sort') : 'newest']);
    }

    public function columns(): array
    {
        $c = fn ($k) => __('marketing.suggestions.cols.'.$k);

        return [
            ['data' => 'id', 'title' => $c('id'), 'searchable' => false],
            ['data' => 'title', 'title' => $c('title')],
            ['data' => 'type_label', 'title' => $c('type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'target', 'title' => $c('target'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status_badge', 'title' => $c('status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'priority_label', 'title' => $c('priority'), 'orderable' => false, 'searchable' => false],
            ['data' => 'source_label', 'title' => $c('source'), 'orderable' => false, 'searchable' => false],
            ['data' => 'created_at', 'title' => $c('created'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function rawColumns(): array
    {
        return ['actions', 'status_badge', 'title'];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->editColumn('title', fn (MarketingSuggestion $s) => '<a class="font-medium text-brand-700 hover:underline" href="'.e(route('marketer.suggestions.show', $s->id)).'">'.e($s->title).'</a>'
                .($s->submitted_at ? '' : ' <span class="badge badge-muted">'.e(__('marketing.suggestions.blog.draft')).'</span>'))
            ->addColumn('type_label', fn (MarketingSuggestion $s) => $s->type->label())
            ->addColumn('target', fn (MarketingSuggestion $s) => $s->target_entity_type ? __('marketing.suggestions.targets.'.$s->target_entity_type).($s->target_entity_id ? ' #'.$s->target_entity_id : '') : __('marketing.suggestions.new_target'))
            ->addColumn('status_badge', fn (MarketingSuggestion $s) => '<span class="badge '.$s->status->badge().'" data-status="'.$s->status->value.'">'.e($s->status->label()).'</span>'
                .($s->rolled_back_at ? ' <span class="badge badge-muted">'.e(__('marketing.suggestions.rolled_back')).'</span>' : ''))
            ->addColumn('priority_label', fn (MarketingSuggestion $s) => $s->priority->label())
            ->addColumn('source_label', fn (MarketingSuggestion $s) => $s->source->label())
            ->editColumn('created_at', fn (MarketingSuggestion $s) => $s->created_at?->format('Y-m-d H:i'))
            ->addColumn('actions', fn (MarketingSuggestion $s) => '<a href="'.e(route('marketer.suggestions.show', $s->id)).'" class="btn-sm btn-secondary">'.e(__('marketing.suggestions.open')).'</a>'
                .(in_array($s->status, SuggestionStatus::PANEL_EDITABLE, true) && auth('admin')->user()?->can('ai_suggestions.edit')
                    ? ' <a href="'.e(route('marketer.suggestions.edit', $s->id)).'" class="btn-sm btn-secondary" data-action="edit">'.e(__('marketing.suggestions.btn.edit')).'</a>' : ''));
    }
}

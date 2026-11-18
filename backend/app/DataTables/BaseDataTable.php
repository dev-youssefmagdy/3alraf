<?php

namespace App\DataTables;

use App\Models\Language;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\EloquentDataTable;

/**
 * Server-side table. Subclass: query(), columns() (client definition), tweak(EloquentDataTable).
 * Render with <x-datatable :table="$table" /> ; the same class answers the ajax call via ajax().
 */
abstract class BaseDataTable
{
    abstract public function query(): Builder;

    /** @return array<int,array{data:string,title:string,orderable?:bool,searchable?:bool,class?:string}> */
    abstract public function columns(): array;

    protected function tweak(EloquentDataTable $dt): void {}

    /** Columns holding raw HTML (rendered unescaped). */
    protected function rawColumns(): array
    {
        return ['actions'];
    }

    /**
     * Filter bar of this table (rendered by <x-datatable>). Names travel as query parameters, so never use
     * DataTables' own: draw, start, length, search, order, columns.
     *
     * @return array<int,Filter>
     */
    public function filters(): array
    {
        return [];
    }

    /** columns() for the browser: the row buttons column gets the class that pins it on screen (app.css). */
    public function clientColumns(): array
    {
        return array_map(fn (array $c) => $c['data'] === 'actions' ? ['className' => trim(($c['className'] ?? '').' dt-actions')] + $c : $c, $this->columns());
    }

    public function ajax(): JsonResponse
    {
        $query = $this->query();
        foreach ($this->filters() as $filter) {
            $filter->apply($query, request()->query($filter->name));
        }
        $dt = DataTables::eloquent($query);
        $this->tweak($dt);

        return $dt->rawColumns($this->rawColumns())->toJson();
    }

    protected function filterLabel(string $key): string
    {
        return __('admin.table_filters.'.$key);
    }

    protected function likeValue(string $value): string
    {
        return '%'.addcslashes($value, '\\%_').'%';
    }

    /** Contains-search over plain columns. */
    protected function textFilter(string $name, array $columns, ?string $label = null): Filter
    {
        return Filter::text($name, $label ?? $this->filterLabel($name), fn (Builder $q, string $v) => $q->where(function (Builder $w) use ($columns, $v) {
            foreach ($columns as $column) {
                $w->orWhere($w->qualifyColumn($column), 'like', $this->likeValue($v));
            }
        }));
    }

    /** Contains-search over a translated JSON column, in every language, ignoring case. */
    protected function translatedFilter(string $name = 'name', ?string $column = null): Filter
    {
        return Filter::text($name, $this->filterLabel($name), function (Builder $q, string $v) use ($name, $column) {
            $sql = $q->getGrammar()->wrap($q->qualifyColumn($column ?? $name));
            $q->where(function (Builder $w) use ($sql, $v) {
                foreach (Language::query()->pluck('code') as $code) {
                    $w->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT($sql, ?)) COLLATE utf8mb4_unicode_ci LIKE ?", ['$."'.$code.'"', $this->likeValue($v)]);
                }
            });
        });
    }

    /** Exact match on a column, choosing from $options. */
    protected function selectFilter(string $name, array|Closure $options, ?string $column = null, ?string $label = null, bool $searchable = false): Filter
    {
        return Filter::select($name, $label ?? $this->filterLabel($name), $options, fn (Builder $q, string $v) => $q->where($q->qualifyColumn($column ?? $name), $v), $searchable);
    }

    /** @param class-string $enum backed enum with options() */
    protected function enumFilter(string $enum, string $name = 'status', ?string $column = null): Filter
    {
        return $this->selectFilter($name, fn () => $enum::options(), $column);
    }

    protected function activeFilter(string $column = 'is_active'): Filter
    {
        return Filter::select('status', $this->filterLabel('status'), ['1' => __('admin.active'), '0' => __('admin.inactive')], fn (Builder $q, string $v) => $q->where($q->qualifyColumn($column), $v === '1'));
    }

    /** @return array{Filter,Filter} from / to, inclusive */
    protected function dateFilters(string $column = 'created_at'): array
    {
        return [
            Filter::date('from', $this->filterLabel('from'), fn (Builder $q, string $v) => $q->whereDate($q->qualifyColumn($column), '>=', $v)),
            Filter::date('to', $this->filterLabel('to'), fn (Builder $q, string $v) => $q->whereDate($q->qualifyColumn($column), '<=', $v)),
        ];
    }

    /** id => translated name of a model, for select filters. @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    protected function nameOptions(string $model): Closure
    {
        return fn () => $model::query()->get()->pluck('name', 'id')->sort()->all();
    }

    /** Standard edit/delete buttons html. $base = route prefix (e.g. admin.languages). */
    protected function actionButtons(string $editUrl, string $deleteUrl, bool $canEdit = true, bool $canDelete = true): string
    {
        $h = '<div class="flex items-center gap-2">';
        if ($canEdit) {
            $h .= '<a href="'.e($editUrl).'" class="btn-sm btn-secondary">'.e(__('admin.edit')).'</a>';
        }
        if ($canDelete) {
            $h .= '<button type="button" data-delete="'.e($deleteUrl).'" class="btn-sm btn-danger">'.e(__('admin.delete')).'</button>';
        }

        return $h.'</div>';
    }

    /** Catalog drafts (HasDraftState) show "Draft" instead of active / inactive. */
    protected function draftBadge(): string
    {
        return '<span class="badge badge-warn">'.e(__('admin.drafts.badge')).'</span>';
    }

    protected function badge(bool $on, ?string $yes = null, ?string $no = null): string
    {
        return $on
            ? '<span class="badge badge-success">'.e($yes ?? __('admin.active')).'</span>'
            : '<span class="badge badge-muted">'.e($no ?? __('admin.inactive')).'</span>';
    }

    /** Route-name base of a module in the panel serving this request: `admin.{uri}` or `marketer.{uri}` (shared marketing modules). */
    protected function base(string $uri): string
    {
        return (request()->routeIs('marketer.*') ? 'marketer.' : 'admin.').$uri;
    }

    /** Edit/delete buttons for resource routes ({route}.edit / {route}.destroy, param = id) honoring the permissions of the current panel user. */
    protected function rowActions(\Illuminate\Database\Eloquent\Model $m, string $route, string $perm): string
    {
        $u = request()->user();

        return $this->actionButtons(route("$route.edit", $m->getKey()), route("$route.destroy", $m->getKey()), (bool) $u?->can("$perm.edit"), (bool) $u?->can("$perm.delete"));
    }

    protected function money(mixed $v): string
    {
        return $v === null ? '—' : number_format((float) $v, 2);
    }
}

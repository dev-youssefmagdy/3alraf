<?php

namespace App\DataTables;

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

    public function ajax(): JsonResponse
    {
        $dt = DataTables::eloquent($this->query());
        $this->tweak($dt);

        return $dt->rawColumns($this->rawColumns())->toJson();
    }

    /** Standard edit/delete buttons html. $base = route prefix (e.g. central.languages). */
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

    protected function badge(bool $on, ?string $yes = null, ?string $no = null): string
    {
        return $on
            ? '<span class="badge badge-success">'.e($yes ?? __('admin.active')).'</span>'
            : '<span class="badge badge-muted">'.e($no ?? __('admin.inactive')).'</span>';
    }

    /** Edit/delete buttons for tenant resource routes ({route}.edit / {route}.destroy, param = id) honoring tenant permissions. */
    protected function tenantActions(\Illuminate\Database\Eloquent\Model $m, string $route, string $perm): string
    {
        $u = auth('tenant')->user();

        return $this->actionButtons(route("$route.edit", $m->getKey()), route("$route.destroy", $m->getKey()), (bool) $u?->can("$perm.edit"), (bool) $u?->can("$perm.delete"));
    }

    protected function money(mixed $v): string
    {
        return $v === null ? '—' : number_format((float) $v, 2);
    }
}

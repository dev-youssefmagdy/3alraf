<?php

namespace App\DataTables;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class AttributeDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Attribute::query()->withCount('values')->orderBy('sort_order')->orderByDesc('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'code', 'title' => __('admin.fields.code')],
            ['data' => 'type_label', 'title' => __('admin.fields.type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'values_count', 'title' => __('admin.values'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('admin')->user();
        $dt->addColumn('name_label', fn (Attribute $a) => e($a->name).($a->is_draft ? ' '.$this->draftBadge() : ''))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->editColumn('code', fn (Attribute $a) => $a->is_draft && str_starts_with($a->code, \App\Services\AttributeService::DRAFT_CODE_PREFIX) ? '—' : e($a->code))
            ->addColumn('type_label', fn (Attribute $a) => e($a->type->label()))
            ->addColumn('actions', fn (Attribute $a) => $this->actionButtons(route('admin.attributes.edit', $a), route('admin.attributes.destroy', $a), $u?->can('attributes.edit'), $u?->can('attributes.delete')))
            ->rawColumns($this->rawColumns());
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'type_label', 'actions'];
    }
}

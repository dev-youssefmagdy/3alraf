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
        $u = auth('central')->user();
        $dt->addColumn('name_label', fn (Attribute $a) => e($a->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('type_label', fn (Attribute $a) => e($a->type->label()))
            ->addColumn('actions', fn (Attribute $a) => $this->actionButtons(route('central.attributes.edit', $a), route('central.attributes.destroy', $a), $u?->can('attributes.edit'), $u?->can('attributes.delete')))
            ->rawColumns($this->rawColumns());
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'type_label', 'actions'];
    }
}

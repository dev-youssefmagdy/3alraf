<?php

namespace App\DataTables;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class TagDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Tag::query()->withCount('products')->orderBy('sort_order')->orderByDesc('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'slug', 'title' => __('admin.fields.slug_short')],
            ['data' => 'products_count', 'title' => __('admin.products'), 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('central')->user();
        $dt->addColumn('name_label', fn (Tag $b) => e($b->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('status', fn (Tag $b) => $this->badge($b->is_active))
            ->addColumn('actions', fn (Tag $b) => $this->actionButtons(route('central.tags.edit', $b), route('central.tags.destroy', $b), $u?->can('tags.edit'), $u?->can('tags.delete')))
            ->rawColumns($this->rawColumns());
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'status', 'actions'];
    }
}

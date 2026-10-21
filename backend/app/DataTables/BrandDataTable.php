<?php

namespace App\DataTables;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class BrandDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Brand::query()->withCount('products')->orderBy('sort_order')->orderByDesc('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'logo_html', 'title' => '', 'orderable' => false, 'searchable' => false],
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
        $dt->addColumn('logo_html', fn (Brand $b) => $b->logo_url ? '<img src="'.e($b->logo_url).'" class="size-10 rounded object-contain" alt="">' : '')
            ->addColumn('name_label', fn (Brand $b) => e($b->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('status', fn (Brand $b) => $this->badge($b->is_active))
            ->addColumn('actions', fn (Brand $b) => $this->actionButtons(route('central.brands.edit', $b), route('central.brands.destroy', $b), $u?->can('brands.edit'), $u?->can('brands.delete')))
            ->rawColumns($this->rawColumns());
    }

    protected function rawColumns(): array
    {
        return ['logo_html', 'name_label', 'status', 'actions'];
    }
}

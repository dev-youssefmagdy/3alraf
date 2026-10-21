<?php

namespace App\DataTables;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class ProductDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Product::query()->with(['brand', 'images'])->withCount(['variants', 'categories'])->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'thumb', 'title' => '', 'orderable' => false, 'searchable' => false],
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'sku', 'title' => __('admin.fields.sku')],
            ['data' => 'type_label', 'title' => __('admin.fields.type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'brand_label', 'title' => __('admin.brand'), 'orderable' => false, 'searchable' => false],
            ['data' => 'variants_count', 'title' => __('admin.variants'), 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('central')->user();
        $dt->addColumn('thumb', fn (Product $p) => ($url = $p->primaryImageUrl()) ? '<img src="'.e($url).'" class="size-10 rounded object-cover" alt="">' : '')
            ->addColumn('name_label', fn (Product $p) => e($p->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('type_label', fn (Product $p) => e($p->type->label()))
            ->addColumn('brand_label', fn (Product $p) => e($p->brand?->name ?? '—'))
            ->addColumn('status', fn (Product $p) => $this->badge($p->is_active))
            ->addColumn('actions', fn (Product $p) => $this->actionButtons(route('central.products.edit', $p), route('central.products.destroy', $p), $u?->can('products.edit'), $u?->can('products.delete')))
            ->rawColumns($this->rawColumns());
    }

    protected function rawColumns(): array
    {
        return ['thumb', 'name_label', 'type_label', 'brand_label', 'status', 'actions'];
    }
}

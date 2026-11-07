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
            ['data' => 'price', 'title' => __('admin.fields.price'), 'searchable' => false],
            ['data' => 'sale_price', 'title' => __('admin.fields.sale_price'), 'searchable' => false],
            ['data' => 'cost', 'title' => __('admin.fields.cost'), 'searchable' => false],
            ['data' => 'variants_count', 'title' => __('admin.variants'), 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('admin')->user();
        $dt->addColumn('thumb', fn (Product $p) => ($url = $p->primaryImageUrl()) ? '<img src="'.e($url).'" class="size-10 rounded object-cover" alt="">' : '')
            ->addColumn('name_label', fn (Product $p) => e($p->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where(fn ($w) => $w->where('name', 'like', "%$k%")->orWhere('sku', 'like', "%$k%")))
            ->addColumn('type_label', fn (Product $p) => e($p->type->label()))
            ->addColumn('brand_label', fn (Product $p) => e($p->brand?->name ?? '—'))
            ->editColumn('price', fn (Product $p) => $p->price === null ? '—' : number_format((float) $p->price, 2))
            ->editColumn('sale_price', fn (Product $p) => $p->sale_price === null ? '—' : number_format((float) $p->sale_price, 2))
            ->editColumn('cost', fn (Product $p) => $p->cost === null ? '—' : number_format((float) $p->cost, 2))
            ->addColumn('status', fn (Product $p) => $this->badge($p->is_active))
            ->addColumn('actions', function (Product $p) use ($u) {
                $catalog = $p->price !== null && $u?->can('catalog.edit')
                    ? '<a href="'.e(route('admin.catalog.edit', $p)).'" class="btn-sm btn-secondary">'.e(__('admin.catalog')).'</a>'
                    : '';

                return '<div class="flex items-center justify-end gap-1">'.$catalog.$this->actionButtons(route('admin.products.edit', $p), route('admin.products.destroy', $p), (bool) $u?->can('products.edit'), (bool) $u?->can('products.delete')).'</div>';
            })
            ->rawColumns($this->rawColumns());
    }

    protected function rawColumns(): array
    {
        return ['thumb', 'name_label', 'type_label', 'brand_label', 'status', 'actions'];
    }
}

<?php

namespace App\DataTables;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/** Catalog page: products that have been activated (have a price), with their selling fields. */
class CatalogDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Product::query()->with(['images', 'brand'])->whereNotNull('price')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'thumb', 'title' => '', 'orderable' => false, 'searchable' => false],
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'sku', 'title' => __('admin.fields.sku')],
            ['data' => 'price', 'title' => __('admin.fields.price')],
            ['data' => 'sale_price', 'title' => __('admin.fields.sale_price')],
            ['data' => 'cost', 'title' => __('admin.fields.cost')],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('admin')->user();
        $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
        $dt->addColumn('thumb', fn (Product $p) => ($url = $p->primaryImageUrl()) ? '<img src="'.e($url).'" class="size-10 rounded object-cover" alt="">' : '')
            ->addColumn('name_label', fn (Product $p) => e($p->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where(fn ($w) => $w->where('name', 'like', "%$k%")->orWhere('sku', 'like', "%$k%")))
            ->editColumn('sku', fn (Product $p) => e($p->sku))
            ->editColumn('price', fn (Product $p) => $money($p->price))
            ->editColumn('sale_price', fn (Product $p) => $money($p->sale_price))
            ->editColumn('cost', fn (Product $p) => $money($p->cost))
            ->addColumn('status', fn (Product $p) => $this->badge($p->is_active))
            ->addColumn('actions', function (Product $p) use ($u) {
                $master = $u?->can('products.edit')
                    ? '<a href="'.e(route('admin.products.edit', $p)).'" class="btn-sm btn-secondary me-1" title="'.e(__('admin.edit_master_product')).'">'.e(__('admin.edit_master_product')).'</a>'
                    : '';

                return '<div class="flex items-center justify-end gap-1">'.$master.$this->actionButtons(route('admin.catalog.edit', $p), route('admin.catalog.destroy', $p), (bool) $u?->can('catalog.edit'), (bool) $u?->can('catalog.delete')).'</div>';
            })
            ->rawColumns($this->rawColumns());
    }

    protected function rawColumns(): array
    {
        return ['thumb', 'name_label', 'sku', 'status', 'actions'];
    }
}

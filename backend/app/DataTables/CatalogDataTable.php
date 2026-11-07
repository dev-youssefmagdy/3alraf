<?php

namespace App\DataTables;

use App\Models\TenantProduct;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class TenantProductDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return TenantProduct::query()->with(['product.images', 'product.brand'])->whereHas('product')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'thumb', 'title' => '', 'orderable' => false, 'searchable' => false],
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'sku', 'title' => __('admin.fields.sku'), 'orderable' => false],
            ['data' => 'price', 'title' => __('admin.fields.price')],
            ['data' => 'sale_price', 'title' => __('admin.fields.sale_price')],
            ['data' => 'cost', 'title' => __('admin.fields.cost')],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('tenant')->user();
        $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
        $dt->addColumn('thumb', fn (TenantProduct $t) => ($url = $t->product->primaryImageUrl()) ? '<img src="'.e($url).'" class="size-10 rounded object-cover" alt="">' : '')
            ->addColumn('name_label', fn (TenantProduct $t) => e($t->product->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%$k%")->orWhere('sku', 'like', "%$k%")))
            ->addColumn('sku', fn (TenantProduct $t) => e($t->product->sku))
            ->editColumn('price', fn (TenantProduct $t) => $money($t->price))
            ->editColumn('sale_price', fn (TenantProduct $t) => $money($t->sale_price))
            ->editColumn('cost', fn (TenantProduct $t) => $money($t->cost))
            ->addColumn('status', fn (TenantProduct $t) => $this->badge($t->is_active))
            ->addColumn('actions', fn (TenantProduct $t) => $this->actionButtons(route('tenant.catalog.edit', $t), route('tenant.catalog.destroy', $t), $u?->can('catalog.edit'), $u?->can('catalog.delete')))
            ->rawColumns($this->rawColumns());
    }

    protected function rawColumns(): array
    {
        return ['thumb', 'name_label', 'sku', 'status', 'actions'];
    }
}

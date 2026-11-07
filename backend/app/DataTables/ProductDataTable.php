<?php

namespace App\DataTables;

use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/** Products list (the only product module): identity + selling fields (price / sale / cost), stock, status, QR, edit / delete. */
class ProductDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Product::query()->with(['brand', 'images', 'categories'])
            ->addSelect(['stock_on_hand' => StockLevel::query()->selectRaw('COALESCE(SUM(on_hand), 0)')->whereColumn('stock_levels.product_id', 'products.id')])
            ->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'thumb', 'title' => '', 'orderable' => false, 'searchable' => false],
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'sku', 'title' => __('admin.fields.sku')],
            ['data' => 'barcode', 'title' => __('admin.fields.barcode')],
            ['data' => 'brand_label', 'title' => __('admin.brand'), 'orderable' => false, 'searchable' => false],
            ['data' => 'category_label', 'title' => __('admin.categories'), 'orderable' => false, 'searchable' => false],
            ['data' => 'price', 'title' => __('admin.fields.price'), 'searchable' => false],
            ['data' => 'sale_price', 'title' => __('admin.fields.sale_price'), 'searchable' => false],
            ['data' => 'cost', 'title' => __('admin.fields.cost'), 'searchable' => false],
            ['data' => 'stock_on_hand', 'title' => __('admin.stock_qty'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('admin')->user();
        $dt->addColumn('thumb', fn (Product $p) => ($url = $p->primaryImageUrl()) ? '<img src="'.e($url).'" class="size-10 rounded object-cover" alt="">' : '')
            ->addColumn('name_label', fn (Product $p) => e($p->name).($p->isVariable() ? ' <span class="badge badge-muted">'.e(__('admin.variants')).'</span>' : ''))
            ->filterColumn('name_label', fn ($q, $k) => $q->where(fn ($w) => $w->where('name', 'like', "%$k%")->orWhere('sku', 'like', "%$k%")->orWhere('barcode', 'like', "%$k%")))
            ->editColumn('sku', fn (Product $p) => e($p->sku))
            ->editColumn('barcode', fn (Product $p) => e($p->barcode ?? '—'))
            ->addColumn('brand_label', fn (Product $p) => e($p->brand?->name ?? '—'))
            ->addColumn('category_label', fn (Product $p) => e($p->categories->pluck('name')->implode(', ') ?: '—'))
            ->editColumn('price', fn (Product $p) => $this->money($p->price))
            ->editColumn('sale_price', fn (Product $p) => $this->money($p->sale_price))
            ->editColumn('cost', fn (Product $p) => $this->money($p->cost))
            ->editColumn('stock_on_hand', fn (Product $p) => (int) $p->stock_on_hand)
            ->addColumn('status', fn (Product $p) => $p->price === null
                ? '<span class="badge badge-muted">'.e(__('admin.not_priced')).'</span>'
                : $this->badge($p->is_active))
            ->addColumn('actions', function (Product $p) use ($u) {
                $qr = $p->price !== null && $u?->can('products.view')
                    ? '<a href="'.e(route('admin.products.qr', $p)).'" class="btn-sm btn-secondary me-1" download>'.e(__('admin.qr_code')).'</a>'
                    : '';

                return '<div class="flex items-center justify-end gap-1">'.$qr.$this->actionButtons(route('admin.products.edit', $p), route('admin.products.destroy', $p), (bool) $u?->can('products.edit'), (bool) $u?->can('products.delete')).'</div>';
            })
            ->rawColumns($this->rawColumns());
    }

    protected function rawColumns(): array
    {
        return ['thumb', 'name_label', 'sku', 'barcode', 'brand_label', 'category_label', 'status', 'actions'];
    }
}

<?php

namespace App\DataTables;

use App\Models\StockRequest;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class StockRequestDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return StockRequest::query()->with(['product', 'variant.values'])->when(request('status'), fn ($q, $s) => $q->where('status', $s))->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'product_name', 'title' => __('growth.product'), 'orderable' => false, 'searchable' => false],
            ['data' => 'name', 'title' => __('stock_requests.customer')],
            ['data' => 'phone_full', 'title' => __('stock_requests.phone'), 'orderable' => false, 'searchable' => false],
            ['data' => 'note', 'title' => __('stock_requests.note'), 'orderable' => false],
            ['data' => 'created_at', 'title' => __('stock_requests.requested_at'), 'searchable' => false],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('tenant')->user();
        $dt->addColumn('product_name', fn (StockRequest $m) => e($m->product?->name).($m->variant ? '<div class="text-xs text-slate-500">'.e($m->variant->label()).'</div>' : ''))
            ->editColumn('name', fn (StockRequest $m) => e($m->name))
            ->addColumn('phone_full', fn (StockRequest $m) => '<a dir="ltr" class="text-brand-600" href="tel:'.e($m->phone_country_code.$m->phone).'">'.e(trim($m->phone_country_code.' '.$m->phone)).'</a>')
            ->editColumn('note', fn (StockRequest $m) => e(\Illuminate\Support\Str::limit((string) $m->note, 120)))
            ->editColumn('created_at', fn (StockRequest $m) => $m->created_at?->format('Y-m-d H:i'))
            ->addColumn('status_label', fn (StockRequest $m) => '<span class="badge '.['new' => 'badge-warn', 'contacted' => 'badge-success', 'closed' => 'badge-muted'][$m->status->value].'">'.e($m->status->label()).'</span>')
            ->addColumn('actions', function (StockRequest $m) use ($u) {
                $h = '<div class="flex gap-2">';
                if ($u?->can('stock_requests.edit')) {
                    foreach (['contacted', 'closed'] as $s) {
                        if ($m->status->value !== $s) {
                            $h .= '<button type="button" data-post-action="'.e(route('tenant.stock-requests.status', [$m->id, $s])).'" class="btn-sm btn-secondary">'.e(__('stock_requests.mark_'.$s)).'</button>';
                        }
                    }
                }
                if ($u?->can('stock_requests.delete')) {
                    $h .= '<button type="button" data-delete="'.e(route('tenant.stock-requests.destroy', $m->id)).'" class="btn-sm btn-danger">'.e(__('admin.delete')).'</button>';
                }

                return $h.'</div>';
            });
    }

    protected function rawColumns(): array
    {
        return ['product_name', 'phone_full', 'note', 'status_label', 'actions'];
    }
}

<?php

namespace App\DataTables;

use App\Enums\ReviewStatus;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class ReviewDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Review::query()->with(['product', 'customer'])->when(request('status'), fn ($q, $s) => $q->where('status', $s))->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'product_name', 'title' => __('growth.product'), 'orderable' => false, 'searchable' => false],
            ['data' => 'customer_name', 'title' => __('orders.fields.customer'), 'orderable' => false, 'searchable' => false],
            ['data' => 'rating', 'title' => __('growth.fields.rating'), 'searchable' => false],
            ['data' => 'body', 'title' => __('growth.fields.body'), 'orderable' => false],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('tenant')->user();
        $dt->addColumn('product_name', fn (Review $m) => e($m->product?->name))
            ->addColumn('customer_name', fn (Review $m) => e($m->customer?->name))
            ->editColumn('rating', fn (Review $m) => str_repeat('★', $m->rating).str_repeat('☆', 5 - $m->rating))
            ->editColumn('body', fn (Review $m) => e(\Illuminate\Support\Str::limit((string) $m->body, 120)))
            ->addColumn('status_label', fn (Review $m) => '<span class="badge '.['approved' => 'badge-success', 'pending' => 'badge-warn', 'rejected' => 'badge-muted'][$m->status->value].'">'.e($m->status->label()).'</span>')
            ->addColumn('actions', function (Review $m) use ($u) {
                $h = '<div class="flex gap-2">';
                if ($u?->can('reviews.edit')) {
                    if ($m->status !== ReviewStatus::APPROVED) {
                        $h .= '<button type="button" data-post-action="'.e(route('tenant.reviews.approve', $m->id)).'" class="btn-sm btn-secondary">'.e(__('growth.approve')).'</button>';
                    }
                    if ($m->status !== ReviewStatus::REJECTED) {
                        $h .= '<button type="button" data-post-action="'.e(route('tenant.reviews.reject', $m->id)).'" class="btn-sm btn-secondary">'.e(__('growth.reject')).'</button>';
                    }
                }
                if ($u?->can('reviews.delete')) {
                    $h .= '<button type="button" data-delete="'.e(route('tenant.reviews.destroy', $m->id)).'" class="btn-sm btn-danger">'.e(__('admin.delete')).'</button>';
                }

                return $h.'</div>';
            });
    }

    protected function rawColumns(): array
    {
        return ['product_name', 'customer_name', 'body', 'status_label', 'actions'];
    }
}

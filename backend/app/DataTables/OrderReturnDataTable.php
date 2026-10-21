<?php

namespace App\DataTables;

use App\Enums\ReturnStatus;
use App\Models\OrderReturn;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class OrderReturnDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return OrderReturn::query()->with('order')->latest('id')
            ->when(ReturnStatus::tryFrom((string) request()->query('status')), fn ($q, $s) => $q->where('status', $s->value));
    }

    public function columns(): array
    {
        return [
            ['data' => 'number_link', 'title' => __('ops.fields.number'), 'name' => 'number'],
            ['data' => 'order_number', 'title' => __('orders.fields.order'), 'orderable' => false, 'searchable' => false],
            ['data' => 'created_at', 'title' => __('ops.fields.date')],
            ['data' => 'return_value', 'title' => __('orders.fields.return_value')],
            ['data' => 'refund_amount', 'title' => __('orders.fields.refund_amount')],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('number_link', fn (OrderReturn $m) => '<a class="font-semibold text-brand-600" href="'.e(route('tenant.returns.show', $m->id)).'">'.e($m->number).'</a>')
            ->filterColumn('number_link', fn ($q, $k) => $q->where('number', 'like', "%$k%"))
            ->addColumn('order_number', fn (OrderReturn $m) => '<a class="text-brand-600" href="'.e(route('tenant.orders.show', $m->order_id)).'">'.e($m->order?->number).'</a>')
            ->editColumn('created_at', fn (OrderReturn $m) => $m->created_at->format('Y-m-d H:i'))
            ->editColumn('return_value', fn (OrderReturn $m) => $this->money($m->return_value))
            ->editColumn('refund_amount', fn (OrderReturn $m) => $this->money($m->refund_amount))
            ->addColumn('status_label', fn (OrderReturn $m) => '<span class="badge '.($m->status === ReturnStatus::REFUNDED ? 'badge-success' : ($m->status === ReturnStatus::REJECTED ? 'badge-danger' : 'badge-muted')).'">'.e($m->status->label()).'</span>');
    }

    protected function rawColumns(): array
    {
        return ['number_link', 'order_number', 'status_label'];
    }
}

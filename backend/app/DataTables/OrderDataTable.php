<?php

namespace App\DataTables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/** Filters (query string): status, payment_status, from, to (placed_at dates), payment_method_id. */
class OrderDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        $r = request();

        return Order::query()->with('paymentMethod')->latest('id')
            ->when(OrderStatus::tryFrom((string) $r->query('status')), fn ($q, $s) => $q->where('status', $s->value))
            ->when(PaymentStatus::tryFrom((string) $r->query('payment_status')), fn ($q, $s) => $q->where('payment_status', $s->value))
            ->when($r->query('payment_method_id'), fn ($q, $v) => $q->where('payment_method_id', (int) $v))
            ->when($r->query('from'), fn ($q, $v) => $q->whereDate('placed_at', '>=', $v))
            ->when($r->query('to'), fn ($q, $v) => $q->whereDate('placed_at', '<=', $v));
    }

    public function columns(): array
    {
        return [
            ['data' => 'number_link', 'title' => __('ops.fields.number'), 'name' => 'number'],
            ['data' => 'customer_name', 'title' => __('orders.fields.customer')],
            ['data' => 'placed_at', 'title' => __('ops.fields.date')],
            ['data' => 'total', 'title' => __('ops.fields.total')],
            ['data' => 'payment_label', 'title' => __('orders.fields.payment'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('number_link', fn (Order $m) => '<a class="font-semibold text-brand-600" href="'.e(route('tenant.orders.show', $m->id)).'">'.e($m->number).'</a>')
            ->filterColumn('number_link', fn ($q, $k) => $q->where('number', 'like', "%$k%"))
            ->editColumn('placed_at', fn (Order $m) => $m->placed_at?->format('Y-m-d H:i'))
            ->editColumn('total', fn (Order $m) => $this->money($m->total))
            ->addColumn('payment_label', fn (Order $m) => e($m->paymentMethod?->name).' <span class="badge '.($m->payment_status === PaymentStatus::PAID ? 'badge-success' : ($m->payment_status === PaymentStatus::REVIEW ? 'badge-warn' : 'badge-muted')).'">'.e($m->payment_status->label()).'</span>')
            ->addColumn('status_label', fn (Order $m) => '<span class="badge '.$m->status->color().'">'.e($m->status->label()).'</span>')
            ->addColumn('actions', fn (Order $m) => '<a href="'.e(route('tenant.orders.show', $m->id)).'" class="btn-sm btn-secondary">'.e(__('orders.view')).'</a>');
    }

    protected function rawColumns(): array
    {
        return ['number_link', 'payment_label', 'status_label', 'actions'];
    }
}

<?php

namespace App\DataTables;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class CustomerDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Customer::query()->withCount('orders')->withSum('orders as spent', 'total')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_link', 'title' => __('admin.fields.name'), 'name' => 'name'],
            ['data' => 'email', 'title' => __('ops.fields.email')],
            ['data' => 'phone', 'title' => __('ops.fields.phone')],
            ['data' => 'orders_count', 'title' => __('orders.fields.orders'), 'searchable' => false],
            ['data' => 'spent', 'title' => __('orders.fields.spent'), 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('name_link', fn (Customer $m) => '<a class="font-semibold text-brand-600" href="'.e(route('tenant.customers.show', $m->id)).'">'.e($m->name).'</a>')
            ->filterColumn('name_link', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->editColumn('phone', fn (Customer $m) => $m->phone_display ?: $m->phone)
            ->editColumn('spent', fn (Customer $m) => $this->money($m->spent ?? 0))
            ->addColumn('status', fn (Customer $m) => $this->badge($m->is_active));
    }

    protected function rawColumns(): array
    {
        return ['name_link', 'status'];
    }
}

<?php

namespace App\DataTables;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class PaymentMethodDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return PaymentMethod::query()->orderBy('sort_order')->orderBy('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'type_label', 'title' => __('ops.fields.type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'provider_label', 'title' => __('ops.fields.provider'), 'orderable' => false, 'searchable' => false],
            ['data' => 'fee', 'title' => __('ops.fields.fee'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('name_label', fn (PaymentMethod $m) => e($m->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('type_label', fn (PaymentMethod $m) => e($m->type->label()))
            ->addColumn('provider_label', fn (PaymentMethod $m) => e($m->provider->label()))
            ->addColumn('fee', fn (PaymentMethod $m) => e(rtrim(rtrim((string) $m->fee_percent, '0'), '.').'% + '.$this->money($m->fee_fixed).' ('.$m->fee_bearer->label().')'))
            ->addColumn('status', fn (PaymentMethod $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (PaymentMethod $m) => $this->tenantActions($m, 'tenant.payment-methods', 'payment_methods'));
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'type_label', 'provider_label', 'fee', 'status', 'actions'];
    }
}

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
            ->addColumn('actions', function (PaymentMethod $m) {
                $u = auth('tenant')->user();
                $toggle = $u?->can('payment_methods.edit')
                    ? '<button type="button" class="btn-sm btn-secondary" data-post-action="'.e(route('tenant.payment-methods.toggle', $m->id)).'">'.e($m->is_active ? __('ops.deactivate') : __('ops.activate')).'</button>' : '';
                $actions = $this->tenantActions($m, 'tenant.payment-methods', 'payment_methods');
                if ($m->type === \App\Enums\PaymentMethodType::COD) { // default COD can be deactivated, never deleted
                    $actions = preg_replace('/<button[^>]*data-delete[^>]*>.*?<\/button>/', '', $actions);
                }

                return '<div class="flex items-center gap-2">'.$toggle.$actions.'</div>';
            });
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'type_label', 'provider_label', 'fee', 'status', 'actions'];
    }
}

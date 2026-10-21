<?php

namespace App\DataTables;

use App\Enums\PayoutStatus;
use App\Models\AffiliatePayout;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class AffiliatePayoutDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return AffiliatePayout::query()->with('affiliate')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'id', 'title' => '#'],
            ['data' => 'affiliate_name', 'title' => __('growth.affiliate'), 'orderable' => false, 'searchable' => false],
            ['data' => 'amount', 'title' => __('growth.fields.amount'), 'searchable' => false],
            ['data' => 'status_label', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'created_at', 'title' => __('growth.date'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('tenant')->user();
        $dt->addColumn('affiliate_name', fn (AffiliatePayout $m) => e($m->affiliate?->name))
            ->editColumn('amount', fn (AffiliatePayout $m) => $this->money($m->amount))
            ->editColumn('created_at', fn (AffiliatePayout $m) => $m->created_at->format('Y-m-d H:i'))
            ->addColumn('status_label', fn (AffiliatePayout $m) => '<span class="badge '.($m->status === PayoutStatus::PAID ? 'badge-success' : ($m->status === PayoutStatus::REQUESTED ? 'badge-warn' : 'badge-muted')).'">'.e($m->status->label()).'</span>')
            ->addColumn('actions', function (AffiliatePayout $m) use ($u) {
                if ($m->status !== PayoutStatus::REQUESTED || ! $u?->can('affiliate_payouts.edit')) {
                    return '';
                }

                return '<div class="flex gap-2"><button type="button" data-post-action="'.e(route('tenant.affiliate-payouts.pay', $m->id)).'" class="btn-sm btn-secondary">'.e(__('growth.mark_paid')).'</button>'
                    .'<button type="button" data-post-action="'.e(route('tenant.affiliate-payouts.reject', $m->id)).'" class="btn-sm btn-danger">'.e(__('growth.reject')).'</button></div>';
            });
    }

    protected function rawColumns(): array
    {
        return ['affiliate_name', 'status_label', 'actions'];
    }
}
